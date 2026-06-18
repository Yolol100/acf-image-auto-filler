<?php
/**
 * REST controller methods split by responsibility.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait AIAF_REST_Mutations_Trait
{
    public static function get_fields(WP_REST_Request $request): WP_REST_Response
    {
        $content_id = AIAF_Content_ID::single_from_request($request);
        $include_groups = rest_sanitize_boolean($request->get_param('include_groups'));

        if ($content_id === '' || !AIAF_Content_ID::current_user_can_edit($content_id)) {
            return new WP_REST_Response(['message' => __('Invalid or inaccessible content item.', 'acf-image-auto-filler')], 400);
        }

        if (!AIAF_ACF_Runtime::is_available()) {
            return new WP_REST_Response([
                'acfActive' => false,
                'fields'    => [],
                'message'   => __('Advanced Custom Fields is not active or required ACF functions are unavailable.', 'acf-image-auto-filler'),
            ], 200);
        }

        $scanner = new AIAF_Field_Scanner();
        $fields = $scanner->get_image_fields(AIAF_Content_ID::to_acf_target($content_id), $include_groups);

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

    public static function rollback_run(WP_REST_Request $request): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response($manager->rollback_run((string) $request->get_param('run_id')));
    }

    public static function rollback_status(): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        $run = $manager->get_last_run();

        return rest_ensure_response([
            'hasRollback'   => $run !== null,
            'rollbackRunId' => isset($run['run_id']) ? (string) $run['run_id'] : '',
        ]);
    }

    public static function get_audit_log(): WP_REST_Response
    {
        if (!self::can_view_audit_log()) {
            return new WP_REST_Response([
                'message' => __('You do not have permission to view the audit log.', 'acf-image-auto-filler'),
            ], 403);
        }

        $manager = new AIAF_Rollback_Manager();
        $items = $manager->get_audit_log();

        if (!self::can_view_full_audit_log()) {
            $current_user_id = get_current_user_id();
            $items = array_values(array_filter($items, static fn (array $item): bool => isset($item['user_id']) && absint($item['user_id']) === $current_user_id));
        }

        return rest_ensure_response(['items' => $items]);
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
            'content_ids' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'string',
                ],
                'sanitize_callback' => [self::class, 'sanitize_content_id_array'],
                'validate_callback' => [self::class, 'validate_content_id_array'],
            ],
            'post_ids' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'integer',
                ],
                'sanitize_callback' => [self::class, 'sanitize_positive_integer_array'],
                'validate_callback' => [self::class, 'validate_positive_integer_array'],
            ],
            'attachment_ids' => [
                'required'          => true,
                'type'              => 'array',
                'items'             => [
                    'type' => 'integer',
                ],
                'sanitize_callback' => [self::class, 'sanitize_positive_integer_array'],
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
                'type'              => 'object',
                'sanitize_callback' => [AIAF_Manual_Mapping::class, 'sanitize'],
                'validate_callback' => [AIAF_Manual_Mapping::class, 'validate'],
            ],
            'overwrite_existing' => [
                'required'          => false,
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'include_groups' => [
                'required'          => false,
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'use_featured_image' => [
                'required'          => false,
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
        ];
    }


    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, int>
     */
    public static function sanitize_positive_integer_array($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    public static function validate_positive_integer_array($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_int($item) && !ctype_digit((string) $item)) {
                return false;
            }

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
        $service = new AIAF_Mutation_Service();
        return $service->process($request, $execute);
    }



    /**
     * @param mixed $value Raw content id.
     */
    public static function sanitize_content_id($value): string
    {
        return AIAF_Content_ID::sanitize($value);
    }

    public static function is_valid_content_id(string $value): bool
    {
        return AIAF_Content_ID::is_valid($value);
    }

    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, string>
     */
    public static function sanitize_content_id_array($value): array
    {
        return AIAF_Content_ID::sanitize_array($value);
    }

    /**
     * @param mixed $value Raw REST array value.
     */
    public static function validate_content_id_array($value): bool
    {
        return AIAF_Content_ID::validate_array($value);
    }
}
