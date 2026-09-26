# TV Digital Signage

WordPress plugin for a fixed 1920×1080 digital-signage layout without a paid page builder.

Author: Seehank

## Current scope

Version `0.1.0` is deliberately limited to a single test page. It provides:

- background image plus editable text layers;
- fixed 1920×1080 coordinates, typography, color and alignment;
- visual drag positioning in the WordPress page editor;
- WordPress nonce, capability and input-sanitization checks;
- isolation from existing live Brizy signage until a page is explicitly migrated.

## Installation for testing

1. Upload the repository contents to `wp-content/plugins/merk-signage-studio/`.
2. Activate **Merk Signage Studio** in WordPress.
3. Open the designated test page and use the **Signage Studio (test)** box.
4. Add its background URL and text layers, then save and preview.

The page ID is intentionally hard-coded during the proof-of-concept phase. It will become configurable when the venue and operator selector is introduced.

## License

GPL-2.0-or-later.
