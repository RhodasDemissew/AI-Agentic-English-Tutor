# 0.3.3: JSON-mode lesson request

Hosted tests showed text-only and PDF/simple-question generation passing, while the full lesson request failed with HTTP 503. This release removes the provider-side nested responseSchema constraint and describes the lesson JSON shape in the prompt instead. responseMimeType remains application/json. The same model, PDF transport, endpoint and output-token allowance are retained. Local lesson validation still rejects incomplete, oversized or unexpected structures before saving anything.

This is a targeted compatibility change, not proof that schema compilation caused the 503. JSON mode without schema constraints may return structurally invalid content; local validation reports invalidlesson rather than silently accepting it. Official API reference: https://aistudio.google.com/api-reference/generate-content (responseMimeType).

Upgrade with mod_aienglishlesson-0.3.3.zip without uninstalling. Keep the existing settings/key and one-page PDF. Open Test generation in stages and run test 3 once. If it passes, return to the activity and Generate lesson to save a draft. If it fails, report the new code and elapsed time. Diagnostics never save lessons.

Validation: PHP syntax, mocked request checks confirming no provider responseSchema, valid fixture acceptance, invalid JSON structure rejection, safe error propagation and no draft/job side effects. No real provider calls were made locally. Hosted live success is not yet established.
