# HardLight Phase 2 – Technical Strategy

## Purpose (AI-Oriented Summary)
This document defines **Phase 2** of the HardLight WordPress plugin.
Its goal is to evolve HardLight from an isolated AI container into a **deeply integrated WordPress ecosystem tool**.

Primary challenges:
- Bridging **modern encapsulated UIs** (React / Web Components / Shadow DOM)
- With **legacy WordPress plugins** that rely on the global DOM, jQuery, and CSS inheritance

Key solutions:
- Media Library bridge (`wp.media`)
- Light DOM Projection via `<slot>`
- Hybrid WooCommerce integration (API + legacy rendering)

---

## 1. Media Picker Bridge (`HardLightMediaBridge`)

### Problem
AI code editors (Monaco / CodeMirror) run in isolated contexts and cannot directly access the WordPress Media Library.

### Solution
Create a **JavaScript bridge** that wraps the native `wp.media` Backbone application and exposes a clean Promise-based API to the AI editor.

### Core Rules
- MUST use `wp_enqueue_media()`
- MUST implement a **singleton** media frame
- MUST return **raw JSON data**, never HTML

### Example API (Conceptual)
```js
const image = await HardLightMediaBridge.request({
  type: 'image',
  return: 'url'
});
```

### Data Extraction Flow
1. `frame.state().get('selection')`
2. `.first().toJSON()`
3. Normalize → `{ id, url, alt, caption }`

---

## 2. Plugin Paradox & DOM Architecture

### The Conflict
| Legacy Plugins | Modern Components |
|---------------|------------------|
| Global DOM | Shadow DOM |
| jQuery selectors | Encapsulation |
| Global CSS | Scoped CSS |

Result: **Pure Shadow DOM breaks WordPress plugins**.

### The Solution: Light DOM Projection
Use `<slot>` to project **Light DOM children** into a Shadow DOM wrapper.

### Implementation Pattern
```html
<hardlight-slot>
  <!-- legacy plugin HTML lives here (Light DOM) -->
</hardlight-slot>
```

```html
<template shadowrootmode="open">
  <slot></slot>
</template>
```

### Benefits
- Global CSS works
- jQuery selectors work
- Event bubbling works
- Legacy plugins remain functional

---

## 3. Script Rehydration (Critical)

### Problem
AJAX-based plugins (Gravity Forms, CF7) replace DOM nodes without re-triggering init scripts.

### Solution
`ScriptRehydrator` module:
- Uses `MutationObserver`
- Detects DOM replacement
- Manually re-triggers plugin init hooks

### Gravity Forms Example
```js
if (window.gform) gform.initCondLogic();
if (window.gformInitPriceFields) gformInitPriceFields();
```

---

## 4. WooCommerce Hybrid Architecture

### Two Integration Modes

#### A. Slotted Shortcodes (Legacy)
- `[product_page]`
- `[woocommerce_checkout]`
- Maximum compatibility
- Slower, server-rendered

#### B. Store API (Modern)
- `wc/store/v1/cart`
- JSON-based
- Fast, reactive UI
- **Breaks extensions**

### Final Strategy (Hybrid)
| Feature | Strategy |
|------|---------|
| Product grids | Store API |
| Mini cart | Store API |
| Single product | Slotted shortcode |
| Checkout | Slotted shortcode |

---

## 5. Phase 2 Requirements (AI-Readable)

### Media Bridge
- Singleton `wp.media` frame
- Context-aware MIME filtering
- JSON-only return values

### Slot Engine
- Light DOM injection
- `<slot>` projection
- MutationObserver rehydration

### WooCommerce
- API-first cart
- Fallback to slotting for complex products
- Automatic nonce handling

---

## Mental Model for AI Agents

> **Rule of Thumb**  
> - Use APIs when data is simple  
> - Use slotting when plugins are involved  
> - Never assume Shadow DOM compatibility in WordPress  

---
