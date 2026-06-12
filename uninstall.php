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

    $aiaf_option_prefix = $wpdb->esc_like('aiaf_last_rollback_') . '%';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pattern-based cleanup is required on uninstall for per-user rollback options.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $aiaf_option_prefix
        )
    );
}

if (is_multisite()) {
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
