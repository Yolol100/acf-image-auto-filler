<?php
/**
 * Preview and fill orchestration for REST mutation requests.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Mutation_Service
{
    public function process(WP_REST_Request $request, bool $execute): WP_REST_Response
    {
        $content_ids = AIAF_Content_ID::from_request($request);
        $attachment_ids = $request->get_param('attachment_ids');
        $overwrite = $request->has_param('overwrite_existing') ? rest_sanitize_boolean($request->get_param('overwrite_existing')) : false;
        $include_groups = rest_sanitize_boolean($request->get_param('include_groups'));
        $use_featured_image = rest_sanitize_boolean($request->get_param('use_featured_image'));

        if (!is_array($attachment_ids)) {
            return new WP_REST_Response(['message' => __('Attachment IDs must be provided as a list.', 'acf-image-auto-filler')], 400);
        }

        if (empty($content_ids)) {
            return new WP_REST_Response(['message' => __('Select at least one editable item.', 'acf-image-auto-filler')], 400);
        }

        foreach ($content_ids as $content_id) {
            if (!AIAF_Content_ID::is_allowed_target((string) $content_id) || !AIAF_Content_ID::current_user_can_edit((string) $content_id)) {
                return new WP_REST_Response(['message' => __('One or more selected items are not editable.', 'acf-image-auto-filler')], 403);
            }
        }

        $max_posts = (int) apply_filters('aiaf_max_posts_per_run', 50);
        if ($max_posts > 0 && count($content_ids) > $max_posts) {
            return new WP_REST_Response([
                'message' => sprintf(
                    /* translators: %d: Maximum number of posts that may be processed in one run. */
                    __('You can process a maximum of %d items per run.', 'acf-image-auto-filler'),
                    $max_posts
                ),
            ], 400);
        }

        $attachment_ids = array_values(array_unique(array_filter(array_map('absint', $attachment_ids))));
        $max_items = (int) apply_filters('aiaf_max_attachments_per_run', 100);
        if ($max_items > 0 && count($attachment_ids) > $max_items) {
            return new WP_REST_Response([
                'message' => sprintf(
                    /* translators: %d: Maximum number of images that may be selected in one run. */
                    __('You can select a maximum of %d images per run.', 'acf-image-auto-filler'),
                    $max_items
                ),
            ], 400);
        }

        $field_keys = $request->get_param('field_keys');
        $field_keys = is_array($field_keys) ? array_values(array_filter(array_map('sanitize_key', $field_keys))) : [];
        $max_field_keys = (int) apply_filters('aiaf_max_field_keys_per_run', 500);
        if ($max_field_keys > 0 && count($field_keys) > $max_field_keys) {
            return new WP_REST_Response([
                'message' => sprintf(
                    /* translators: %d: Maximum number of fields that may be processed in one run. */
                    __('You can process up to %d fields per run.', 'acf-image-auto-filler'),
                    $max_field_keys
                ),
            ], 400);
        }

        $manual_mapping = AIAF_Manual_Mapping::sanitize($request->get_param('manual_mapping'));
        $max_manual_mapping = (int) apply_filters('aiaf_max_manual_mapping_items_per_run', 500);
        if ($max_manual_mapping > 0 && count($manual_mapping) > $max_manual_mapping) {
            return new WP_REST_Response([
                'message' => sprintf(
                    /* translators: %d: Maximum number of manual mapping items that may be processed in one run. */
                    __('You can process a maximum of %d manual mappings per run.', 'acf-image-auto-filler'),
                    $max_manual_mapping
                ),
            ], 400);
        }

        $attachment_ids = array_values(array_unique(array_merge($attachment_ids, array_values($manual_mapping))));
        $acf_available = AIAF_ACF_Runtime::is_available();

        if (!$acf_available) {
            $field_keys = [];
            $manual_mapping = [];
            $include_groups = false;
        }

        $has_acf_targets = $acf_available && (!empty($field_keys) || !empty($manual_mapping));
        $has_featured_image_targets = $use_featured_image && AIAF_Content_ID::contains_featured_image_target($content_ids);
        if (!$has_acf_targets && !$has_featured_image_targets) {
            return new WP_REST_Response([
                'message' => $acf_available
                    ? __('Select at least one ACF image field or a content item that supports featured images.', 'acf-image-auto-filler')
                    : __('Select a content item that supports featured images.', 'acf-image-auto-filler'),
            ], 400);
        }

        $writer = new AIAF_ACF_Writer();
        $scanner = new AIAF_Field_Scanner();
        $combined = [
            'filled'        => [],
            'skipped'       => [],
            'errors'        => [],
            'executed'      => $execute,
            'rollbackRunId' => '',
            'batch'         => count($content_ids) > 1,
        ];
        $rollback_items = [];

        foreach ($content_ids as $content_id) {
            $acf_target_id = AIAF_Content_ID::to_acf_target($content_id);
            $is_term_target = is_string($acf_target_id) && strpos($acf_target_id, 'term_') === 0;
            $post_mapping = $this->mapping_for_content_id($manual_mapping, (string) $content_id);
            $post_field_keys = array_values(array_unique(array_merge($field_keys, array_keys($post_mapping))));
            $has_post_acf_targets = !empty($post_field_keys);

            $fields = $has_post_acf_targets ? $scanner->get_image_fields($acf_target_id, $include_groups) : [];
            if (empty($fields) && (!$use_featured_image || $is_term_target)) {
                $combined['skipped'][] = [
                    'post_id'     => $content_id,
                    'field_label' => __('No suitable image fields', 'acf-image-auto-filler'),
                    'field_name'  => '',
                    'field_key'   => '',
                    'reason'      => __('No suitable ACF image fields were found for this item.', 'acf-image-auto-filler'),
                    'status'      => 'no_fields',
                ];
                continue;
            }

            $featured_image_id = (!$is_term_target && $use_featured_image && !empty($attachment_ids) && AIAF_Content_ID::supports_featured_image($content_id)) ? (int) $attachment_ids[0] : 0;
            $result = $writer->process($acf_target_id, $fields, $attachment_ids, $overwrite, $execute, $post_field_keys, $post_mapping, $featured_image_id);
            $combined['filled'] = array_merge($combined['filled'], $result['filled']);
            $combined['skipped'] = array_merge($combined['skipped'], $result['skipped']);
            $combined['errors'] = array_values(array_unique(array_merge($combined['errors'], $result['errors'])));
            if (!empty($result['rollback']) && is_array($result['rollback'])) {
                $rollback_items = array_merge($rollback_items, $result['rollback']);
            }
        }

        if ($execute && !empty($rollback_items)) {
            $manager = new AIAF_Rollback_Manager();
            $combined['rollbackRunId'] = $manager->save_run($rollback_items);
        }

        return rest_ensure_response($combined);
    }

    /**
     * @param array<string, int> $manual_mapping Sanitized mapping.
     * @return array<string, int>
     */
    private function mapping_for_content_id(array $manual_mapping, string $content_id): array
    {
        $global_mapping = [];
        $specific_mapping = [];

        foreach ($manual_mapping as $mapping_key => $attachment_id) {
            $mapping_key = (string) $mapping_key;
            $prefix = $content_id . ':';
            if (strpos($mapping_key, $prefix) === 0) {
                $field_key = sanitize_key(substr($mapping_key, strlen($prefix)));
                if ($field_key !== '') {
                    $specific_mapping[$field_key] = absint($attachment_id);
                }
                continue;
            }

            if (strpos($mapping_key, ':') === false) {
                $field_key = sanitize_key($mapping_key);
                if ($field_key !== '') {
                    $global_mapping[$field_key] = absint($attachment_id);
                }
            }
        }

        return array_merge($global_mapping, $specific_mapping);
    }
}
