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

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_notices', [self::class, 'maybe_show_acf_notice']);
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

        remove_submenu_page(self::PAGE_SLUG, self::PAGE_SLUG);
    }

    public static function enqueue_assets(string $hook): void
    {
        $allowed_hooks = [
            'toplevel_page_' . self::PAGE_SLUG,
        ];

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_media();

        $asset_file = AIAF_PLUGIN_DIR . 'build/index.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : [
            'dependencies' => ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
            'version'      => AIAF_VERSION,
        ];

        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies']) ? $asset['dependencies'] : [];
        $dependencies = array_values(array_diff($dependencies, ['wp-icons']));
        $dependencies = array_values(array_unique(array_merge($dependencies, ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready'])));

        wp_enqueue_style('wp-components');

        $asset_version = isset($asset['version']) ? (string) $asset['version'] : AIAF_VERSION;
        $style_file = AIAF_PLUGIN_DIR . 'build/index.css';
        $script_file = AIAF_PLUGIN_DIR . 'build/index.js';
        $style_version = file_exists($style_file) ? $asset_version . '-' . (string) filemtime($style_file) : $asset_version;
        $script_version = file_exists($script_file) ? $asset_version . '-' . (string) filemtime($script_file) : $asset_version;

        wp_enqueue_style(
            'aiaf-admin-app',
            AIAF_PLUGIN_URL . 'build/index.css',
            ['wp-components'],
            $style_version
        );

        wp_enqueue_script(
            'aiaf-admin-app',
            AIAF_PLUGIN_URL . 'build/index.js',
            $dependencies,
            $script_version,
            true
        );

        wp_set_script_translations('aiaf-admin-app', 'acf-image-auto-filler');

        $settings = [
            'restUrl'       => esc_url_raw(rest_url('acf-image-auto-filler/v1')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'adminUrl'      => esc_url_raw(admin_url()),
            'pluginVersion' => AIAF_VERSION,
            'acfActive'     => function_exists('acf_get_field_groups') && function_exists('acf_get_fields') && function_exists('get_field') && function_exists('update_field'),
            'canViewAuditLog' => current_user_can(self::audit_log_capability()),
        ];

        wp_add_inline_script(
            'aiaf-admin-app',
            'window.AIAFSettings = ' . wp_json_encode($settings) . ';',
            'before'
        );
    }

    public static function maybe_show_acf_notice(): void
    {
        if (!current_user_can(self::view_capability())) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen && isset($screen->id) ? (string) $screen->id : '';
        $allowed_screens = [
            'toplevel_page_' . self::PAGE_SLUG,
        ];

        if (!in_array($screen_id, $allowed_screens, true)) {
            return;
        }

        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields') || !function_exists('get_field') || !function_exists('update_field')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Advanced Custom Fields is unavailable. ACF field filling is disabled, but featured-image-only runs can still be used.', 'acf-image-auto-filler') . '</p></div>';
        }
    }


    private static function view_capability(): string
    {
        /**
         * Filters the capability required to open the ACF Image Auto Filler admin screen.
         *
         * The legacy aiaf_required_capability filter is still applied as the fallback
         * so existing implementations keep working. Keep this at manage_options unless
         * the project intentionally allows trusted editors to access the tool.
         *
         * @param string $capability Required capability.
         */
        $legacy_capability = apply_filters('aiaf_required_capability', 'manage_options');
        $legacy_capability = is_string($legacy_capability) && $legacy_capability !== '' ? $legacy_capability : 'manage_options';
        $capability = apply_filters('aiaf_view_capability', $legacy_capability);

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    private static function audit_log_capability(): string
    {
        /**
         * Filters the capability required to view the admin audit log.
         *
         * Defaults to manage_options because the log can reveal who changed content.
         *
         * @param string $capability Required capability.
         */
        $capability = apply_filters('aiaf_audit_log_capability', 'manage_options');

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    public static function render_page(): void
    {
        if (!current_user_can(self::view_capability())) {
            wp_die(esc_html__('You do not have permission to access this page.', 'acf-image-auto-filler'));
        }

        echo '<div class="wrap aiaf-react-wrap">';
        echo '<div id="acf-image-auto-filler-app"><div class="notice notice-info inline"><p>' . esc_html__('Admin interface wordt geladen. Als dit blijft staan, laad het JavaScript-bestand niet.', 'acf-image-auto-filler') . '</p></div></div>';
        echo '<noscript>' . esc_html__('JavaScript is required to use ACF Image Auto Filler.', 'acf-image-auto-filler') . '</noscript>';
        echo '</div>';
    }
}
