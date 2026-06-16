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
            'items'      => array_values($items),
        ];

        update_option($this->get_run_option_name($run_id), $payload, false);
        update_option($this->get_last_option_name(), $run_id, false);
        $this->append_audit_item($payload);

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
        $run_id = $this->sanitize_run_id($run_id);
        if ($run_id === '') {
            return null;
        }

        $payload = get_option($this->get_run_option_name($run_id));
        return is_array($payload) ? $payload : null;
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
                'errors'     => [__('Er is geen rollbackdata gevonden voor deze run.', 'acf-image-auto-filler')],
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
                $errors[] = __('Je hebt geen toestemming meer om één of meer items terug te draaien.', 'acf-image-auto-filler');
                $failed_items[] = $item;
                continue;
            }
            $post_id = is_int($target_id) ? $target_id : 0;

            $type = isset($item['type']) ? (string) $item['type'] : 'acf_image';
            $previous = isset($item['previous_attachment_id']) ? absint($item['previous_attachment_id']) : 0;

            if ($type === 'featured_image') {
                if ($previous > 0 && (!$this->can_restore_attachment($previous))) {
                    $errors[] = __('Je hebt geen toestemming meer om één of meer vorige uitgelichte afbeeldingen te herstellen.', 'acf-image-auto-filler');
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
                    $errors[] = __('De uitgelichte afbeelding kon voor één of meer items niet worden hersteld.', 'acf-image-auto-filler');
                    $failed_items[] = $item;
                    continue;
                }
                $rolled_back[] = $item;
                continue;
            }

            if (!$acf_available) {
                $errors[] = __('ACF is niet actief of vereiste ACF-functies zijn niet beschikbaar voor één of meer ACF-rollbacks.', 'acf-image-auto-filler');
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
            $field_name = isset($item['field_name']) ? sanitize_key((string) $item['field_name']) : '';

            if ($previous > 0 && !$this->can_restore_attachment($previous)) {
                $errors[] = __('Je hebt geen toestemming meer om één of meer vorige ACF-afbeeldingen te herstellen.', 'acf-image-auto-filler');
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
                $errors[] = __('Eén of meer ACF Image fields konden niet worden hersteld.', 'acf-image-auto-filler');
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

            return [
                'run_id'       => $run_id,
                'user_id'      => isset($item['user_id']) ? absint($item['user_id']) : 0,
                'created_at'   => isset($item['created_at']) ? absint($item['created_at']) : 0,
                'item_count'   => isset($item['item_count']) ? absint($item['item_count']) : 0,
                'can_rollback' => $this->get_run($run_id) !== null,
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
        ]);

        $items = array_slice($items, 0, 25);
        update_option(self::AUDIT_OPTION, $items, false);
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
        return get_post_type($attachment_id) === 'attachment'
            && wp_attachment_is_image($attachment_id)
            && current_user_can('read_post', $attachment_id);
    }

    private function sanitize_run_id(string $run_id): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $run_id) ?: '';
    }

    private function get_last_option_name(): string
    {
        return self::LAST_OPTION_PREFIX . get_current_user_id();
    }

    private function get_run_option_name(string $run_id): string
    {
        return self::RUN_OPTION_PREFIX . get_current_user_id() . '_' . $this->sanitize_run_id($run_id);
    }
}
