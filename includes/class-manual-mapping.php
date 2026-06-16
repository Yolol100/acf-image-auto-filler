<?php
/**
 * Manual field-to-attachment mapping sanitization.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Manual_Mapping
{
    /**
     * @param mixed $mapping Raw mapping.
     * @return array<string, int>
     */
    public static function sanitize($mapping): array
    {
        if (!is_array($mapping)) {
            return [];
        }

        $clean = [];
        foreach ($mapping as $key => $attachment_id) {
            $key = self::sanitize_key($key);
            if ($key === '') {
                continue;
            }

            $attachment_id = absint($attachment_id);
            if ($attachment_id > 0) {
                $clean[$key] = $attachment_id;
            }
        }

        return $clean;
    }

    /**
     * @param mixed $mapping Raw mapping.
     */
    public static function validate($mapping): bool
    {
        if (is_null($mapping)) {
            return true;
        }

        if (!is_array($mapping)) {
            return false;
        }

        foreach ($mapping as $key => $attachment_id) {
            if (self::sanitize_key($key) === '' || absint($attachment_id) <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $key Raw mapping key.
     */
    private static function sanitize_key($key): string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return '';
        }

        if (strpos($key, 'term:') === 0) {
            $parts = explode(':', $key, 4);
            $taxonomy = sanitize_key($parts[1] ?? '');
            $term_id = absint($parts[2] ?? 0);
            $field_key = sanitize_key($parts[3] ?? '');

            return $taxonomy !== '' && $term_id > 0 && $field_key !== '' ? 'term:' . $taxonomy . ':' . $term_id . ':' . $field_key : '';
        }

        $parts = explode(':', $key, 2);
        if (count($parts) === 2) {
            $post_id = absint($parts[0]);
            $field_key = sanitize_key($parts[1]);

            return $post_id > 0 && $field_key !== '' ? $post_id . ':' . $field_key : '';
        }

        return sanitize_key($key);
    }
}
