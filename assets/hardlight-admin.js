(function(window, document) {
    if (!window.wp || !wp.media) {
        return;
    }

    var frame = null;

    function generateSecret(length) {
        var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var output = '';
        if (window.crypto && window.crypto.getRandomValues) {
            var buffer = new Uint32Array(length);
            window.crypto.getRandomValues(buffer);
            for (var i = 0; i < buffer.length; i += 1) {
                output += chars[buffer[i] % chars.length];
            }
            return output;
        }
        for (var j = 0; j < length; j += 1) {
            output += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return output;
    }

    function setupSettingsActions() {
        var secretButton = document.getElementById('hardlight-generate-secret');
        var secretInput = document.querySelector('input[name=\"hardlight_shared_secret\"]');
        if (secretButton && secretInput) {
            secretButton.addEventListener('click', function() {
                secretInput.value = generateSecret(48);
                secretInput.type = 'text';
                secretInput.focus();
                secretInput.select();
            });
        }

        var copyButton = document.getElementById('hardlight-copy-webhook');
        if (copyButton) {
            copyButton.addEventListener('click', function() {
                var webhook = copyButton.getAttribute('data-webhook');
                if (!webhook) {
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(webhook);
                    return;
                }
                var temp = document.createElement('input');
                temp.value = webhook;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', setupSettingsActions);

    document.addEventListener('click', function(event) {
        if (!event.target.classList.contains('hardlight-copy-shortcode')) {
            return;
        }
        var shortcode = event.target.getAttribute('data-shortcode');
        if (!shortcode) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode);
            return;
        }
        var temp = document.createElement('input');
        temp.value = shortcode;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
    });

    window.HardLightMediaBridge = {
        request: function(options) {
            options = options || {};
            if (!frame) {
                frame = wp.media({
                    title: options.title || 'Select Media',
                    multiple: false,
                    library: options.type ? { type: options.type } : undefined,
                    button: { text: options.buttonText || 'Use media' }
                });
            }

            return new Promise(function(resolve, reject) {
                frame.off('select');
                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    var model = selection.first();
                    if (!model) {
                        resolve(null);
                        return;
                    }
                    var data = model.toJSON();
                    resolve({
                        id: data.id,
                        url: data.url,
                        alt: data.alt,
                        caption: data.caption
                    });
                });
                frame.on('close', function() {
                    frame.off('select');
                });
                frame.on('escape', function() {
                    reject(new Error('Media selection canceled'));
                });
                frame.open();
            });
        }
    };
})(window, document);
