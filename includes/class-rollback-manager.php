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
    private const LAST_OPTION_PREFIX = 'aiaf_last_rollback_';
    private const RUN_OPTION_PREFIX  = 'aiaf_rollback_run_';
    private const AUDIT_OPTION       = 'aiaf_audit_log';

    /**
     * @param array<int, array<string, mixed>> $items Rollback items.
     */
    public function save_run(array $items): string
    {
        $run_id = $this->sanitize_run_id(function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('aiaf_', true));
        $payload = [
            'run_id'     => $run_id,
            'user_id'    => get_current_user_id(),
            'created_at' => time(),
            'items'      => $this->sanitize_items($items),
            'summary'    => $this->build_summary($items),
        ];

        update_option($this->get_run_option_name($run_id), $payload, false);
        update_option($this->get_last_option_name(), $run_id, false);
        $this->append_audit_item($payload);
        $this->cleanup_old_runs();

        return $run_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_last_run(): ?array
    {
        $stored = get_option($this->get_last_option_name());
        if (is_string($stored) && $stored !== '') {
            return $this->get_run($stored);
        }

        if (is_array($stored)) {
            $stored['run_id'] = isset($stored['run_id']) ? $this->sanitize_run_id((string) $stored['run_id']) : '';
            $stored['items'] = isset($stored['items']) && is_array($stored['items']) ? array_values($stored['items']) : [];

            return $stored;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_run(string $run_id): ?array
    {
        return $this->get_run_for_user($run_id, get_current_user_id());
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback_last(): array
    {
        $payload = $this->get_last_run();
        return $this->rollback_payload($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback_run(string $run_id): array
    {
        $payload = $this->get_run($run_id);
        return $this->rollback_payload($payload);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function rollback_payload(?array $payload): array
    {
        if (!$payload || empty($payload['items']) || !is_array($payload['items'])) {
            return [
                'rolledBack' => [],
                'errors'     => [__('No rollback data was found for this run.', 'acf-image-auto-filler')],
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

            $target_id = isset($item['post_id']) ? $this->normalize_target_id($item['post_id']) : 0;
            if (!$this->can_edit_target($target_id)) {
                $errors[] = __('You no longer have permission to undo one or more items.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }
            $post_id = is_int($target_id) ? $target_id : 0;

            $type = isset($item['type']) ? (string) $item['type'] : 'acf_image';
            $previous = isset($item['previous_attachment_id']) ? absint($item['previous_attachment_id']) : 0;

            if ($type === 'featured_image') {
                if ($previous > 0 && (!$this->can_restore_attachment($previous))) {
                    $errors[] = __('You no longer have permission to restore one or more previous featured images.', 'acf-image-auto-filler');
                    $failed_items[] = $item;
                    continue;
                }

                if ($post_id <= 0) {
                    $rolled_back_successfully = false;
                } elseif ($previous > 0) {
                    $rolled_back_successfully = (bool) set_post_thumbnail($post_id, $previous);
                } else {
                    $had_thumbnail = has_post_thumbnail($post_id);
                    $deleted_thumbnail = delete_post_thumbnail($post_id);
                    $rolled_back_successfully = !$had_thumbnail || (bool) $deleted_thumbnail;
                }

                if (!$rolled_back_successfully) {
                    $errors[] = __('The featured image could not be restored for one or more items.', 'acf-image-auto-filler');
                    $failed_items[] = $item;
                    continue;
                }
                $rolled_back[] = $item;
                continue;
            }

            if (!$acf_available) {
                $errors[] = __('ACF is not active or required ACF functions are unavailable for one or more ACF undo actions.', 'acf-image-auto-filler');
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
            $field_name = isset($item['field_name']) ? $this->sanitize_acf_array_key((string) $item['field_name']) : '';

            if ($previous > 0 && !$this->can_restore_attachment($previous)) {
                $errors[] = __('You no longer have permission to restore one or more previous ACF images.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }

            if ($scope === 'group' && $parent_key !== '' && $field_name !== '') {
                $group_value = get_field($parent_key, $target_id, false);
                if (!is_array($group_value)) {
                    $group_value = [];
                }
                $group_value[$field_name] = $value;
                $updated = update_field($parent_key, $group_value, $target_id);
            } else {
                $updated = update_field($field_key, $value, $target_id);
            }

            if ($updated === false) {
                $errors[] = __('One or more ACF image fields could not be restored.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }

            $rolled_back[] = $item;
        }

        $run_id = isset($payload['run_id']) ? $this->sanitize_run_id((string) $payload['run_id']) : '';
        $payload['run_id'] = $run_id;

        if (empty($failed_items)) {
            if ($run_id !== '') {
                delete_option($this->get_run_option_name($run_id));
                $this->mark_audit_item_status($run_id, 'restored');
            }
            if ((string) get_option($this->get_last_option_name()) === $run_id) {
                delete_option($this->get_last_option_name());
            }
        } else {
            $payload['items'] = array_values($failed_items);
            if ($run_id !== '') {
                update_option($this->get_run_option_name($run_id), $payload, false);
            }
        }

        return [
            'rolledBack'  => $rolled_back,
            'errors'      => array_values(array_unique($errors)),
            'hasRollback' => !empty($failed_items),
            'runId'       => $run_id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_audit_log(): array
    {
        $items = get_option(self::AUDIT_OPTION, []);
        if (!is_array($items)) {
            return [];
        }

        $items = array_filter(array_map(function ($item): array {
            if (!is_array($item)) {
                return [];
            }

            $run_id = isset($item['run_id']) ? $this->sanitize_run_id((string) $item['run_id']) : '';
            if ($run_id === '') {
                return [];
            }

            $user_id = isset($item['user_id']) ? absint($item['user_id']) : 0;
            $current_user_run = $this->get_run($run_id);
            $owner_run = $user_id > 0 ? $this->get_run_for_user($run_id, $user_id) : null;
            $stored_status = isset($item['rollback_status']) ? sanitize_key((string) $item['rollback_status']) : '';
            $can_rollback = $current_user_run !== null;
            $owner_has_rollback = $owner_run !== null;
            if ($can_rollback) {
                $rollback_status = 'available';
            } elseif ($owner_has_rollback) {
                $rollback_status = 'available_for_owner';
            } elseif ($stored_status === 'restored') {
                $rollback_status = 'restored';
            } else {
                $rollback_status = 'rollback_data_missing';
            }

            return [
                'run_id'             => $run_id,
                'user_id'            => $user_id,
                'created_at'         => isset($item['created_at']) ? absint($item['created_at']) : 0,
                'item_count'         => isset($item['item_count']) ? absint($item['item_count']) : 0,
                'summary'            => isset($item['summary']) && is_array($item['summary']) ? $this->sanitize_summary($item['summary']) : $this->empty_summary(),
                'can_rollback'       => $can_rollback,
                'owner_has_rollback' => $owner_has_rollback,
                'rollback_status'    => $rollback_status,
            ];
        }, $items));

        return array_values($items);
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
            'summary'    => isset($payload['summary']) && is_array($payload['summary']) ? $this->sanitize_summary($payload['summary']) : $this->empty_summary(),
            'rollback_status' => 'available',
        ]);

        $items = array_slice($items, 0, 25);
        update_option(self::AUDIT_OPTION, $items, false);
    }

    /**
     * @param array<string, mixed> $payload Stored rollback payload.
     * @return array<string, mixed>
     */
    private function sanitize_payload(array $payload): array
    {
        $run_id = isset($payload['run_id']) ? $this->sanitize_run_id((string) $payload['run_id']) : '';

        return [
            'run_id'     => $run_id,
            'user_id'    => isset($payload['user_id']) ? absint($payload['user_id']) : 0,
            'created_at' => isset($payload['created_at']) ? absint($payload['created_at']) : 0,
            'items'      => isset($payload['items']) && is_array($payload['items']) ? $this->sanitize_items($payload['items']) : [],
            'summary'    => isset($payload['summary']) && is_array($payload['summary']) ? $this->sanitize_summary($payload['summary']) : $this->empty_summary(),
            'rollback_status' => isset($payload['rollback_status']) ? sanitize_key((string) $payload['rollback_status']) : '',
        ];
    }

    private function mark_audit_item_status(string $run_id, string $status): void
    {
        $run_id = $this->sanitize_run_id($run_id);
        $status = sanitize_key($status);
        if ($run_id === '' || $status === '') {
            return;
        }

        $items = get_option(self::AUDIT_OPTION, []);
        if (!is_array($items)) {
            return;
        }

        $updated = false;
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }

            $item_run_id = isset($item['run_id']) ? $this->sanitize_run_id((string) $item['run_id']) : '';
            if ($item_run_id !== $run_id) {
                continue;
            }

            $item['rollback_status'] = $status;
            $updated = true;
            break;
        }
        unset($item);

        if ($updated) {
            update_option(self::AUDIT_OPTION, $items, false);
        }
    }


    /**
     * @param array<int, array<string, mixed>> $items Rollback items.
     * @return array<string, mixed>
     */
    private function build_summary(array $items): array
    {
        $titles = [];
        $seen_targets = [];
        $featured_count = 0;
        $acf_count = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = isset($item['type']) && (string) $item['type'] === 'featured_image' ? 'featured_image' : 'acf_image';
            if ($type === 'featured_image') {
                $featured_count++;
            } else {
                $acf_count++;
            }

            $target_id = isset($item['post_id']) ? $this->normalize_target_id($item['post_id']) : 0;
            $target_key = is_int($target_id) ? 'post:' . $target_id : (is_string($target_id) ? $target_id : '');
            if ($target_key === '' || isset($seen_targets[$target_key]) || count($titles) >= 3) {
                continue;
            }

            $title = $this->get_target_title($target_id);
            if ($title !== '') {
                $titles[] = [
                    'id'       => $target_id,
                    'title'    => $title,
                    'edit_url' => $this->get_target_edit_url($target_id),
                ];
                $seen_targets[$target_key] = true;
            }
        }

        return $this->sanitize_summary([
            'titles'         => $titles,
            'featured_count' => $featured_count,
            'acf_count'      => $acf_count,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_summary(): array
    {
        return [
            'titles'         => [],
            'featured_count' => 0,
            'acf_count'      => 0,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function sanitize_summary(array $summary): array
    {
        $titles = [];
        $raw_titles = isset($summary['titles']) && is_array($summary['titles']) ? $summary['titles'] : [];

        foreach ($raw_titles as $item) {
            if (count($titles) >= 3 || !is_array($item)) {
                break;
            }

            $id = isset($item['id']) ? $this->normalize_target_id($item['id']) : 0;
            $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
            if ($title === '') {
                continue;
            }

            $titles[] = [
                'id'       => $id,
                'title'    => $title,
                'edit_url' => isset($item['edit_url']) ? esc_url_raw((string) $item['edit_url']) : '',
            ];
        }

        return [
            'titles'         => $titles,
            'featured_count' => isset($summary['featured_count']) ? absint($summary['featured_count']) : 0,
            'acf_count'      => isset($summary['acf_count']) ? absint($summary['acf_count']) : 0,
        ];
    }

    /**
     * @param int|string $target_id Post ID or ACF term target.
     */
    private function get_target_title($target_id): string
    {
        if (is_int($target_id) && $target_id > 0) {
            $title = get_the_title($target_id);
            return is_string($title) ? sanitize_text_field($title) : '';
        }

        if (is_string($target_id) && strpos($target_id, 'term_') === 0) {
            $term = get_term(absint(substr($target_id, 5)));
            return $term instanceof WP_Term ? sanitize_text_field($term->name) : '';
        }

        return '';
    }

    /**
     * @param int|string $target_id Post ID or ACF term target.
     */
    private function get_target_edit_url($target_id): string
    {
        if (is_int($target_id) && $target_id > 0) {
            $url = get_edit_post_link($target_id, 'raw');
            return is_string($url) ? esc_url_raw($url) : '';
        }

        if (is_string($target_id) && strpos($target_id, 'term_') === 0) {
            $term_id = absint(substr($target_id, 5));
            $term = $term_id > 0 ? get_term($term_id) : null;
            if ($term instanceof WP_Term) {
                $url = get_edit_term_link($term_id, $term->taxonomy);
                return is_string($url) ? esc_url_raw($url) : '';
            }
        }

        return '';
    }

    /**
     * @param array<int, mixed> $items Stored rollback items.
     * @return array<int, array<string, mixed>>
     */
    private function sanitize_items(array $items): array
    {
        $clean = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = isset($item['type']) && (string) $item['type'] === 'featured_image' ? 'featured_image' : 'acf_image';
            $target_id = isset($item['post_id']) ? $this->normalize_target_id($item['post_id']) : 0;
            if (!$target_id) {
                continue;
            }

            $clean[] = [
                'type'                   => $type,
                'post_id'                => $target_id,
                'field_key'              => isset($item['field_key']) ? sanitize_key((string) $item['field_key']) : '',
                'field_name'             => isset($item['field_name']) ? $this->sanitize_acf_array_key((string) $item['field_name']) : '',
                'field_label'            => isset($item['field_label']) ? sanitize_text_field((string) $item['field_label']) : '',
                'scope'                  => isset($item['scope']) ? sanitize_key((string) $item['scope']) : 'top_level',
                'parent_key'             => isset($item['parent_key']) ? sanitize_key((string) $item['parent_key']) : '',
                'previous_attachment_id' => isset($item['previous_attachment_id']) ? absint($item['previous_attachment_id']) : 0,
                'new_attachment_id'      => isset($item['new_attachment_id']) ? absint($item['new_attachment_id']) : 0,
            ];
        }

        return $clean;
    }

    /**
     * @param mixed $value Rollback target value.
     * @return int|string
     */
    private function normalize_target_id($value)
    {
        if (is_string($value) && strpos($value, 'term_') === 0) {
            return 'term_' . absint(substr($value, 5));
        }

        return absint($value);
    }

    /**
     * @param int|string $target_id Post ID or ACF term target.
     */
    private function can_edit_target($target_id): bool
    {
        if (is_int($target_id)) {
            return $target_id > 0 && current_user_can('edit_post', $target_id);
        }

        if (is_string($target_id) && strpos($target_id, 'term_') === 0) {
            $term_id = absint(substr($target_id, 5));
            $term = $term_id > 0 ? get_term($term_id) : null;
            if (!$term instanceof WP_Term) {
                return false;
            }

            return current_user_can('edit_term', $term_id);
        }

        return false;
    }

    private function can_restore_attachment(int $attachment_id): bool
    {
        if (get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id) || !current_user_can('read_post', $attachment_id)) {
            return false;
        }

        /**
         * Filters the additional capability required to restore an attachment.
         * Keep this aligned with aiaf_attachment_usage_capability unless a site
         * intentionally needs different restore rules.
         *
         * @param string $capability Attachment capability.
         * @param int    $attachment_id Attachment ID.
         */
        $capability = apply_filters('aiaf_attachment_restore_capability', apply_filters('aiaf_attachment_usage_capability', 'upload_files', $attachment_id), $attachment_id);
        $capability = is_string($capability) ? trim($capability) : 'upload_files';

        return $capability === '' || current_user_can($capability);
    }

    private function sanitize_acf_array_key(string $value): string
    {
        $value = trim(wp_strip_all_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return is_string($value) ? $value : '';
    }


    private function cleanup_old_runs(): void
    {
        global $wpdb;

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $max_runs = (int) apply_filters('aiaf_rollback_max_runs_per_user', 25, $user_id);
        $max_runs = max(1, $max_runs);
        $retention_days = (int) apply_filters('aiaf_rollback_retention_days', 90, $user_id);
        $retention_days = max(0, $retention_days);

        $option_prefix = self::RUN_OPTION_PREFIX . $user_id . '_';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Pattern-based cleanup is required for per-user rollback options.
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($option_prefix) . '%'
            )
        );

        if (!is_array($option_names) || (count($option_names) <= $max_runs && $retention_days === 0)) {
            return;
        }

        $runs = [];
        foreach ($option_names as $option_name) {
            if (!is_string($option_name) || strpos($option_name, $option_prefix) !== 0) {
                continue;
            }

            $payload = get_option($option_name);
            $created_at = is_array($payload) && isset($payload['created_at']) ? absint($payload['created_at']) : 0;
            $runs[] = [
                'option_name' => $option_name,
                'created_at'  => $created_at,
            ];
        }

        usort($runs, static function (array $a, array $b): int {
            return $b['created_at'] <=> $a['created_at'];
        });

        $cutoff = $retention_days > 0 ? time() - ($retention_days * DAY_IN_SECONDS) : 0;
        foreach ($runs as $index => $run) {
            $too_many = $index >= $max_runs;
            $too_old = $cutoff > 0 && (int) $run['created_at'] > 0 && (int) $run['created_at'] < $cutoff;
            if ($too_many || $too_old) {
                delete_option((string) $run['option_name']);
            }
        }
    }

    private function get_run_for_user(string $run_id, int $user_id): ?array
    {
        $run_id = $this->sanitize_run_id($run_id);
        $user_id = absint($user_id);
        if ($run_id === '' || $user_id <= 0) {
            return null;
        }

        $payload = get_option($this->get_run_option_name($run_id, $user_id));
        return is_array($payload) ? $this->sanitize_payload($payload) : null;
    }

    private function sanitize_run_id(string $run_id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $run_id) ?: '';
    }

    private function get_last_option_name(): string
    {
        return self::LAST_OPTION_PREFIX . get_current_user_id();
    }

    private function get_run_option_name(string $run_id, ?int $user_id = null): string
    {
        $user_id = $user_id === null ? get_current_user_id() : absint($user_id);
        return self::RUN_OPTION_PREFIX . $user_id . '_' . $this->sanitize_run_id($run_id);
    }
}
