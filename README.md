# HardLight WordPress Plugin

HardLight is a WordPress plugin that lets you deploy AI-generated HTML, CSS, and JavaScript into WordPress with isolation (Shadow DOM or iframe) and a secure webhook workflow.

## Features
- Custom post type for HardLight components.
- Secure REST deploy webhook with HMAC-SHA256 validation.
- Render modes: Shadow DOM (default), Slot (Light DOM projection), or iframe.
- Admin settings page for shared secret and webhook URL.
- Gutenberg block for inserting components.
- Admin list tooling for quick shortcode copy.
- WooCommerce slot shortcode + Store API helper.

## Installation
1. Upload the plugin folder to `wp-content/plugins/hardlight`.
2. Activate **HardLight** in the WordPress admin.
3. Set the shared secret under **Settings → HardLight**.

## Deployment API
Endpoint:
```
POST /wp-json/hardlight/v1/deploy
```

Headers:
```
X-HardLight-Signature: sha256=<hmac>
```

Payload:
```json
{
  "title": "Pricing Table",
  "html": "<div>...</div>",
  "css": ".price { color: red }",
  "js": "init();",
  "mode": "shadow",
  "update_strategy": "overwrite"
}
```

The signature is `HMAC_SHA256(body, shared_secret)`.

## Rendering
- Shortcode: `[hardlight id="123"]` or `[hardlight slug="pricing-table"]`.
- Gutenberg: insert the **HardLight Component** block and set ID/slug.
- WooCommerce slot helper: `[hardlight_woocommerce type="checkout"]` or `[hardlight_woocommerce type="product_page" id="123"]`.
- Slot wrapper helper: `[hardlight_slot]...[/hardlight_slot]` to project legacy markup into a slot with rehydration.

## REST Fetch
Fetch by ID:
```
GET /wp-json/hardlight/v1/component/123
```

Fetch by slug:
```
GET /wp-json/hardlight/v1/component?slug=pricing-table
```

## Security Controls
- JavaScript stored in meta is restricted to administrators.
- Webhook JS payloads can be disabled in settings.
- Payloads are size-limited and configurable in settings.

## Store API Helper (Frontend)
When WooCommerce is active, the frontend script exposes `window.HardLightStoreApi` for Store API usage:\n\n```js\nHardLightStoreApi.getProducts({ per_page: 6 });\nHardLightStoreApi.getCart();\nHardLightStoreApi.addToCart(123, 1);\n```

## License
MIT
