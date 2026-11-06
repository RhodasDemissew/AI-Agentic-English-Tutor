# 0.3.4: Thinking control, reduced output allowance, and staged tiny JSON test

Hosted tests on 0.3.3 showed that stages 1 (text-only) and 2 (PDF + simple question) passed, while stage 3 (full structured lesson request) failed with `HTTP 503: The server handling the request is unavailable or overloaded. Elapsed time: 22.4 seconds`.

### Key Changes in 0.3.4:
1. **Thinking latency elimination (`thinkingConfig`):**
   Gemini 3.x and 2.5 models include built-in reasoning that runs automatically unless configured. In unconstrained complex prompts, the model can spend 15–20 seconds purely generating internal chain-of-thought tokens, triggering Google's backend gateway timeout (~20–25s) and returning HTTP 503.
   - For Gemini 3.x (`gemini-3.5-flash-lite`, etc.): sets `thinkingConfig: {thinkingLevel: 'minimal'}`.
   - For Gemini 2.5: sets `thinkingConfig: {thinkingBudget: 0}`.
   - For Gemini 1.5: omits `thinkingConfig` automatically.

2. **Reduced generation footprint (`maxOutputTokens`):**
   - Reduced `maxOutputTokens` from `16000` to `8192`. Reserving 16,000 output tokens on a Flash-Lite model on shared quotas can trigger severe server-side queue rejection / load shedding under peak traffic. 8,192 tokens provides ample capacity for the 10-section lesson JSON while cutting the server resource reservation in half.

3. **Scoped prompt section count:**
   - Prompt updated from `10-20 sections` to `10-12 sections` (covering all 10 required pedagogical kinds once), satisfying local validation (`lesson::validate` requires 10 to 20 sections) while preventing the model from generating 20 bloated sections.

4. **Staged Diagnostic Stage 4:**
   - Adds **4. Test PDF with a tiny JSON response** to the diagnostics page (`maxOutputTokens: 1024`, `responseMimeType: 'application/json'`). If stage 2 passes and stage 3 fails, administrators can click stage 4 to confirm whether PDF + JSON mode works when output tokens are small.

### Upgrade Instructions:
1. In Moodle, install `dist/mod_aienglishlesson-0.3.4.zip` over the existing plugin through Moodle's plugin installer (**Site administration > Plugins > Install plugins**).
2. Complete the database upgrade (do not uninstall; existing settings, API key, and activities are preserved).
3. Open the activity's **Test generation in stages** page.
4. If stage 2 passes, click **4. Test PDF with a tiny JSON response** first to verify that JSON output returns quickly.
5. Then click **3. Test full structured lesson request**. If successful, return to the activity and click **Generate lesson**.

