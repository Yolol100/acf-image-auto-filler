<?php
/**
 * Uninstall cleanup for ACF Image Auto Filler.
 *
 * Removes only plugin-owned rollback and audit metadata. It does not delete
 * attachments, posts, ACF field groups, or ACF field values.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete plugin-owned options for the current site.
 */
function aiaf_delete_plugin_options(): void
{
    delete_option('aiaf_audit_log');

    global $wpdb;

    $aiaf_option_prefixes = [
        $wpdb->esc_like('aiaf_last_rollback_') . '%',
        $wpdb->esc_like('aiaf_rollback_run_') . '%',
    ];

    foreach ($aiaf_option_prefixes as $aiaf_option_prefix) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pattern-based cleanup is required on uninstall for per-user rollback options.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $aiaf_option_prefix
            )
        );
    }
}

/**
 * Decide whether uninstall should clean plugin-owned metadata on every site.
 *
 * Default behaviour is conservative: network-wide cleanup only runs from the
 * network admin. Site-level uninstall cleanup stays scoped to the current site.
 *
 * @return bool
 */
function aiaf_should_cleanup_network_wide(): bool
{
    $network_wide = is_multisite() && function_exists('is_network_admin') && is_network_admin();

    /**
     * Filters whether uninstall should remove plugin-owned metadata from every site.
     *
     * Keep false for site-scoped cleanup. Set true only when uninstalling this
     * plugin intentionally for the full multisite network.
     *
     * @param bool $network_wide Whether to cleanup all sites on uninstall.
     */
    return (bool) apply_filters('aiaf_uninstall_network_wide_cleanup', $network_wide);
}

if (is_multisite() && aiaf_should_cleanup_network_wide()) {
    $aiaf_site_ids = get_sites([
        'fields' => 'ids',
        'number' => 0,
    ]);

    foreach ($aiaf_site_ids as $aiaf_site_id) {
        switch_to_blog((int) $aiaf_site_id);
        aiaf_delete_plugin_options();
        restore_current_blog();
    }
} else {
    aiaf_delete_plugin_options();
}
