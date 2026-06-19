<?php
/**
 * React admin page shell.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Admin_Page
{
    private const PAGE_SLUG = 'acf-image-auto-filler';
    private const AUDIT_PAGE_SLUG = 'acf-image-auto-filler-audit-log';

    private static string $audit_hook = '';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('ACF Image Auto Filler', 'acf-image-auto-filler'),
            __('ACF Image Filler', 'acf-image-auto-filler'),
            self::view_capability(),
            self::PAGE_SLUG,
            [self::class, 'render_page'],
            'dashicons-format-image',
            58
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('ACF Image Auto Filler', 'acf-image-auto-filler'),
            __('ACF Image Filler', 'acf-image-auto-filler'),
            self::view_capability(),
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );

        self::$audit_hook = (string) add_submenu_page(
            self::PAGE_SLUG,
            __('Audit log', 'acf-image-auto-filler'),
            __('Audit log', 'acf-image-auto-filler'),
            self::audit_log_capability(),
            self::AUDIT_PAGE_SLUG,
            [self::class, 'render_audit_page']
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        $is_main_page = $hook === 'toplevel_page_' . self::PAGE_SLUG;
        $is_audit_page = $hook === self::$audit_hook;

        if (!$is_main_page && !$is_audit_page) {
            return;
        }

        $asset_file = AIAF_PLUGIN_DIR . 'build/index.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : [];
        if (!is_array($asset)) {
            $asset = [];
        }

        $asset_version = isset($asset['version']) ? (string) $asset['version'] : AIAF_VERSION;
        $style_file = AIAF_PLUGIN_DIR . 'build/index.css';
        $style_version = file_exists($style_file) ? $asset_version . '-' . (string) filemtime($style_file) : $asset_version;
        $style_dependencies = $is_main_page ? ['wp-components'] : [];

        if ($is_main_page) {
            wp_enqueue_style('wp-components');
        }

        wp_enqueue_style(
            'aiaf-admin-app',
            AIAF_PLUGIN_URL . 'build/index.css',
            $style_dependencies,
            $style_version
        );

        if (!$is_main_page) {
            return;
        }

        wp_enqueue_media();

        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies']) ? $asset['dependencies'] : ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'];
        $dependencies = array_values(array_diff($dependencies, ['wp-icons']));
        $dependencies = array_values(array_unique(array_merge($dependencies, ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready'])));

        $script_file = AIAF_PLUGIN_DIR . 'build/index.js';
        $script_version = file_exists($script_file) ? $asset_version . '-' . (string) filemtime($script_file) : $asset_version;

        wp_enqueue_script(
            'aiaf-admin-app',
            AIAF_PLUGIN_URL . 'build/index.js',
            $dependencies,
            $script_version,
            true
        );

        wp_set_script_translations('aiaf-admin-app', 'acf-image-auto-filler', AIAF_PLUGIN_DIR . 'languages');

        $settings = [
            'restUrl'       => esc_url_raw(rest_url('acf-image-auto-filler/v1')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'adminUrl'      => esc_url_raw(admin_url()),
            'pluginVersion' => AIAF_VERSION,
            'acfActive'       => AIAF_ACF_Runtime::is_available(),
            'woocommerceActive' => self::is_woocommerce_active(),
            'canViewAuditLog' => current_user_can(self::audit_log_capability()),
            'canViewFullAuditLog' => current_user_can(self::audit_log_capability()),
            'canMutateTool' => current_user_can(self::mutate_capability()),
        ];

        wp_add_inline_script(
            'aiaf-admin-app',
            'window.AIAFSettings = ' . wp_json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
    }


    private static function is_woocommerce_active(): bool
    {
        return AIAF_Environment::is_woocommerce_active();
    }

    private static function view_capability(): string
    {
        return AIAF_Capabilities::view();
    }

    private static function audit_log_capability(): string
    {
        return AIAF_Capabilities::audit_log();
    }

    private static function mutate_capability(): string
    {
        return AIAF_Capabilities::mutate();
    }

    private static function get_audit_user_label(int $user_id): string
    {
        if ($user_id <= 0) {
            return '0';
        }

        $user = get_userdata($user_id);
        if ($user instanceof WP_User && $user->display_name !== '') {
            return sprintf('%s (#%d)', $user->display_name, $user_id);
        }

        return (string) $user_id;
    }


    public static function render_audit_page(): void
    {
        if (!current_user_can(self::audit_log_capability())) {
            wp_die(esc_html__('You do not have permission to view the audit log.', 'acf-image-auto-filler'));
        }

        $manager = new AIAF_Rollback_Manager();
        $items = $manager->get_audit_log();
        $can_view_full_audit_log = current_user_can(self::audit_log_capability());
        $current_user_id = get_current_user_id();
        $has_audit_filter = isset($_GET['aiaf_user']) || isset($_GET['aiaf_date']) || isset($_GET['paged']);
        $audit_filter_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';
        $can_use_audit_filter = !$has_audit_filter || wp_verify_nonce($audit_filter_nonce, 'aiaf_audit_filters');
        $user_filter = $can_use_audit_filter && $can_view_full_audit_log && isset($_GET['aiaf_user']) ? absint(wp_unslash((string) $_GET['aiaf_user'])) : 0;
        $date_filter = $can_use_audit_filter && isset($_GET['aiaf_date']) ? sanitize_text_field(wp_unslash((string) $_GET['aiaf_date'])) : '';
        if ($date_filter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
            $date_filter = '';
        }
        $notice = self::handle_audit_rollback_action();

        if (!$can_view_full_audit_log) {
            $items = array_values(array_filter($items, static fn (array $item): bool => isset($item['user_id']) && absint($item['user_id']) === $current_user_id));
        } elseif ($user_filter > 0) {
            $items = array_values(array_filter($items, static fn (array $item): bool => isset($item['user_id']) && absint($item['user_id']) === $user_filter));
        }

        if ($date_filter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
            $items = array_values(array_filter($items, static function (array $item) use ($date_filter): bool {
                $created = isset($item['created_at']) ? absint($item['created_at']) : 0;
                return $created > 0 && wp_date('Y-m-d', $created) === $date_filter;
            }));
        }

        $per_page = 20;
        $paged = $can_use_audit_filter && isset($_GET['paged']) ? max(1, absint(wp_unslash((string) $_GET['paged']))) : 1;
        $total = count($items);
        $pages = max(1, (int) ceil($total / $per_page));
        $items = array_slice($items, ($paged - 1) * $per_page, $per_page);

        echo '<div class="wrap aiaf-audit-page">';
        echo '<div class="aiaf-audit-shell">';
        echo '<div class="aiaf-audit-header">';
        echo '<div><h1>' . esc_html__('Audit log', 'acf-image-auto-filler') . '</h1><p>' . esc_html__('Review completed runs and undo available changes from one place.', 'acf-image-auto-filler') . '</p></div>';
        echo '<a class="aiaf-audit-back-button" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Back to ACF Image Filler', 'acf-image-auto-filler') . '</a>';
        echo '</div>';
        echo '<div class="aiaf-audit-body">';
        if ($notice !== null) {
            $notice_type = isset($notice['type']) && $notice['type'] === 'success' ? 'success' : 'error';
            echo '<div class="aiaf-audit-notice aiaf-audit-notice--' . esc_attr($notice_type) . '" role="status">' . esc_html((string) ($notice['message'] ?? '')) . '</div>';
        }
        if (!$can_view_full_audit_log) {
            echo '<p class="aiaf-audit-scope-note">' . esc_html__('Showing actions created by your account. Administrators can view and filter all users.', 'acf-image-auto-filler') . '</p>';
        }
        echo '<form method="get" class="aiaf-audit-filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::AUDIT_PAGE_SLUG) . '" />';
        wp_nonce_field('aiaf_audit_filters');
        if ($can_view_full_audit_log) {
            echo '<label><span>' . esc_html__('User ID', 'acf-image-auto-filler') . '</span><input type="number" min="1" name="aiaf_user" value="' . esc_attr($user_filter > 0 ? (string) $user_filter : '') . '" /></label>';
        }
        echo '<label><span>' . esc_html__('Date', 'acf-image-auto-filler') . '</span><input type="date" name="aiaf_date" value="' . esc_attr($date_filter) . '" /></label>';
        submit_button(__('Filter', 'acf-image-auto-filler'), 'secondary', '', false);
        echo '</form>';

        echo '<div class="aiaf-audit-table-wrap">';
        echo '<table class="widefat striped aiaf-audit-table"><thead><tr>';
        echo '<th>' . esc_html__('Action', 'acf-image-auto-filler') . '</th>';
        echo '<th>' . esc_html__('User', 'acf-image-auto-filler') . '</th>';
        echo '<th>' . esc_html__('Changes', 'acf-image-auto-filler') . '</th>';
        echo '<th>' . esc_html__('Date', 'acf-image-auto-filler') . '</th>';
        echo '<th>' . esc_html__('Status', 'acf-image-auto-filler') . '</th>';
        echo '</tr></thead><tbody>';

        if (!$items) {
            echo '<tr><td colspan="5">' . esc_html__('No action history available yet.', 'acf-image-auto-filler') . '</td></tr>';
        }

        foreach ($items as $item) {
            $summary = isset($item['summary']) && is_array($item['summary']) ? $item['summary'] : [];
            $titles = isset($summary['titles']) && is_array($summary['titles']) ? $summary['titles'] : [];
            $first = isset($titles[0]) && is_array($titles[0]) ? $titles[0] : [];
            $title = isset($first['title']) ? sanitize_text_field((string) $first['title']) : __('Previous action', 'acf-image-auto-filler');
            $edit_url = isset($first['edit_url']) ? esc_url((string) $first['edit_url']) : '';
            $count = isset($item['item_count']) ? absint($item['item_count']) : 0;
            $featured = isset($summary['featured_count']) ? absint($summary['featured_count']) : 0;
            $acf = isset($summary['acf_count']) ? absint($summary['acf_count']) : 0;
            $created = isset($item['created_at']) ? absint($item['created_at']) : 0;
            $run_id = isset($item['run_id']) ? sanitize_text_field((string) $item['run_id']) : '';
            $rollback_status = isset($item['rollback_status']) ? sanitize_key((string) $item['rollback_status']) : '';
            $can_rollback = $rollback_status === 'available' && $run_id !== '' && current_user_can(self::mutate_capability());

            $change_parts = [];
            /* translators: %d: Number of changed image values. */
            $change_parts[] = $count === 1 ? __('1 change', 'acf-image-auto-filler') : sprintf(__('%d changes', 'acf-image-auto-filler'), $count);
            if ($featured > 0) {
                /* translators: %d: Number of featured images changed. */
                $change_parts[] = $featured === 1 ? __('Featured image', 'acf-image-auto-filler') : sprintf(__('%d featured images', 'acf-image-auto-filler'), $featured);
            }
            if ($acf > 0) {
                /* translators: %d: Number of ACF image fields changed. */
                $change_parts[] = $acf === 1 ? __('1 ACF field', 'acf-image-auto-filler') : sprintf(__('%d ACF fields', 'acf-image-auto-filler'), $acf);
            }

            echo '<tr>';
            echo '<td>' . ($edit_url !== '' ? '<a href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>' : esc_html($title)) . '</td>';
            $user_id = isset($item['user_id']) ? absint($item['user_id']) : 0;
            echo '<td>' . esc_html(self::get_audit_user_label($user_id)) . '</td>';
            echo '<td>' . esc_html(implode(' - ', $change_parts)) . '</td>';
            echo '<td>' . esc_html($created > 0 ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $created) : '') . '</td>';
            if ($can_rollback) {
                $confirm = wp_json_encode(__('Undo this run? The previous image values will be restored where possible.', 'acf-image-auto-filler'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                echo '<td><form method="post" class="aiaf-audit-rollback-form" onsubmit="return window.confirm(' . esc_attr(is_string($confirm) ? $confirm : '""') . ');">';
                wp_nonce_field('aiaf_audit_rollback_' . $run_id, 'aiaf_audit_nonce');
                echo '<input type="hidden" name="aiaf_rollback_run_id" value="' . esc_attr($run_id) . '" />';
                submit_button(__('Undo this run', 'acf-image-auto-filler'), 'secondary aiaf-audit-rollback-button', 'submit', false);
                echo '</form></td>';
            } elseif ($rollback_status === 'available_for_owner') {
                echo '<td><span class="aiaf-audit-status aiaf-audit-status--muted">' . esc_html__('Only original user can undo', 'acf-image-auto-filler') . '</span></td>';
            } elseif ($rollback_status === 'rollback_data_missing') {
                echo '<td><span class="aiaf-audit-status aiaf-audit-status--muted">' . esc_html__('Rollback data unavailable', 'acf-image-auto-filler') . '</span></td>';
            } else {
                echo '<td><span class="aiaf-audit-status aiaf-audit-status--done">' . esc_html__('Already undone', 'acf-image-auto-filler') . '</span></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        if ($pages > 1) {
            /* translators: 1: Current audit log page number, 2: Total number of audit log pages. */
            $page_label = sprintf(__('Page %1$d of %2$d', 'acf-image-auto-filler'), $paged, $pages);
            echo '<p class="tablenav-pages">' . esc_html($page_label) . '</p>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * @return array{type: string, message: string}|null
     */
    private static function handle_audit_rollback_action(): ?array
    {
        if (!isset($_POST['aiaf_rollback_run_id'])) {
            return null;
        }

        $run_id = sanitize_text_field(wp_unslash((string) $_POST['aiaf_rollback_run_id']));
        if ($run_id === '') {
            return [
                'type'    => 'error',
                'message' => __('No rollback run was selected.', 'acf-image-auto-filler'),
            ];
        }

        $nonce = isset($_POST['aiaf_audit_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['aiaf_audit_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aiaf_audit_rollback_' . $run_id)) {
            return [
                'type'    => 'error',
                'message' => __('The undo request could not be verified. Refresh the page and try again.', 'acf-image-auto-filler'),
            ];
        }

        if (!current_user_can(self::audit_log_capability()) || !current_user_can(self::mutate_capability())) {
            return [
                'type'    => 'error',
                'message' => __('You do not have permission to undo this run.', 'acf-image-auto-filler'),
            ];
        }

        $manager = new AIAF_Rollback_Manager();
        $result = $manager->rollback_run($run_id);
        $errors = isset($result['errors']) && is_array($result['errors']) ? array_filter(array_map('strval', $result['errors'])) : [];
        if ($errors !== []) {
            return [
                'type'    => 'error',
                'message' => implode(' ', $errors),
            ];
        }

        return [
            'type'    => 'success',
            'message' => __('Run undone. Previous image values were restored where possible.', 'acf-image-auto-filler'),
        ];
    }


    public static function render_page(): void
    {
        if (!current_user_can(self::view_capability())) {
            wp_die(esc_html__('You do not have permission to open this page.', 'acf-image-auto-filler'));
        }

        echo '<div class="wrap aiaf-react-wrap">';
        echo '<h1 class="screen-reader-text">' . esc_html__('ACF Image Auto Filler', 'acf-image-auto-filler') . '</h1>';
        echo '<div id="acf-image-auto-filler-app"></div>';
        echo '</div>';
    }
}
