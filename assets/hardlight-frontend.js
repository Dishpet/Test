(function() {
    var config = window.HardLightConfig || {};

    function rehydrate(target) {
        if (!window.gform && !window.gformInitPriceFields) {
            // Continue to other rehydration checks.
        }
        if (window.gform && window.gform.initCondLogic) {
            window.gform.initCondLogic();
        }
        if (window.gformInitPriceFields) {
            window.gformInitPriceFields();
        }
        if (window.gformInitDatepicker) {
            window.gformInitDatepicker();
        }
        if (window.gformInitChosenFields) {
            window.gformInitChosenFields();
        }

        if (window.wpcf7 && window.wpcf7.init) {
            window.wpcf7.init(target || document);
        }

        if (window.wc && window.wc.blocksCheckout && window.wc.blocksCheckout.registerCheckoutFilters) {
            window.wc.blocksCheckout.registerCheckoutFilters('hardlight', {});
        }

        if (window.jQuery) {
            var $body = window.jQuery(document.body);
            $body.trigger('updated_checkout');
            $body.trigger('wc_fragment_refresh');
            $body.trigger('wc_fragment_load');
            $body.trigger('wc_fragment_loaded');
            $body.trigger('wc_update_cart');
            $body.trigger('init_checkout');
            $body.trigger('wc-enhanced-select-init');
            $body.trigger('wc-credit-card-form-init');
        }

        if (window.wc_cart_fragments && window.wc_cart_fragments.refresh) {
            window.wc_cart_fragments.refresh();
        }
    }

    function observeSlots() {
        var hosts = document.querySelectorAll('[data-hardlight-slot="1"]');
        if (!hosts.length) {
            return;
        }
        hosts.forEach(function(host) {
            var observer = new MutationObserver(function() {
                rehydrate(host);
            });
            observer.observe(host, { childList: true, subtree: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeSlots);
    } else {
        observeSlots();
    }

    window.HardLightStoreApi = {
        enabled: !!config.wcEnabled,
        baseUrl: config.wcStoreApiBase || '',
        nonce: config.nonce || '',
        request: function(path, options) {
            if (!this.enabled || !this.baseUrl) {
                return Promise.reject(new Error('WooCommerce Store API not available'));
            }
            options = options || {};
            options.credentials = 'same-origin';
            options.headers = options.headers || {};
            if (this.nonce) {
                options.headers['X-WP-Nonce'] = this.nonce;
            }
            return fetch(this.baseUrl + path.replace(/^\\//, ''), options).then(function(response) {
                if (!response.ok) {
                    throw new Error('Store API request failed');
                }
                return response.json();
            });
        },
        getProducts: function(query) {
            var qs = query ? ('?' + new URLSearchParams(query).toString()) : '';
            return this.request('products' + qs, { method: 'GET' });
        },
        getCart: function() {
            return this.request('cart', { method: 'GET' });
        },
        addToCart: function(id, quantity) {
            return this.request('cart/add-item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, quantity: quantity || 1 })
            });
        }
    };
})();
