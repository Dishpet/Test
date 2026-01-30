<?php
/**
 * Plugin Name: HardLight
 * Description: Deploy AI-generated HTML/CSS/JS into WordPress using Shadow DOM isolation and secure webhooks.
 * Version: 0.1.0
 * Author: HardLight
 */

if (!defined('ABSPATH')) {
    exit;
}

final class HardLight_Plugin {
    const POST_TYPE = 'hardlight_component';
    const META_HTML = '_hl_html';
    const META_CSS = '_hl_css';
    const META_JS = '_hl_js';
    const META_MODE = '_hl_mode';
    const OPTION_SECRET = 'hardlight_shared_secret';
    const SCRIPT_ADMIN = 'hardlight-admin';
    const SCRIPT_FRONTEND = 'hardlight-frontend';
    const SCRIPT_BLOCK = 'hardlight-block';
    const STYLE_FRONTEND = 'hardlight-frontend';
    const NONCE_META = 'hardlight_meta_nonce';
    const OPTION_ALLOW_WEBHOOK_JS = 'hardlight_allow_webhook_js';
    const OPTION_MAX_BODY_BYTES = 'hardlight_max_body_bytes';
    const MAX_BODY_BYTES = 512000;

    public function register() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_meta'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_component_meta'));
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('init', array($this, 'register_blocks'));
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'register_admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_shortcode('hardlight', array($this, 'render_shortcode'));
        add_shortcode('hardlight_woocommerce', array($this, 'render_woocommerce_shortcode'));
        add_shortcode('hardlight_slot', array($this, 'render_slot_shortcode'));
    }

    public function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name' => 'HardLight Components',
                    'singular_name' => 'HardLight Component',
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_rest' => true,
                'supports' => array('title'),
                'menu_icon' => 'dashicons-layout',
            )
        );
    }

    public function register_meta() {
        register_post_meta(
            self::POST_TYPE,
            self::META_HTML,
            array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => array($this, 'sanitize_html'),
                'auth_callback' => '__return_true',
            )
        );
        register_post_meta(
            self::POST_TYPE,
            self::META_CSS,
            array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => array($this, 'sanitize_code'),
                'auth_callback' => '__return_true',
            )
        );
        register_post_meta(
            self::POST_TYPE,
            self::META_JS,
            array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => array($this, 'sanitize_code'),
                'auth_callback' => array($this, 'allow_js_meta'),
            )
        );
        register_post_meta(
            self::POST_TYPE,
            self::META_MODE,
            array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => array($this, 'sanitize_mode'),
                'auth_callback' => '__return_true',
            )
        );
    }

    public function register_routes() {
        register_rest_route(
            'hardlight/v1',
            '/deploy',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_deploy'),
                'permission_callback' => array($this, 'verify_signature'),
            )
        );
        register_rest_route(
            'hardlight/v1',
            '/component/(?P<id>\\d+)',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_component'),
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            'hardlight/v1',
            '/component',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_component_by_slug'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'slug' => array(
                        'type' => 'string',
                        'required' => true,
                    ),
                ),
            )
        );
    }

    public function register_settings() {
        register_setting('hardlight_settings', self::OPTION_SECRET, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_secret'),
            'default' => '',
        ));
        register_setting('hardlight_settings', self::OPTION_ALLOW_WEBHOOK_JS, array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_flag'),
            'default' => false,
        ));
        register_setting('hardlight_settings', self::OPTION_MAX_BODY_BYTES, array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_max_body_bytes'),
            'default' => self::MAX_BODY_BYTES,
        ));

        add_settings_section(
            'hardlight_security',
            'Security',
            '__return_false',
            'hardlight_settings'
        );

        add_settings_field(
            'hardlight_shared_secret',
            'Shared Secret',
            array($this, 'render_secret_field'),
            'hardlight_settings',
            'hardlight_security'
        );
        add_settings_field(
            'hardlight_allow_webhook_js',
            'Allow JS via Webhook',
            array($this, 'render_allow_js_field'),
            'hardlight_settings',
            'hardlight_security'
        );
        add_settings_field(
            'hardlight_max_body_bytes',
            'Max Webhook Payload (bytes)',
            array($this, 'render_max_body_bytes_field'),
            'hardlight_settings',
            'hardlight_security'
        );
    }

    public function register_settings_page() {
        add_options_page(
            'HardLight',
            'HardLight',
            'manage_options',
            'hardlight-settings',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('settings_page_hardlight-settings', 'post.php', 'post-new.php', 'edit.php'), true)) {
            return;
        }
        wp_enqueue_media();
        $screen = get_current_screen();
        if ($screen && $screen->post_type === self::POST_TYPE) {
            wp_enqueue_code_editor(array('type' => 'text/html'));
            wp_enqueue_code_editor(array('type' => 'text/css'));
            wp_enqueue_code_editor(array('type' => 'text/javascript'));
            wp_enqueue_script('wp-theme-plugin-editor');
            wp_enqueue_style('wp-codemirror');
        }
        wp_enqueue_script(
            self::SCRIPT_ADMIN,
            plugins_url('assets/hardlight-admin.js', __FILE__),
            array('jquery', 'media-editor'),
            '0.1.0',
            true
        );
    }

    public function register_frontend_assets() {
        wp_register_script(
            self::SCRIPT_FRONTEND,
            plugins_url('assets/hardlight-frontend.js', __FILE__),
            array(),
            '0.1.0',
            true
        );
        wp_localize_script(
            self::SCRIPT_FRONTEND,
            'HardLightConfig',
            array(
                'restUrl' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'wcStoreApiBase' => esc_url_raw(rest_url('wc/store/v1/')),
                'wcEnabled' => class_exists('WooCommerce'),
            )
        );
        wp_register_style(
            self::STYLE_FRONTEND,
            plugins_url('assets/hardlight-frontend.css', __FILE__),
            array(),
            '0.1.0'
        );
    }

    public function register_blocks() {
        register_block_type('hardlight/component', array(
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'id' => array('type' => 'number', 'default' => 0),
                'slug' => array('type' => 'string', 'default' => ''),
            ),
        ));
    }

    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            self::SCRIPT_BLOCK,
            plugins_url('assets/hardlight-block.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            '0.1.0',
            true
        );
    }

    public function render_block($attributes) {
        $atts = array(
            'id' => isset($attributes['id']) ? absint($attributes['id']) : 0,
            'slug' => isset($attributes['slug']) ? sanitize_text_field($attributes['slug']) : '',
        );
        return $this->render_shortcode($atts);
    }

    public function register_admin_columns($columns) {
        $columns['hardlight_shortcode'] = 'Shortcode';
        $columns['hardlight_mode'] = 'Mode';
        return $columns;
    }

    public function render_admin_columns($column, $post_id) {
        if ($column === 'hardlight_shortcode') {
            $shortcode = sprintf('[hardlight id="%d"]', $post_id);
            echo '<code>' . esc_html($shortcode) . '</code> ';
            echo '<button type="button" class="button button-small hardlight-copy-shortcode" data-shortcode="' . esc_attr($shortcode) . '">Copy</button>';
        }
        if ($column === 'hardlight_mode') {
            $mode = get_post_meta($post_id, self::META_MODE, true);
            echo esc_html($this->sanitize_mode($mode));
        }
    }

    public function get_component($request) {
        $post_id = absint($request['id']);
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('hardlight_not_found', 'Component not found.', array('status' => 404));
        }
        return $this->build_component_response($post_id);
    }

    public function get_component_by_slug($request) {
        $slug = isset($request['slug']) ? sanitize_text_field($request['slug']) : '';
        if (!$slug) {
            return new WP_Error('hardlight_missing_slug', 'Missing component slug.', array('status' => 400));
        }
        $post = get_page_by_path($slug, OBJECT, self::POST_TYPE);
        if (!$post) {
            return new WP_Error('hardlight_not_found', 'Component not found.', array('status' => 404));
        }
        return $this->build_component_response($post->ID);
    }

    private function build_component_response($post_id) {
        $html = get_post_meta($post_id, self::META_HTML, true);
        $css = get_post_meta($post_id, self::META_CSS, true);
        $js = get_post_meta($post_id, self::META_JS, true);
        $mode = get_post_meta($post_id, self::META_MODE, true);
        return rest_ensure_response(
            array(
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'slug' => get_post_field('post_name', $post_id),
                'html' => $html,
                'css' => $css,
                'js' => $js,
                'mode' => $this->sanitize_mode($mode),
            )
        );
    }

    public function render_settings_page() {
        $webhook_url = esc_url(rest_url('hardlight/v1/deploy'));
        echo '<div class="wrap">';
        echo '<h1>HardLight Settings</h1>';
        echo '<p><strong>Webhook URL:</strong> <code id="hardlight-webhook-url">' . $webhook_url . '</code> ';
        echo '<button type="button" class="button" id="hardlight-copy-webhook" data-webhook="' . $webhook_url . '">Copy</button></p>';
        echo '<p class="description">Send signed POST requests to this endpoint to deploy components.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('hardlight_settings');
        do_settings_sections('hardlight_settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function render_secret_field() {
        $value = esc_attr(get_option(self::OPTION_SECRET, ''));
        echo '<input type="password" name="' . esc_attr(self::OPTION_SECRET) . '" value="' . $value . '" class="regular-text" autocomplete="new-password" />';
        echo ' <button type="button" class="button" id="hardlight-generate-secret">Generate</button>';
        echo '<p class="description">Used to validate webhook signatures. Keep this value secret.</p>';
    }

    public function render_allow_js_field() {
        $value = (bool) get_option(self::OPTION_ALLOW_WEBHOOK_JS, false);
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ALLOW_WEBHOOK_JS) . '" value="1" ' . checked($value, true, false) . ' /> Allow JavaScript payloads sent via the webhook.</label>';
    }

    public function render_max_body_bytes_field() {
        $value = (int) get_option(self::OPTION_MAX_BODY_BYTES, self::MAX_BODY_BYTES);
        echo '<input type="number" min="10240" step="1024" name="' . esc_attr(self::OPTION_MAX_BODY_BYTES) . '" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">Limits the maximum size of webhook payloads in bytes.</p>';
    }

    public function sanitize_html($value) {
        return wp_kses_post($value);
    }

    public function sanitize_code($value) {
        if (!is_string($value)) {
            return '';
        }
        return wp_unslash($value);
    }

    public function sanitize_secret($value) {
        return is_string($value) ? trim($value) : '';
    }

    public function sanitize_flag($value) {
        return (bool) $value;
    }

    public function sanitize_max_body_bytes($value) {
        $value = absint($value);
        if ($value < 10240) {
            return self::MAX_BODY_BYTES;
        }
        return $value;
    }

    public function sanitize_mode($value) {
        $allowed = array('shadow', 'iframe', 'slot');
        $value = is_string($value) ? strtolower($value) : 'shadow';
        return in_array($value, $allowed, true) ? $value : 'shadow';
    }

    public function allow_js_meta() {
        return current_user_can('manage_options');
    }

    public function register_meta_boxes() {
        add_meta_box(
            'hardlight_component_meta',
            'HardLight Component',
            array($this, 'render_component_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_component_meta_box($post) {
        wp_nonce_field('hardlight_save_component', self::NONCE_META);
        $html = get_post_meta($post->ID, self::META_HTML, true);
        $css = get_post_meta($post->ID, self::META_CSS, true);
        $js = get_post_meta($post->ID, self::META_JS, true);
        $mode = $this->sanitize_mode(get_post_meta($post->ID, self::META_MODE, true));

        echo '<p><label for="hardlight_mode"><strong>Mode</strong></label></p>';
        echo '<select id="hardlight_mode" name="hardlight_mode">';
        foreach (array('shadow' => 'Shadow DOM', 'slot' => 'Slot (Light DOM)', 'iframe' => 'Iframe') as $value => $label) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($value),
                selected($mode, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        echo '<p><label for="hardlight_html"><strong>HTML</strong></label></p>';
        echo '<textarea id="hardlight_html" name="hardlight_html" rows="8" class="widefat code">' . esc_textarea($html) . '</textarea>';

        echo '<p><label for="hardlight_css"><strong>CSS</strong></label></p>';
        echo '<textarea id="hardlight_css" name="hardlight_css" rows="6" class="widefat code">' . esc_textarea($css) . '</textarea>';

        if ($this->allow_js_meta()) {
            echo '<p><label for="hardlight_js"><strong>JavaScript</strong></label></p>';
            echo '<textarea id="hardlight_js" name="hardlight_js" rows="6" class="widefat code">' . esc_textarea($js) . '</textarea>';
        } else {
            echo '<p><em>You do not have permission to edit JavaScript.</em></p>';
        }
    }

    public function save_component_meta($post_id) {
        if (!isset($_POST[self::NONCE_META]) || !wp_verify_nonce($_POST[self::NONCE_META], 'hardlight_save_component')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['hardlight_html'])) {
            update_post_meta($post_id, self::META_HTML, $this->sanitize_html(wp_unslash($_POST['hardlight_html'])));
        }
        if (isset($_POST['hardlight_css'])) {
            update_post_meta($post_id, self::META_CSS, $this->sanitize_code($_POST['hardlight_css']));
        }
        if (isset($_POST['hardlight_mode'])) {
            update_post_meta($post_id, self::META_MODE, $this->sanitize_mode($_POST['hardlight_mode']));
        }
        if ($this->allow_js_meta() && isset($_POST['hardlight_js'])) {
            update_post_meta($post_id, self::META_JS, $this->sanitize_code($_POST['hardlight_js']));
        }
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'slug' => '',
            ),
            $atts,
            'hardlight'
        );

        $post_id = absint($atts['id']);
        if (!$post_id && $atts['slug']) {
            $post = get_page_by_path($atts['slug'], OBJECT, self::POST_TYPE);
            if ($post) {
                $post_id = $post->ID;
            }
        }

        if (!$post_id) {
            return '';
        }

        $html = get_post_meta($post_id, self::META_HTML, true);
        $css = get_post_meta($post_id, self::META_CSS, true);
        $js = get_post_meta($post_id, self::META_JS, true);
        $mode = get_post_meta($post_id, self::META_MODE, true);
        $mode = $this->sanitize_mode($mode);
        $host_id = 'hardlight-host-' . $post_id;

        if ($mode === 'iframe') {
            $srcdoc = $this->build_iframe_srcdoc($html, $css, $js);
            wp_enqueue_style(self::STYLE_FRONTEND);
            return sprintf(
                '<iframe class="hardlight-frame" title="HardLight Component %1$d" sandbox="allow-scripts" srcdoc="%2$s"></iframe>',
                esc_attr($post_id),
                esc_attr($srcdoc)
            );
        }

        $light_dom = $mode === 'slot' ? $html : '';
        $template = $this->build_shadow_template($html, $css, $mode === 'slot');
        $script = $this->build_shadow_bootstrap($host_id, $js);
        $slot_attr = $mode === 'slot' ? ' data-hardlight-slot="1"' : '';
        wp_enqueue_style(self::STYLE_FRONTEND);
        if ($mode === 'slot') {
            wp_enqueue_script(self::SCRIPT_FRONTEND);
        }

        return sprintf(
            '<div id="%1$s" class="hardlight-host"%4$s>%2$s%5$s</div>%3$s',
            esc_attr($host_id),
            $template,
            $script,
            $slot_attr,
            $light_dom
        );
    }

    public function render_woocommerce_shortcode($atts) {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        $atts = shortcode_atts(
            array(
                'type' => 'checkout',
                'id' => 0,
            ),
            $atts,
            'hardlight_woocommerce'
        );

        $type = sanitize_text_field($atts['type']);
        $shortcode = '';
        switch ($type) {
            case 'product_page':
                $product_id = absint($atts['id']);
                if ($product_id) {
                    $shortcode = '[product_page id="' . $product_id . '"]';
                }
                break;
            case 'cart':
                $shortcode = '[woocommerce_cart]';
                break;
            case 'checkout':
                $shortcode = '[woocommerce_checkout]';
                break;
            case 'my_account':
                $shortcode = '[woocommerce_my_account]';
                break;
            default:
                $shortcode = '';
        }

        if (!$shortcode) {
            return '';
        }

        $content = do_shortcode($shortcode);
        return $this->render_slot_wrapper($content, 'hardlight-woo-' . $type);
    }

    public function render_slot_shortcode($atts, $content = '') {
        $atts = shortcode_atts(
            array(
                'id' => 'hardlight-slot-' . wp_rand(1000, 9999),
            ),
            $atts,
            'hardlight_slot'
        );
        $content = do_shortcode($content);
        return $this->render_slot_wrapper($content, sanitize_text_field($atts['id']));
    }

    private function render_slot_wrapper($html, $host_id) {
        $template = $this->build_shadow_template('', '', true);
        $script = $this->build_shadow_bootstrap($host_id, '');
        wp_enqueue_style(self::STYLE_FRONTEND);
        wp_enqueue_script(self::SCRIPT_FRONTEND);

        return sprintf(
            '<div id="%1$s" class="hardlight-host" data-hardlight-slot="1">%2$s%3$s</div>%4$s',
            esc_attr($host_id),
            $template,
            $html,
            $script
        );
    }

    private function build_iframe_srcdoc($html, $css, $js) {
        $document = '<!doctype html><html><head><style>' . $css . '</style></head><body>';
        $document .= $html;
        if ($js) {
            $document .= '<script>' . $js . '</script>';
        }
        $document .= '</body></html>';
        return $document;
    }

    private function build_shadow_template($html, $css, $slot_only) {
        $content = $slot_only ? '<slot></slot>' : $html;
        return sprintf(
            '<template shadowrootmode="open"><style>%1$s</style>%2$s</template>',
            $css,
            $content
        );
    }

    private function build_shadow_bootstrap($host_id, $js) {
        $boot = "(function(){var host=document.getElementById('" . esc_js($host_id) . "');if(!host){return;}if(!host.shadowRoot){var tpl=host.querySelector('template[shadowrootmode]');if(tpl&&host.attachShadow){host.attachShadow({mode:'open'}).appendChild(tpl.content.cloneNode(true));tpl.remove();}}";
        if ($js) {
            $boot .= "var run=function(){try{" . $js . "}catch(e){console.error('HardLight error',e);}};if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);}else{run();}";
        }
        $boot .= '})();';
        return '<script>' . $boot . '</script>';
    }

    public function verify_signature($request) {
        $secret = $this->get_shared_secret();
        if (!$secret) {
            return new WP_Error('hardlight_missing_secret', 'HardLight shared secret is not configured.', array('status' => 403));
        }

        $signature = $request->get_header('X-HardLight-Signature');
        if (!$signature) {
            return new WP_Error('hardlight_missing_signature', 'Missing HardLight signature.', array('status' => 403));
        }

        $body = $request->get_body();
        if (strlen($body) > $this->get_max_body_bytes()) {
            return new WP_Error('hardlight_payload_too_large', 'Payload exceeds size limit.', array('status' => 413));
        }
        $expected = hash_hmac('sha256', $body, $secret);
        $signature = $this->normalize_signature($signature);
        if (!hash_equals($expected, $signature)) {
            return new WP_Error('hardlight_invalid_signature', 'Invalid HardLight signature.', array('status' => 403));
        }

        return true;
    }

    private function normalize_signature($signature) {
        $signature = trim($signature);
        if (stripos($signature, 'sha256=') === 0) {
            return substr($signature, 7);
        }
        return $signature;
    }

    private function get_shared_secret() {
        if (defined('HARDLIGHT_SHARED_SECRET')) {
            return HARDLIGHT_SHARED_SECRET;
        }
        $secret = get_option(self::OPTION_SECRET);
        return is_string($secret) ? $secret : '';
    }

    private function allow_webhook_js() {
        return (bool) get_option(self::OPTION_ALLOW_WEBHOOK_JS, false);
    }

    private function get_max_body_bytes() {
        return (int) get_option(self::OPTION_MAX_BODY_BYTES, self::MAX_BODY_BYTES);
    }

    public function handle_deploy($request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new WP_Error('hardlight_invalid_payload', 'Invalid payload.', array('status' => 400));
        }

        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        if (!$title) {
            return new WP_Error('hardlight_missing_title', 'Missing component title.', array('status' => 400));
        }

        $component_id = isset($params['component_id']) ? absint($params['component_id']) : 0;
        $strategy = isset($params['update_strategy']) ? sanitize_text_field($params['update_strategy']) : 'overwrite';
        $mode = isset($params['mode']) ? $this->sanitize_mode($params['mode']) : 'shadow';

        $post_args = array(
            'post_type' => self::POST_TYPE,
            'post_title' => $title,
            'post_status' => 'publish',
        );

        $existing_id = 0;
        if ($component_id) {
            $existing_id = $component_id;
        } elseif ($strategy === 'overwrite') {
            $existing = get_page_by_title($title, OBJECT, self::POST_TYPE);
            if ($existing) {
                $existing_id = $existing->ID;
            }
        }

        if ($existing_id) {
            $post_args['ID'] = $existing_id;
            $post_id = wp_update_post($post_args, true);
        } else {
            $post_id = wp_insert_post($post_args, true);
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $html = isset($params['html']) ? $this->sanitize_html($params['html']) : '';
        $css = isset($params['css']) ? $this->sanitize_code($params['css']) : '';
        $js = isset($params['js']) ? $this->sanitize_code($params['js']) : '';
        $js_allowed = $this->allow_webhook_js();
        if (!$js_allowed) {
            $js = '';
        }

        update_post_meta($post_id, self::META_HTML, $html);
        update_post_meta($post_id, self::META_CSS, $css);
        if ($js !== '') {
            update_post_meta($post_id, self::META_JS, $js);
        }
        update_post_meta($post_id, self::META_MODE, $mode);

        return rest_ensure_response(
            array(
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'mode' => $mode,
                'shortcode' => sprintf('[hardlight id="%d"]', $post_id),
                'js_allowed' => $js_allowed,
            )
        );
    }
}

$hardlight_plugin = new HardLight_Plugin();
$hardlight_plugin->register();
