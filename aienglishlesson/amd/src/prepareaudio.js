/* GPL-3.0-or-later. Each teacher-authorized request prepares one missing audio file. */
define([], function() {
    return {init: async function(config) {
        var status = document.getElementById('aiel-audio-progress');
        var bar = document.getElementById('aiel-audio-bar');
        var back = document.getElementById('aiel-audio-back');
        var finish = function(message, warning) {
            status.textContent = message;
            if (bar) {
                bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                bar.classList.add(warning ? 'bg-warning' : 'bg-success');
                bar.style.width = '100%';
            }
            if (warning) { status.className = 'alert alert-warning mt-3'; }
            if (back) { back.hidden = false; }
        };
        try {
            for (var step = 0; step < 20; step++) {
                var body = new URLSearchParams({id: config.id, version: config.version, sesskey: config.sesskey, step: 1});
                var response = await fetch(config.url, {method: 'POST', credentials: 'same-origin', body: body});
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                var result = await response.json();
                if (result.done) {
                    finish(config.complete, false);
                    // Go straight back to the draft once audio is ready.
                    window.location.assign(config.lessonurl);
                    return;
                }
                if (result.status !== 'pending') { throw new Error(result.status); }
            }
            throw new Error('limit');
        } catch (error) {
            // Do not display raw server HTML, provider response bodies, or credentials.
            finish(config.failed, true);
        }
    }};
});
