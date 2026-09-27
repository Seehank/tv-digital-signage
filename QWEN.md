# Qwen collaboration playbook

Reusable working agreement for collaboration with the local Qwen model. Keep this document project-neutral: no customer data, credentials, application-specific field names, host names, or implementation details belong here.

## Responsibilities

- **Qwen writes implementation code.**
- **Codex prepares a precise task, supplies the relevant current source, reviews Qwen's output, integrates reviewed changes, verifies them, and manages repository hygiene.**
- Do not quietly replace Qwen with Codex-authored implementation because a change looks small. If Qwen's result is incomplete or unsafe, send Qwen a narrower correction task.
- The user decides whether and when changes are deployed to an external system.

## Runtime baseline

- Qwen is configured for a **16k context window**. Design every request to fit that limit comfortably.
- Use `num_ctx: 16384` when calling the local API, so the request matches the user's configured limit.
- Disable reasoning/thinking for deterministic coding tasks unless the user asks for exploratory reasoning.
- Prefer a short request with a focused source excerpt. Long whole-project prompts make the result slower and less consistent.

## How to write a good Qwen task

1. Start with an observed fact, not an assumption.

   Example: “The UI renders, but the browser has no script tag for the editor JavaScript.”

2. Include only the current method, component, or file that must change.
3. Explicitly name what must remain unchanged.
4. Describe the smallest permitted change and its expected behavior.
5. Require a machine-readable response with exact markers and no commentary.

   ```text
   Return only the complete replacement method:
   <<<METHOD:method_name>>>
   ...
   <<<END METHOD>>>

   Include the full function declaration. No PHP tags, no prose.
   ```

6. State invariants explicitly: authorization, validation, backward compatibility, output escaping, tests, performance limits, or public API stability as applicable.

## Granularity rules

- Prefer **one method or one small file per request**.
- If the task spans several methods, do them in dependency order and validate after each integration.
- For a complex feature, ask Qwen first for a concise implementation plan or data model, then request the individual implementation blocks.
- Never ask Qwen for a broad rewrite when a targeted revision will do.

## What works well

- “Copy this exact source unchanged except for these three edits.”
- Giving Qwen a small real source excerpt rather than describing the code from memory.
- Clear input/output contracts and fixed names that must be preserved.
- Exact response markers, full declarations, and a ban on explanatory text.
- Feeding back a specific review finding with the offending source excerpt and asking for only that correction.

## What does not work well

- Broad prompts such as “implement the whole feature coherently.” They encourage invented architecture, unrelated data models, and inconsistent naming.
- Large multi-method responses. They often mix conventions between the generated pieces.
- Vague requests such as “make it secure” without the actual threat model or current source.
- Accepting a response before checking that it is complete code rather than a fragment, prose, or a partial replacement.
- Asking Qwen to infer hidden project conventions that were not included in the prompt.

## Review and integration gate

Before modifying the project, check that Qwen's response:

- contains the requested marker block and complete declaration;
- preserves the required identifiers and public behavior;
- does not add unrelated configuration, dependencies, network calls, telemetry, or secrets;
- validates untrusted input and respects authorization boundaries;
- escapes or safely renders dynamic output;
- has no dangling comments, opening tags, placeholder code, or prose embedded in source.

After integration, run the checks appropriate to the stack. At minimum:

- language syntax/lint checks;
- formatter or whitespace/diff validation;
- relevant tests or a focused reproduction;
- a browser or UI verification when the change affects user interaction.

## Debugging loop

1. Reproduce the issue without changing data.
2. Gather one authoritative observation: console error, missing asset, failing test, HTTP response, or visible state.
3. Isolate the smallest responsible source area.
4. Give Qwen the evidence and that exact source area.
5. Review and verify the narrow patch.
6. Only then broaden the investigation if the symptom remains.

## Persistence and hygiene

- Update this document only when a lesson is reusable across projects or sessions.
- Keep secrets, personal data, access tokens, passwords, client names, URLs, and production identifiers out of it.
- Record principles and prompt patterns, not transient implementation details.
- When a project has its own conventions, keep them in that project's README or dedicated project documentation rather than here.
