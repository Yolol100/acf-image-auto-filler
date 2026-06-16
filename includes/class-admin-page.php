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

        wp_set_script_translations('aiaf-admin-app', 'acf-image-auto-filler', AIAF_PLUGIN_DIR . 'languages');

        $settings = [
            'restUrl'       => esc_url_raw(rest_url('acf-image-auto-filler/v1')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'adminUrl'      => esc_url_raw(admin_url()),
            'pluginVersion' => AIAF_VERSION,
            'acfActive'       => AIAF_ACF_Runtime::is_available(),
            'woocommerceActive' => self::is_woocommerce_active(),
            'canViewAuditLog' => current_user_can(self::audit_log_capability()),
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
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active = function_exists('is_plugin_active') && is_plugin_active('woocommerce/woocommerce.php');
        $network_active = is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network('woocommerce/woocommerce.php');

        return ($active || $network_active)
            && class_exists('WooCommerce')
            && function_exists('WC')
            && post_type_exists('product');
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

    public static function render_page(): void
    {
        if (!current_user_can(self::view_capability())) {
            wp_die(esc_html__('Je hebt geen toestemming om deze pagina te openen.', 'acf-image-auto-filler'));
        }

        echo '<div class="wrap aiaf-react-wrap">';
        echo '<h1 class="screen-reader-text">' . esc_html__('ACF Image Auto Filler', 'acf-image-auto-filler') . '</h1>';
        echo '<div id="acf-image-auto-filler-app"></div>';
        echo '</div>';
    }
}
