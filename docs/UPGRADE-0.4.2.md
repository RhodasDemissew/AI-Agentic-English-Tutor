# 0.4.2 generation-state fix

This update fixes a job completion check that could leave generation marked `running` after a valid provider response when Moodle returns an integer database field as a string. No database migration is needed. Existing jobs and lesson versions are preserved.

On a test Moodle site, install `dist/mod_aienglishlesson-0.4.2.zip` over the existing plugin, complete the Moodle upgrade, and purge caches. A previously stuck `running` job becomes eligible for **Retry or resume generation** after 20 minutes from its recorded start. Click once and keep the request open. The retry should save one draft.

The portable job test now simulates string-valued database fields. Live Hostinger integration remains to be verified on the user's site.
