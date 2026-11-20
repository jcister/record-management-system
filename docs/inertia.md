# Inertia Architecture Guide

> This document explains how Laravel, Inertia, and Vue collaborate inside this repository so you can trace every request end-to-end, customize layouts, and debug issues quickly.

## High-Level Flow

1. **HTTP request hits Laravel routing** (`routes/web.php`, `routes/auth.php`, `routes/settings.php`).
2. Matching controller action returns `Inertia::render(<PageName>, <props>)`.
3. `App\Http\Middleware\HandleInertiaRequests` injects shared props (auth user, Ziggy config, quotes, sidebar state, etc.).
4. The root Blade view [`resources/views/app.blade.php`](resources/views/app.blade.php) loads Vite assets and mounts Inertia.
5. [`resources/js/app.ts`](resources/js/app.ts) bootstraps the Vue 3 app, registers Ziggy, and resolves a Vue page component from `resources/js/pages`.
6. That Vue page optionally wraps itself in a layout from `resources/js/layouts`, renders components, and uses Inertia helpers (`useForm`, `Link`, `router`) to make subsequent requests without a full page refresh.

Everything after the first request happens via XHR/Fetch; controllers still return `Inertia::render(...)`, but Inertia swaps the component tree client-side, preserving scroll/forms by default.

## Backend Pieces

### Routing
- `routes/web.php` defines the authenticated area (Dashboard, Rack, Files, Bookmarks, Trash, etc.).
- `routes/auth.php` wires guest-only routes (login, register, password reset) plus logout.
- `routes/settings.php` scopes settings pages.

Each route either points at a controller (`DashboardController@index`) or, occasionally, can use `Inertia::render` directly for simple pages.

### Controllers & Props
Controllers live under `app/Http/Controllers`. They typically:

```php
return Inertia::render('Dashboard', [
    'media' => $media,
]);
```

The string (e.g., `'Dashboard'`) maps to `resources/js/pages/Dashboard.vue`. Props become the Vue component's `defineProps` data.

### Shared Props Middleware
`App\Http\Middleware\HandleInertiaRequests` extends Inertia's base middleware. It:
- Sets the root Blade view to `app`.
- Merges shared props such as `auth.user`, a random Inspiring quote, Ziggy route definitions, and the sidebar cookie state.

Anything placed in the `share()` method is globally available in every Vue page via `$page.props`.

### Appearance Middleware
`App\Http\Middleware\HandleAppearance` (used in `app/Http/Kernel.php`) inspects cookies/query params to decide whether the `<html>` tag should render in dark mode before Vue mounts. It pushes an `appearance` prop consumed by layouts and `useAppearance.ts`.

## Frontend Bootstrapping

### Root Blade View
`resources/views/app.blade.php` contains:
- Inline script to apply dark mode instantly.
- Ziggy route definitions via `@routes`.
- Vite asset tags for `resources/js/app.ts` and the current page component (ensures HMR and SSR compatibility).
- `<title inertia>` and `@inertiaHead` for dynamic metadata.

### `resources/js/app.ts`
- Calls `createInertiaApp` with a dynamic resolver to auto-import every file under `resources/js/pages/**/*.vue`.
- Installs the Inertia plugin and ZiggyVue (`route()` helper in templates/components).
- Registers a progress indicator (gray bar) and initializes the appearance theme on page load.

### `resources/js/ssr.ts`
- Mirrors the browser bootstrapping but uses `createSSRApp` + `@vue/server-renderer`.
- Configures Ziggy manually for SSR so `route()` works during server rendering.
- Used when you run `composer run dev:ssr` / `npm run build:ssr` to pre-render pages on the server.

## Pages, Layouts, and Components

- Pages live in `resources/js/pages`. The naming convention matches `Inertia::render('Folder/PageName')` (e.g., `settings/Appearance.vue`).
- Layouts (`resources/js/layouts`) wrap pages. Example: `resources/js/pages/auth/Login.vue` uses `<AuthBase>` from `resources/js/layouts/AuthLayout.vue`.
- Shared UI primitives live under `resources/js/components` (buttons, inputs, modals, etc.).

