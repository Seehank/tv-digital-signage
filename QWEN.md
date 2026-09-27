# Qwen collaboration playbook

This file preserves the working agreement and practical lessons for future sessions on this project. It contains no credentials, API tokens, customer data, or production-only configuration.

## Roles and ownership

- **Qwen writes implementation code.** This is the user's explicit preference.
- **Codex scopes tasks, gives Qwen precise source context, reviews output, integrates it mechanically, runs checks, builds the ZIP, and maintains GitHub.**
- Do not silently replace Qwen with Codex-authored implementation just because a task looks small. If Qwen's output is unsafe or incomplete, explain the defect internally and send Qwen a narrower correction request.
- Do not deploy to the WordPress site unless the user explicitly asks. Keep the current built `merk-signage-studio.zip` in this repository root for manual FTP upload.

## Local Qwen setup used here

- Model: `hf.co/ISTA-DASLab/Qwen3.8-27B-GSQ-RCO-GGUF:IQ3_S` through local Ollama.
- The user chose a 16k context. Match it with `num_ctx: 16384` and disable thinking for deterministic code tasks.
- Long answers are slower and less reliable. Prefer a narrowly bounded method-level request over a full-plugin rewrite.

## Prompt shape that works best

1. State the observed fact, not a guess. Example: “The PHP metabox renders, but the browser has no `admin.js` tag and `MerkSignageStudio` is undefined.”
2. Give Qwen the exact current method or file as `SOURCE`.
3. Say what must remain unchanged, then list the smallest allowed edits.
4. Require a machine-readable response, for example:

   ```text
   Return only the complete replacement method in:
   <<<METHOD:enqueue_admin_assets>>>
   ...
   <<<END METHOD>>>
   No PHP tags and no explanation.
   ```

5. Require the full function declaration, not only a method body.
6. Review the response before touching project files. Validate it with PHP lint, JavaScript syntax check, and `git diff --check` after integration.

## Patterns proven during this project

### Good: targeted source-preserving revision

Qwen produced a correct WordPress fix after receiving the exact `enqueue_admin_assets()` source and this constrained request:

> Keep all checks and localization unchanged. Keep `wp_enqueue_media()`. Replace the unregistered `wp-media` dependency with WordPress core's `media-editor` handle.

This solved the real production symptom: WordPress omitted the whole dependent admin script, so neither **Select Background** nor **Add text layer** worked.

### Good: one method at a time for ACF work

For ACF binding, Qwen was reliable when asked to alter one existing method at a time and to preserve the established data model:

- `source`: `static` or `acf`
- `acf_key`: the actual ACF field key
- legacy `text`: remains static-layer text

The prompt must explicitly say that the plugin uses **existing ACF fields only** and must never create, register, or modify ACF field groups.

### Good: exact safety invariants in the prompt

State security rules as non-negotiable constraints:

- use the current WordPress nonce and `edit_post` capability checks;
- only `manage_options` may alter layout, background, or bindings;
- an operator may alter only static text;
- validate ACF keys against the current page's eligible ACF field list;
- output dynamic values with `esc_html` and preview text with `textContent`.

### Needs explicit attention: mixed static and ACF layers

When non-admin operators edit a page containing both layer types, their submitted form must retain sequential layer indexes. ACF layers should be read-only, but must still submit a harmless hidden `text` field so server-side validation can preserve the whole configuration. The server must ignore that field for `source: acf`.

## Patterns that did not work

### Avoid broad redesign prompts

Requests such as “implement coherent media library and ACF binding for the whole plugin” led Qwen to invent unrelated meta keys, HTML structures, and field names. It sometimes returned fragments instead of method definitions.

### Avoid asking for many methods in one response

Large multi-method answers mixed incompatible conventions, for example `acf_field` in the editor versus `acf_key` in the saving code. Split work into small, source-backed revisions.

### Do not accept prose or partial code as a patch

Qwen can accidentally include a dangling PHPDoc opener, opening PHP tags, commentary, or only a function body. Extract only the requested marker block, confirm that it contains the intended declaration and matching braces, then lint before integration.

### Do not let Qwen replace the architecture

Keep these project identifiers unless the user asks for a migration:

- meta key: `_merk_signage_studio`
- page option: `merk_signage_studio_pages`
- static layer field: `text`
- ACF binding field: `acf_key`

## Standard implementation loop

1. Reproduce and observe the problem on the test page without saving data.
2. Inspect the smallest relevant local source area.
3. Give Qwen a source-preserving method/file task with exact markers.
4. Review for authorization, validation, output escaping, backward compatibility, and data-model consistency.
5. Integrate only the reviewed Qwen block.
6. Run PHP lint, `node --check` where relevant, and `git diff --check`.
7. Update the plugin version for a browser-cache-visible asset change, rebuild the root ZIP, sync the mirror folder, commit, and push GitHub.
8. Ask the user to upload via FTP, then validate the installed behavior in WordPress.

## Current project-specific checks

- The studio box rendering alone does **not** prove the JavaScript loaded. Check that an `admin.js` script tag exists and `window.MerkSignageStudio` is defined.
- Before a background is configured, the public signage page intentionally keeps rendering its original Brizy content. This is a safety fallback, not a plugin failure.
- When a page has an active WordPress post lock, do not take it over without the user's clear instruction.
- Never place WordPress credentials, FTP credentials, license keys, or private site data in this file or in GitHub.
