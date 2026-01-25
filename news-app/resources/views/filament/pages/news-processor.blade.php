<x-filament-panels::page>
    <div x-data="{ 
        processing: @entangle('isProcessing'),
    }">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-cpu-chip"
                    class="h-10 w-10 text-sky-500"
                />
                <div>
                    <h2 class="text-lg font-bold">News Processing Console</h2>
                    <p class="text-sm text-gray-500">
                        Categories: {{ count($this->categories) }} | 
                        Processed: {{ $this->processedCount }} | 
                        New Saved: {{ $this->savedCount }}
                    </p>
                </div>
            </div>
            
            <x-filament::button 
                wire:click="startProcessing"
                x-bind:disabled="processing"
                color="success"
                icon="heroicon-m-play"
            >
                <span x-show="!processing">Start Processing</span>
                <span x-show="processing">Processing...</span>
            </x-filament::button>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 shadow-sm">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-t-lg flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-m-command-line"
                    class="h-5 w-5 text-amber-500"
                />
                <h3 class="text-sm font-medium uppercase tracking-wider text-gray-500">Live Logs</h3>
            </div>
            
            <!-- Polling Div -->
            <div wire:poll.2000ms="checkProgress"></div>
            
            <div 
                class="p-4 h-96 overflow-y-auto font-mono text-sm bg-black text-gray-300" 
                id="log-container"
                x-effect="$el.scrollTop = $el.scrollHeight"
            >
                <!-- Logs Stream -->
                <div wire:stream="logs"></div>
                
                @foreach($logs as $log)
                    {!! $log !!}
                @endforeach
                
                <div x-show="processing" class="animate-pulse mt-2 text-green-500">_</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>