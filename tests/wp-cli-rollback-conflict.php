<?php
/**
 * Real WordPress and ACF regression for existing image rollback.
 * Only synthetic pages, image fixtures and field values in a disposable CI site.
 */
if (!defined('ABSPATH') || !function_exists('acf_add_local_field_group')) {
    throw new RuntimeException('WordPress/ACF unavailable.');
}
wp_set_current_user(1);
if (!current_user_can('manage_options')) {
    throw new RuntimeException('Synthetic admin is unavailable.');
}
acf_add_local_field_group([
    'key' => 'group_aiaf_ci_rollback',
    'title' => 'CI rollback',
    'fields' => [
        [
            'key' => 'field_aiaf_ci_image',
            'label' => 'CI image',
            'name' => 'aiaf_ci_image',
            'type' => 'image',
            'return_format' => 'id',
        ],
    ],
    'location' => [[['param'=>'post_type', 'operator'=>'==', 'value'=>'page']]],
]);
$page = wp_insert_post([
    'post_type' => 'page',
    'post_title' => 'AIAF CI synthetic rollback',
    'post_status' => 'draft',
], true);
if (is_wp_error($page) || !$page) {
    throw new RuntimeException('Synthetic page could not be created.');
}
$images = [];
try {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('Synthetic uploads directory unavailable.');
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    for ($i=1; $i<=3; $i++) {
        $file = trailingslashit($uploads['path']) . 'aiaf-ci-' . $page . '-' . $i . '.png';
        $bitmap = imagecreatetruecolor(2, 2);
        if (!$bitmap || !imagepng($bitmap, $file)) {
            throw new RuntimeException('Cannot produce synthetic PNG.');
        }
        imagedestroy($bitmap);
        $id = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => 'CI image ' . $i,
            'post_status' => 'inherit',
            'guid' => trailingslashit($uploads['url']) . basename($file),
        ], $file, $page, true);
        if (is_wp_error($id) || !$id) {
            throw new RuntimeException('Synthetic attachment creation failed.');
        }
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
        $images[] = (int) $id;
    }
    [$previous, $applied, $newer] = $images;

    $manager = new AIAF_Rollback_Manager();
    $method = new ReflectionMethod($manager, 'rollback_payload');
    $item = static function ($type) use ($page, $previous, $applied): array {
        return [
            'type' => $type,
            'post_id' => $page,
            'field_key' => $type === 'featured_image' ? '_thumbnail_id' : 'field_aiaf_ci_image',
            'field_name' => $type === 'featured_image' ? '_thumbnail_id' : 'aiaf_ci_image',
            'scope' => 'top_level',
            'parent_key' => '',
            'previous_attachment_id' => $previous,
            'new_attachment_id' => $applied,
        ];
    };
    $run = static function ($type) use ($method, $manager, $item): array {
        return $method->invoke($manager, ['run_id'=>'', 'items'=>[$item($type)]]);
    };
    $assert = static function ($condition, $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    update_field('field_aiaf_ci_image', $newer, $page);
    $conflict = $run('acf_image');
    $assert((int) get_field('field_aiaf_ci_image', $page, false) === $newer
        && empty($conflict['rolledBack']) && !empty($conflict['hasRollback']),
        'ACF manual edit was overwritten.');
    update_field('field_aiaf_ci_image', $applied, $page);
    $restored = $run('acf_image');
    $assert((int) get_field('field_aiaf_ci_image', $page, false) === $previous
        && count($restored['rolledBack']) === 1, 'ACF prior image was not restored.');

    if (!set_post_thumbnail($page, $newer)) {
        throw new RuntimeException('Synthetic featured image could not be set.');
    }
    $conflict = $run('featured_image');
    $assert((int) get_post_thumbnail_id($page) === $newer
        && empty($conflict['rolledBack']) && !empty($conflict['hasRollback']),
        'Featured image manual edit was overwritten.');
    $assert(set_post_thumbnail($page, $applied), 'Synthetic applied image missing.');
    $restored = $run('featured_image');
    $assert((int) get_post_thumbnail_id($page) === $previous
        && count($restored['rolledBack']) === 1, 'Featured image was not restored.');
    echo "WordPress ACF and featured image rollback: PASS (4 real-runtime scenarios)\n";
} finally {
    foreach ($images as $id) {
        wp_delete_attachment($id, true);
    }
    wp_delete_post($page, true);
}
