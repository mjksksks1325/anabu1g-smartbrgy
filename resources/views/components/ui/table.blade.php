{{--
    Data table: native <table> markup (thead/tbody written by the page) in a horizontally scrollable region.
    Its styles live in resources/css/staff-ui.css ([data-ui-table]) so they override the legacy table rules inside
    migrated pages only. `label` names the scroll region for screen readers and makes it keyboard scrollable.
    Phones: hide secondary columns (`hidden md:table-cell`) so the rest fit; give `table-class` a minimum width
    (for example `min-w-[64rem]`) only for tables that must keep every column and scroll sideways instead.
--}}
@props([
    'label' => null,
    'tableClass' => '',
])

{{-- relative: screen-reader-only text in cells is positioned inside the scroll region, not past the page edge. --}}
<div {{ $attributes->class('relative overflow-x-auto') }} @if ($label) role="region" aria-label="{{ $label }}" tabindex="0" @endif data-ui-table-scroll>
    <table class="w-full {{ $tableClass }}" data-ui-table>
        {{ $slot }}
    </table>
</div>
