# TV Digital Signage

WordPress plugin for a fixed 1920×1080 digital-signage layout without a paid page builder.

Author: Seehank

## Current scope

Version `0.3.1` lets an administrator choose which WordPress pages the plugin manages as signage. On a fresh installation, only `TEST_ADMINA` (page ID `82477`) is selected by default. It provides:

- background image selected from the WordPress Media Library;
- fixed 1920×1080 coordinates, typography, color and alignment;
- visual drag positioning in the WordPress page editor;
- layers with either static text or a live value from an existing scalar ACF field on that page;
- WordPress nonce, capability and input-sanitization checks;
- isolation from existing live Brizy signage until a page is explicitly migrated.

## Installation for testing

1. Upload the repository contents to `wp-content/plugins/merk-signage-studio/`.
2. Activate **Merk Signage Studio** in WordPress.
3. In **Pages → Signage Settings**, choose the pages managed by the plugin. Initially this is only `TEST_ADMINA`.
4. Open a selected page and use its **Signage Studio** box.
5. Select a background from the Media Library. Add text layers, bind a layer to an existing ACF field where needed, then save and preview.

## Security and verification

The selected-page list controls where the plugin provides its signage editor and full-screen output. It does not grant access: a user still needs WordPress permission to edit each page. Only users with `manage_options` can change backgrounds, layer bindings, layout or typography; other permitted editors can change only existing static-layer text. ACF-bound values remain read-only in the studio and are read directly from the existing ACF field when the signage is rendered. This is enforced on the server, even when the submitted form is modified.

Regression tests in `tests/security.php` use WordPress API doubles; they do not replace integration testing on WordPress with the actual roles and Brizy enabled. Deploy only the plugin PHP file and `assets/`, not the tests.

## 0.3.1 fix

The admin editor now depends on WordPress's registered `media-editor` script handle. This loads the Media Library picker and the visual-layer editor on the page-edit screen.

## License

GPL-2.0-or-later.
