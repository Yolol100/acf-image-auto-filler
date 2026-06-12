<?php
/**
 * Validates attachment IDs and writes ACF image values.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_ACF_Writer
{
    /**
     * Build or execute the image-to-field mapping.
     *
     * @param int   $post_id Post ID.
     * @param array<int, array<string, mixed>> $fields ACF image fields.
     * @param array<int, int> $attachment_ids Attachment IDs.
     * @param bool  $overwrite Whether to overwrite existing values.
     * @param bool  $execute Whether to write values.
     * @param array<int, string> $field_keys Optional selected field keys.
     * @param array<string, int> $manual_mapping Field key to attachment ID.
     * @param int $featured_image_id Optional featured image ID.
     * @return array<string, mixed>
     */
    public function process(
        int $post_id,
        array $fields,
        array $attachment_ids,
        bool $overwrite,
        bool $execute,
        array $field_keys = [],
        array $manual_mapping = [],
        int $featured_image_id = 0
    ): array {
        $result = [
            'filled'    => [],
            'skipped'   => [],
            'errors'    => [],
            'rollback'  => [],
        ];

        $needs_acf = !empty($fields);
        if ($needs_acf && (!function_exists('get_field') || !function_exists('update_field'))) {
            $result['errors'][] = __('ACF is not active or required ACF functions are unavailable.', 'acf-image-auto-filler');
            return $result;
        }

        $had_fields_before_filter = !empty($fields);
        $field_key_allowlist = array_values(array_filter(array_map('sanitize_key', $field_keys)));
        if (!empty($field_key_allowlist)) {
            $fields = array_values(array_filter($fields, static function (array $field) use ($field_key_allowlist): bool {
                return isset($field['key']) && in_array((string) $field['key'], $field_key_allowlist, true);
            }));
        }

        if (empty($fields) && $had_fields_before_filter && !empty($field_key_allowlist)) {
            $result['skipped'][] = [
                'post_id'     => $post_id,
                'field_label' => __('Selected fields unavailable', 'acf-image-auto-filler'),
                'field_name'  => '',
                'field_key'   => '',
                'reason'      => __('The selected ACF image fields are not available for this post.', 'acf-image-auto-filler'),
                'status'      => 'missing_selected_fields',
            ];
        }

        if (empty($fields) && !$had_fields_before_filter && $featured_image_id <= 0) {
            $result['skipped'][] = [
                'post_id'     => $post_id,
                'field_label' => __('No eligible fields', 'acf-image-auto-filler'),
                'field_name'  => '',
                'field_key'   => '',
                'reason'      => __('No eligible ACF image fields were found for this post.', 'acf-image-auto-filler'),
                'status'      => 'no_fields',
            ];
        }

        $valid_attachment_ids = $this->filter_valid_image_attachments($attachment_ids, $result);
        if ($featured_image_id > 0 && !in_array($featured_image_id, $valid_attachment_ids, true)) {
            return $result;
        }

        $auto_attachment_ids = $valid_attachment_ids;
        if ($featured_image_id > 0) {
            $auto_attachment_ids = array_values(array_filter($valid_attachment_ids, static function (int $attachment_id) use ($featured_image_id): bool {
                return $attachment_id !== $featured_image_id;
            }));
        }

        if (empty($valid_attachment_ids) && $featured_image_id <= 0 && empty($manual_mapping)) {
            $result['errors'][] = __('No valid image attachments were selected.', 'acf-image-auto-filler');
            return $result;
        }

        $valid_lookup = array_fill_keys($valid_attachment_ids, true);
        $used_attachment_ids = [];
        $image_index = 0;

        foreach ($fields as $field) {
            if (!isset($field['key'], $field['name'], $field['label'])) {
                continue;
            }

            $field_key = sanitize_key((string) $field['key']);
            $field_name = (string) $field['name'];
            $field_label = (string) $field['label'];
            $scope = isset($field['scope']) ? (string) $field['scope'] : 'top_level';
            $parent_key = isset($field['parent_key']) ? sanitize_key((string) $field['parent_key']) : '';
            $current_value = $this->get_current_field_value($post_id, $field_key, $field_name, $scope, $parent_key);
            $current_attachment_id = $this->normalize_attachment_id($current_value);
            $has_current_value = $current_attachment_id > 0;
            $attachment_id = 0;

            if (isset($manual_mapping[$field_key])) {
                $candidate = absint($manual_mapping[$field_key]);
                if ($candidate > 0 && isset($valid_lookup[$candidate])) {
                    $attachment_id = $candidate;
                }
            } else {
                if ($has_current_value && !$overwrite) {
                    $result['skipped'][] = [
                        'post_id'     => $post_id,
                        'field_label' => $field_label,
                        'field_name'  => $field_name,
                        'field_key'   => $field_key,
                        'reason'      => __('Field already has a value.', 'acf-image-auto-filler'),
                        'status'      => 'existing',
                    ];
                    continue;
                }

                if (isset($auto_attachment_ids[$image_index])) {
                    $attachment_id = (int) $auto_attachment_ids[$image_index];
                    $image_index++;
                }
            }

            if ($attachment_id <= 0) {
                $result['skipped'][] = [
                    'post_id'     => $post_id,
                    'field_label' => $field_label,
                    'field_name'  => $field_name,
                    'field_key'   => $field_key,
                    'reason'      => __('No image selected for this field.', 'acf-image-auto-filler'),
                    'status'      => 'no_image',
                ];
                continue;
            }

            if ($has_current_value && !$overwrite && isset($manual_mapping[$field_key])) {
                $result['skipped'][] = [
                    'post_id'     => $post_id,
                    'field_label' => $field_label,
                    'field_name'  => $field_name,
                    'field_key'   => $field_key,
                    'reason'      => __('Field already has a value.', 'acf-image-auto-filler'),
                    'status'      => 'existing',
                ];
                continue;
            }

            if ($current_attachment_id === $attachment_id) {
                $used_attachment_ids[] = $attachment_id;
                $result['skipped'][] = [
                    'post_id'       => $post_id,
                    'field_label'   => $field_label,
                    'field_name'    => $field_name,
                    'field_key'     => $field_key,
                    'attachment_id' => $attachment_id,
                    'reason'        => __('Field already contains this image.', 'acf-image-auto-filler'),
                    'status'        => 'unchanged',
                ];
                continue;
            }

            if ($execute) {
                $updated = $this->update_image_field($post_id, $field_key, $field_name, $attachment_id, $scope, $parent_key);
                if ($updated === false) {
                    $result['errors'][] = sprintf(
                        /* translators: 1: field label, 2: attachment ID. */
                        __('Could not update field "%1$s" with attachment ID %2$d.', 'acf-image-auto-filler'),
                        $field_label,
                        $attachment_id
                    );
                    continue;
                }

                $used_attachment_ids[] = $attachment_id;

                $result['rollback'][] = [
                    'type'                   => 'acf_image',
                    'post_id'                => $post_id,
                    'field_key'              => $field_key,
                    'field_name'             => $field_name,
                    'field_label'            => $field_label,
                    'scope'                  => $scope,
                    'parent_key'             => $parent_key,
                    'previous_attachment_id' => $current_attachment_id,
                    'new_attachment_id'      => $attachment_id,
                ];
            } else {
                $used_attachment_ids[] = $attachment_id;
            }

            $result['filled'][] = [
                'post_id'               => $post_id,
                'post_title'            => wp_strip_all_tags(get_the_title($post_id)),
                'field_label'           => $field_label,
                'field_name'            => $field_name,
                'field_key'             => $field_key,
                'attachment_id'         => $attachment_id,
                'attachment_title'      => get_the_title($attachment_id),
                'thumbnail'             => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
                'medium'                => wp_get_attachment_image_url($attachment_id, 'medium'),
                'executed'              => $execute,
                'will_overwrite'        => $has_current_value,
                'current_attachment_id' => $current_attachment_id,
            ];
        }

        $used_attachment_ids = array_values(array_unique(array_map('absint', $used_attachment_ids)));
        $unused_image_count = empty($manual_mapping)
            ? count(array_diff($auto_attachment_ids, $used_attachment_ids))
            : count(array_diff($valid_attachment_ids, $used_attachment_ids));
        if (empty($manual_mapping) && $unused_image_count > 0) {
            $result['skipped'][] = [
                'post_id'     => $post_id,
                'field_label' => __('Extra images', 'acf-image-auto-filler'),
                'field_name'  => '',
                'field_key'   => '',
                'reason'      => sprintf(
                    /* translators: %d: number of images. */
                    __('%d selected image(s) were not used because there were not enough eligible fields.', 'acf-image-auto-filler'),
                    $unused_image_count
                ),
                'status'      => 'extra_images',
            ];
        }

        if ($featured_image_id > 0) {
            $this->process_featured_image($post_id, $featured_image_id, $overwrite, $execute, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result Result reference.
     */
    private function process_featured_image(int $post_id, int $attachment_id, bool $overwrite, bool $execute, array &$result): void
    {
        if (!wp_attachment_is_image($attachment_id)) {
            $result['errors'][] = __('The selected featured image is not a valid image attachment.', 'acf-image-auto-filler');
            return;
        }

        $current = get_post_thumbnail_id($post_id);
        $current = $current ? absint($current) : 0;

        if ($current > 0 && !$overwrite) {
            $result['skipped'][] = [
                'post_id'     => $post_id,
                'field_label' => __('Featured image', 'acf-image-auto-filler'),
                'field_name'  => '_thumbnail_id',
                'field_key'   => '_thumbnail_id',
                'reason'      => __('Featured image already has a value.', 'acf-image-auto-filler'),
                'status'      => 'existing',
            ];
            return;
        }

        if ($current === $attachment_id) {
            $result['skipped'][] = [
                'post_id'       => $post_id,
                'field_label'   => __('Featured image', 'acf-image-auto-filler'),
                'field_name'    => '_thumbnail_id',
                'field_key'     => '_thumbnail_id',
                'attachment_id' => $attachment_id,
                'reason'        => __('Featured image already contains this image.', 'acf-image-auto-filler'),
                'status'        => 'unchanged',
            ];
            return;
        }

        if ($execute) {
            $ok = set_post_thumbnail($post_id, $attachment_id);
            if (!$ok) {
                $result['errors'][] = __('Could not update the featured image.', 'acf-image-auto-filler');
                return;
            }
            $result['rollback'][] = [
                'type'                   => 'featured_image',
                'post_id'                => $post_id,
                'field_key'              => '_thumbnail_id',
                'field_name'             => '_thumbnail_id',
                'field_label'            => __('Featured image', 'acf-image-auto-filler'),
                'previous_attachment_id' => $current,
                'new_attachment_id'      => $attachment_id,
            ];
        }

        $result['filled'][] = [
            'post_id'               => $post_id,
            'post_title'            => wp_strip_all_tags(get_the_title($post_id)),
            'field_label'           => __('Featured image', 'acf-image-auto-filler'),
            'field_name'            => '_thumbnail_id',
            'field_key'             => '_thumbnail_id',
            'attachment_id'         => $attachment_id,
            'attachment_title'      => get_the_title($attachment_id),
            'thumbnail'             => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
            'medium'                => wp_get_attachment_image_url($attachment_id, 'medium'),
            'executed'              => $execute,
            'will_overwrite'        => $current > 0,
            'current_attachment_id' => $current,
        ];
    }


    /**
     * Read a top-level ACF image field or an image subfield inside an ACF group.
     *
     * @param int    $post_id Post ID.
     * @param string $field_key Field key.
     * @param string $field_name Field name.
     * @param string $scope Field scope.
     * @param string $parent_key Parent group field key.
     * @return mixed
     */
    private function get_current_field_value(int $post_id, string $field_key, string $field_name, string $scope, string $parent_key)
    {
        if ($scope === 'group' && $parent_key !== '') {
            $group_value = get_field($parent_key, $post_id, false);
            if (is_array($group_value) && array_key_exists($field_name, $group_value)) {
                return $group_value[$field_name];
            }
        }

        return get_field($field_key, $post_id, false);
    }

    /**
     * Update a top-level ACF image field or an image subfield inside an ACF group.
     */
    private function update_image_field(int $post_id, string $field_key, string $field_name, int $attachment_id, string $scope, string $parent_key): bool
    {
        if ($scope === 'group' && $parent_key !== '') {
            $group_value = get_field($parent_key, $post_id, false);
            if (!is_array($group_value)) {
                $group_value = [];
            }
            $group_value[$field_name] = $attachment_id;

            return update_field($parent_key, $group_value, $post_id) !== false;
        }

        return update_field($field_key, $attachment_id, $post_id) !== false;
    }

    /**
     * @param array<int, int> $attachment_ids Attachment IDs.
     * @param array<string, mixed> $result Result reference.
     * @return array<int, int>
     */
    private function filter_valid_image_attachments(array $attachment_ids, array &$result): array
    {
        $valid = [];

        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = absint($attachment_id);
            if ($attachment_id <= 0) {
                continue;
            }

            if (get_post_type($attachment_id) !== 'attachment') {
                $result['errors'][] = sprintf(
                    /* translators: %d: Attachment ID. */
                    __('ID %d is not an attachment.', 'acf-image-auto-filler'),
                    $attachment_id
                );
                continue;
            }

            if (!wp_attachment_is_image($attachment_id)) {
                $result['errors'][] = sprintf(
                    /* translators: %d: Attachment ID. */
                    __('Attachment ID %d is not an image.', 'acf-image-auto-filler'),
                    $attachment_id
                );
                continue;
            }

            if (!current_user_can('read_post', $attachment_id)) {
                $result['errors'][] = sprintf(
                    /* translators: %d: Attachment ID. */
                    __('You do not have permission to use attachment ID %d.', 'acf-image-auto-filler'),
                    $attachment_id
                );
                continue;
            }

            $valid[] = $attachment_id;
        }

        return array_values(array_unique($valid));
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
