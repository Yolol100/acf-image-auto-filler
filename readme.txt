=== ACF Image Auto Filler ===
Contributors: webactueel
Tags: acf, images, media library, custom fields
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.5.48
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

Test on staging before using batch mode or overwrite mode on client content. The plugin stores only plugin-owned rollback and audit metadata and removes that metadata on uninstall. On multisite, uninstall cleanup stays scoped to the current site unless the uninstall runs from the network admin or `aiaf_uninstall_network_wide_cleanup` is explicitly filtered to true. Rollback can restore saved runs for the current user while their rollback data still exists. The audit log is only visible to administrators by default.

== Privacy and Data ==

The plugin does not send data to external services. It reads WordPress posts, terms, ACF field definitions and Media Library attachment metadata inside the admin screen. When a fill run is executed, the plugin stores rollback data for the current user and a small audit-log entry containing the run ID, user ID, timestamp and item count. Uninstall removes plugin-owned rollback and audit options.

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

Use the source package for development. The source package also contains CSS/JS/i18n source files, build scripts, Composer/PHPCS configuration, wp-env configuration, GitHub Actions and internal verification documents.

== Changelog ==

= 1.5.48 =
* Completed REST argument schemas for stronger validation metadata and Plugin Check readiness.
* Rechecked package hygiene, version alignment, REST permissions and asset references after the UI fixes.

= 1.5.47 =
* Aligned content filter labels above the content type and search fields.
* Preserved the current bordered select, chevron and search-field styling.

= 1.5.46 =
* Cleaned production readme package references while preserving the current admin UI and CSS behavior.
* Aligned release metadata for the cleaned production package.

= 1.5.45 =
* Restored the content type field as a bordered native select with a chevron, matching the current search field styling.
* Kept the existing admin UI CSS intact outside this targeted content-type control fix.

= 1.5.44 =
* Cleaned stale production-package notes without changing the current admin UI styling.
* Removed outdated CSS version comments while preserving the active CSS rules used by the current interface.
* Removed changelog references to source-only and removed minified asset paths from the production readme.

= 1.5.43 =
* Cleaned the production package so root markdown documentation is not bundled in the installable WordPress.org ZIP.
* Kept the 1.5.42 UI-loader and admin asset fixes intact.

= 1.5.42 =
* Fix admin UI loading behavior so the full-screen loader only appears during the first interface load.
* Fix broken minified admin assets causing missing class spacing, layout issues and mixed result text.


= 1.5.41 =
* Fixed WordPress Plugin Check findings for i18n translator comments, WordPress.org update headers, textdomain loading, tested-up-to metadata and production markdown packaging.

= 1.5.38 =
* Fixed modal action buttons so primary and secondary actions render visibly and consistently.
* Fixed result screen action buttons for rollback, preview and starting a new selection.
* Kept overwrite-by-default behavior for featured images and ACF image fields.
* Rebuilt minified JS/CSS assets and bundled updated Dutch and English translations.

= 1.5.23 =
* Added blocking progress overlay for fill and rollback mutations.
* Added rollback per run ID with audit log actions and rollback status.
* Added English translation files and aligned release metadata.


= 1.5.22 =
* Tightened WordPress-native admin spacing across the wizard, cards and form controls.
* Reduced stepper height and active-step dominance while keeping the SaaS wizard style.
* Standardized cards, buttons, status messages and image placeholders for one cohesive UI system.

= 1.5.21 =
* Corrected production-package documentation so it no longer references source-only files as if they are included in the installable ZIP.
* Removed the stale developer-build note from the production readme.
* Aligned plugin header, runtime asset version, translation metadata and stable tag for the repaired production package.

= 1.5.20 =
* Replaced static JavaScript fallback markup injection with DOM-based rendering.
* Removed an unused admin capability wrapper to reduce dead-code noise.
* Built a separate production package that excludes source, CI, local tooling, and internal audit files.

= 1.5.19 =
* Refactored REST mutation orchestration into `AIAF_Mutation_Service`.
* Added `AIAF_Content_ID`, `AIAF_Manual_Mapping` and `AIAF_ACF_Runtime` services to reduce controller responsibility.
* Added project governance documentation and EditorConfig files for maintainability and release governance.
* Removed the screenshot section because no matching WordPress.org screenshot assets are bundled.
* Expanded the static audit gate for service files, governance docs and screenshot/readme consistency.

= 1.5.18 =
* Improved the reproducibility of the admin JavaScript runtime build.
* Updated the admin JavaScript build pipeline and runtime asset loading.
* Added generated Dutch `nl_NL` PO/MO catalog files and `scripts/build-i18n.mjs`.
* Expanded static audit checks to cover JS source/build assets and language catalog assets.

