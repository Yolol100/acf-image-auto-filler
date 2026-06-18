<?php
/**
 * REST controller methods split by responsibility.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait AIAF_REST_Content_Types_Trait
{
    public static function get_post_types(): WP_REST_Response
    {
        $items = [];
        $allowed_post_types = ['post', 'page'];

        if (self::is_woocommerce_active()) {
            $allowed_post_types[] = 'product';
        }

        $allowed_post_types = apply_filters('aiaf_allowed_post_types', $allowed_post_types);
        $allowed_post_types = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($allowed_post_types) ? $allowed_post_types : []))));

        foreach ($allowed_post_types as $post_type) {
            if (!self::is_allowed_content_post_type($post_type)) {
                continue;
            }

            $object = get_post_type_object($post_type);
            if (!$object) {
                continue;
            }

            $edit_posts_cap = isset($object->cap->edit_posts) ? (string) $object->cap->edit_posts : 'edit_posts';
            if (!current_user_can($edit_posts_cap)) {
                continue;
            }

            $items[] = [
                'slug'                  => (string) $post_type,
                'label'                 => self::get_content_type_label($post_type, $object),
                'kind'                  => 'post_type',
                'supportsFeaturedImage' => post_type_supports($post_type, 'thumbnail'),
            ];
        }

        foreach (self::get_supported_taxonomies() as $taxonomy) {
            $taxonomy_object = get_taxonomy($taxonomy);
            if (!$taxonomy_object || !self::taxonomy_has_eligible_acf_image_fields($taxonomy)) {
                continue;
            }

            $manage_terms_cap = isset($taxonomy_object->cap->manage_terms) ? (string) $taxonomy_object->cap->manage_terms : 'manage_categories';
            $edit_terms_cap = isset($taxonomy_object->cap->edit_terms) ? (string) $taxonomy_object->cap->edit_terms : $manage_terms_cap;
            if (!current_user_can($edit_terms_cap)) {
                continue;
            }

            $items[] = [
                'slug'                  => 'taxonomy:' . $taxonomy,
                'label'                 => self::get_taxonomy_label($taxonomy, $taxonomy_object),
                'kind'                  => 'taxonomy',
                'taxonomy'              => $taxonomy,
                'supportsFeaturedImage' => false,
            ];
        }

        return rest_ensure_response(['postTypes' => $items]);
    }

    private static function is_woocommerce_active(): bool
    {
        return AIAF_Environment::is_woocommerce_active();
    }

    private static function get_content_type_label(string $post_type, WP_Post_Type $object): string
    {
        $labels = [
            'post'    => __('Posts', 'acf-image-auto-filler'),
            'page'    => __("Pages", 'acf-image-auto-filler'),
            'product' => __('Products', 'acf-image-auto-filler'),
        ];

        if (isset($labels[$post_type])) {
            return $labels[$post_type];
        }

        return isset($object->labels->name) ? (string) $object->labels->name : (string) $object->label;
    }


    private static function is_allowed_content_post_type(string $post_type): bool
    {
        if ($post_type === 'product' && !self::is_woocommerce_active()) {
            return false;
        }

        $allowed = ['post', 'page'];
        $allowed = apply_filters('aiaf_allowed_post_types', self::is_woocommerce_active() ? array_merge($allowed, ['product']) : $allowed);
        $allowed = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($allowed) ? $allowed : []))));

        if (!in_array($post_type, $allowed, true) || !post_type_exists($post_type) || $post_type === 'attachment') {
            return false;
        }

        if (!AIAF_ACF_Runtime::is_available() && !post_type_supports($post_type, 'thumbnail')) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $value Raw selector type.
     */
    public static function sanitize_content_type($value): string
    {
        $value = trim((string) $value);
        if (strpos($value, 'taxonomy:') === 0) {
            $taxonomy = sanitize_key(substr($value, 9));
            return $taxonomy !== '' ? 'taxonomy:' . $taxonomy : '';
        }

        return sanitize_key($value);
    }

    private static function is_allowed_content_type(string $content_type): bool
    {
        if (strpos($content_type, 'taxonomy:') === 0) {
            $taxonomy = sanitize_key(substr($content_type, 9));
            return in_array($taxonomy, self::get_supported_taxonomies(), true) && self::taxonomy_has_eligible_acf_image_fields($taxonomy);
        }

        return self::is_allowed_content_post_type(sanitize_key($content_type));
    }

    /**
     * @return array<int, string>
     */
    private static function get_supported_taxonomies(): array
    {
        $taxonomies = [];

        if (taxonomy_exists('category')) {
            $taxonomies[] = 'category';
        }

        if (self::is_woocommerce_active() && taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }

        /**
         * Filter the taxonomy selectors shown by the tool. Keep this narrow.
         *
         * @param array<int, string> $taxonomies Taxonomy slugs.
         */
        $taxonomies = apply_filters('aiaf_allowed_taxonomies', $taxonomies);
        $taxonomies = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($taxonomies) ? $taxonomies : []))));

        return array_values(array_filter($taxonomies, static fn (string $taxonomy): bool => taxonomy_exists($taxonomy)));
    }

    private static function taxonomy_has_eligible_acf_image_fields(string $taxonomy): bool
    {
        static $cache = [];

        $taxonomy = sanitize_key($taxonomy);
        $acf_available = function_exists('acf_get_field_groups') && function_exists('acf_get_fields');
        $cache_key = $taxonomy . '|' . ($acf_available ? 'acf-on' : 'acf-off');

        if (array_key_exists($cache_key, $cache)) {
            return (bool) $cache[$cache_key];
        }

        if (!$acf_available) {
            $cache[$cache_key] = false;
            return false;
        }

        $groups = acf_get_field_groups(['taxonomy' => $taxonomy]);
        if (!is_array($groups)) {
            $cache[$cache_key] = false;
            return false;
        }

        foreach ($groups as $group) {
            if (empty($group['key']) || !is_string($group['key'])) {
                continue;
            }

            $fields = acf_get_fields($group['key']);
            if (self::fields_contain_supported_image_field(is_array($fields) ? $fields : [])) {
                $cache[$cache_key] = true;
                return true;
            }
        }

        $cache[$cache_key] = false;
        return false;
    }

    /**
     * @param array<int, mixed> $fields ACF fields.
     */
    private static function fields_contain_supported_image_field(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = isset($field['type']) ? (string) $field['type'] : '';
            if ($type === 'image') {
                return true;
            }

            if ($type === 'group' && !empty($field['sub_fields']) && is_array($field['sub_fields']) && self::fields_contain_supported_image_field($field['sub_fields'])) {
                return true;
            }
        }

        return false;
    }

    private static function get_taxonomy_label(string $taxonomy, WP_Taxonomy $object): string
    {
        $labels = [
            'category'    => __('Categories', 'acf-image-auto-filler'),
            'product_cat' => __('Product categories', 'acf-image-auto-filler'),
        ];

        return $labels[$taxonomy] ?? (isset($object->labels->name) ? (string) $object->labels->name : (string) $object->label);
    }
}
