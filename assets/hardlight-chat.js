(function(document, apiFetch) {
    function createNode(tag, className) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        return node;
    }

    function renderChat(root) {
        var container = createNode('div', 'hardlight-chat');
        var form = createNode('form', 'hardlight-chat__form');
        var provider = createNode('select', 'hardlight-chat__provider');
        ['gemini', 'anthropic', 'openai'].forEach(function(name) {
            var option = createNode('option');
            option.value = name;
            option.textContent = name;
            provider.appendChild(option);
        });

        var title = createNode('input', 'hardlight-chat__title');
        title.type = 'text';
        title.placeholder = 'Component title (for save)';

        var model = createNode('input', 'hardlight-chat__model');
        model.type = 'text';
        model.placeholder = 'Model (optional)';

        var message = createNode('textarea', 'hardlight-chat__message');
        message.rows = 5;
        message.placeholder = 'Ask something...';

        var submit = createNode('button', 'button button-primary');
        submit.type = 'submit';
        submit.textContent = 'Send';

        var save = createNode('button', 'button');
        save.type = 'button';
        save.textContent = 'Save as Component';

        var generate = createNode('button', 'button button-primary');
        generate.type = 'button';
        generate.textContent = 'Generate Component';

        var createPage = createNode('button', 'button');
        createPage.type = 'button';
        createPage.textContent = 'Create Page';

        var output = createNode('div', 'hardlight-chat__output');
        var raw = createNode('textarea', 'hardlight-chat__raw');
        raw.rows = 8;
        raw.placeholder = 'Paste AI-generated HTML/CSS/JS here (JSON or HTML).';

        form.appendChild(provider);
        form.appendChild(title);
        form.appendChild(model);
        form.appendChild(message);
        form.appendChild(submit);
        form.appendChild(save);
        form.appendChild(generate);
        form.appendChild(createPage);

        container.appendChild(form);
        container.appendChild(output);
        container.appendChild(raw);
        root.appendChild(container);

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            output.textContent = 'Sending...';
            apiFetch({
                path: '/hardlight/v1/chat',
                method: 'POST',
                headers: { 'X-WP-Nonce': (window.HardLightChatConfig && HardLightChatConfig.nonce) || '' },
                data: {
                    provider: provider.value,
                    model: model.value,
                    message: message.value
                }
            }).then(function(response) {
                output.textContent = response.message || 'No response.';
            }).catch(function(error) {
                output.textContent = (error && error.message) ? error.message : 'Request failed.';
            });
        });

        save.addEventListener('click', function() {
            output.textContent = 'Saving component...';
            var payload = parsePayload(raw.value || output.textContent);
            if (!payload || !payload.html) {
                output.textContent = 'Provide HTML or JSON with html/css/js before saving.';
                return;
            }
            apiFetch({
                path: '/hardlight/v1/chat/deploy',
                method: 'POST',
                headers: { 'X-WP-Nonce': (window.HardLightChatConfig && HardLightChatConfig.nonce) || '' },
                data: {
                    title: title.value || 'AI Component',
                    html: payload.html,
                    css: payload.css || '',
                    js: payload.js || '',
                    mode: payload.mode || 'shadow'
                }
            }).then(function(response) {
                output.textContent = 'Saved. Shortcode: ' + (response.shortcode || '');
                createPage.setAttribute('data-shortcode', response.shortcode || '');
                createPage.setAttribute('data-component-id', response.id || '');
            }).catch(function(error) {
                output.textContent = (error && error.message) ? error.message : 'Save failed.';
            });
        });

        generate.addEventListener('click', function() {
            output.textContent = 'Generating component...';
            apiFetch({
                path: '/hardlight/v1/chat/generate-component',
                method: 'POST',
                headers: { 'X-WP-Nonce': (window.HardLightChatConfig && HardLightChatConfig.nonce) || '' },
                data: {
                    provider: provider.value,
                    model: model.value,
                    prompt: message.value,
                    title: title.value || 'AI Component'
                }
            }).then(function(response) {
                output.textContent = 'Generated. Shortcode: ' + (response.shortcode || '');
                createPage.setAttribute('data-shortcode', response.shortcode || '');
                createPage.setAttribute('data-component-id', response.id || '');
            }).catch(function(error) {
                output.textContent = (error && error.message) ? error.message : 'Generation failed.';
            });
        });

        createPage.addEventListener('click', function() {
            output.textContent = 'Creating page...';
            var componentId = createPage.getAttribute('data-component-id');
            var shortcode = createPage.getAttribute('data-shortcode');
            if (!componentId && !shortcode) {
                output.textContent = 'Save a component first to create a page.';
                return;
            }
            apiFetch({
                path: '/hardlight/v1/chat/create-page',
                method: 'POST',
                headers: { 'X-WP-Nonce': (window.HardLightChatConfig && HardLightChatConfig.nonce) || '' },
                data: {
                    page_title: title.value || 'AI Page',
                    component_id: componentId,
                    shortcode: shortcode
                }
            }).then(function(response) {
                output.textContent = 'Page created: ' + (response.permalink || '');
            }).catch(function(error) {
                output.textContent = (error && error.message) ? error.message : 'Page creation failed.';
            });
        });
    }

    function parsePayload(text) {
        if (!text) {
            return null;
        }
        try {
            var parsed = JSON.parse(text);
            if (parsed && (parsed.html || parsed.css || parsed.js)) {
                return parsed;
            }
        } catch (err) {
            // fallback below
        }
        return { html: text };
    }

    document.addEventListener('DOMContentLoaded', function() {
        var root = document.getElementById('hardlight-chat-root');
        if (root) {
            renderChat(root);
        }
    });
})(document, window.wp && window.wp.apiFetch);
