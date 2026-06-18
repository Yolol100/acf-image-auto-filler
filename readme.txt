=== ACF Image Auto Filler ===
Contributors: webactueel
Tags: acf, images, media library, custom fields
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.7.47
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely map selected Media Library images to supported ACF Image Fields or featured images from a React-powered WordPress admin screen.

== Description ==

ACF Image Auto Filler helps editors and agencies fill supported ACF Image Fields from selected Media Library images. It supports normal top-level ACF Image fields and, when enabled, image fields directly inside ACF Group fields. Repeater, flexible content, gallery and clone fields are intentionally not auto-filled. The plugin includes preview mapping, optional overwrite confirmation, rollback per saved run from the audit log, manual field mapping, CSV dry-run export, optional featured image support, batch mode and a small admin audit log.


== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open ACF Image Filler from the WordPress admin sidebar.
4. Select content, fields, images, preview the mapping, and run the update.

== Safety Notes ==

Test on staging before using batch mode or overwrite mode on client content. The plugin stores only plugin-owned rollback and audit metadata and removes that metadata on uninstall. On multisite, uninstall cleanup stays scoped to the current site unless the uninstall runs from the network admin or `aiaf_uninstall_network_wide_cleanup` is explicitly filtered to true. Rollback can restore saved runs for the current user while their rollback data still exists. By default, the plugin keeps up to 25 rollback runs per user and removes runs older than 90 days. The audit log is only visible to administrators by default.

== Privacy and Data ==

The plugin does not send data to external services. It reads WordPress posts, terms, ACF field definitions and Media Library attachment metadata inside the admin screen. When a fill run is executed, the plugin stores rollback data for the current user and a small audit-log entry containing the run ID, user ID, timestamp, item count and a short summary with item titles and changed target counts. Uninstall removes plugin-owned rollback and audit options.

== Field Support ==

Supported targets:

* Normal top-level ACF Image fields.
* Image fields directly inside ACF Group fields when the group option is enabled.
* Featured images when the featured-image option is enabled.

Not automatically filled:

* ACF Repeater fields.
* ACF Flexible Content fields.
* ACF Gallery fields.
* ACF Clone fields.

These limits are intentional to avoid unsafe assumptions about nested field rows, layouts and cloned field storage.

== Capability Filters ==

The tool uses `manage_options` by default for write actions, rollback and audit-log access. `aiaf_view_capability` can be used for read-only tool access. Lower `aiaf_mutate_capability` only for trusted roles that may bulk-edit selected posts, terms and featured images; per-item `edit_post` and `edit_term` checks still run, but the mutation capability remains the main bulk-action gate. Capability filter values are trimmed and empty or non-string values fall back to `manage_options`.

Example for trusted editors:

```php
add_filter('aiaf_view_capability', fn() => 'edit_others_posts');
add_filter('aiaf_mutate_capability', fn() => 'edit_others_posts');
```

Use this only for roles that may intentionally change multiple posts, terms and featured images. The per-item `edit_post` and `edit_term` checks still apply.

Rollback retention can be adjusted for large sites:

```php
add_filter('aiaf_rollback_max_runs_per_user', fn() => 25);
add_filter('aiaf_rollback_retention_days', fn() => 90);
```

The default retention days value is `90`. Use `0` only when age-based cleanup should be disabled intentionally. The run-count cleanup still keeps the newest runs per user.

== Known Limits ==

The content selector loads items in batches of up to 100 results. Use search or the load-more action on large sites. Batch runs are capped by the `aiaf_max_posts_per_run` filter and selected images are capped by the `aiaf_max_attachments_per_run` filter. Taxonomies do not support featured images in this tool.

== Frequently Asked Questions ==

= Does this overwrite existing values? =

Only when the overwrite toggle is explicitly enabled. Otherwise existing ACF image values and featured images are skipped.

= Can I use this without ACF active? =

Featured-image-only runs can still work when ACF is unavailable. ACF field filling requires the expected ACF runtime functions.

= Which WooCommerce items are shown? =

Products and product categories are shown only when WooCommerce is active and the relevant post type or taxonomy exists. Product categories are shown only when eligible ACF Image fields are attached.

= Can I undo a run? =

Saved runs for the current user can be rolled back from the audit log while their rollback data still exists and the current user still has permission to edit the affected posts or terms.

== Staging Checklist ==

Before using batch mode or overwrite mode on client content, test activation, one-post filling, batch filling, overwrite off, overwrite on, manual mapping, featured-image-only mode, group-field mode, rollback after a normal run, rollback after a batch run, low-privilege access, ACF temporarily unavailable, uninstall cleanup and responsive admin behaviour.

