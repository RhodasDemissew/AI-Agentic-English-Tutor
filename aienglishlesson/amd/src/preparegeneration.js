/* GPL-3.0-or-later. Each request produces one bounded lesson stage. */
define([], function() {
    return {init: async function(config) {
        var status = document.getElementById('aiel-generation-progress');
        try {
            for (var step = 0; step < 100; step++) {
                var body = new URLSearchParams({id: config.id, job: config.job, sesskey: config.sesskey, step: 1});
                var response = await fetch(config.url, {method: 'POST', credentials: 'same-origin', body: body});
                if (!response.ok) { throw new Error('request'); }
                var result = await response.json();
                if (result.status === 'succeeded' && result.version) {
                    var url = config.audioenabled ? config.audiourl + '?id=' + encodeURIComponent(config.id) +
                        '&version=' + encodeURIComponent(result.version) : config.lessonurl + '&version=' +
                        encodeURIComponent(result.version);
                    window.location.assign(url);
                    return;
                }
                if (result.status === 'failed') { throw new Error('failed'); }
                status.textContent = config.working + ' (' + Math.min(3, result.stage + 1) + '/3)';
                if (result.status === 'running') { await new Promise(function(resolve) { setTimeout(resolve, 5000); }); }
            }
            throw new Error('limit');
        } catch (error) {
            status.textContent = config.failed;
            status.className = 'alert alert-warning';
        }
    }};
});