= 1.5.17 =
* Centralized capability filter handling in `AIAF_Capabilities` so admin and REST permissions use one policy.
* Added static audit checks for version metadata, CSS layers, minified CSS, required source files and stale package references.
* Added Composer, PHPCS, PHPCompatibilityWP, wp-env and GitHub Actions configuration to make quality checks repeatable.

= 1.5.16 =
* Rebuilt the admin stylesheet structure for more maintainable runtime styling.
* Added CSS cascade layers (`base`, `components`, `utilities`, `responsive`) to the generated runtime stylesheet.
* Updated admin stylesheet generation and runtime asset loading.

= 1.5.15 =
* Rebuilt the compiled admin stylesheet into one consolidated scoped CSS file, removing the accumulated version-patch blocks override layer.
* Split preview permission from write/rollback permission so users with view access and item edit rights can generate previews without mutation-only REST failures.
* Exposed mutation capability to the admin app so write and rollback actions are disabled before a REST request when the current user cannot mutate.

= 1.5.14 =
* Hardened admin action and confirmation modal button styling to use WordPress primary/secondary button colors reliably, including modal portal rendering.

= 1.5.13 =
* Corrected the no-change confirmation modal so zero executable changes show a clear no-action state with a single primary close button.

= 1.5.12 =
* Improved the admin confirmation modal with clearer four-card action metrics and a sticky action row so the primary action remains visible.
* Improved the fixed review action bar spacing and visibility in the WordPress admin viewport.
* Added the skipped-count card to the result summary for a more complete execution overview.
* Kept the changes limited to UI polish and release metadata; no write logic, REST logic or rollback logic was changed.

= 1.5.11 =
* Bumped runtime package metadata from 1.5.10 to 1.5.11 so the repaired build is distinguishable from the previous package.
* Hardened release metadata by aligning the stable tag, plugin header, runtime asset version and translation template version.
* Set the readme tested-up-to value to the conservative verified support baseline until newer WordPress runtime testing is completed.
* Retained the multisite uninstall hardening and capability-filter normalization from the repaired 1.5.10 package.

= 1.5.10 =
* Added a screen-reader-only page heading (H1) so the document outline starts correctly and WordPress admin notices anchor in the expected place, without adding a visible title bar.
* Moved keyboard/screen-reader focus to the active step when navigating the workflow, so step changes are announced and reachable without a mouse.
* Made the pill radius design token consistent with its name.
* Fixed the review-step empty state so featured-image-only content no longer shows the misleading "choose content and fields" message; it now explains that only the featured image will be filled.
* Kept the Media Library action available after the first selection (labelled "Selectie aanpassen") and pre-seeded the media frame with the current selection so images can be added or removed without starting over.
* Removed the duplicated status title on the result screen.
* Gave the content-selector quick actions clearer link affordance instead of flat grey text.

= 1.5.9 =
* Changed the selected-image list from a stretching grid to compact wrapping cards so single items no longer create oversized empty rows.
* Tightened the image-card layout and action spacing to better match the plugin's calm WordPress-admin style.

= 1.5.8 =
* Refined the selected-image cards with a cleaner two-column layout, calmer badge styling and clearer action buttons.
* Reworked the destructive action so the remove button feels more modern and less visually harsh.

= 1.5.7 =
* Restored the workflow stepper to full width while keeping the calmer modern UI polish.
* Removed the desktop max-width clamp from the step navigation so all five steps keep an even full-width layout.

= 1.5.6 =
* Corrected the primary run button label so the action is imperative while the confirmation modal remains the decision question.
* Cleaned runtime/package metadata for the final UI polish package.

= 1.5.5 =
* Reworked the admin UI styling for the image selection, field selection, review, confirmation and result screens.
* Improved empty states, warning states, selected image cards, preview/result rows and final action layout for a calmer client-facing workflow.
* Restyled the execution confirmation modal so the summary, overwrite warning and actions match the plugin interface.

= 1.5.2 =
* Hardened featured-image validation so taxonomy terms and post types without thumbnail support cannot be processed as featured-image targets.
* Filtered empty attachment IDs before mutation limits and write processing.
* Escaped inline admin settings JSON with hex options before printing to the admin page.
* Disabled the featured-image toggle in the React admin UI for unsupported content types.
* Added previous/next step navigation and clickable completed workflow steps.
* Limited the fullscreen loading modal to the initial plugin load only.
* Simplified the final result screen, image cards and rollback presentation.

= 1.4.1 =
* Moved the fullscreen loading modal slightly upward while preserving horizontal centering and the blocking opaque overlay.

