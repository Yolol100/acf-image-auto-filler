<?php
/**
 * Centralized capability policy for the plugin.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Capabilities
{
    /**
     * @param mixed $capability Capability returned from plugin filters.
     */
    public static function normalize($capability): string
    {
        if (!is_string($capability)) {
            return 'manage_options';
        }

        $capability = trim($capability);

        return $capability !== '' ? $capability : 'manage_options';
    }

    public static function legacy(): string
    {
        /**
         * Legacy filter for the capability required to use ACF Image Auto Filler.
         *
         * Kept for backward compatibility. Prefer aiaf_view_capability,
         * aiaf_mutate_capability and aiaf_audit_log_capability for new projects.
         *
         * @param string $capability Required capability.
         */
        return self::normalize(apply_filters('aiaf_required_capability', 'manage_options'));
    }

    public static function view(): string
    {
        /**
         * Filters the capability required to browse the admin screen and REST data.
         *
         * @param string $capability Required capability.
         */
        return self::normalize(apply_filters('aiaf_view_capability', self::legacy()));
    }

    public static function mutate(): string
    {
        /**
         * Filters the capability required to execute fills and rollbacks.
         *
         * Defaults to manage_options so read access can be granted separately from
         * bulk mutation rights. Per-item edit_post and edit_term checks still run.
         *
         * @param string $capability Required capability.
         */
        return self::normalize(apply_filters('aiaf_mutate_capability', 'manage_options'));
    }

    public static function audit_log(): string
    {
        /**
         * Filters the capability required to view the audit log.
         *
         * @param string $capability Required capability.
         */
        return self::normalize(apply_filters('aiaf_audit_log_capability', 'manage_options'));
    }
}
