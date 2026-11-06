# 0.3.5: Unified content request, 4096-token allocation, and transient retry backoff

Hosted tests on 0.3.4 confirmed that:
- **Test 4 passed in 9.9 seconds**, establishing that PDF input together with `responseMimeType: 'application/json'` works properly and returns valid structured output.
- **Test 3 failed in 2.5 seconds** with `HTTP 503: The server handling the request is unavailable or overloaded`.

Because Test 4 took 9.9 seconds to ingest the PDF and respond, the 2.5-second failure on Test 3 indicates an immediate gateway/admission rejection before generation began.

### Key Changes in 0.3.5:
1. **Unified Request Shape (Matching Test 4):**
   - In 0.3.4, Test 3 placed lesson instructions inside `systemInstruction` while Test 4 placed prompt text directly in `contents` alongside `inlineData`.
   - In 0.3.5, Test 3 moves lesson instructions directly into `contents[0]['parts']` alongside the PDF `inlineData`. This aligns Test 3 to the exact request shape already validated by Test 4.
2. **Right-Sized Token Reservation (`maxOutputTokens: 4096`):**
   - Reduced `maxOutputTokens` from `8192` to `4096`. A valid 10-section lesson JSON requires ~1,600 tokens. Setting `maxOutputTokens` to `4096` provides ample headroom while staying well under Google's shared/free-tier burst admission limits.
3. **Automatic Exponential Backoff Retry on Transient Errors:**
   - In `gemini::request()`, added an automatic retry loop for transient `HTTP 503` and `HTTP 429` statuses (retrying up to 3 attempts with 2s and 4s sleep). If Google's cluster sheds the first request under momentary peak load, the client pauses and retries automatically instead of failing instantly.

### Upgrade Instructions:
1. Install `dist/mod_aienglishlesson-0.3.5.zip` via **Site administration > Plugins > Install plugins**.
2. Complete the database upgrade.
3. Open the activity's **Test generation in stages** page.
4. Run **3. Test full structured lesson request**.

