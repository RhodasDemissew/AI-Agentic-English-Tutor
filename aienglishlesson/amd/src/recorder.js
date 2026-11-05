/* GPL-3.0-or-later. Recording is local until the learner submits the form. */
define(['core/str'], function(Str) {
    return {init: function() {
        document.querySelectorAll('.aiel-recorder').forEach(function(root) {
            var start = root.querySelector('[data-action="record"]');
            var stop = root.querySelector('[data-action="stop"]');
            var status = root.querySelector('[data-region="status"]');
            var preview = root.querySelector('[data-region="preview"]');
            var download = root.querySelector('[data-region="download"]');
            var input = root.querySelector('input[type="file"]');
            var recorder, stream, timer, objecturl, pending = false;
            var form = root.closest('form');
            var submit = form.querySelector('[type="submit"]');
            var keys = ['micunavailable', 'micdenied', 'recordingactive', 'recordingready', 'recordingtoolarge'];
            Str.get_strings(keys.map(function(key) { return {key: key, component: 'mod_aienglishlesson'}; }))
            .then(function(messages) {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
                    start.disabled = true; status.textContent = messages[0]; return;
                }
                var release = function() {
                    if (timer) { clearTimeout(timer); }
                    if (stream) { stream.getTracks().forEach(function(track) { track.stop(); }); }
                    submit.disabled = false;
                };
                start.addEventListener('click', async function() {
                    start.disabled = true; submit.disabled = true; pending = true;
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({audio: true, video: false});
                        if (!pending) { release(); return; }
                        var type = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4']
                            .find(function(mime) { return MediaRecorder.isTypeSupported(mime); });
                        recorder = new MediaRecorder(stream, type ? {mimeType: type, audioBitsPerSecond: 64000} : {});
                        var chunks = [], bytes = 0;
                        recorder.ondataavailable = function(event) {
                            bytes += event.data.size; chunks.push(event.data);
                            if (bytes > 8 * 1024 * 1024 && recorder.state === 'recording') { recorder.stop(); }
                        };
                        recorder.onstop = function() {
                            release(); start.disabled = false; stop.disabled = true;
                            if (bytes > 8 * 1024 * 1024) { status.textContent = messages[4]; return; }
                            var mime = recorder.mimeType || 'audio/webm';
                            var extension = mime.indexOf('mp4') >= 0 ? 'm4a' : (mime.indexOf('ogg') >= 0 ? 'ogg' : 'webm');
                            var blob = new Blob(chunks, {type: mime});
                            if (objecturl) { URL.revokeObjectURL(objecturl); }
                            objecturl = URL.createObjectURL(blob);
                            preview.src = objecturl; preview.hidden = false;
                            download.href = objecturl; download.download = 'speaking.' + extension; download.hidden = false;
                            try {
                                var transfer = new DataTransfer();
                                transfer.items.add(new File([blob], 'speaking.' + extension, {type: mime}));
                                input.files = transfer.files;
                            } catch (error) { /* The download/upload fallback stays available. */ }
                            status.textContent = messages[3];
                        };
                        recorder.onerror = function() { release(); start.disabled = false; stop.disabled = true; status.textContent = messages[1]; };
                        recorder.start(1000); stop.disabled = false; status.textContent = messages[2];
                        timer = setTimeout(function() { if (recorder.state === 'recording') { recorder.stop(); } }, 120000);
                    } catch (error) { release(); start.disabled = false; status.textContent = messages[1]; }
                });
                stop.addEventListener('click', function() { if (recorder && recorder.state === 'recording') { recorder.stop(); } });
                window.addEventListener('pagehide', function() { pending = false; release(); if (objecturl) { URL.revokeObjectURL(objecturl); } });
            }).catch(function() { start.disabled = true; });
        });
    }};
});
