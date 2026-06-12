<?php
/**
 * Stores rollback data and a small audit log for fill runs.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_Rollback_Manager
{
    private const OPTION_PREFIX = 'aiaf_last_rollback_';
    private const AUDIT_OPTION  = 'aiaf_audit_log';

    /**
     * @param array<int, array<string, mixed>> $items Rollback items.
     */
    public function save_run(array $items): string
    {
        $run_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('aiaf_', true);
        $payload = [
            'run_id'     => $run_id,
            'user_id'    => get_current_user_id(),
            'created_at' => time(),
            'items'      => array_values($items),
        ];

        update_option($this->get_user_option_name(), $payload, false);
        $this->append_audit_item($payload);

        return $run_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_last_run(): ?array
    {
        $payload = get_option($this->get_user_option_name());
        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback_last(): array
    {
        $payload = $this->get_last_run();
        if (!$payload || empty($payload['items']) || !is_array($payload['items'])) {
            return [
                'rolledBack' => [],
                'errors'     => [__('No rollback data was found for your last run.', 'acf-image-auto-filler')],
            ];
        }

        $acf_available = function_exists('get_field') && function_exists('update_field');

        $rolled_back = [];
        $errors = [];
        $failed_items = [];

        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $post_id = isset($item['post_id']) ? absint($item['post_id']) : 0;
            if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
                $errors[] = __('You no longer have permission to roll back one or more posts.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }

            $type = isset($item['type']) ? (string) $item['type'] : 'acf_image';
            $previous = isset($item['previous_attachment_id']) ? absint($item['previous_attachment_id']) : 0;

            if ($type === 'featured_image') {
                $rolled_back_successfully = $previous > 0 ? (bool) set_post_thumbnail($post_id, $previous) : (bool) delete_post_thumbnail($post_id);
                if (!$rolled_back_successfully) {
                    $errors[] = __('Could not restore the featured image for one or more posts.', 'acf-image-auto-filler');
                    $failed_items[] = $item;
                    continue;
                }
                $rolled_back[] = $item;
                continue;
            }

            if (!$acf_available) {
                $errors[] = __('ACF is not active or required ACF field functions are unavailable for one or more ACF field rollbacks.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }

            $field_key = isset($item['field_key']) ? sanitize_key((string) $item['field_key']) : '';
            if ($field_key === '') {
                $failed_items[] = $item;
                continue;
            }

            $value = $previous > 0 ? $previous : '';
            $scope = isset($item['scope']) ? (string) $item['scope'] : 'top_level';
            $parent_key = isset($item['parent_key']) ? sanitize_key((string) $item['parent_key']) : '';
            $field_name = isset($item['field_name']) ? (string) $item['field_name'] : '';

            if ($scope === 'group' && $parent_key !== '' && $field_name !== '') {
                $group_value = get_field($parent_key, $post_id, false);
                if (!is_array($group_value)) {
                    $group_value = [];
                }
                $group_value[$field_name] = $value;
                $updated = update_field($parent_key, $group_value, $post_id);
            } else {
                $updated = update_field($field_key, $value, $post_id);
            }

            if ($updated === false) {
                $errors[] = __('Could not restore one or more ACF image fields.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }

            $rolled_back[] = $item;
        }

        if (empty($failed_items)) {
            delete_option($this->get_user_option_name());
        } else {
            $payload['items'] = array_values($failed_items);
            update_option($this->get_user_option_name(), $payload, false);
        }

        return [
            'rolledBack'  => $rolled_back,
            'errors'      => array_values(array_unique($errors)),
            'hasRollback' => !empty($failed_items),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_audit_log(): array
    {
        $items = get_option(self::AUDIT_OPTION, []);
        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @param array<string, mixed> $payload Run payload.
     */
    private function append_audit_item(array $payload): void
    {
        $items = $this->get_audit_log();
        array_unshift($items, [
            'run_id'     => isset($payload['run_id']) ? (string) $payload['run_id'] : '',
            'user_id'    => isset($payload['user_id']) ? absint($payload['user_id']) : 0,
            'created_at' => isset($payload['created_at']) ? absint($payload['created_at']) : time(),
            'item_count' => isset($payload['items']) && is_array($payload['items']) ? count($payload['items']) : 0,
        ]);

        $items = array_slice($items, 0, 25);
        update_option(self::AUDIT_OPTION, $items, false);
    }

    private function get_user_option_name(): string
    {
        return self::OPTION_PREFIX . get_current_user_id();
    }
}
