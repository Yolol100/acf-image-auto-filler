=== ACF Image Auto Filler ===
Contributors: webactueel
Tags: acf, images, media library, custom fields
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.3.50
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely map selected Media Library images to normal ACF Image Fields from a React-powered WordPress admin screen.

== Description ==

ACF Image Auto Filler helps editors and agencies fill normal top-level ACF Image Fields from selected Media Library images. It includes preview mapping, optional overwrite confirmation, rollback for the last run, manual field mapping, CSV dry-run export, optional featured image support, optional ACF Group field support, batch mode, and a small admin audit log.


== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open ACF Image Filler from the WordPress admin sidebar.
4. Select content, fields, images, preview the mapping, and run the update.

== Safety Notes ==

Test on staging before using batch mode or overwrite mode on client content. The plugin stores only plugin-owned rollback and audit metadata and removes that metadata on uninstall. Rollback restores only the last saved run for the current user. The audit log is only visible to administrators by default.

== Package Notes ==

This ZIP is a production runtime package. It includes the plugin PHP files, built admin assets, translation template and readme. Source files such as src/, package.json and development documentation are intentionally not included. Keep a matching developer/source package separately when this plugin is maintained, reviewed or redistributed.

== Changelog ==


= 1.3.50 =
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
* Added important CSS overrides so the content controls keep the plugin style inside WordPress admin screens.
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
* Restored the visible admin action for rolling back the last saved run.
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
* Corrected release metadata: `Tested up to` now targets WordPress 6.9 instead of the invalid future 7.0 value.
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

This production ZIP does not include the JavaScript source project. The runtime admin interface uses build/index.js, build/index.css and build/index.asset.php.