= 1.4.0 =
* Render only the fullscreen loading overlay during initial data load so the underlying interface is not visible before it is ready.
* Center the loading modal horizontally and vertically in the viewport until the initial post-type and content-list requests have finished.
* Keep later async loading states blocked by an opaque overlay instead of showing the old inline “Bezig…” state.

= 1.3.98 =
* Reworked the Contenttype/Search field styling to use one shared scoped control system.
* Removed the previous double-border focus conflict by styling the WordPress Components backdrop as the only visible border layer.
* Kept the native select arrow single while matching the search field height, border, radius and focus state.

= 1.3.95 =
* Aligned the Contenttype SelectControl with the adjacent search TextControl using one shared scoped control style.
* Matched height, border color, border radius, font sizing, padding, focus state and spacing for both controls.
* Kept the native/WordPress select arrow single by avoiding custom background arrows.
* Updated runtime, asset and source package metadata to 1.3.95.

= 1.3.92 =
* Fixed out-of-range post selector totals so exhausted scans return the actual counted editable total.
* Added bounded, filterable post selector scans for large admin datasets.
* Added object-level `edit_term` checks for term selector results, term mutations and rollback.
* Added bounded, filterable term selector scans with permission-matched totals.
* Added bundled translation files for plugin strings.
* Added a direct-access guard to `build/index.asset.php`.
* Kept JavaScript translations pointed at the plugin `languages/` directory.
* Updated POT, runtime, asset and source package metadata to 1.3.92.
* Documented the source archive as a reproducible source snapshot for this release.

= 1.3.90 =
* Fixed featured-image rollback when the previous state was empty and the post already has no thumbnail.
* Reduced hard CSS overrides in the action summary UI to lower coupling with WordPress Components.
* Added an explicit developer source package notice for maintenance and redistribution.

= 1.3.89 =
* Dedicated step 5 result screen further polished after UX audit.
* Result feedback uses clearer Dutch user-facing copy and less technical metadata by default.
* Result summary, result messages, last action card and technical details were tightened for a calmer plugin-native finish.
* Confirm modal, result messages and featured-image feedback remain aligned with the final UI style.

= Version 1.3.67 =
* Replaced the plain JavaScript loading fallback with a modern WordPress admin loading card.
* Added styled no-script and startup error states for a cleaner plugin interface.

= 1.3.65 =
* Vertically centered the media count pill in the Images step header.
* Matched the pill height, padding and line-height for consistent optical centering.
* Updated plugin, asset and readme versions to 1.3.65.

= 1.3.58 =
* Fixed stepper vertical alignment so inactive and active steps use the same centered layout.

= 1.3.57 =
* Replaced the blue support note with a compact WordPress-native inline support summary that better matches the plugin UI.
* The summary now updates its copy when ACF Group scanning is enabled or disabled.
* Kept the unsupported field types visible as secondary helper text without using a prominent admin notice style.

= 1.3.56 =
* Fixed Post type SelectControl label typography so it matches the Search label in WordPress Components.

= 1.3.55 =
* Limited the content selector to Berichten, Pagina's and Producten only. Producten are shown only when WooCommerce is active.
* Kept WooCommerce-only content out of the selector when WooCommerce is unavailable, so Coupon and unrelated custom post types no longer appear by default.

= 1.3.54 =
* Split the main capability boundary into dedicated view, mutate and audit-log capability filters. The legacy aiaf_required_capability filter remains the fallback for read/access checks, while bulk mutations now default to manage_options unless aiaf_mutate_capability is explicitly lowered.
* Kept bulk preview, fill and rollback actions protected by the mutate capability plus per-post edit_post checks.
* Added request-local caching for ACF image field scans to reduce repeated work inside one request.
* Clarified rollback and production-runtime package notes in the readme.

= 1.3.44 =
* Tightened the admin UI further toward WordPress Core/Gutenberg patterns.
* Reduced custom boxed list styling for the stepper, content rows, field rows, image tiles, result rows and badges.
* Kept styling scoped to layout, spacing and plugin-specific states while preserving existing functionality.

= 1.3.43 =
* Refined the admin UI toward a stricter WordPress-native React/Gutenberg component style.
* Reduced decorative custom styling and kept CSS focused on scoped layout, spacing and plugin-specific states.
* Rechecked old menu, capability, text and release-version remnants.

= 1.3.41 =
* Cleaned up remaining version, asset and readme references after the design-token UI pass.
* Removed stale legacy admin-hook references now that the plugin uses its own WordPress admin menu item.
* Removed one unused legacy CSS selector so the admin UI follows the scoped design-token layer more cleanly.

