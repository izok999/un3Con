---
name: maryui-development
description: "Activate when working with MaryUI components in Blade templates. Covers x-mary-* prefixed components (header, alert, badge, stat, card, input), unprefixed components (x-checkbox, x-main, x-menu*, x-toast, x-icon), local wrappers (mary-alert → x-alert, mary-badge → x-badge, mary-stat → x-stat), Livewire binding rules, Alpine.js escaping patterns, and integration with DaisyUI/TailwindCSS. Skip for backend PHP, database queries, API routes, or vanilla CSS."
license: MIT
metadata:
  author: laravel
---

# MaryUI Development

## Documentation

Use `search-docs` for MaryUI v2 component APIs before writing code:
```
search-docs --queries="checkbox component" --packages="robsontenorio/mary"
```

## Component Prefix — Project-Specific Rules

This project uses a **mixed prefix** pattern. No `config/mary.php` exists. The convention is:

| Component | Prefix |
|-----------|--------|
| `x-mary-header` | `mary-` |
| `x-mary-alert` | `mary-` |
| `x-mary-badge` | `mary-` |
| `x-mary-stat` | `mary-` |
| `x-mary-card` | `mary-` |
| `x-mary-input` | `mary-` |
| `x-checkbox` | none |
| `x-main`, `x-menu`, `x-menu-item`, `x-menu-sub`, `x-menu-separator` | none |
| `x-toast` | none |
| `x-icon` | none |

Local wrappers in `resources/views/components/` bridge the gap:
- `mary-alert.blade.php` → delegates to `<x-alert>`
- `mary-badge.blade.php` → delegates to `<x-badge>`
- `mary-stat.blade.php` → delegates to `<x-stat>`

**Rule:** always check sibling components in the same directory before choosing the prefix for a new component. If the surrounding file uses `x-mary-*`, follow that pattern. If it uses unprefixed, follow that.

## Component API Reference (as used in this project)

### x-mary-header
```blade
<x-mary-header title="Page Title" subtitle="Optional subtitle" icon="o-building-library" separator />
```
Props: `title` (required), `subtitle`, `icon` (HeroIcons outline, e.g. `o-cog-6-tooth`), `separator` (boolean, adds bottom border)

### x-mary-alert
```blade
<x-mary-alert title="Success message" icon="o-check-circle" class="alert-success" />
<x-mary-alert title="Warning message" icon="o-exclamation-triangle" class="alert-warning" />
<x-mary-alert title="Info message" icon="o-information-circle" class="alert-info" />
<x-mary-alert title="Error message" icon="o-exclamation-triangle" class="alert-error" />
```
Props: `title`, `icon`, `class` (DaisyUI alert classes: `alert-success`, `alert-warning`, `alert-info`, `alert-error`)

### x-mary-badge
```blade
<x-mary-badge value="Approved" class="badge-success badge-sm" />
<x-mary-badge value="Pending" class="badge-warning badge-sm" />
```
Props: `value`, `class` (DaisyUI badge classes)

### x-mary-stat
```blade
<x-mary-stat title="Total" value="42" icon="o-currency-dollar" />
```
Props: `title`, `value` (can be string or number), `icon`, `description`, `color`

### x-mary-card
```blade
<x-mary-card shadow class="border border-base-300">
    <!-- content -->
</x-mary-card>
```
Props: `shadow` (boolean), `class`

### x-mary-input
```blade
<x-mary-input icon="o-magnifying-glass" wire:model.live.debounce.300ms="search" />
```
Props: `icon`, `wire:model`, `wire:model.live.debounce.300ms`

### x-checkbox
```blade
<x-checkbox wire:model="isChecked">
    <x-slot:label>
        <span class="text-sm">Label text</span>
    </x-slot:label>
</x-checkbox>
```
**Critical:** `wire:model` binds to a **boolean** value, not an array. Use a boolean map `[id => true/false]` for multiple checkboxes. Do NOT use `wire:model.live` on checkboxes — it causes unnecessary server roundtrips. Use `wire:model` (default, fires on `change`).

### x-toast
```blade
<x-toast />
```
Place once in the layout. Trigger from Livewire via `session()->flash('status', 'message')` (displayed with `x-mary-alert`).

