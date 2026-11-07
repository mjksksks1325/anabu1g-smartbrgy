{{-- Definition list for record details: two columns from small screens up. Fill it with x-ui.detail items. --}}
<dl {{ $attributes->class('grid gap-x-6 gap-y-4 sm:grid-cols-2') }} data-ui-details>
    {{ $slot }}
</dl>
