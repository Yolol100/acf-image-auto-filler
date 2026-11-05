<?php
/**
 * Isolated conflict regression for rollback of ACF/featured images.
 * Uses synthetic attachment IDs only; no WordPress installation or user data.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
$GLOBALS['aiaf_test_featured'] = [];
$GLOBALS['aiaf_test_fields'] = [];

function __($message, $domain = '') { return $message; }
function absint($value): int { return abs((int) $value); }
function sanitize_key($key): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $key)) ?? ''; }
function wp_strip_all_tags($value): string { return strip_tags((string) $value); }
function get_current_user_id(): int { return 7; }
function current_user_can($capability, ...$args): bool { return true; }
function apply_filters($hook, $value, ...$args) { return $value; }
function get_post_type($id): string { return 'attachment'; }
function wp_attachment_is_image($id): bool { return (int) $id > 0; }
function get_post_thumbnail_id($id): int { return $GLOBALS['aiaf_test_featured'][$id] ?? 0; }
function has_post_thumbnail($id): bool { return get_post_thumbnail_id($id) > 0; }
function set_post_thumbnail($id, $attachment): bool { $GLOBALS['aiaf_test_featured'][$id] = (int) $attachment; return true; }
function delete_post_thumbnail($id): bool { unset($GLOBALS['aiaf_test_featured'][$id]); return true; }
function get_field($key, $id, $format = false) { return $GLOBALS['aiaf_test_fields'][$id . ':' . $key] ?? null; }
function update_field($key, $value, $id): bool { $GLOBALS['aiaf_test_fields'][$id . ':' . $key] = $value; return true; }
function get_option($key, $default = false) { return $default; }
function delete_option($key): bool { return true; }

require_once dirname(__DIR__) . '/includes/class-rollback-manager.php';

$manager = new AIAF_Rollback_Manager();
$method = new ReflectionMethod($manager, 'rollback_payload');
$failed = [];
$run = static function (array $item) use ($manager, $method): array {
    return $method->invoke($manager, ['run_id' => '', 'items' => [$item]]);
};
$check = static function (bool $condition, string $label) use (&$failed): void {
    if (!$condition) { $failed[] = $label; }
};
$item = static function (string $type, int $previous = 101, int $new = 202): array {
    return [
        'type' => $type,
        'post_id' => 42,
        'previous_attachment_id' => $previous,
        'new_attachment_id' => $new,
        'field_key' => 'field_image',
        'field_name' => 'image',
        'scope' => 'top_level',
        'parent_key' => '',
    ];
};

// An editor replaces the plugin-applied featured image before rollback.
$GLOBALS['aiaf_test_featured'][42] = 303;
$r = $run($item('featured_image'));
$check(get_post_thumbnail_id(42) === 303 && empty($r['rolledBack']) && !empty($r['hasRollback']), 'Featured image: later editor change must survive');

// When unchanged since apply, the original image should be restored.
$GLOBALS['aiaf_test_featured'][42] = 202;
$r = $run($item('featured_image'));
$check(get_post_thumbnail_id(42) === 101 && count($r['rolledBack']) === 1, 'Featured image: unchanged applied value must restore');

// Top-level ACF fields must follow the same optimistic check.
$GLOBALS['aiaf_test_fields']['42:field_image'] = 303;
$r = $run($item('acf_image'));
$check(get_field('field_image', 42) === 303 && empty($r['rolledBack']) && !empty($r['hasRollback']), 'ACF field: later editor change must survive');
$GLOBALS['aiaf_test_fields']['42:field_image'] = 202;
$r = $run($item('acf_image'));
$check(get_field('field_image', 42) === 101 && count($r['rolledBack']) === 1, 'ACF field: unchanged applied value must restore');

// Nested group child rollback must never reset newer data or unrelated siblings.
$group = $item('acf_image');
$group['scope'] = 'group';
$group['parent_key'] = 'field_group';
$GLOBALS['aiaf_test_fields']['42:field_group'] = ['image' => 303, 'other' => 404];
$r = $run($group);
$check(get_field('field_group', 42)['image'] === 303 && empty($r['rolledBack']) && !empty($r['hasRollback']), 'ACF group: later editor change must survive');
$GLOBALS['aiaf_test_fields']['42:field_group'] = ['image' => 202, 'other' => 404];
$r = $run($group);
$check(get_field('field_group', 42)['image'] === 101 && get_field('field_group', 42)['other'] === 404 && count($r['rolledBack']) === 1, 'ACF group: restore image without touching siblings');

// Legacy/incomplete rollback records without a known applied value fail closed.
$GLOBALS['aiaf_test_featured'][42] = 303;
$legacy = $item('featured_image');
unset($legacy['new_attachment_id']);
$r = $run($legacy);
$check(get_post_thumbnail_id(42) === 303 && empty($r['rolledBack']), 'Legacy rollback without applied ID must not overwrite editor change');

if ($failed) {
    fwrite(STDERR, "FAIL: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo "ACF Image Auto Filler rollback conflicts: PASS (7 scenarios)" . PHP_EOL;
