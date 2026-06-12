<?php
/**
 * REST API controller for the React admin app.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_REST_Controller
{
    private const NAMESPACE = 'acf-image-auto-filler/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/post-types', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_post_types'],
            'permission_callback' => [self::class, 'can_view_tool'],
        ]);

        register_rest_route(self::NAMESPACE, '/posts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_posts'],
            'permission_callback' => [self::class, 'can_view_tool'],
            'args'                => [
                'post_type' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => static function ($value): bool {
                        return is_string($value) && post_type_exists($value) && $value !== 'attachment';
                    },
                ],
                'search' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fields', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_fields'],
            'permission_callback' => [self::class, 'can_edit_requested_post'],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0,
                ],
                'include_groups' => [
                    'required'          => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/preview', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'preview'],
            'permission_callback' => [self::class, 'can_edit_any_requested_post'],
            'args'                => self::mutation_args(),
        ]);

        register_rest_route(self::NAMESPACE, '/fill', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'fill'],
            'permission_callback' => [self::class, 'can_edit_any_requested_post'],
            'args'                => self::mutation_args(),
        ]);

        register_rest_route(self::NAMESPACE, '/rollback-last', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'rollback_last'],
            'permission_callback' => [self::class, 'can_mutate_tool'],
        ]);

        register_rest_route(self::NAMESPACE, '/rollback-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'rollback_status'],
            'permission_callback' => [self::class, 'can_view_tool'],
        ]);

        register_rest_route(self::NAMESPACE, '/audit-log', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_audit_log'],
            'permission_callback' => [self::class, 'can_view_audit_log'],
        ]);
    }

    public static function can_view_tool(): bool
    {
        return current_user_can(self::view_capability());
    }

    public static function can_mutate_tool(): bool
    {
        return current_user_can(self::mutate_capability());
    }

    private static function legacy_capability(): string
    {
        /**
         * Legacy filter for the capability required to use ACF Image Auto Filler.
         *
         * Kept for backward compatibility. Prefer aiaf_view_capability,
         * aiaf_mutate_capability and aiaf_audit_log_capability for new projects.
         *
         * @param string $capability Required capability.
         */
        $capability = apply_filters('aiaf_required_capability', 'manage_options');

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    private static function view_capability(): string
    {
        /**
         * Filters the capability required to browse the REST data used by the tool.
         *
         * @param string $capability Required capability.
         */
        $capability = apply_filters('aiaf_view_capability', self::legacy_capability());

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    private static function mutate_capability(): string
    {
        /**
         * Filters the capability required to preview, write and rollback image assignments.
         *
         * Defaults to manage_options so the legacy aiaf_required_capability filter can
         * safely grant read access without also lowering bulk mutation rights. Lower this
         * only for trusted roles; per-post edit_post checks are still enforced.
         *
         * @param string $capability Required capability.
         */
        $capability = apply_filters('aiaf_mutate_capability', 'manage_options');

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    private static function audit_log_capability(): string
    {
        /**
         * Filters the capability required to view the audit log.
         *
         * @param string $capability Required capability.
         */
        $capability = apply_filters('aiaf_audit_log_capability', 'manage_options');

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    public static function can_view_audit_log(): bool
    {
        return current_user_can(self::audit_log_capability());
    }

    public static function can_edit_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_view_tool()) {
            return false;
        }

        $post_id = absint($request->get_param('post_id'));
        if ($post_id <= 0) {
            $json = $request->get_json_params();
            $post_id = isset($json['post_id']) ? absint($json['post_id']) : 0;
        }

        return $post_id > 0 && current_user_can('edit_post', $post_id);
    }

    public static function can_edit_any_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_mutate_tool()) {
            return false;
        }

        $post_ids = self::get_request_post_ids($request);
        if (empty($post_ids)) {
            return false;
        }

        $max_posts = (int) apply_filters('aiaf_max_posts_per_run', 50);
        if ($max_posts > 0 && count($post_ids) > $max_posts) {
            return false;
        }

        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                return false;
            }
        }

        return true;
    }

    public static function get_post_types(): WP_REST_Response
    {
        $objects = get_post_types(['show_ui' => true], 'objects');
        $items = [];

        foreach ($objects as $post_type => $object) {
            if ($post_type === 'attachment') {
                continue;
            }

            $edit_posts_cap = isset($object->cap->edit_posts) ? (string) $object->cap->edit_posts : 'edit_posts';
            if (!current_user_can($edit_posts_cap)) {
                continue;
            }

            $items[] = [
                'slug'  => (string) $post_type,
                'label' => isset($object->labels->singular_name) ? (string) $object->labels->singular_name : (string) $object->label,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']));

        return rest_ensure_response(['postTypes' => $items]);
    }

    public static function get_posts(WP_REST_Request $request): WP_REST_Response
    {
        $post_type = sanitize_key((string) $request->get_param('post_type'));
        $search = sanitize_text_field((string) ($request->get_param('search') ?? ''));

        if (!post_type_exists($post_type) || $post_type === 'attachment') {
            return new WP_REST_Response(['message' => __('Invalid post type.', 'acf-image-auto-filler')], 400);
        }

        $post_type_object = get_post_type_object($post_type);
        $edit_posts_cap = $post_type_object && isset($post_type_object->cap->edit_posts) ? (string) $post_type_object->cap->edit_posts : 'edit_posts';
        if (!current_user_can($edit_posts_cap)) {
            return new WP_REST_Response(['message' => __('You do not have permission to view this post type.', 'acf-image-auto-filler')], 403);
        }

        $query = new WP_Query([
            'post_type'              => $post_type,
            'post_status'            => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page'         => 100,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            's'                      => $search,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post || !current_user_can('edit_post', (int) $post->ID)) {
                continue;
            }

            $title = get_the_title($post);
            if ($title === '') {
                $title = sprintf(
                    /* translators: %d: Post ID. */
                    __('Post #%d', 'acf-image-auto-filler'),
                    (int) $post->ID
                );
            }

            $items[] = [
                'id'     => (int) $post->ID,
                'title'  => wp_strip_all_tags($title),
                'status' => (string) get_post_status($post),
                'date'   => (string) get_the_date('', $post),
            ];
        }

        return rest_ensure_response(['posts' => $items]);
    }

    public static function get_fields(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('post_id'));
        $include_groups = rest_sanitize_boolean($request->get_param('include_groups'));

        if (!self::is_acf_runtime_available()) {
            return new WP_REST_Response([
                'acfActive' => false,
                'fields'    => [],
                'message'   => __('Advanced Custom Fields is not active or required ACF functions are unavailable.', 'acf-image-auto-filler'),
            ], 200);
        }

        $scanner = new AIAF_Field_Scanner();
        $fields = $scanner->get_image_fields($post_id, $include_groups);

        return rest_ensure_response([
            'acfActive' => true,
            'fields'    => $fields,
        ]);
    }

    public static function preview(WP_REST_Request $request): WP_REST_Response
    {
        return self::process_mutation($request, false);
    }

    public static function fill(WP_REST_Request $request): WP_REST_Response
    {
        return self::process_mutation($request, true);
    }

    public static function rollback_last(): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response($manager->rollback_last());
    }

    public static function rollback_status(): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response(['hasRollback' => $manager->get_last_run() !== null]);
    }

    public static function get_audit_log(): WP_REST_Response
    {
        if (!current_user_can(self::audit_log_capability())) {
            return new WP_REST_Response([
                'message' => __('You do not have permission to view the audit log.', 'acf-image-auto-filler'),
            ], 403);
        }

        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response(['items' => $manager->get_audit_log()]);
    }

    private static function is_acf_runtime_available(): bool
    {
        return function_exists('acf_get_field_groups')
            && function_exists('acf_get_fields')
            && function_exists('get_field')
            && function_exists('update_field');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function mutation_args(): array
    {
        return [
            'post_id' => [
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn ($value): bool => absint($value) > 0,
            ],
            'post_ids' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'integer',
                ],
                'validate_callback' => [self::class, 'validate_positive_integer_array'],
            ],
            'attachment_ids' => [
                'required'          => true,
                'type'              => 'array',
                'items'             => [
                    'type' => 'integer',
                ],
                'validate_callback' => [self::class, 'validate_positive_integer_array'],
            ],
            'field_keys' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'string',
                ],
                'sanitize_callback' => [self::class, 'sanitize_string_array'],
                'validate_callback' => static fn ($value): bool => is_null($value) || is_array($value),
            ],
            'manual_mapping' => [
                'required'          => false,
                'validate_callback' => static fn ($value): bool => is_null($value) || is_array($value),
            ],
            'overwrite_existing' => [
                'required'          => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'include_groups' => [
                'required'          => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'use_featured_image' => [
                'required'          => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
        ];
    }


    public static function validate_positive_integer_array($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (absint($item) <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, string>
     */
    public static function sanitize_string_array($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_key', $value)));
    }

    private static function process_mutation(WP_REST_Request $request, bool $execute): WP_REST_Response
    {
        $post_ids = self::get_request_post_ids($request);
        $attachment_ids = $request->get_param('attachment_ids');
        $overwrite = rest_sanitize_boolean($request->get_param('overwrite_existing'));
        $include_groups = rest_sanitize_boolean($request->get_param('include_groups'));
        $use_featured_image = rest_sanitize_boolean($request->get_param('use_featured_image'));

        if (!is_array($attachment_ids)) {
            return new WP_REST_Response(['message' => __('Attachment IDs must be an array.', 'acf-image-auto-filler')], 400);
        }

        if (empty($post_ids)) {
            return new WP_REST_Response(['message' => __('Select at least one editable post.', 'acf-image-auto-filler')], 400);
        }

        $max_posts = (int) apply_filters('aiaf_max_posts_per_run', 50);
        if ($max_posts > 0 && count($post_ids) > $max_posts) {
            $message = sprintf(
                /* translators: %d: Maximum number of posts that may be processed in one run. */
                __('You can process a maximum of %d posts per run.', 'acf-image-auto-filler'),
                $max_posts
            );

            return new WP_REST_Response([
                'message' => $message,
            ], 400);
        }

        $attachment_ids = array_values(array_unique(array_map('absint', $attachment_ids)));
        $max_items = (int) apply_filters('aiaf_max_attachments_per_run', 100);
        if ($max_items > 0 && count($attachment_ids) > $max_items) {
            $message = sprintf(
                /* translators: %d: Maximum number of images that may be selected in one run. */
                __('You can select a maximum of %d images per run.', 'acf-image-auto-filler'),
                $max_items
            );

            return new WP_REST_Response([
                'message' => $message,
            ], 400);
        }

        $field_keys = $request->get_param('field_keys');
        $field_keys = is_array($field_keys) ? array_values(array_filter(array_map('sanitize_key', $field_keys))) : [];
        $manual_mapping = self::sanitize_manual_mapping($request->get_param('manual_mapping'));

        $has_acf_targets = !empty($field_keys) || !empty($manual_mapping);
        if (!$has_acf_targets && !$use_featured_image) {
            return new WP_REST_Response([
                'message' => __('Select at least one ACF image field or enable the featured image option.', 'acf-image-auto-filler'),
            ], 400);
        }

        $acf_available = self::is_acf_runtime_available();
        if (!$acf_available && $has_acf_targets) {
            return new WP_REST_Response([
                'message' => __('Advanced Custom Fields is not active or required ACF functions are unavailable.', 'acf-image-auto-filler'),
            ], 400);
        }

        $writer = new AIAF_ACF_Writer();
        $scanner = new AIAF_Field_Scanner();
        $combined = [
            'filled'   => [],
            'skipped'  => [],
            'errors'   => [],
            'executed' => $execute,
            'rollbackRunId' => '',
            'batch'    => count($post_ids) > 1,
        ];
        $rollback_items = [];

        foreach ($post_ids as $post_id) {
            $global_mapping = [];
            $specific_mapping = [];
            foreach ($manual_mapping as $mapping_key => $attachment_id) {
                $parts = explode(':', (string) $mapping_key, 2);
                if (count($parts) === 2 && absint($parts[0]) === $post_id) {
                    $specific_mapping[sanitize_key($parts[1])] = absint($attachment_id);
                } elseif (count($parts) === 1) {
                    $global_mapping[sanitize_key($parts[0])] = absint($attachment_id);
                }
            }
            $post_mapping = array_merge($global_mapping, $specific_mapping);
            $post_field_keys = array_values(array_unique(array_merge($field_keys, array_keys($post_mapping))));
            $has_post_acf_targets = !empty($post_field_keys);

            $fields = $has_post_acf_targets ? $scanner->get_image_fields($post_id, $include_groups) : [];
            if (empty($fields) && !$use_featured_image) {
                $combined['skipped'][] = [
                    'post_id'     => $post_id,
                    'field_label' => __('No eligible fields', 'acf-image-auto-filler'),
                    'field_name'  => '',
                    'field_key'   => '',
                    'reason'      => __('No eligible ACF image fields were found for this post.', 'acf-image-auto-filler'),
                    'status'      => 'no_fields',
                ];
                continue;
            }

            $featured_image_id = $use_featured_image && !empty($attachment_ids) ? (int) $attachment_ids[0] : 0;
            $result = $writer->process($post_id, $fields, $attachment_ids, $overwrite, $execute, $post_field_keys, $post_mapping, $featured_image_id);
            $combined['filled'] = array_merge($combined['filled'], $result['filled']);
            $combined['skipped'] = array_merge($combined['skipped'], $result['skipped']);
            $combined['errors'] = array_merge($combined['errors'], $result['errors']);
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
     * @return array<int, int>
     */
    private static function get_request_post_ids(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        $post_ids = $request->get_param('post_ids');
        if (!is_array($post_ids) && isset($json['post_ids']) && is_array($json['post_ids'])) {
            $post_ids = $json['post_ids'];
        }

        if (is_array($post_ids) && !empty($post_ids)) {
            return array_values(array_unique(array_filter(array_map('absint', $post_ids))));
        }

        $post_id = absint($request->get_param('post_id'));
        if ($post_id <= 0 && isset($json['post_id'])) {
            $post_id = absint($json['post_id']);
        }

        return $post_id > 0 ? [$post_id] : [];
    }

    /**
     * @param mixed $mapping Raw mapping.
     * @return array<string, int>
     */
    private static function sanitize_manual_mapping($mapping): array
    {
        if (!is_array($mapping)) {
            return [];
        }

        $clean = [];
        foreach ($mapping as $key => $attachment_id) {
            $key = sanitize_text_field((string) $key);
            if ($key === '') {
                continue;
            }
            $attachment_id = absint($attachment_id);
            if ($attachment_id > 0) {
                $clean[$key] = $attachment_id;
            }
        }

        return $clean;
    }
}
