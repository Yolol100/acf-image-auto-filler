# ACF Image Auto Filler

> **Supporting portfolio project · WordPress/PHP · ACF · media mapping · preview and rollback**

**Developer profile:** [Andrew Baeten](https://github.com/Yolol100) · [Portfolio cases](https://andrewbaeten.nl/category/cases)

ACF Image Auto Filler is a WordPress admin plugin for safely mapping selected Media Library images to supported ACF Image fields or featured images. It is built for controlled editorial and agency workflows with previewing, explicit overwrite behavior, rollback support and audit logging.

The WordPress-style `readme.txt` remains the detailed distribution documentation and changelog. This `README.md` provides the GitHub project overview.

## What it does

The plugin helps editors fill image fields across selected WordPress content without manually opening each item.

Key capabilities include:

- Fill normal top-level ACF Image fields.
- Fill image fields directly inside ACF Group fields when enabled.
- Optionally set featured images.
- Preview mappings before applying changes.
- Skip existing values by default unless overwrite is explicitly enabled.
- Configure manual field mappings when automatic mapping is not sufficient.
- Run bounded batch updates.
- Export a CSV dry-run for review.
- Store rollback data per saved run.
- Restore eligible runs from the admin audit log.
- Keep a small administrator-facing audit trail.

The plugin intentionally does not automatically fill ACF Repeater, Flexible Content, Gallery or Clone fields because those structures require assumptions that can make bulk updates unsafe.

## Requirements

- WordPress 6.5 or newer.
- PHP 8.1 or newer.
- Advanced Custom Fields for ACF-field filling.

Featured-image-only runs can still work when ACF is unavailable.

WooCommerce products and product categories can be exposed when WooCommerce is active and eligible fields exist.

## Installation

1. Copy the plugin directory to `wp-content/plugins/acf-image-auto-filler/` or upload the packaged plugin ZIP.
2. Activate **ACF Image Auto Filler** in WordPress.
3. Open **ACF Image Filler** from the WordPress admin sidebar.
4. Select the content, target fields and Media Library images.
5. Preview the mapping before running the update.

## Safe workflow

For normal use:

1. Select a small group of content items.
2. Choose supported ACF image fields or featured images.
3. Select the Media Library images.
4. Review the preview mapping.
5. Leave overwrite disabled unless replacement is intentional.
6. Run the update.
7. Use the audit log and rollback controls if a saved run needs to be restored.

Test batch and overwrite workflows on staging before using them on client production content.

## Permissions and rollback

Write actions, rollback and audit-log access use `manage_options` by default. The repository includes capability filters for trusted installations that need a different policy, while per-item post or term permission checks still apply.

Rollback data is retained for a bounded number of runs and a bounded age by default. A rollback is only available while its stored data still exists and the current user remains allowed to edit the affected content.

## Privacy

The plugin does not send data to external services. It reads WordPress content, ACF definitions and Media Library metadata inside the admin workflow.

When a fill run is executed, plugin-owned rollback information and a small audit entry are stored in WordPress. Uninstall cleanup removes plugin-owned rollback and audit options according to the plugin's cleanup rules.

## Repository structure

- `acf-image-auto-filler.php` — plugin bootstrap and metadata.
- `includes/` — field scanning, mapping, permissions, REST, mutation, rollback and admin logic.
- `build/` — built admin assets.
- `languages/` — translation files.
- `uninstall.php` — plugin cleanup logic.
- `readme.txt` — WordPress distribution documentation and changelog.

## License

GPL-2.0-or-later, as declared in the plugin metadata and `readme.txt`.
