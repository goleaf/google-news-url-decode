@if ($user)
    <x-filament::icon
        icon="heroicon-m-user-circle"
        {{ $attributes->class(['fi-user-avatar h-9 w-9 text-gray-400']) }}
    />
@endif