### x-icon
```blade
<x-icon name="o-x-mark" class="h-4 w-4" />
```
Uses HeroIcons outline set (`o-*` prefix).

## Livewire Binding Rules

| Element | Directive | Why |
|---------|-----------|-----|
| `<input type="text">` | `wire:model.live.debounce.300ms` | Debounced search, avoids too many roundtrips |
| `<select>` | `wire:model.change` | Fires on selection change |
| `<input type="checkbox">` / `<x-checkbox>` | `wire:model` (no modifier) | Default is `change`, `.live` causes double roundtrips and state corruption |
| `<select>` for sede | `wire:model` (no modifier) | Same as checkbox — avoid `.live` |

## Alpine.js Patterns

### Calling Livewire methods from Alpine
```blade
<button x-on:click.prevent="$wire.methodName(id)">Click</button>
```
Use `$wire.method()` to call component PHP methods from Alpine.js.

### Safe JS strings with dynamic data
```blade
<!-- CORRECT: @js() generates double-quoted JSON -->
<button x-on:click.prevent="
    if (confirm('Delete assignments for ' + @js($admin->name) + '?')) {
        $wire.clearScopes({{ $admin->id }})
    }
">
```

```blade
<!-- WRONG: Js::from() generates single quotes that break JS string literals -->
<button x-on:click.prevent="
    if (confirm('Delete assignments for {{ Js::from($admin->name) }}?')) { ... }
">
```
**Rule:** always use `@js()` + string concatenation for dynamic values inside JS string literals in Alpine directives.

### NO $set in wire:click
`$set` is an Alpine.js magic, not available in `wire:click` context. Use a component method instead:
```blade
<!-- WRONG -->
<button wire:click="$set('search', ''); $set('filter', '')">

<!-- CORRECT -->
<button wire:click="clearFilters">
```

## Anti-Patterns Learned

1. **`wire:model.live` on checkboxes** — causes DOM reload on every toggle, resets state, hides other admins. Use `wire:model`.
2. **`Js::from()` in Alpine directives** — generates single quotes that prematurely close JS string literals. Use `@js()` + concatenation.
3. **`$set` in `wire:click`** — not available in Livewire context. Use a PHP method.
4. **`boot()` resetting collections** — in Volt class-based SFC, `boot()` runs on every request, wiping state. Use `mount()` only and reset arrays explicitly in data-loading methods.
5. **Mixing Livewire and Alpine state** — keep checkbox/sede state in Livewire properties (`$selectedAcademicUnitsByUser`, `$selectedSedesByUser`), not Alpine.js `x-data`.
6. **`modal-box` without `.modal` parent** — DaisyUI's `modal-box` renders transparent when used outside a `<dialog class="modal">` container. Use `card bg-base-100 shadow-2xl rounded-[1.75rem] max-w-md w-full p-6` for inline Alpine modals instead. Never use `modal-box` standalone.
7. **Event propagation in clickable rows** — when a button sits inside a `<tr x-on:click="...">`, use `@click.stop` (not `.prevent`) to stop event bubbling. `.prevent` only blocks default action but the click still reaches the row, causing dual request issues and 404 errors.

## Integration with DaisyUI / TailwindCSS 4

MaryUI v2 sits on top of DaisyUI 5 + TailwindCSS 4. DaisyUI classes coexist with MaryUI components:
```blade
<!-- MaryUI component with DaisyUI classes -->
<x-mary-badge value="Active" class="badge-success badge-sm" />
<x-mary-card shadow class="border border-base-300" />

<!-- DaisyUI classes on non-MaryUI elements -->
<button class="btn btn-primary min-w-48">
<select class="select select-bordered select-xs w-full">
<input class="input input-bordered w-full">
```

### Glass-morphism utilities (project-specific CSS)
These are custom classes defined in `resources/css/app.css`, not MaryUI:
- `glass-card` — card with backdrop blur
- `glass-surface` — surface with subtle transparency
- `glass-sidebar` — sidebar glass effect
- `glass-navbar` — sticky navbar glass effect
- `bg-app-pattern` — background texture