== Package Notes ==

Use the production package for normal WordPress installation. It contains only runtime files: plugin PHP files, built admin assets, bundled language files, uninstall cleanup and readme.txt.

Use the source package for development. The source package also contains readable CSS/JS source files and internal verification documents.

== Changelog ==

= 1.7.47 =
* Removed the manual text domain loader for WordPress.org translation loading.
* Added translator comments for placeholder strings in the audit log.
* Tightened read-only audit filter handling to avoid nonce verification warnings.
* Aligned release metadata for WordPress.org-style distribution.
* Removed private-update metadata for WordPress.org-style packaging.

= 1.6.22 =
* Finalized release metadata for the production-ready private build.

= 1.6.21 =
* Shortened the plugin header description, added the author URI and corrected duplicate changelog metadata.

= 1.6.20 =
* Increased spacing between the Audit log title and intro text for better visual separation.

= 1.6.19 =
* Matched the Audit log header typography and intro text spacing to the main Choose content card header.

= 1.6.18 =
* Improved audit-log user labels while keeping the stored audit data minimal.
* Hardened audit-log date filtering and rollback confirmation output.
* Cleaned minor code-quality issues in rollback housekeeping.

= 1.6.17 =
* Fixed audit-log status handling so restored runs are not confused with expired or missing rollback data.
* Aligned audit-log date filtering with the WordPress site timezone.
* Corrected rollback-retention documentation to match the 90-day default.

= 1.6.16 =
* Aligned audit-log REST and rollback form authorization with the audit-log capability.
* Removed a duplicate featured-image response key.

= 1.6.15 =
* Hardened mutate capabilities so lowering view access no longer grants fill or rollback rights.
* Added allowed-target validation for preview and fill requests.
* Restricted audit-log access to the audit-log capability by default.
* Added stricter attachment-use capability checks with filters for site-specific policy.
* Preserved original ACF group subfield names for writes and rollbacks.
* Reduced default selector scan limits, added visible focus states, set default rollback retention to 90 days, and cleaned release packaging notes.

= 1.6.14 =
* Added a real rollback action to the Audit log page for runs owned by the current user.
* Clarified audit-log status labels and disabled states for runs that cannot be undone by the current user.
* Renamed the overwrite option to “Replace existing images” and clarified its safe default behavior.
* Improved audit-log change summaries for featured-image and ACF-field counts.
* Removed a duplicate REST validation callback.

= 1.6.13 =
* Removed the WordPress Help tab from the plugin screen.
* Restyled the Audit log back button as the primary blue plugin action and kept it in the top-right header area.

= 1.6.11 =
* Fixed malformed admin CSS so audit-log and checkbox styles are parsed at the correct top-level scope.
* Fixed audit log asset loading on submenu pages by detecting the plugin page slug as a fallback.
* Improved audit log header copy and back navigation label.
* Kept the main ACF Image Filler tool visible as the first submenu item alongside the Audit log page.

= 1.6.7 =
* Fix: keep the Next step action visible on the Target step when the featured-image-only target is ready.

= 1.6.6 =
* Centralized the WooCommerce active-state check in `AIAF_Environment` so admin and REST layers share one implementation.
* Kept existing admin and REST call sites intact while reducing duplicate maintenance code.

= 1.6.4 =
* Converted the plugin source strings to English and refreshed bundled Dutch/English translation catalogs to reduce mixed-language admin UI.
* Added rollback-run housekeeping with `aiaf_rollback_max_runs_per_user` and `aiaf_rollback_retention_days` filters.
* Changed overwrite mode to an explicit opt-in by default in REST and the admin app.
* Added audit-log summaries with item titles, featured-image counts, ACF counts and first-item edit links.
* Improved destructive batch confirmation copy, primary action labels and overwrite warning state.
* Kept the fields step visible for featured-image-only runs so users see why no ACF fields are available.
* Added a WordPress admin help tab, capability filter examples and rollback-retention documentation.
* Sanitized group-field path labels before returning field metadata.

= 1.6.1 =
* Hardened rollback payload sanitization before stored data is returned or executed.
* Added defense-in-depth edit checks inside the mutation service.
* Added limits for field keys and manual mappings.
* Included manual-mapped attachments in attachment validation.
* Improved asset metadata fallback handling.
* Added textdomain loading support for bundled translations.
* Cleaned production packaging so development sources are excluded from the install zip.

= 1.6.0 =
* Architecture refactor release.
