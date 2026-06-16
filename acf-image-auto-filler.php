<?php
/**
 * Plugin Name: ACF Image Auto Filler
 * Description: Select Media Library images and safely fill supported ACF image fields or featured images from a WordPress admin screen with preview and rollback.
 * Version: 1.5.48
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Webactueel
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acf-image-auto-filler
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('AIAF_VERSION', '1.5.48');
define('AIAF_PLUGIN_FILE', __FILE__);
define('AIAF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIAF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once AIAF_PLUGIN_DIR . 'includes/class-field-scanner.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-rollback-manager.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-acf-writer.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-acf-runtime.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-capabilities.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-content-id.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-manual-mapping.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-mutation-service.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once AIAF_PLUGIN_DIR . 'includes/class-admin-page.php';
add_action('plugins_loaded', static function (): void {
    if (is_admin()) {
        AIAF_Admin_Page::init();
    }

    AIAF_REST_Controller::init();
});
