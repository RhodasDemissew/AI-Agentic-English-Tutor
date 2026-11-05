# 0.3.1 diagnostic update

Install `dist/mod_aienglishlesson-0.3.1.zip` over the existing plugin through Moodle's plugin installer and complete the upgrade. Do not uninstall. Existing settings, keys and lesson data remain.

This release distinguishes numerical cURL transport failures from HTTP errors, including DNS, connection, timeout, TLS certificate, HTTP 429 and HTTP 5xx failures. It never displays or stores raw response bodies, keys, headers or raw cURL error strings. Direct-mode retry wording is corrected.

After updating, return to the failed lesson and click Generate lesson once. Send the NEW diagnostic message. Earlier recorded generic failures cannot be reconstructed. Do not change billing, key or PDF solely to investigate the old generic error.

The diagnostic reports the server response/transport result, not proof of its underlying cause. A proxy may also return HTTP errors. Live hosted-site failure remains undiagnosed until a new attempt reports its code.

Validation: PHP syntax; simulated error-code/retry classifications. No external AI requests required.