### Layout Usage Pattern
```vue
<script setup>
import AuthBase from '@/layouts/AuthLayout.vue';
</script>

<template>
  <AuthBase title="Open your RACK Account">
    <!-- page content -->
  </AuthBase>
</template>
```
Layouts can forward props (title, description) and manage slots for consistent framing.

### Navigation and Forms
- Use `<Link :href="route('dashboard')" method="get">` for internal links.
- Use `const form = useForm({...})` and `form.post(route('login'))` for submissions. Inertia automatically handles validation errors and preserves form state.
- Access server props via `const page = usePage();` then `page.props.auth.user`.

## Ziggy Routes
- Backend routes are exposed to Vue via Ziggy.
- `@routes` in the Blade template exports every named route.
- In Vue, call `route('folders.files', folderId)` or `route('files.update', file.id)` without hardcoding URLs.

## Shared State & Appearance
- `HandleInertiaRequests` shares `sidebarOpen` based on the `sidebar_state` cookie.
- `resources/js/composables/useAppearance.ts` reads `$page.props.appearance` and toggles classes.
- Appearance preferences are stored server-side (see `App\Http\Controllers\Settings\ProfileController` & `HandleAppearance` middleware), so the theme persists across devices.

## SSR & Caching
- SSR entry point is `resources/js/ssr.ts`.
- Blade view uses `<title inertia>` so server-rendered head tags update automatically.
- `php artisan inertia:flush` (if enabled) can reset server-side cached responses when deploying new assets (handled automatically by Inertia's versioning that points to Vite manifest hash).

## Development Workflow

| Goal | Command |
| --- | --- |
| Install deps | `composer install && npm install` |
| Dev (PHP + Vite) | `composer run dev` (spawns `php artisan serve`, queue worker, `npm run dev`) |
| Build assets | `npm run build` |
| SSR build | `npm run build:ssr` + `composer run dev:ssr` |
| Clear caches | `php artisan optimize:clear` |

During dev, you should have:
1. `composer run dev` (spawns Vite dev server with HMR).
2. `php artisan serve` (if not already started by the composer script).
3. Visit `http://127.0.0.1:8000`; Vite serves assets and pushes updates without reloading.

## Typical Request Lifecycle

1. User visits `/dashboard` → `Route::get('dashboard', [DashboardController::class, 'index'])`.
2. Controller fetches data, returns `Inertia::render('Dashboard', ['media' => $media])`.
3. Middleware merges `auth.user`, Ziggy routes, quotes, etc.
4. Blade root prints JSON page payload into the response.
5. Browser initializes Vue (from `app.ts`), hydrates the `Dashboard.vue` component, and renders data.
6. Clicking an Inertia `<Link>` or submitting a form sends an XHR request to Laravel, which returns a new JSON page payload; Vue swaps components without a full reload.

## Debugging Tips

- **Page props inspection**: In devtools, `window.Inertia.page.props` mirrors `$page.props`.
- **Shared props**: Modify `share()` in `HandleInertiaRequests` to add global data (remember to keep it serializable).
- **Route helper**: If `route()` is undefined, ensure ZiggyVue is registered in `app.ts` and that `@routes` appears in the Blade template.
- **Asset version mismatch**: run `php artisan optimize:clear` or restart Vite if you see `Inertia: Asset version mismatch`.
- **SSR issues**: keep SSR-compatible code (avoid direct DOM access) or guard it with `if (typeof window !== 'undefined')`.

## Where to Explore Next

- `app/Http/Controllers/*` – see how each feature module (files, folders, bookmarks) passes props.
- `resources/js/pages/**/*.vue` – inspect page-level logic/layout composition.
- `resources/js/components/ui/*` – collection of Shadcn-inspired components.
- `resources/js/composables` – utilities such as `useAppearance` and `useUploadTracker` for reactive state shared across pages.

With this map, you can confidently trace any feature from route → controller → shared props → Vue page and understand how data flows through Inertia in this project.
