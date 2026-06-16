<?php
/**
 * ACF runtime availability checks.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_ACF_Runtime
{
    public static function is_available(): bool
    {
        return function_exists('acf_get_field_groups')
            && function_exists('acf_get_fields')
            && function_exists('get_field')
            && function_exists('update_field');
    }
}
