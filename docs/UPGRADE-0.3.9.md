# mod_aienglishlesson 0.3.9 Upgrade Notes

## Summary of Changes
1. **Simplified LLM Generation Schema**:
   - The AI model only generates the essential educational content: `title`, `focus`, and 10 concise sections (1-2 sentences each, 2 vocabulary cards, practice prompts).
   - `maxOutputTokens` reduced to `2048`.
2. **Server-Side PHP Normalization**:
   - PHP automatically structures, orders, and fills all required fields (`example`, `practice`, `answer`, `feedback`, `hint`, `simpler`, `extraexample`, `audioscript`, `origin`, `cards`) to pass `lesson::validate()`.
   - Even if the AI returns partial sections or omits a section kind, PHP gracefully fills in clean defaults.
3. **Model Configuration**:
   - Both `gemini-3.5-flash-lite` and `gemini-3.5-flash` are fully supported.
   - Older deprecated endpoints like `gemini-2.5-flash` (which return HTTP 404) are migrated away during upgrade.

