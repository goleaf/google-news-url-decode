@php
    $hasDarkMode = filament()->hasDarkMode() && (! filament()->hasDarkModeForced());
@endphp

@if ($hasDarkMode)
    <div class="flex items-center gap-x-4">
        <x-filament-panels::theme-switcher />
    </div>
@endif