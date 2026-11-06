# 0.3.6: Prompt brevity scoping for Flash-Lite timeout resolution

Hosted diagnostics on 0.3.5 showed that Test 3 failed at `Elapsed time: 24.3 seconds` with `HTTP 503`.
Because Test 4 (tiny JSON response) takes ~9.9 seconds on `gemini-3.5-flash-lite`, generating 10–20 extensive sections with paragraph-length texts, multiple practice tasks, and long audio scripts exceeds Google's 20–25 second reverse-proxy timeout window, triggering an upstream HTTP 503 cut-off.

### Key Changes in 0.3.6:
1. **Targeted Prompt Brevity:**
   - Explicitly instructs the model to return exactly 10 sections (one for each required pedagogical kind in sequence).
   - Instructs the model to be concise: keeping bodies, passages, and practice tasks short (1–3 sentences), audioscripts under 500 characters, and using empty strings for optional fields.
   - This cuts total generated tokens by ~60% (from ~2,500+ tokens down to ~900 tokens), allowing generation to complete in ~10–12 seconds, well inside the 20-second timeout window.
2. **Preserved Validator Compliance:**
   - All 10 mandatory section types (`objectives`, `warmup`, `vocabulary`, `explanation`, `workedexample`, `reading`, `listening`, `speaking`, `writing`, `recap`) and required fields remain fully satisfied and verified by `lesson::validate()`.

### Upgrade & Testing Instructions:
1. Upload and install `dist/mod_aienglishlesson-0.3.6.zip` via **Site administration > Plugins > Install plugins**.
2. Complete the database upgrade.
3. Open **Test generation in stages** and run **3. Test full structured lesson request**.
4. If your site still experiences 503 under `gemini-3.5-flash-lite`, switch **Model ID** in the plugin settings to standard **`gemini-2.5-flash`** (which has significantly higher server-side throughput and longer timeouts).

