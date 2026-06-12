<?php
/**
 * Finds eligible ACF image fields for a post.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Field_Scanner
{
    /**
     * Request-local cache for ACF field scans.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private static array $image_fields_cache = [];
    /**
     * Return eligible ACF image fields for a post.
     *
     * @param int  $post_id Post ID.
     * @param bool $include_groups Include image fields inside ACF group fields.
     * @return array<int, array<string, mixed>>
     */
    public function get_image_fields(int $post_id, bool $include_groups = false): array
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return [];
        }

        $cache_key = $post_id . ':' . ($include_groups ? 'groups' : 'top');
        if (array_key_exists($cache_key, self::$image_fields_cache)) {
            return self::$image_fields_cache[$cache_key];
        }

        $field_groups = acf_get_field_groups(['post_id' => $post_id]);
        if (!is_array($field_groups)) {
            return [];
        }

        $fields = [];

        foreach ($field_groups as $group) {
            if (empty($group['key']) || !is_string($group['key'])) {
                continue;
            }

            $group_fields = acf_get_fields($group['key']);
            if (!is_array($group_fields)) {
                continue;
            }

            foreach ($group_fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $this->collect_field($fields, $field, $post_id, [
                    'group_title' => isset($group['title']) ? (string) $group['title'] : '',
                    'path'        => [],
                    'parent_type' => 'top_level',
                ], $include_groups);
            }
        }

        usort($fields, static function (array $a, array $b): int {
            return ((int) $a['menu_order'] <=> (int) $b['menu_order']) ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        self::$image_fields_cache[$cache_key] = array_values($fields);

        return self::$image_fields_cache[$cache_key];
    }

    /**
     * Backwards-compatible alias for earlier plugin versions.
     *
     * @param int $post_id Post ID.
     * @return array<int, array<string, mixed>>
     */
    public function get_top_level_image_fields(int $post_id): array
    {
        return $this->get_image_fields($post_id, false);
    }

    /**
     * @param array<int, array<string, mixed>> $fields Collected fields.
     * @param array<string, mixed> $field ACF field.
     * @param int $post_id Post ID.
     * @param array<string, mixed> $context Context.
     * @param bool $include_groups Include group subfields.
     */
    private function collect_field(array &$fields, array $field, int $post_id, array $context, bool $include_groups): void
    {
        $type = isset($field['type']) ? (string) $field['type'] : '';

        if ($type === 'image') {
            if (empty($field['key']) || empty($field['name'])) {
                return;
            }

            $field_key = (string) $field['key'];
            $field_name = (string) $field['name'];
            $parent_type = isset($context['parent_type']) ? (string) $context['parent_type'] : 'top_level';
            $parent_key = isset($context['parent_key']) ? (string) $context['parent_key'] : '';
            $parent_name = isset($context['parent_name']) ? (string) $context['parent_name'] : '';
            $current_value = function_exists('get_field') ? get_field($field_key, $post_id, false) : null;

            if ($parent_type === 'group' && $parent_key !== '') {
                $group_value = function_exists('get_field') ? get_field($parent_key, $post_id, false) : null;
                if (is_array($group_value) && array_key_exists($field_name, $group_value)) {
                    $current_value = $group_value[$field_name];
                }
            }

            $current_attachment_id = $this->normalize_attachment_id($current_value);
            $path = isset($context['path']) && is_array($context['path']) ? $context['path'] : [];
            $path[] = isset($field['label']) ? (string) $field['label'] : (string) $field['name'];

            $fields[] = [
                'key'                   => $field_key,
                'name'                  => $field_name,
                'label'                 => isset($field['label']) ? (string) $field['label'] : (string) $field['name'],
                'menu_order'            => isset($field['menu_order']) ? (int) $field['menu_order'] : 0,
                'return_format'         => isset($field['return_format']) ? (string) $field['return_format'] : '',
                'group_title'           => isset($context['group_title']) ? (string) $context['group_title'] : '',
                'path'                  => implode(' > ', $path),
                'scope'                 => $parent_type,
                'parent_key'            => $parent_key,
                'parent_name'           => $parent_name,
                'has_value'             => $current_attachment_id > 0,
                'current_attachment_id' => $current_attachment_id,
                'current_thumbnail'     => $current_attachment_id > 0 ? (string) wp_get_attachment_image_url($current_attachment_id, 'thumbnail') : '',
                'current_title'         => $current_attachment_id > 0 ? (string) get_the_title($current_attachment_id) : '',
            ];
            return;
        }

        if ($type === 'group' && $include_groups && !empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            $path = isset($context['path']) && is_array($context['path']) ? $context['path'] : [];
            $path[] = isset($field['label']) ? (string) $field['label'] : (string) ($field['name'] ?? 'group');

            foreach ($field['sub_fields'] as $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $this->collect_field($fields, $sub_field, $post_id, [
                    'group_title' => isset($context['group_title']) ? (string) $context['group_title'] : '',
                    'path'        => $path,
                    'parent_type' => 'group',
                    'parent_key'  => isset($field['key']) ? (string) $field['key'] : '',
                    'parent_name' => isset($field['name']) ? (string) $field['name'] : '',
                ], $include_groups);
            }
        }

        // Intentionally do not recurse into repeater, flexible_content, gallery or clone fields.
    }

    /**
     * @param mixed $value ACF raw image value.
     */
    private function normalize_attachment_id($value): int
    {
        if (is_numeric($value)) {
            return absint($value);
        }

        if (is_array($value) && isset($value['ID'])) {
            return absint($value['ID']);
        }

        if (is_array($value) && isset($value['id'])) {
            return absint($value['id']);
        }

        return 0;
    }
}
