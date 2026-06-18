<?php
/**
 * Content identifier normalization and permission helpers.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Content_ID
{
    /**
     * @param mixed $value Raw content id.
     */
    public static function sanitize($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            return (string) absint($value);
        }

        $parts = explode(':', $value);
        if (count($parts) === 3 && $parts[0] === 'term') {
            $taxonomy = sanitize_key($parts[1]);
            $term_id = absint($parts[2]);
            return $taxonomy !== '' && $term_id > 0 ? 'term:' . $taxonomy . ':' . $term_id : '';
        }

        return '';
    }

    public static function is_valid(string $value): bool
    {
        $value = self::sanitize($value);
        return $value !== '' && self::current_user_can_edit($value);
    }

    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, string>
     */
    public static function sanitize_array($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map([self::class, 'sanitize'], $value))));
    }

    /**
     * @param mixed $value Raw REST array value.
     */
    public static function validate_array($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!self::is_valid((string) $item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function from_request(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $content_ids = $request->get_param('content_ids');
        if (!is_array($content_ids) && isset($json['content_ids']) && is_array($json['content_ids'])) {
            $content_ids = $json['content_ids'];
        }

        if (is_array($content_ids) && !empty($content_ids)) {
            return self::sanitize_array($content_ids);
        }

        $post_ids = $request->get_param('post_ids');
        if (!is_array($post_ids) && isset($json['post_ids']) && is_array($json['post_ids'])) {
            $post_ids = $json['post_ids'];
        }

        if (is_array($post_ids) && !empty($post_ids)) {
            return array_map('strval', array_values(array_unique(array_filter(array_map('absint', $post_ids)))));
        }

        $content_id = self::single_from_request($request);
        return $content_id !== '' ? [$content_id] : [];
    }

    public static function single_from_request(WP_REST_Request $request): string
    {
        $json = $request->get_json_params();
        $content_id = $request->get_param('content_id');
        if ((!is_string($content_id) || $content_id === '') && isset($json['content_id'])) {
            $content_id = $json['content_id'];
        }

        if (is_string($content_id) && $content_id !== '') {
            return self::sanitize($content_id);
        }

        $post_id = absint($request->get_param('post_id'));
        if ($post_id <= 0 && isset($json['post_id'])) {
            $post_id = absint($json['post_id']);
        }

        return $post_id > 0 ? (string) $post_id : '';
    }

    /**
     * @return int|string
     */
    public static function to_acf_target(string $content_id)
    {
        if (strpos($content_id, 'term:') === 0) {
            $parts = explode(':', $content_id);
            return 'term_' . absint($parts[2] ?? 0);
        }

        return absint($content_id);
    }

    public static function is_allowed_target(string $content_id): bool
    {
        $content_id = self::sanitize($content_id);
        if ($content_id === '') {
            return false;
        }

        if (strpos($content_id, 'term:') === 0) {
            $parts = explode(':', $content_id);
            $taxonomy = sanitize_key($parts[1] ?? '');
            if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
                return false;
            }

            $allowed = self::get_allowed_taxonomies();

            return in_array($taxonomy, $allowed, true);
        }

        $post_id = absint($content_id);
        $post_type = $post_id > 0 ? get_post_type($post_id) : '';
        if (!is_string($post_type) || $post_type === '' || $post_type === 'attachment' || !post_type_exists($post_type)) {
            return false;
        }

        $allowed = self::get_allowed_post_types();
        if (!in_array($post_type, $allowed, true)) {
            return false;
        }

        return AIAF_ACF_Runtime::is_available() || post_type_supports($post_type, 'thumbnail');
    }

    public static function current_user_can_edit(string $content_id): bool
    {
        $content_id = self::sanitize($content_id);
        if ($content_id === '') {
            return false;
        }

        if (strpos($content_id, 'term:') === 0) {
            $parts = explode(':', $content_id);
            $taxonomy = sanitize_key($parts[1] ?? '');
            $term_id = absint($parts[2] ?? 0);
            $taxonomy_object = $taxonomy !== '' ? get_taxonomy($taxonomy) : null;
            if (!$taxonomy_object || $term_id <= 0 || !term_exists($term_id, $taxonomy)) {
                return false;
            }

            return current_user_can('edit_term', $term_id);
        }

        return current_user_can('edit_post', absint($content_id));
    }

    /**
     * @param array<int, string> $content_ids Content IDs from the REST request.
     */
    public static function contains_featured_image_target(array $content_ids): bool
    {
        foreach ($content_ids as $content_id) {
            if (self::supports_featured_image((string) $content_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function get_allowed_post_types(): array
    {
        $allowed = ['post', 'page'];
        if (AIAF_Environment::is_woocommerce_active()) {
            $allowed[] = 'product';
        }

        $allowed = apply_filters('aiaf_allowed_post_types', $allowed);

        return array_values(array_unique(array_filter(array_map('sanitize_key', is_array($allowed) ? $allowed : []))));
    }

    /**
     * @return array<int, string>
     */
    private static function get_allowed_taxonomies(): array
    {
        $taxonomies = [];
        if (taxonomy_exists('category')) {
            $taxonomies[] = 'category';
        }
        if (AIAF_Environment::is_woocommerce_active() && taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }

        $taxonomies = apply_filters('aiaf_allowed_taxonomies', $taxonomies);

        return array_values(array_unique(array_filter(array_map('sanitize_key', is_array($taxonomies) ? $taxonomies : []))));
    }

    public static function supports_featured_image(string $content_id): bool
    {
        $content_id = self::sanitize($content_id);
        if ($content_id === '' || strpos($content_id, 'term:') === 0) {
            return false;
        }

        $post_id = absint($content_id);
        $post_type = $post_id > 0 ? get_post_type($post_id) : '';

        return is_string($post_type) && $post_type !== '' && post_type_supports($post_type, 'thumbnail');
    }
}