= 1.3.37 =
* Refined the fields step summary, card layout, toggle controls and empty-state styling to match the modern plugin interface.
* Updated plugin, asset, POT and readme versions to 1.3.37.

= 1.3.36 =
* Refined the workflow stepper with a lighter, more modern, rounded admin style that fits the plugin UI.
* Improved responsive stepper behaviour for tablet and mobile admin screens.
* Updated plugin, asset, POT and readme versions to 1.3.36.

= 1.3.35 =
* Refined post card title spacing with the requested scoped CSS rule.
* Updated plugin, asset, POT and readme versions to 1.3.35.

= 1.3.34 =
* Refined the content selection toolbar and post cards for a cleaner, more professional full-width admin UI.
* Added a selected-card state for chosen content items.
* Updated plugin, asset, POT and readme versions to 1.3.34.

= 1.3.33 =
* Added strong scoped admin styling for the post type/search filter row and empty-state message.
* Added scoped CSS overrides so the content controls keep the plugin style inside WordPress admin screens.
* Updated plugin, asset, POT and readme versions to 1.3.33.

= 1.3.32 =
* Made the admin workflow full width so the stepper, cards and content list use the available WordPress admin space.
* Added bulk content selection actions: select all visible, deselect visible, select published and select draft/pending/future items.
* Changed the content list to a responsive multi-column grid so more pages, posts and custom post type items are visible at once.
* Updated plugin, asset, POT and readme versions to 1.3.32.

= 1.3.30 =
* Corrected the action-bar target count for featured-image-only workflows.
* Updated plugin, asset and readme versions to 1.3.30.

= 1.3.29 =
* Clarified ACF-unavailable admin messaging now that featured-image-only runs can work without ACF field filling.
* Updated plugin, asset and readme versions to 1.3.29.

= 1.3.28 =
* Kept post-specific manual mapping scoped to the intended post in batch/REST workflows.
* Updated plugin, asset and readme versions to 1.3.28.

= 1.3.27 =
* Refreshed the translation template so current PHP and admin UI strings are included and stale strings are removed.
* Updated plugin, asset and readme versions to 1.3.27.

= 1.3.26 =
* Prevented featured-image-only runs from unintentionally treating an empty field selection as all ACF image fields.
* Required an explicit ACF field target or featured-image option for mutation requests.
* Updated plugin, asset and readme versions to 1.3.26.

= 1.3.25 =
* Repaired the recent changelog entries after the iterative 1.3.20-1.3.24 bugfix loop.
* Updated plugin, asset and readme versions to 1.3.25.

= 1.3.24 =
* Restored the missing 1.3.4 changelog entry so the packaged release history is complete.
* Updated plugin, asset and readme versions to 1.3.24.

= 1.3.23 =
* Made REST manual mapping deterministic by letting post-specific mappings override global mappings for the same field.
* Updated plugin, asset and readme versions to 1.3.23.

= 1.3.22 =
* Kept the rollback action reachable when a previous rollback exists, even before selecting content for a new run.
* Updated plugin, asset and readme versions to 1.3.22.

= 1.3.21 =
* Added audit-log rollback for specific saved run IDs.
* Updated plugin, asset and readme versions to 1.3.21.

= 1.3.20 =
* Prevented partial ACF writes when the selected featured image is not a valid image attachment.
* Updated plugin, asset and readme versions to 1.3.20.

= 1.3.19 =
* Kept rollback availability visible after a partial rollback failure by returning and reading the remaining rollback state.
* Updated plugin, asset and readme versions to 1.3.19.

= 1.3.18 =
* Preserved failed featured-image rollback items when removing a newly set featured image fails, instead of marking the rollback as successful.
* Updated plugin, asset and readme versions to 1.3.18.

= 1.3.17 =
* Kept the featured-image-reserved attachment out of automatic ACF mapping even when manual field mapping is also present, matching the admin preview.
* Updated plugin, asset and readme versions to 1.3.17.

= 1.3.16 =
* Repaired the changelog after iterative version bumps so recent bugfix entries are listed accurately and separately.
* Updated plugin, asset and readme versions to 1.3.16.

= 1.3.15 =
* Corrected too-many/too-few image warnings when the first selected image is reserved for the featured image.
* Updated plugin, asset and readme versions to 1.3.15.

= 1.3.14 =
* Aligned the admin mapping preview and image-count guidance with featured-image reservation, so automatic ACF mapping starts after the featured image.
* Updated plugin, asset and readme versions to 1.3.14.

