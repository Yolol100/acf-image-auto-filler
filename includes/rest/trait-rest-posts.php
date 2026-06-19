<?php
/**
 * REST controller methods split by responsibility.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

trait AIAF_REST_Posts_Trait
{
    public static function get_posts(WP_REST_Request $request): WP_REST_Response
    {
        $content_type = (string) $request->get_param('post_type');
        $search = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 100)));

        if (!self::is_allowed_content_type($content_type)) {
            return new WP_REST_Response(['message' => __('Invalid or unsupported content type.', 'acf-image-auto-filler')], 400);
        }

        if (strpos($content_type, 'taxonomy:') === 0) {
            return self::get_terms_for_selector(sanitize_key(substr($content_type, 9)), $search, $page, $per_page);
        }

        $post_type = sanitize_key($content_type);
        $post_type_object = get_post_type_object($post_type);
        $edit_posts_cap = $post_type_object && isset($post_type_object->cap->edit_posts) ? (string) $post_type_object->cap->edit_posts : 'edit_posts';
        if (!current_user_can($edit_posts_cap)) {
            return new WP_REST_Response(['message' => __('You do not have permission to view this post type.', 'acf-image-auto-filler')], 403);
        }

        $selector_page = self::get_editable_post_selector_page($post_type, $search, $page, $per_page);

        return rest_ensure_response([
            'posts'       => $selector_page['items'],
            'page'        => $page,
            'perPage'     => $per_page,
            'total'       => $selector_page['total'],
            'totalPages'  => $selector_page['totalPages'],
            'hasMore'     => $selector_page['hasMore'],
            'totalExact'  => $selector_page['totalExact'],
        ]);
    }


    /**
     * Builds a post selector page using the same per-post edit permission check as the
     * returned response items. This avoids leaking raw WP_Query found_posts totals for
     * posts that are later filtered out by edit_post capability checks.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, totalPages: int, hasMore: bool, totalExact: bool}
     */
    private static function get_editable_post_selector_page(string $post_type, string $search, int $page, int $per_page): array
    {
        $target_offset = max(0, ($page - 1) * $per_page);
        $raw_offset = 0;
        $editable_seen = 0;
        $items = [];
        $has_more = false;
        $scan_limited = false;
        $chunk_size = (int) apply_filters('aiaf_post_selector_scan_chunk_size', 300);
        $chunk_size = min(1000, max($per_page + 1, $chunk_size));
        $max_scan = (int) apply_filters('aiaf_post_selector_scan_max_posts', 1000);
        $max_scan = $max_scan > 0 ? max($chunk_size, $max_scan) : 0;

        do {
            $query_limit = $max_scan > 0 ? min($chunk_size, max(1, $max_scan - $raw_offset)) : $chunk_size;
            $query = new WP_Query([
                'post_type'              => $post_type,
                'post_status'            => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page'         => $query_limit,
                'offset'                 => $raw_offset,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                's'                      => $search,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $raw_ids = array_values(array_filter(array_map('absint', is_array($query->posts) ? $query->posts : [])));
            foreach ($raw_ids as $post_id) {
                if (!current_user_can('edit_post', $post_id)) {
                    continue;
                }

                if ($editable_seen < $target_offset) {
                    $editable_seen++;
                    continue;
                }

                if (count($items) >= $per_page) {
                    $has_more = true;
                    break 2;
                }

                $post = get_post($post_id);
                if (!$post instanceof WP_Post) {
                    continue;
                }

                $title = get_the_title($post);
                if ($title === '') {
                    /* translators: %d: WordPress post ID. */
                    $title = sprintf(__('Item #%d', 'acf-image-auto-filler'), (int) $post->ID);
                }

                $status = sanitize_key((string) get_post_status($post));
                $date = sanitize_text_field((string) get_the_date('', $post));
                $items[] = [
                    'id'     => (int) $post->ID,
                    'title'  => sanitize_text_field(wp_strip_all_tags($title)),
                    'status' => $status,
                    'date'   => $date,
                    'meta'   => sanitize_text_field('#' . (int) $post->ID . ' - ' . $status . ($date !== '' ? ' - ' . $date : '')),
                ];
                $editable_seen++;
            }

            $raw_offset += $query_limit;
            if ($max_scan > 0 && $raw_offset >= $max_scan && count($raw_ids) === $query_limit) {
                $scan_limited = true;
                $has_more = true;
                break;
            }
        } while (count($raw_ids) === $query_limit);

        $total = ($has_more || $scan_limited) ? $target_offset + count($items) + 1 : $editable_seen;
        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

        return [
            'items'      => $items,
            'total'      => $total,
            'totalPages' => max(1, $total_pages),
            'hasMore'   => $has_more,
            'totalExact' => !($has_more || $scan_limited),
        ];
    }

    private static function get_terms_for_selector(string $taxonomy, string $search, int $page = 1, int $per_page = 100): WP_REST_Response
    {
        $taxonomy_object = get_taxonomy($taxonomy);
        if (!$taxonomy_object) {
            return new WP_REST_Response(['message' => __('Invalid or unsupported taxonomy.', 'acf-image-auto-filler')], 400);
        }

        $manage_terms_cap = isset($taxonomy_object->cap->manage_terms) ? (string) $taxonomy_object->cap->manage_terms : 'manage_categories';
        $edit_terms_cap = isset($taxonomy_object->cap->edit_terms) ? (string) $taxonomy_object->cap->edit_terms : $manage_terms_cap;
        if (!current_user_can($edit_terms_cap)) {
            return new WP_REST_Response(['message' => __('You do not have permission to view this taxonomy.', 'acf-image-auto-filler')], 403);
        }

        $selector_page = self::get_editable_term_selector_page($taxonomy, $taxonomy_object, $search, $page, $per_page);

        return rest_ensure_response([
            'posts'       => $selector_page['items'],
            'page'        => $page,
            'perPage'     => $per_page,
            'total'       => $selector_page['total'],
            'totalPages'  => $selector_page['totalPages'],
            'hasMore'     => $selector_page['hasMore'],
            'totalExact'  => $selector_page['totalExact'],
        ]);
    }

    /**
     * Builds a term selector page using object-level edit_term checks, matching
     * the permission model used by term mutations and rollback.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, totalPages: int, hasMore: bool, totalExact: bool}
     */
    private static function get_editable_term_selector_page(string $taxonomy, WP_Taxonomy $taxonomy_object, string $search, int $page, int $per_page): array
    {
        $target_offset = max(0, ($page - 1) * $per_page);
        $raw_offset = 0;
        $editable_seen = 0;
        $items = [];
        $has_more = false;
        $scan_limited = false;
        $chunk_size = (int) apply_filters('aiaf_term_selector_scan_chunk_size', 300);
        $chunk_size = min(1000, max($per_page + 1, $chunk_size));
        $max_scan = (int) apply_filters('aiaf_term_selector_scan_max_terms', 1000);
        $max_scan = $max_scan > 0 ? max($chunk_size, $max_scan) : 0;
        $term_label = self::get_taxonomy_label($taxonomy, $taxonomy_object);

        do {
            $query_limit = $max_scan > 0 ? min($chunk_size, max(1, $max_scan - $raw_offset)) : $chunk_size;
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => $query_limit,
                'offset'     => $raw_offset,
                'search'     => $search,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);

            if (is_wp_error($terms)) {
                break;
            }

            $terms = is_array($terms) ? $terms : [];
            foreach ($terms as $term) {
                if (!$term instanceof WP_Term || !current_user_can('edit_term', (int) $term->term_id)) {
                    continue;
                }

                if ($editable_seen < $target_offset) {
                    $editable_seen++;
                    continue;
                }

                if (count($items) >= $per_page) {
                    $has_more = true;
                    break 2;
                }

                /* translators: %d: Number of content items linked to the term. */
                $count_label = sprintf(_n('%d linked item', '%d linked items', (int) $term->count, 'acf-image-auto-filler'), (int) $term->count);
                $items[] = [
                    'id'       => 'term:' . $taxonomy . ':' . (int) $term->term_id,
                    'title'    => sanitize_text_field(wp_strip_all_tags($term->name)),
                    'status'   => $taxonomy,
                    'date'     => '',
                    'meta'     => sanitize_text_field('#' . (int) $term->term_id . ' - ' . $term_label . ' - ' . $count_label),
                    'taxonomy' => $taxonomy,
                ];
                $editable_seen++;
            }

            $raw_offset += $query_limit;
            if ($max_scan > 0 && $raw_offset >= $max_scan && count($terms) === $query_limit) {
                $scan_limited = true;
                $has_more = true;
                break;
            }
        } while (count($terms) === $query_limit);

        $total = ($has_more || $scan_limited) ? $target_offset + count($items) + 1 : $editable_seen;
        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

        return [
            'items'      => $items,
            'total'      => $total,
            'totalPages' => max(1, $total_pages),
            'hasMore'   => $has_more,
            'totalExact' => !($has_more || $scan_limited),
        ];
    }
}
