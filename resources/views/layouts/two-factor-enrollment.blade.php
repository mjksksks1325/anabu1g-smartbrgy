<x-layouts::auth :title="$title ?? __('Security settings')">
    <div data-two-factor-enrollment-shell>
        {{ $slot }}
    </div>
</x-layouts::auth>