= 1.3.13 =
* Prevented the first selected image from being reused as the first automatic ACF field image when it is already reserved for the featured image.
* Updated plugin, asset and readme versions to 1.3.13.

= 1.3.12 =
* Preserved failed rollback items instead of deleting all rollback data after a partial rollback failure, so failed items can be retried after fixing permissions or ACF availability.
* Updated plugin, asset and readme versions to 1.3.12.

= 1.3.11 =
* Aligned the REST fields endpoint ACF availability check with the admin and mutation checks so the UI cannot report ACF as active when required runtime functions are unavailable.
* Updated plugin, asset and readme versions to 1.3.11.

= 1.3.10 =
* Hardened ACF runtime availability checks by requiring get_field() and update_field() where group-field reading/writing or rollback can use both functions.
* Updated plugin, asset and readme versions to 1.3.10.

= 1.3.9 =
* Matched the admin ACF availability flag with the REST/API checks by requiring both core ACF field functions.
* Applied the batch post-count limit before the REST permission loop to avoid oversized permission checks.
* Updated plugin, asset and readme versions to 1.3.9.

= 1.3.8 =
* Corrected release metadata for the 1.3.8 package; current tested-up-to metadata is maintained in the readme header.
* Allowed featured-image-only runs to work without ACF runtime functions when no ACF fields are selected.
* Added a configurable maximum post count per batch run to prevent oversized REST requests.
* Fixed rollback so featured-image rollbacks can still run when ACF is unavailable.
* Updated plugin, asset and readme versions to 1.3.8.

= 1.3.7 =
* Fixed SelectControl fallback markup so fallback dropdowns stay visible when WordPress components are unavailable.
* Made manual image mapping field selection use a functional state update to avoid stale selection state.
* Updated plugin, asset and readme versions to 1.3.7.

= 1.3.6 =
* Fixed the admin control table so automatic image mapping matches backend behavior when selected ACF fields already have values and overwrite mode is off.
* Improved image-count notices so filled fields are not counted as automatic targets unless overwrite mode is enabled.
* Updated plugin, asset and readme versions to 1.3.6.

= 1.3.5 =
* Fixed featured-image-only runs when a post has no eligible ACF image fields.
* Added clearer skipped feedback when selected batch fields are not available on one of the selected posts.
* Corrected extra-image counting when featured-image mode reuses a selected image.
* Hardened rollback error reporting for failed ACF field or featured-image restores.
* Updated plugin, asset and readme versions to 1.3.5.

= 1.3.4 =
* Improved featured-image-only, batch unavailable-field and rollback edge cases.
* Updated plugin, asset and readme versions to 1.3.4.

= 1.3.3 =
* Fixed the mapping preview so automatic images follow the selected ACF fields, matching the backend write order.
* Corrected featured-image counting: the first selected image is reused for the featured image and is not consumed separately from ACF field mapping.
* Added a clearer notice for featured-image mode in the image-selection step.
* Added small component fallbacks for optional WordPress admin UI wrappers to reduce blank-screen risk.
* Updated plugin, asset and readme versions to 1.3.3.

= 1.3.2 =
* Hardened admin app loading and improved the admin page registration to prevent blank screens.
* Removed the unsupported wp-icons runtime dependency from the admin app dependencies.
* Fixed version metadata so the plugin header, constant, asset version and readme match.
* Fixed ACF Group image-field reads/writes and rollback handling.
* Corrected the production package notes in the readme.

= 1.2.7 =
* Removed the large plugin header, icon, version badge and normal-state ACF status from the main admin screen.
* Reduced the interface to a compact stepper and one task card per step.
* Made early-step cards narrow to avoid empty right-side space.
* Kept sticky execution actions only for preview/control and execution phases.

= 1.2.3 =
* Added translation template in languages/.
* Hardened uninstall cleanup for multisite and single-site installs.
* Documented production package boundaries.

= 1.2.2 =
* UX/UI hardening for the React WordPress admin interface.
* Added clearer batch mode warning for multi-post updates.
* Replaced technical labels with more editor-friendly Dutch microcopy.
* Added mapping filters for selected fields, overwrites, empty fields and missing images.
* Improved responsive mapping layout for narrow wp-admin viewports.
* Improved accessibility labels, focus states, table captions and action bar status handling.

= 1.2.1 =
* Clean production release package.
* Added uninstall cleanup for plugin-owned rollback and audit metadata.
* Restricted audit log access to administrators.
* Removed development-only source and legacy asset folders from the release package.

== Developer Build ==

This installable production package intentionally excludes source files, local tooling, CI files and internal audit documents. Use the separate source package for build scripts, Composer/PHPCS configuration, wp-env configuration and development-only verification documents.
