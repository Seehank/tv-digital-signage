# TV Digital Signage

WordPress plugin for a fixed 1920×1080 digital-signage layout without a paid page builder.

Author: Seehank

## Current scope

Version `0.2.0` lets an administrator choose which WordPress pages the plugin manages as signage. On a fresh installation, only `TEST_ADMINA` (page ID `82477`) is selected by default. It provides:

- background image plus editable text layers;
- fixed 1920×1080 coordinates, typography, color and alignment;
- visual drag positioning in the WordPress page editor;
- WordPress nonce, capability and input-sanitization checks;
- isolation from existing live Brizy signage until a page is explicitly migrated.

## Installation for testing

1. Upload the repository contents to `wp-content/plugins/merk-signage-studio/`.
2. Activate **Merk Signage Studio** in WordPress.
3. In **Pages → Signage Settings**, choose the pages managed by the plugin. Initially this is only `TEST_ADMINA`.
4. Open a selected page and use its **Signage Studio** box.
5. Add its background URL and text layers, then save and preview.

## Security and verification

The selected-page list controls where the plugin provides its signage editor and full-screen output. It does not grant access: a user still needs WordPress permission to edit each page. Only users with `manage_options` can change backgrounds, layers or typography; other permitted editors can change the text of existing layers. This is enforced on the server, even when the submitted form is modified.

Regression tests in `tests/security.php` use WordPress API doubles; they do not replace integration testing on WordPress with the actual roles and Brizy enabled. Deploy only the plugin PHP file and `assets/`, not the tests.

## License

GPL-2.0-or-later.
