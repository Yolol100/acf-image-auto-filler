<?php
/**
 * Environment checks shared across admin and REST layers.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Environment
{
    public static function is_woocommerce_active(): bool
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
}
