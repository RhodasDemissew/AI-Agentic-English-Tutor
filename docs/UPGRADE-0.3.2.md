# 0.3.2 staged generation diagnostics

Upgrade with mod_aienglishlesson-0.3.2.zip through Moodle's plugin installer. Do not uninstall. Settings and lesson data are preserved.

As site administrator, open the activity containing your one-page PDF and click **Test generation in stages** near Review learner responses. Run **1. Test text-only generation** first. Stop at the first failure and send the displayed result and elapsed time. Only if test 1 passes, run test 2, then test 3.

1. Text-only: a small greeting request, no PDF and no lesson schema.
2. PDF/simple question: sends the same activity PDF with a small prompt, no schema.
3. Full lesson: invokes the normal generation request and validates its lesson response, but discards it without saving a draft or creating audio.

Each button makes one provider request against the same saved key/model and daily cap. It can consume provider quota; eligibility requirements still apply. No raw responses or keys are displayed. These tests narrow the failing request stage; they do not by themselves prove the underlying cause of a 503.

Verified locally with mocked provider responses: same model/endpoint; no PDF/schema in test 1; PDF without schema in test 2; production generation contract in test 3; one request per test; exact safe error propagation; no drafts/jobs created. PHP syntax passed. The live model failure remains to be diagnosed on the hosted site.
