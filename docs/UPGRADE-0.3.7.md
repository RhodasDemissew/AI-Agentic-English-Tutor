# 0.3.7: Resilient retry backoff and model guidance

Test 3 in diagnostics passed in 45 seconds, confirming that the generation payload and validator accept the response. However, live job generation on `gemini-3.5-flash-lite` experienced intermittent HTTP 503 capacity drops.

### Key Changes in 0.3.7:
1. **Extended Backoff Retries:**
   - In `gemini::request()`, increased retries to 4 attempts with progressive backoff (3s, 6s, 9s). This absorbs temporary capacity spikes on Google's endpoint without aborting the generation job.
2. **Model Recommendation:**
   - For high reliability and zero 503 capacity drops, switch `Model ID` to standard `gemini-2.5-flash` in **Site administration > Plugins > Activity modules > AI English Lesson**. The plugin automatically applies `thinkingConfig: {thinkingBudget: 0}` for `gemini-2.5-flash` to ensure rapid, zero-lag generation.

