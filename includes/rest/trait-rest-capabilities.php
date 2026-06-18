<?php
/**
 * REST controller methods split by responsibility.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait AIAF_REST_Capabilities_Trait
{
    public static function can_view_tool(): bool
    {
        return current_user_can(self::view_capability());
    }

    public static function can_mutate_tool(): bool
    {
        return current_user_can(self::mutate_capability());
    }

    private static function view_capability(): string
    {
        return AIAF_Capabilities::view();
    }

    private static function mutate_capability(): string
    {
        return AIAF_Capabilities::mutate();
    }

    private static function audit_log_capability(): string
    {
        return AIAF_Capabilities::audit_log();
    }

    public static function can_view_audit_log(): bool
    {
        return current_user_can(self::audit_log_capability());
    }

    public static function can_view_full_audit_log(): bool
    {
        return current_user_can(self::audit_log_capability());
    }

    public static function can_edit_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_view_tool()) {
            return false;
        }

        $content_id = AIAF_Content_ID::single_from_request($request);

        return $content_id !== ''
            && self::content_id_is_allowed($content_id)
            && AIAF_Content_ID::current_user_can_edit($content_id);
    }

    public static function can_edit_any_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_mutate_tool()) {
            return false;
        }

        return self::request_targets_are_editable($request);
    }

    public static function can_preview_any_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_view_tool()) {
            return false;
        }

        return self::request_targets_are_editable($request);
    }

    private static function request_targets_are_editable(WP_REST_Request $request): bool
    {
        $content_ids = AIAF_Content_ID::from_request($request);
        if (empty($content_ids)) {
            return false;
        }

        $max_items = (int) apply_filters('aiaf_max_posts_per_run', 50);
        if ($max_items > 0 && count($content_ids) > $max_items) {
            return false;
        }

        foreach ($content_ids as $content_id) {
            if (!self::content_id_is_allowed((string) $content_id) || !AIAF_Content_ID::current_user_can_edit((string) $content_id)) {
                return false;
            }
        }

        return true;
    }

    private static function content_id_is_allowed(string $content_id): bool
    {
        $content_id = AIAF_Content_ID::sanitize($content_id);
        if ($content_id === '') {
            return false;
        }

        if (strpos($content_id, 'term:') === 0) {
            $parts = explode(':', $content_id);
            $taxonomy = sanitize_key($parts[1] ?? '');

            return $taxonomy !== '' && self::is_allowed_content_type('taxonomy:' . $taxonomy);
        }

        $post_type = get_post_type(absint($content_id));

        return is_string($post_type) && $post_type !== '' && self::is_allowed_content_type($post_type);
    }
}
