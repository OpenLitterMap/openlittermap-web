# OpenLitterMap — Translations

## Overview

The frontend uses vue-i18n v9 with a single flat JSON file for English translations. Every user-visible string in Vue templates goes through `$t()`.

**Status:** Complete. ~430 translation keys covering all Vue components. `npm run build` clean.

---

## Architecture

### Single flat file: `resources/js/langs/en.json`

Keys are full English strings, values are the same string (identity mapping):

```json
{
    "Upload": "Upload",
    "Add Tags": "Add Tags",
    "Show name on leaderboards": "Show name on leaderboards",
    "Are you sure you want to delete your account?": "Are you sure you want to delete your account?"
}
```

Plus a nested `litter` object for tag category/object display names.

### i18n configuration: `resources/js/i18n.js`

- Loads `en.json` as the default locale
- `fallbackLocale: 'en'` — missing keys show the English key itself
- `missingWarn: false` — no console spam for missing keys
- Registered globally — `$t()` available in all templates

### Adding a new language

1. Copy `en.json` → `fr.json` (or any locale code)
2. Translate just the values, keep keys as English
3. Register in `i18n.js` messages object
4. Add locale switcher UI (not yet built)

---

## Conventions

### In templates (`<template>`)
```html
<!-- Static text -->
<button>{{ $t('Upload') }}</button>

<!-- Interpolation -->
<p>{{ $t('You have {count} photos', { count }) }}</p>

<!-- Attributes -->
<input :placeholder="$t('Email or username')" />
```

### In `<script setup>`
```javascript
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const message = t('Upload complete');
```

### What to translate
- Button text, labels, headings, descriptions
- Error messages, toast/notification messages
- Placeholder text, empty state messages, modal text
- `title` and `aria-label` attributes

### What NOT to translate
- `class`, `id`, `name` attributes
- `console.log` messages
- API endpoint strings
- Vue component names

---

## Key Files

| File | Purpose |
|------|---------|
| `resources/js/langs/en.json` | Single translation file (~430 keys) |
| `resources/js/i18n.js` | vue-i18n configuration |
| `resources/js/app.js` | Registers i18n plugin globally |
