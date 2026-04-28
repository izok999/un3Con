---
description: "Use when: editing resources/views/livewire/**, adding Livewire or Volt loading states, building skeleton placeholders, or refining wire:loading behavior in this Laravel project."
name: "Livewire Loading States"
applyTo: "resources/views/livewire/**"
---

# Livewire Loading States

- Prefer skeletons over spinner-only loading states when the view has cards, stats, tables, lists, or any layout the user can anticipate.
- Keep the skeleton footprint aligned with the final UI so content does not jump when the request completes.
- Match the final responsive structure: keep the same grid columns, gaps, card groupings, and major container widths.
- Use DaisyUI `skeleton` blocks with explicit widths and heights for text lines, badges, stats, rows, and actions.
- Keep radii and surfaces aligned with the UNE visual system. Reuse `rounded-[1.5rem]`, `rounded-[1.75rem]`, `border border-base-300`, `bg-base-100/85`, `glass-card`, and `glass-surface` when they match the surrounding component.
- Keep loading UI theme-safe for both `uneTheme` and `uneThemeDark`. Use existing tokens and surfaces, not hard-coded light-gray placeholders.

## Choose The Right Pattern

- For lazy Volt components or expensive first paint, prefer a `placeholder(): string` method that returns a realistic skeleton shell.
- For Livewire views that hydrate in place, prefer a boolean gate such as `$isLoaded` with `wire:init` and swap the skeleton with the real content once data is ready.
- For buttons, tabs, filters, and submit actions, use scoped `wire:loading` and `wire:loading.remove` with `wire:target` on the exact action or property.
- Add `wire:loading.delay` for indicators that would otherwise flicker on fast requests.
- Use a compact spinner only for small action affordances inside buttons or inline controls, not as the only loading state for an entire view.

## Component Rules

- Reuse the surrounding MaryUI structure for headers, cards, alerts, and tables. The skeleton should replace the content area, not the whole page frame.
- Prefer multiple realistic placeholder lines instead of a single large block when the final UI contains readable hierarchy.
- For table-like content, render several row skeletons with consistent column rhythm.
- For dashboard cards and stats, mirror the final title, value, badge, and action placement.
- If you render repeated skeleton items in a Livewire loop, keep stable `wire:key` usage consistent with the final loop.

## Avoid

- Do not hide the entire screen behind a centered spinner when the final layout is known.
- Do not use unscoped `wire:loading` on a busy component when only one action is pending.
- Do not introduce a new loading visual language if the surrounding view already uses skeletons.
- Do not hard-code arbitrary placeholder colors or border radii that fight the existing UNE glass styling.

## Repo Examples

- `resources/views/livewire/alumno/dashboard-carreras.blade.php`: use `placeholder()` with card-shaped skeletons that match the final grid.
- `resources/views/livewire/alumno/detalle-carrera.blade.php`: gate heavy sections behind an `$isLoaded` skeleton swap.
- `resources/views/livewire/admin/consulta-alumno.blade.php`: use action-scoped `wire:loading` with `wire:target="buscar"` for button feedback.

## Example Patterns

```blade
@if (! $isLoaded)
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="skeleton h-64 rounded-[1.5rem]"></div>
        <div class="skeleton h-64 rounded-[1.5rem]"></div>
    </div>
@else
    {{-- real content --}}
@endif
```

```blade
<button wire:click="buscar" class="btn btn-primary">
    <span wire:loading.remove wire:target="buscar">
        <x-icon name="o-magnifying-glass" class="w-4 h-4" />
    </span>
    <span wire:loading wire:target="buscar" class="loading loading-spinner loading-sm"></span>
    Buscar
</button>
```