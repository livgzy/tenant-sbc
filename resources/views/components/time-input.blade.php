@props(['model', 'label' => null])

<div 
    x-data="{
        open: false,
        hours: Array.from({length: 24}, (_, i) => String(i).padStart(2, '0')),
        minutes: Array.from({length: 60}, (_, i) => String(i).padStart(2, '0')),
        get displayValue() {
            let val = $wire.get('{{ $model }}');
            return val ? val : '-- : --';
        },
        selectTime(h, m) {
            $wire.set('{{ $model }}', h + ':' + m);
            open = false;
        }
    }"
    class="relative"
>
    @if($label)
        <label class="block text-sm font-semibold text-gray-700 mb-1">{{ $label }}</label>
    @endif

    <button
        type="button"
        x-on:click="open = !open"
        x-on:click.outside="open = false"
        class="w-full flex items-center justify-between px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 bg-white text-left"
    >
        <span x-text="displayValue" class="text-gray-700"></span>
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </button>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute z-50 mt-2 w-full bg-white border border-gray-200 rounded-xl shadow-lg flex overflow-hidden"
    >
        <div class="flex-1 max-h-48 overflow-y-auto border-r">
            <template x-for="h in hours" :key="h">
                <button
                    type="button"
                    x-on:click="selectTime(h, $wire.get('{{ $model }}') ? $wire.get('{{ $model }}').split(':')[1] : '00')"
                    class="w-full px-3 py-2 text-sm text-center hover:bg-orange-50 hover:text-orange-600"
                    x-text="h"
                ></button>
            </template>
        </div>
        <div class="flex-1 max-h-48 overflow-y-auto">
            <template x-for="m in minutes" :key="m">
                <button
                    type="button"
                    x-on:click="selectTime($wire.get('{{ $model }}') ? $wire.get('{{ $model }}').split(':')[0] : '00', m)"
                    class="w-full px-3 py-2 text-sm text-center hover:bg-orange-50 hover:text-orange-600"
                    x-text="m"
                ></button>
            </template>
        </div>
    </div>
</div>