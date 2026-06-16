<?php
/**
 * REST API controller for the React admin app.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class AIAF_REST_Controller
{
    private const NAMESPACE = 'acf-image-auto-filler/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/post-types', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_post_types'],
            'permission_callback' => [self::class, 'can_view_tool'],
        ]);

        register_rest_route(self::NAMESPACE, '/posts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_posts'],
            'permission_callback' => [self::class, 'can_view_tool'],
            'args'                => [
                'post_type' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => [self::class, 'sanitize_content_type'],
                    'validate_callback' => static function ($value): bool {
                        return is_string($value) && self::is_allowed_content_type((string) $value);
                    },
                ],
                'search' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0,
                ],
                'per_page' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0 && absint($value) <= 100,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fields', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_fields'],
            'permission_callback' => [self::class, 'can_edit_requested_post'],
            'args'                => [
                'content_id' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => [self::class, 'sanitize_content_id'],
                    'validate_callback' => static fn ($value): bool => self::is_valid_content_id((string) $value),
                ],
                'post_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn ($value): bool => absint($value) > 0,
                ],
                'include_groups' => [
                    'required'          => false,
                    'type'              => 'boolean',
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/preview', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'preview'],
            'permission_callback' => [self::class, 'can_preview_any_requested_post'],
            'args'                => self::mutation_args(),
        ]);

        register_rest_route(self::NAMESPACE, '/fill', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'fill'],
            'permission_callback' => [self::class, 'can_edit_any_requested_post'],
            'args'                => self::mutation_args(),
        ]);

        register_rest_route(self::NAMESPACE, '/rollback-last', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'rollback_last'],
            'permission_callback' => [self::class, 'can_mutate_tool'],
        ]);

        register_rest_route(self::NAMESPACE, '/rollback-run', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'rollback_run'],
            'permission_callback' => [self::class, 'can_mutate_tool'],
            'args'                => [
                'run_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => static fn ($value): bool => is_string($value) && preg_match('/^[a-zA-Z0-9_-]{8,80}$/', $value) === 1,
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/rollback-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'rollback_status'],
            'permission_callback' => [self::class, 'can_view_tool'],
        ]);

        register_rest_route(self::NAMESPACE, '/audit-log', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_audit_log'],
            'permission_callback' => [self::class, 'can_view_audit_log'],
        ]);
    }

    public static function can_view_tool(): bool
    {
        return current_user_can(self::view_capability());
    }

    public static function can_mutate_tool(): bool
    {
        return current_user_can(self::mutate_capability());
    }

    private static function view_capability(): string
    {
        return AIAF_Capabilities::view();
    }

    private static function mutate_capability(): string
    {
        return AIAF_Capabilities::mutate();
    }

    private static function audit_log_capability(): string
    {
        return AIAF_Capabilities::audit_log();
    }

    public static function can_view_audit_log(): bool
    {
        return current_user_can(self::audit_log_capability());
    }

    public static function can_edit_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_view_tool()) {
            return false;
        }

        $content_id = AIAF_Content_ID::single_from_request($request);

        return $content_id !== '' && AIAF_Content_ID::current_user_can_edit($content_id);
    }

    public static function can_edit_any_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_mutate_tool()) {
            return false;
        }

        return self::request_targets_are_editable($request);
    }

    public static function can_preview_any_requested_post(WP_REST_Request $request): bool
    {
        if (!self::can_view_tool()) {
            return false;
        }

        return self::request_targets_are_editable($request);
    }

    private static function request_targets_are_editable(WP_REST_Request $request): bool
    {
        $content_ids = AIAF_Content_ID::from_request($request);
        if (empty($content_ids)) {
            return false;
        }

        $max_items = (int) apply_filters('aiaf_max_posts_per_run', 50);
        if ($max_items > 0 && count($content_ids) > $max_items) {
            return false;
        }

        foreach ($content_ids as $content_id) {
            if (!AIAF_Content_ID::current_user_can_edit($content_id)) {
                return false;
            }
        }

        return true;
    }

    public static function get_post_types(): WP_REST_Response
    {
        $items = [];
        $allowed_post_types = ['post', 'page'];

        if (self::is_woocommerce_active()) {
            $allowed_post_types[] = 'product';
        }

        $allowed_post_types = apply_filters('aiaf_allowed_post_types', $allowed_post_types);
        $allowed_post_types = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($allowed_post_types) ? $allowed_post_types : []))));

        foreach ($allowed_post_types as $post_type) {
            if (!self::is_allowed_content_post_type($post_type)) {
                continue;
            }

            $object = get_post_type_object($post_type);
            if (!$object) {
                continue;
            }

            $edit_posts_cap = isset($object->cap->edit_posts) ? (string) $object->cap->edit_posts : 'edit_posts';
            if (!current_user_can($edit_posts_cap)) {
                continue;
            }

            $items[] = [
                'slug'                  => (string) $post_type,
                'label'                 => self::get_content_type_label($post_type, $object),
                'kind'                  => 'post_type',
                'supportsFeaturedImage' => post_type_supports($post_type, 'thumbnail'),
            ];
        }

        foreach (self::get_supported_taxonomies() as $taxonomy) {
            $taxonomy_object = get_taxonomy($taxonomy);
            if (!$taxonomy_object || !self::taxonomy_has_eligible_acf_image_fields($taxonomy)) {
                continue;
            }

            $manage_terms_cap = isset($taxonomy_object->cap->manage_terms) ? (string) $taxonomy_object->cap->manage_terms : 'manage_categories';
            $edit_terms_cap = isset($taxonomy_object->cap->edit_terms) ? (string) $taxonomy_object->cap->edit_terms : $manage_terms_cap;
            if (!current_user_can($edit_terms_cap)) {
                continue;
            }

            $items[] = [
                'slug'                  => 'taxonomy:' . $taxonomy,
                'label'                 => self::get_taxonomy_label($taxonomy, $taxonomy_object),
                'kind'                  => 'taxonomy',
                'taxonomy'              => $taxonomy,
                'supportsFeaturedImage' => false,
            ];
        }

        return rest_ensure_response(['postTypes' => $items]);
    }

    private static function is_woocommerce_active(): bool
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

    private static function get_content_type_label(string $post_type, WP_Post_Type $object): string
    {
        $labels = [
            'post'    => __('Berichten', 'acf-image-auto-filler'),
            'page'    => __("Pagina's", 'acf-image-auto-filler'),
            'product' => __('Producten', 'acf-image-auto-filler'),
        ];

        if (isset($labels[$post_type])) {
            return $labels[$post_type];
        }

        return isset($object->labels->name) ? (string) $object->labels->name : (string) $object->label;
    }


    private static function is_allowed_content_post_type(string $post_type): bool
    {
        if ($post_type === 'product' && !self::is_woocommerce_active()) {
            return false;
        }

        $allowed = ['post', 'page'];
        $allowed = apply_filters('aiaf_allowed_post_types', self::is_woocommerce_active() ? array_merge($allowed, ['product']) : $allowed);
        $allowed = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($allowed) ? $allowed : []))));

        if (!in_array($post_type, $allowed, true) || !post_type_exists($post_type) || $post_type === 'attachment') {
            return false;
        }

        if (!AIAF_ACF_Runtime::is_available() && !post_type_supports($post_type, 'thumbnail')) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $value Raw selector type.
     */
    public static function sanitize_content_type($value): string
    {
        $value = trim((string) $value);
        if (strpos($value, 'taxonomy:') === 0) {
            $taxonomy = sanitize_key(substr($value, 9));
            return $taxonomy !== '' ? 'taxonomy:' . $taxonomy : '';
        }

        return sanitize_key($value);
    }

    private static function is_allowed_content_type(string $content_type): bool
    {
        if (strpos($content_type, 'taxonomy:') === 0) {
            $taxonomy = sanitize_key(substr($content_type, 9));
            return in_array($taxonomy, self::get_supported_taxonomies(), true) && self::taxonomy_has_eligible_acf_image_fields($taxonomy);
        }

        return self::is_allowed_content_post_type(sanitize_key($content_type));
    }

    /**
     * @return array<int, string>
     */
    private static function get_supported_taxonomies(): array
    {
        $taxonomies = [];

        if (taxonomy_exists('category')) {
            $taxonomies[] = 'category';
        }

        if (self::is_woocommerce_active() && taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }

        /**
         * Filter the taxonomy selectors shown by the tool. Keep this narrow.
         *
         * @param array<int, string> $taxonomies Taxonomy slugs.
         */
        $taxonomies = apply_filters('aiaf_allowed_taxonomies', $taxonomies);
        $taxonomies = array_values(array_unique(array_filter(array_map('sanitize_key', is_array($taxonomies) ? $taxonomies : []))));

        return array_values(array_filter($taxonomies, static fn (string $taxonomy): bool => taxonomy_exists($taxonomy)));
    }

    private static function taxonomy_has_eligible_acf_image_fields(string $taxonomy): bool
    {
        static $cache = [];

        $taxonomy = sanitize_key($taxonomy);
        $acf_available = function_exists('acf_get_field_groups') && function_exists('acf_get_fields');
        $cache_key = $taxonomy . '|' . ($acf_available ? 'acf-on' : 'acf-off');

        if (array_key_exists($cache_key, $cache)) {
            return (bool) $cache[$cache_key];
        }

        if (!$acf_available) {
            $cache[$cache_key] = false;
            return false;
        }

        $groups = acf_get_field_groups(['taxonomy' => $taxonomy]);
        if (!is_array($groups)) {
            $cache[$cache_key] = false;
            return false;
        }

        foreach ($groups as $group) {
            if (empty($group['key']) || !is_string($group['key'])) {
                continue;
            }

            $fields = acf_get_fields($group['key']);
            if (self::fields_contain_supported_image_field(is_array($fields) ? $fields : [])) {
                $cache[$cache_key] = true;
                return true;
            }
        }

        $cache[$cache_key] = false;
        return false;
    }

    /**
     * @param array<int, mixed> $fields ACF fields.
     */
    private static function fields_contain_supported_image_field(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = isset($field['type']) ? (string) $field['type'] : '';
            if ($type === 'image') {
                return true;
            }

            if ($type === 'group' && !empty($field['sub_fields']) && is_array($field['sub_fields']) && self::fields_contain_supported_image_field($field['sub_fields'])) {
                return true;
            }
        }

        return false;
    }

    private static function get_taxonomy_label(string $taxonomy, WP_Taxonomy $object): string
    {
        $labels = [
            'category'    => __('Categorieën', 'acf-image-auto-filler'),
            'product_cat' => __('Productcategorieën', 'acf-image-auto-filler'),
        ];

        return $labels[$taxonomy] ?? (isset($object->labels->name) ? (string) $object->labels->name : (string) $object->label);
    }

    public static function get_posts(WP_REST_Request $request): WP_REST_Response
    {
        $content_type = (string) $request->get_param('post_type');
        $search = sanitize_text_field((string) ($request->get_param('search') ?? ''));
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 100)));

        if (!self::is_allowed_content_type($content_type)) {
            return new WP_REST_Response(['message' => __('Ongeldig of niet-ondersteund contenttype.', 'acf-image-auto-filler')], 400);
        }

        if (strpos($content_type, 'taxonomy:') === 0) {
            return self::get_terms_for_selector(sanitize_key(substr($content_type, 9)), $search, $page, $per_page);
        }

        $post_type = sanitize_key($content_type);
        $post_type_object = get_post_type_object($post_type);
        $edit_posts_cap = $post_type_object && isset($post_type_object->cap->edit_posts) ? (string) $post_type_object->cap->edit_posts : 'edit_posts';
        if (!current_user_can($edit_posts_cap)) {
            return new WP_REST_Response(['message' => __('Je hebt geen toestemming om dit post type te bekijken.', 'acf-image-auto-filler')], 403);
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
        $max_scan = (int) apply_filters('aiaf_post_selector_scan_max_posts', 5000);
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
                    'meta'   => sanitize_text_field('#' . (int) $post->ID . ' · ' . $status . ($date !== '' ? ' · ' . $date : '')),
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
            return new WP_REST_Response(['message' => __('Ongeldige of niet-ondersteunde taxonomie.', 'acf-image-auto-filler')], 400);
        }

        $manage_terms_cap = isset($taxonomy_object->cap->manage_terms) ? (string) $taxonomy_object->cap->manage_terms : 'manage_categories';
        $edit_terms_cap = isset($taxonomy_object->cap->edit_terms) ? (string) $taxonomy_object->cap->edit_terms : $manage_terms_cap;
        if (!current_user_can($edit_terms_cap)) {
            return new WP_REST_Response(['message' => __('Je hebt geen toestemming om deze taxonomie te bekijken.', 'acf-image-auto-filler')], 403);
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
        $max_scan = (int) apply_filters('aiaf_term_selector_scan_max_terms', 5000);
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
                $count_label = sprintf(_n('%d gekoppeld item', '%d gekoppelde items', (int) $term->count, 'acf-image-auto-filler'), (int) $term->count);
                $items[] = [
                    'id'       => 'term:' . $taxonomy . ':' . (int) $term->term_id,
                    'title'    => sanitize_text_field(wp_strip_all_tags($term->name)),
                    'status'   => $taxonomy,
                    'date'     => '',
                    'meta'     => sanitize_text_field('#' . (int) $term->term_id . ' · ' . $term_label . ' · ' . $count_label),
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

    public static function get_fields(WP_REST_Request $request): WP_REST_Response
    {
        $content_id = AIAF_Content_ID::single_from_request($request);
        $include_groups = rest_sanitize_boolean($request->get_param('include_groups'));

        if ($content_id === '' || !AIAF_Content_ID::current_user_can_edit($content_id)) {
            return new WP_REST_Response(['message' => __('Ongeldig of ontoegankelijk contentitem.', 'acf-image-auto-filler')], 400);
        }

        if (!AIAF_ACF_Runtime::is_available()) {
            return new WP_REST_Response([
                'acfActive' => false,
                'fields'    => [],
                'message'   => __('Alleen uitgelichte afbeelding beschikbaar.', 'acf-image-auto-filler'),
            ], 200);
        }

        $scanner = new AIAF_Field_Scanner();
        $fields = $scanner->get_image_fields(AIAF_Content_ID::to_acf_target($content_id), $include_groups);

        return rest_ensure_response([
            'acfActive' => true,
            'fields'    => $fields,
        ]);
    }

    public static function preview(WP_REST_Request $request): WP_REST_Response
    {
        return self::process_mutation($request, false);
    }

    public static function fill(WP_REST_Request $request): WP_REST_Response
    {
        return self::process_mutation($request, true);
    }

    public static function rollback_last(): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response($manager->rollback_last());
    }

    public static function rollback_run(WP_REST_Request $request): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response($manager->rollback_run((string) $request->get_param('run_id')));
    }

    public static function rollback_status(): WP_REST_Response
    {
        $manager = new AIAF_Rollback_Manager();
        $run = $manager->get_last_run();

        return rest_ensure_response([
            'hasRollback'   => $run !== null,
            'rollbackRunId' => isset($run['run_id']) ? (string) $run['run_id'] : '',
        ]);
    }

    public static function get_audit_log(): WP_REST_Response
    {
        if (!current_user_can(self::audit_log_capability())) {
            return new WP_REST_Response([
                'message' => __('Je hebt geen toestemming om het auditlog te bekijken.', 'acf-image-auto-filler'),
            ], 403);
        }

        $manager = new AIAF_Rollback_Manager();
        return rest_ensure_response(['items' => $manager->get_audit_log()]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function mutation_args(): array
    {
        return [
            'post_id' => [
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn ($value): bool => absint($value) > 0,
            ],
            'content_ids' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'string',
                ],
                'sanitize_callback' => [self::class, 'sanitize_content_id_array'],
                'validate_callback' => [self::class, 'validate_content_id_array'],
            ],
            'post_ids' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'integer',
                ],
                'sanitize_callback' => [self::class, 'sanitize_positive_integer_array'],
                'validate_callback' => [self::class, 'validate_positive_integer_array'],
            ],
            'attachment_ids' => [
                'required'          => true,
                'type'              => 'array',
                'items'             => [
                    'type' => 'integer',
                ],
                'sanitize_callback' => [self::class, 'sanitize_positive_integer_array'],
                'validate_callback' => [self::class, 'validate_positive_integer_array'],
            ],
            'field_keys' => [
                'required'          => false,
                'type'              => 'array',
                'items'             => [
                    'type' => 'string',
                ],
                'sanitize_callback' => [self::class, 'sanitize_string_array'],
                'validate_callback' => static fn ($value): bool => is_null($value) || is_array($value),
            ],
            'manual_mapping' => [
                'required'          => false,
                'type'              => 'object',
                'sanitize_callback' => [AIAF_Manual_Mapping::class, 'sanitize'],
                'validate_callback' => [AIAF_Manual_Mapping::class, 'validate'],
            ],
            'overwrite_existing' => [
                'required'          => false,
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'include_groups' => [
                'required'          => false,
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
            'use_featured_image' => [
                'required'          => false,
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
        ];
    }


    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, int>
     */
    public static function sanitize_positive_integer_array($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $value))));
    }

    public static function validate_positive_integer_array($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_int($item) && !ctype_digit((string) $item)) {
                return false;
            }

            if (absint($item) <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, string>
     */
    public static function sanitize_string_array($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_key', $value)));
    }

    private static function process_mutation(WP_REST_Request $request, bool $execute): WP_REST_Response
    {
        $service = new AIAF_Mutation_Service();
        return $service->process($request, $execute);
    }



    /**
     * @param mixed $value Raw content id.
     */
    public static function sanitize_content_id($value): string
    {
        return AIAF_Content_ID::sanitize($value);
    }

    public static function is_valid_content_id(string $value): bool
    {
        return AIAF_Content_ID::is_valid($value);
    }

    /**
     * @param mixed $value Raw REST array value.
     * @return array<int, string>
     */
    public static function sanitize_content_id_array($value): array
    {
        return AIAF_Content_ID::sanitize_array($value);
    }

    /**
     * @param mixed $value Raw REST array value.
     */
    public static function validate_content_id_array($value): bool
    {
        return AIAF_Content_ID::validate_array($value);
    }

}
