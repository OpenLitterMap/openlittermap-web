# Vite 8 / Rolldown Upgrade Plan

**Status:** Deferred. The app runs on **Vite 6** (reverted 2026-06-14, v5.12.6). This document records *why* the first attempt failed, what the real blocker is, and the checklist for a safe future upgrade — so the next attempt starts here instead of re-discovering it.

## TL;DR

- A `update packages` bump (commit `df704060`, landed in v5.12.4) jumped **Vite 6 → 8**, `@vitejs/plugin-vue` **5 → 6**, and `laravel-vite-plugin` **1 → 3**. It broke the upload screen and the admin queue and was reverted in **v5.12.6**.
- The blocker is **NOT** Vite plugin-API compatibility — that works fine. It is **CommonJS/UMD default-import interop** in a handful of legacy dependencies, caused by Vite 8 replacing esbuild's dependency pre-bundler with **Rolldown**.
- Upgrading is a **bounded, schedulable effort** (mark ~4 packages for interop, or shim their imports, then regression-test) — *not* a multi-quarter wait. Do it on a dedicated branch via the official `rolldown-vite`-on-Vite-7 de-risk path.

## What broke (evidence)

| Symptom | Where | Cause |
|---|---|---|
| `Uncaught TypeError: vueFilePond is not a function` | `Upload.vue:155` | `vue-filepond` (CJS) default import resolved to a namespace object, not the callable factory |
| `Cannot read properties of null (reading 'component')` | `/admin/queue` | Same class of interop break in another CJS/UMD import (likely `laravel-echo`/`pusher-js` — confirm during upgrade) |
| `glify.longitudeFirst is not a function` then map pan crash | `/global` | `leaflet.glify` (UMD) — already hand-shimmed in `glifyHelpers.js` during the bump (this was the warning sign the rest of the tree wasn't audited) |

`vite.config.js` was **never** changed. The break came entirely from the dependency versions.

## Why "full plugin compatibility" doesn't save us

The Vite 8 announcement's "Most existing Vite plugins work out of the box" / "Rolldown supports the same plugin API as Rollup and Vite" is true — and it **applies to us**: our two Vite plugins (`@vitejs/plugin-vue` v6, `laravel-vite-plugin` v3) are Vite-8-native (Laravel officially supports it).

That claim is about the **plugin API**. It says nothing about how Rolldown's dep-optimizer synthesizes the `default` export when it pre-bundles **CommonJS/UMD npm packages**. When a CJS module doesn't set `__esModule` and has no `default` property, Rolldown binds `import x from 'pkg'` to the module namespace instead of the callable — which is exactly our failure mode. This is a documented Vite 8 issue, not unique to us.

## At-risk dependencies (the bounded list)

Not "many plugins" — really ~3–4 legacy CJS/UMD libraries:

| Dependency | Format | Status |
|---|---|---|
| `vue-filepond` + `filepond` + 5 `filepond-plugin-*` | CJS, no proper ESM/`default` | **Broke** (upload). Maintenance-light — candidate for `needsInterop` long-term, or replacement. |
| `leaflet.glify` | UMD namespace default | **Broke**, shimmed in `glifyHelpers.js` (`glifyModule.glify ?? glifyModule.default ?? glifyModule`) |
| `laravel-echo`, `pusher-js` | UMD, historically interop-finicky | Suspected `/admin/queue` break — verify |
| `moment`, `leaflet` | CJS / UMD | Usually fine; smoke-test anyway |

Everything else (`vue`, `pinia`, `vue-router`, `vue-i18n`, `axios`, `chart.js`, `@headlessui/*`, …) is ESM and not at risk.

## Fix options (all available today)

1. **`optimizeDeps.needsInterop: ['vue-filepond', 'leaflet.glify', ...]`** — explicitly mark the packages that need CJS interop. Targeted and durable. *(preferred)*
2. **`legacy.inconsistentCjsInterop: true`** — one-line flag restoring Rollup's old permissive behavior. Fast, but **deprecated/temporary** — a migration crutch that will be removed. Do not rest here.
3. **Import-site shims** — the `glifyModule.glify ?? glifyModule.default ?? glifyModule` pattern already used for glify. Correct, but manual per library.

## Recommended upgrade path (when scheduled)

1. **Dedicated branch** — never bundle this into an unrelated branch (the first attempt rode in on a cleanup branch and blocked it).
2. **`rolldown-vite` on Vite 7 first** — the official de-risk step. Surfaces Rolldown-specific issues in isolation before the major bump.
3. **Fix interop** — add `needsInterop` (and/or import shims) for the at-risk list above.
4. **Regression-test the full surface** (below).
5. **Bump to Vite 8** + `@vitejs/plugin-vue` v6 + `laravel-vite-plugin` v3, re-run the same regression pass.

## Regression smoke-test surface (page-by-page)

- **Upload** (`Upload.vue` — FilePond init + image preview/resize/EXIF plugins)
- **Admin queue** (`/admin/queue`)
- **`/global` map** (leaflet + `leaflet.glify` point rendering + pan/zoom, popups)
- **Charts** (`chart.js` / `vue-chartjs`)
- **Realtime** (`laravel-echo` + `pusher-js` / Reverb)
- **Tagging** (search index, submit)
- General: production `npm run build` + a dev (`npm run dev`) pass.

## "Are we ready?" checklist

Upgrade is *easy* when, on `rolldown-vite`/Vite 7, the full smoke test passes with **at most** a small `optimizeDeps.needsInterop` list and **no** `legacy.inconsistentCjsInterop` flag.

## Timeline / signals to watch

- **Now:** stay on Vite 6. Not blocked by *time* — blocked by an unscheduled, bounded effort.
- Rolldown is actively closing these gaps (recent `rolldown-vite` added "accept UMD with only default export"). Auto-interop should cover most deps within ~1–2 Vite 8 minors (~Q3–Q4 2026).
- **The true gate is the slowest dependency.** `vue-filepond`/`filepond` and `leaflet.glify` are maintenance-light and may never modernize on Vite's schedule — so "wait for zero-config easy" partly never arrives. The cheaper lever is deciding whether to keep them on `needsInterop` or replace them.

## Sources

- [Vite 8 announcement](https://vite.dev/blog/announcing-vite8)
- [Migration from v7](https://vite.dev/guide/migration) (`legacy.inconsistentCjsInterop`)
- [Dep Optimization Options](https://vite.dev/config/dep-optimization-options) (`optimizeDeps.needsInterop`)
- [rolldown-vite changelog](https://github.com/vitejs/rolldown-vite/blob/rolldown-vite/packages/vite/CHANGELOG.md)
- ["I Reproduced Every CommonJS Failure Introduced in Vite 8"](https://thinkingthroughcode.medium.com/i-reproduced-every-commonjs-failure-introduced-in-vite-8-these-are-the-silent-killers-in-343b5232c833)
