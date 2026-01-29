# Project HardLight – PRD & Technical Plan

## One-Sentence Description (AI-Friendly)
HardLight is a WordPress plugin that allows **AI-generated HTML/CSS/JS** to be deployed safely into WordPress using **Shadow DOM isolation** and **secure webhooks** — without copy-paste.

---

## 1. The "Last Mile" Problem

### Problem
AI can generate code.
WordPress breaks it.

Reasons:
- Global CSS overrides
- jQuery conflicts
- Manual copy-paste workflow

### HardLight Fix
- Encapsulate AI code
- Automate deployment
- Preserve visual fidelity

---

## 2. Core Architecture

### Primary Isolation: Shadow DOM
```js
element.attachShadow({ mode: 'open' });
```

### Fallback Isolation: `<iframe>`
Used when:
- Conflicting JS libraries
- Full-document isolation needed

---

## 3. Declarative Shadow DOM (SEO + Performance)

Server renders:
```html
<div id="hardlight-host-42">
  <template shadowrootmode="open">
    <style>/* AI CSS */</style>
    <div>AI HTML</div>
  </template>
</div>
```

Benefits:
- No FOUC
- Crawlable
- No JS required for first paint

---

## 4. Smart Style Inheritance

### Allowed Through Shadow Boundary
- CSS variables
- Fonts
- Brand colors

### Techniques
- `var(--wp--preset--color-primary)`
- Computed style mirroring for classic themes

---

## 5. No-Paste Workflow (Webhook API)

### Endpoint
```
POST /wp-json/hardlight/v1/deploy
```

### Payload
```json
{
  "title": "Pricing Table",
  "html": "<div>...</div>",
  "css": ".price { color: red }",
  "js": "init();",
  "update_strategy": "overwrite"
}
```

### Security
- HMAC-SHA256
- `X-HardLight-Signature` header
- Shared secret

---

## 6. Data Model

Stored as Custom Post Type:
```
hardlight_component
```

Post Meta:
- `_hl_html`
- `_hl_css`
- `_hl_js`
- `_hl_mode` (shadow | iframe)

---

## 7. AI Canvas (Editor UI)

### Features
- Monaco Editor
- Live preview
- Responsive resizer
- Version history

### Mental Model
> Treat each component as **immutable infrastructure**
> Revisions are safer than edits

---

## 8. Builder Integrations

| Builder | Strategy |
|------|--------|
| Gutenberg | Dynamic block |
| Elementor | Native widget |
| Divi | React module |
| Beaver Builder | FLBuilderModule |

Each integration:
- Attaches Shadow DOM on render
- Re-hydrates on layout changes

---

## 9. Security Rules (AI-Safe)

- Only admins can inject JS
- HTML sanitized via `wp_kses`
- Payload size limits
- CSP nonce support

---

## 10. When to Use Which Mode (AI Decision Table)

| Situation | Mode |
|--------|------|
| Visual component | Shadow DOM |
| Conflicting JS | Iframe |
| Legacy plugin | Slotting (Phase 2+) |
| Commerce checkout | Slotting |
| Simple cart | API |

---

## Core Principle for AI Coders

> **Isolation first. Compatibility second.**
>  
> Shadow DOM is the default.  
> Slotting is the escape hatch.  
> Iframes are the nuclear option.

---
