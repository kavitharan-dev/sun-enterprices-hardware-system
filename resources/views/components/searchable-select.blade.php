@props([
    'name',
    'options' => [],
    'value' => '',
    'placeholder' => 'Type to search…',
    'emptyLabel' => 'Select…',
    'allowEmpty' => true,
    'required' => false,
    'disabled' => false,
    'form' => null,
])

@php
    $normalized = collect($options)->map(function ($opt) {
        if (is_array($opt)) {
            return [
                'value' => (string) ($opt['value'] ?? $opt['id'] ?? ''),
                'label' => (string) ($opt['label'] ?? $opt['name'] ?? ''),
                'search' => (string) ($opt['search'] ?? ''),
            ];
        }

        if (is_object($opt)) {
            return [
                'value' => (string) ($opt->value ?? $opt->id ?? $opt->getKey()),
                'label' => (string) ($opt->label ?? $opt->name ?? $opt),
                'search' => (string) ($opt->search ?? ''),
            ];
        }

        return [
            'value' => (string) $opt,
            'label' => (string) $opt,
            'search' => '',
        ];
    })->values()->all();
@endphp

<div
    x-data="searchableSelect({
        options: {{ \Illuminate\Support\Js::from($normalized) }},
        value: {{ \Illuminate\Support\Js::from((string) $value) }},
        name: {{ \Illuminate\Support\Js::from($name) }},
        placeholder: {{ \Illuminate\Support\Js::from($placeholder) }},
        emptyLabel: {{ \Illuminate\Support\Js::from($emptyLabel) }},
        allowEmpty: {{ $allowEmpty ? 'true' : 'false' }},
        required: {{ $required ? 'true' : 'false' }},
        disabled: {{ $disabled ? 'true' : 'false' }},
    })"
    class="relative"
    :class="open ? 'z-[60]' : 'z-20'"
    x-on:click.outside="open = false"
    {{ $attributes }}
>
    <input type="hidden" :name="nameAttr" :value="value" @if ($required) :required="!value" @endif @if ($form) form="{{ $form }}" @endif>

    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown="onKeydown($event)"
        :disabled="disabled"
        class="flex w-full min-h-[2.5rem] items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm hover:border-amber-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 disabled:cursor-not-allowed disabled:bg-slate-50"
        :class="open ? 'border-amber-500 ring-1 ring-amber-500' : ''"
    >
        <span class="truncate" :class="value ? 'text-slate-900' : 'text-slate-400'" x-text="selectedLabel"></span>
        <span class="flex shrink-0 items-center gap-1 text-slate-400">
            <span x-show="value && allowEmpty && !disabled" x-on:click.stop="clear()" class="rounded px-1 text-xs hover:bg-slate-100 hover:text-slate-700" title="Clear">&times;</span>
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        x-bind:style="panelStyle"
        class="flex flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-2xl"
    >
        <div class="shrink-0 border-b border-slate-100 p-2">
            <input
                x-ref="search"
                type="text"
                x-model="query"
                x-on:keydown="onKeydown($event)"
                class="block w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500"
                :placeholder="placeholder"
                autocomplete="off"
            >
        </div>
        <ul class="min-h-[8rem] flex-1 overflow-y-auto py-1 text-sm">
            <template x-if="allowEmpty">
                <li>
                    <button type="button" class="flex w-full px-3 py-2.5 text-left text-slate-500 hover:bg-amber-50" x-on:click="clear()" x-text="emptyLabel"></button>
                </li>
            </template>
            <template x-for="(opt, i) in filtered" :key="opt.value + '-' + i">
                <li>
                    <button
                        type="button"
                        class="flex w-full px-3 py-2.5 text-left whitespace-normal break-words leading-snug hover:bg-amber-50"
                        :class="String(opt.value) === String(value) ? 'bg-amber-50 font-medium text-amber-900' : (highlighted === i ? 'bg-slate-50' : 'text-slate-800')"
                        x-on:click="select(opt)"
                        x-on:mouseenter="highlighted = i"
                        x-text="opt.label"
                    ></button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-3 text-slate-400">No matches</li>
        </ul>
    </div>
</div>
