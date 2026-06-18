<?php
/**
 * REST API route registration for the React admin app.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/rest/trait-rest-capabilities.php';
require_once __DIR__ . '/rest/trait-rest-content-types.php';
require_once __DIR__ . '/rest/trait-rest-posts.php';
require_once __DIR__ . '/rest/trait-rest-mutations.php';

final class AIAF_REST_Controller
{
    use AIAF_REST_Capabilities_Trait;
    use AIAF_REST_Content_Types_Trait;
    use AIAF_REST_Posts_Trait;
    use AIAF_REST_Mutations_Trait;

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
                    'type'              => 'string',
                    'sanitize_callback' => [self::class, 'sanitize_content_type'],
                    'validate_callback' => static function ($value): bool {
                        return is_string($value) && self::is_allowed_content_type((string) $value);
                    },
                ],
                'search' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0,
                ],
                'per_page' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0 && absint($value) <= 100,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fields', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_fields'],
            'permission_callback' => [self::class, 'can_edit_requested_post'],
            'args'                => [
                'content_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => [self::class, 'sanitize_content_id'],
                    'validate_callback' => static fn ($value): bool => self::is_valid_content_id((string) $value),
                ],
                'post_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0,
                ],
                'include_groups' => [
                    'required'          => false,
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/preview', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'preview'],
            'permission_callback' => [self::class, 'can_preview_any_requested_post'],
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

        register_rest_route(self::NAMESPACE, '/rollback-run', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'rollback_run'],
            'permission_callback' => [self::class, 'can_mutate_tool'],
            'args'                => [
                'run_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => static fn ($value): bool => is_string($value) && preg_match('/^[a-zA-Z0-9_-]{8,80}$/', $value) === 1,
                ],
            ],
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
}
