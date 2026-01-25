<script type="module">
    import selectFormComponent from "{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select', 'filament/forms') }}";
    import tableComponent from "{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('table', 'filament/tables') }}";
    
    function registerComponents() {
        window.Alpine.data('selectFormComponent', selectFormComponent);
        window.Alpine.data('table', tableComponent);
    }

    if (window.Alpine) {
        registerComponents();
    } else {
        document.addEventListener('alpine:init', () => {
            registerComponents();
        });
    }
</script>