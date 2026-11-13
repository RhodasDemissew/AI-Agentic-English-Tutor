/* GPL-3.0-or-later. Each request produces one bounded lesson stage; retries stay hidden behind the progress bar. */
define([], function() {
    var STAGES = 3;
    var NETWORK_RETRIES = 6;

    var wait = function(ms) { return new Promise(function(resolve) { setTimeout(resolve, ms); }); };

    return {init: async function(config) {
        var status = document.getElementById('aiel-generation-progress');
        var bar = document.getElementById('aiel-generation-bar');
        var back = document.getElementById('aiel-generation-back');
        var shown = 0;
        var cap = 0;

        // Each stage is one long request (or a few, when the AI answer is retried), so ease slowly towards,
        // but never reach, the end of the current stage. The bar keeps moving while retries happen.
        var show = function(value) {
            shown = Math.max(shown, Math.min(100, value));
            var percent = Math.floor(shown);
            bar.style.width = Math.max(2, percent) + '%';
            bar.parentNode.setAttribute('aria-valuenow', percent);
            status.textContent = config.working.replace('{percent}', percent);
        };
        var timer = setInterval(function() {
            if (shown < cap) { show(shown + Math.max(0.02, (cap - shown) * 0.006)); }
        }, 400);
        var stageCap = function(done) {
            cap = Math.min(99, ((done + 0.95) / STAGES) * 100);
            show((done / STAGES) * 100);
        };

        // One stage request. Brief network or host-timeout problems are retried quietly: the job keeps its state.
        var step = async function() {
            for (var attempt = 0; ; attempt++) {
                try {
                    var body = new URLSearchParams({id: config.id, job: config.job, sesskey: config.sesskey, step: 1});
                    var response = await fetch(config.url, {method: 'POST', credentials: 'same-origin', body: body});
                    if (response.ok) {
                        var result = await response.json();
                        if (result && result.status) { return result; }
                    }
                } catch (error) {
                    // Fall through to the retry below.
                }
                if (attempt >= NETWORK_RETRIES) { throw new Error('request'); }
                await wait(4000 + attempt * 2000);
            }
        };

        stageCap(0);
        try {
            for (var round = 0; round < 200; round++) {
                var result = await step();
                if (result.status === 'succeeded' && result.version) {
                    clearInterval(timer);
                    cap = 100;
                    show(100);
                    var url = config.audioenabled ? config.audiourl + '?id=' + encodeURIComponent(config.id) +
                        '&version=' + encodeURIComponent(result.version) : config.lessonurl + '&version=' +
                        encodeURIComponent(result.version);
                    window.location.assign(url);
                    return;
                }
                if (result.status === 'failed') { throw new Error('failed'); }
                stageCap(Math.min(STAGES - 1, result.stage));
                if (result.status === 'running') { await wait(5000); }
            }
            throw new Error('limit');
        } catch (error) {
            clearInterval(timer);
            status.textContent = config.failed;
            status.className = 'alert alert-warning mt-3';
            bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            bar.classList.add('bg-warning');
            if (back) { back.hidden = false; }
        }
    }};
});
