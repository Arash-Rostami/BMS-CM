@php
    $ui = [
        'list'         => 'lp-well mt-1.5 rounded-md border lp-divider overflow-hidden',
        'tile'         => 'flex items-center gap-2.5 rounded-md border lp-divider lp-surface-hover px-2.5 py-2 transition-colors duration-150',
        'chip'         => 'group inline-flex items-center gap-1.5 rounded-full border py-1 pl-1 pr-2.5 text-xs font-medium transition-colors !cursor-pointer',
        'chipTone'     => "darkMode ? 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'",
        'labelTone'    => "darkMode ? 'text-slate-500' : 'text-slate-400'",
        'removeBtn'    => 'absolute top-1/2 -translate-y-1/2 right-2 w-6 h-6 rounded-md flex items-center justify-center transition-colors !cursor-pointer',
        'removeTone'   => "darkMode ? 'text-slate-400 hover:bg-red-500/20 hover:text-red-300' : 'text-slate-400 hover:bg-red-50 hover:text-red-500'",
        'renameBtn'    => 'absolute top-1/2 -translate-y-1/2 right-9 w-6 h-6 rounded-md flex items-center justify-center transition-colors !cursor-pointer',
        'renameTone'   => "darkMode ? 'text-slate-400 hover:bg-primary-500/20 hover:text-primary-300' : 'text-slate-400 hover:bg-primary-50 hover:text-primary-500'",
        'sectionLabel' => 'text-[11px] font-semibold uppercase tracking-wide mb-2 flex items-center gap-1.5',
        'editBtn'      => 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold border lp-divider transition-colors !cursor-pointer',
        'editBtnTone'  => "darkMode ? 'text-slate-400 bg-white/5 hover:bg-white/10 hover:text-slate-200' : 'text-slate-500 bg-white hover:bg-slate-50 hover:text-slate-700'",
        'closeTone'    => "darkMode ? 'text-slate-500 hover:bg-white/10 hover:text-slate-300' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'",
    ];
@endphp

<div x-data="{ ...workspace({{ json_encode($workspaceConfig) }}), showModulePicker: false, showRecordPicker: false }"
     class="mb-8 relative z-20 space-y-4">

    <x-accordion-header open="modulesOpen" icon="heroicon-o-squares-2x2"
                         title="{{ __('dashboard/strings.workspace_modules') }}"
                         count="pinnedModuleIds.length" countLabel="{{ __('dashboard/strings.pinned_suffix') }}">

                        <div x-show="pinnedModuleIds.length" x-cloak class="mb-3">
                            <p class="{{ $ui['sectionLabel'] }}" :class="{!! $ui['labelTone'] !!}">{{ __('dashboard/strings.section_pinned') }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <template x-for="m in pinnedModules()" :key="m.id">
                                    <div class="relative group">
                                        <a :href="m.route" target="_blank" rel="noopener noreferrer" class="{{ $ui['tile'] }} pr-10">
                                            <span class="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0" :class="m.theme">
                                                <span class="w-3.5 h-3.5" x-html="m.icon"></span>
                                            </span>
                                            <span class="font-semibold text-xs sm:text-sm truncate flex-1" :class="darkMode ? 'text-slate-100' : 'text-slate-800'" x-text="m.label"></span>
                                        </a>
                                        <template x-if="moduleStat(m.id)">
                                            <span class="absolute top-1/2 -translate-y-1/2 right-2 text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1 flex items-center justify-center tabular-nums transition-opacity group-hover:opacity-0 pointer-events-none" :class="m.badge" x-text="moduleStat(m.id)"></span>
                                        </template>
                                        <button type="button" @click="unpinModule(m.id)" class="{{ $ui['removeBtn'] }}" :class="{!! $ui['removeTone'] !!}" title="{{ __('dashboard/strings.record_pin.added') }}">
                                            <x-heroicon-o-x-mark class="w-3.5 h-3.5"/>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <x-empty-state x-show="!pinnedModuleIds.length && !showModulePicker" x-cloak
                                       icon="heroicon-o-squares-2x2"
                                       hint="{{ __('dashboard/strings.workspace_modules_hint') }}"
                                       ctaLabel="{{ __('dashboard/strings.section_add') }}"
                                       ctaAction="showModulePicker = true" />

                        <div x-show="showModulePicker" x-cloak>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-semibold uppercase tracking-wide" :class="{!! $ui['labelTone'] !!}">
                                    {{ __('dashboard/strings.section_add') }}
                                </span>
                                <button type="button" @click="showModulePicker = false" class="w-6 h-6 rounded-md flex items-center justify-center transition-colors !cursor-pointer" :class="{!! $ui['closeTone'] !!}">
                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5"/>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="m in unpinnedModules()" :key="m.id">
                                    <button type="button" @click="pinModule(m.id)" class="{{ $ui['chip'] }}" :class="{!! $ui['chipTone'] !!}">
                                        <span class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0" :class="m.theme">
                                            <span class="w-3 h-3" x-html="m.icon"></span>
                                        </span>
                                        <span x-text="m.label"></span>
                                        <x-heroicon-o-plus class="w-3 h-3 opacity-50"/>
                                    </button>
                                </template>
                            </div>
                            <p x-show="!unpinnedModules().length" x-cloak class="text-xs" :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                {{ __('dashboard/strings.all_pinned') }}
                            </p>
                        </div>

                        <div x-show="pinnedModuleIds.length && !showModulePicker" x-cloak class="flex justify-end mt-2.5">
                            <button type="button" @click="showModulePicker = true" class="{{ $ui['editBtn'] }}" :class="{!! $ui['editBtnTone'] !!}">
                                <x-heroicon-o-pencil-square class="w-3.5 h-3.5"/>
                                {{ __('dashboard/strings.section_add') }}
                            </button>
                        </div>

    </x-accordion-header>

    <x-accordion-header open="recordsOpen" icon="heroicon-o-bookmark"
                         title="{{ __('dashboard/strings.workspace_records') }}"
                         count="recordPins.length" countLabel="{{ __('dashboard/strings.pinned_suffix') }}">

                        <div x-show="recordPins.length" x-cloak class="mb-3">
                            <p class="{{ $ui['sectionLabel'] }}" :class="{!! $ui['labelTone'] !!}">{{ __('dashboard/strings.section_pinned') }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                <template x-for="p in recordPins" :key="p.key">
                                    <div class="relative group">
                                        <a :href="p.url" target="_blank" rel="noopener noreferrer" @click="editingKey === p.key && $event.preventDefault()" class="{{ $ui['tile'] }} pr-16">
                                            <span class="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0" :class="p.theme">
                                                <span class="w-3.5 h-3.5" x-html="p.icon"></span>
                                            </span>
                                            <span class="flex-1 min-w-0">
                                                <template x-if="editingKey !== p.key">
                                                    <span class="block font-semibold text-xs sm:text-sm truncate" :class="darkMode ? 'text-slate-100' : 'text-slate-800'" x-text="p.label"></span>
                                                </template>
                                                <template x-if="editingKey === p.key">
                                                    <input type="text" x-model="editingLabel" class="block w-full font-semibold text-xs sm:text-sm bg-transparent border-b focus:outline-none" :class="darkMode ? 'text-slate-100 border-primary-400' : 'text-slate-800 border-primary-500'" @click.stop @mousedown.stop @keydown.enter.stop="renameRecord(p.key, editingLabel)" @keydown.escape.stop="editingKey = null" @blur="renameRecord(p.key, editingLabel)" x-init="$nextTick(() => $el.focus())">
                                                </template>
                                                <span x-show="p.subtitle" class="block text-[11px] truncate mt-0.5" :class="darkMode ? 'text-slate-400' : 'text-slate-500'" x-text="p.subtitle"></span>
                                            </span>
                                        </a>
                                        <button type="button" @click.stop="editingKey = p.key; editingLabel = p.label" class="{{ $ui['renameBtn'] }}" :class="{!! $ui['renameTone'] !!}" title="Rename">
                                            <x-heroicon-o-pencil class="w-3.5 h-3.5"/>
                                        </button>
                                        <button type="button" @click="removeRecord(p.key)" class="{{ $ui['removeBtn'] }}" :class="{!! $ui['removeTone'] !!}" title="{{ __('dashboard/strings.record_pin.added') }}">
                                            <x-heroicon-o-x-mark class="w-3.5 h-3.5"/>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <x-empty-state x-show="!recordPins.length && !showRecordPicker" x-cloak
                                       icon="heroicon-o-bookmark"
                                       hint="{{ __('dashboard/strings.workspace_records_hint') }}"
                                       ctaLabel="{{ __('dashboard/strings.section_add') }}"
                                       ctaAction="showRecordPicker = true; recordResults.length === 0 && searchRecords()" />

                        <div x-show="showRecordPicker" x-cloak>
                            <div class="flex items-center justify-between mb-2.5">
                                <span class="text-[11px] font-semibold uppercase tracking-wide" :class="{!! $ui['labelTone'] !!}">
                                    {{ __('dashboard/strings.section_add') }}
                                </span>
                                <button type="button" @click="showRecordPicker = false" class="w-6 h-6 rounded-md flex items-center justify-center transition-colors !cursor-pointer" :class="{!! $ui['closeTone'] !!}">
                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5"/>
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-1.5 mb-2.5">
                                <template x-for="m in searchableModules()" :key="m.id">
                                    <button type="button" @click="selectResource(m.id)" class="{{ $ui['chip'] }}" :class="pickerResource === m.id ? (darkMode ? 'border-primary-400/50 bg-primary-500/15 text-white' : 'border-primary-300 bg-primary-50 text-primary-700') : ({!! $ui['chipTone'] !!})">
                                        <span class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0" :class="m.theme">
                                            <span class="w-3 h-3" x-html="m.icon"></span>
                                        </span>
                                        <span x-text="m.label"></span>
                                    </button>
                                </template>
                            </div>

                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none" :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                    <x-heroicon-o-magnifying-glass class="w-4 h-4"/>
                                </div>
                                <input type="text" x-model="recordQuery" @input.debounce.300ms="searchRecords()" @focus="recordResults.length === 0 && searchRecords()" @keydown.escape.stop="recordQuery = ''; searchRecords()" placeholder="{{ __('dashboard/strings.record_pin.placeholder') }}" class="w-full rounded-md border pl-9 pr-9 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 transition-colors" :class="darkMode ? 'bg-slate-800/70 border-white/10 text-slate-200 focus:bg-slate-800 focus:border-primary-400/60' : 'bg-slate-50 border-slate-200 text-slate-700 focus:bg-white focus:border-primary-400'">
                                <button type="button" x-show="recordQuery" x-cloak @click="recordQuery = ''; searchRecords()" class="absolute inset-y-0 right-0 flex items-center pr-3 transition-colors !cursor-pointer" :class="darkMode ? 'text-slate-500 hover:text-slate-300' : 'text-slate-400 hover:text-slate-600'">
                                    <x-heroicon-o-x-circle class="w-4 h-4"/>
                                </button>
                            </div>

                            <div class="flex items-center justify-between px-1 mt-2 text-[11px] font-semibold" :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                <span>{{ __('dashboard/strings.record_pin.tap') }}</span>
                                <span x-show="!recordLoading && recordResults.length" x-cloak>
                                    <span x-text="recordResults.length"></span> {{ __('dashboard/strings.record_pin.found') }}
                                </span>
                            </div>

                            <div class="{{ $ui['list'] }}">
                                <div class="max-h-64 overflow-y-auto custom-scrollbar">
                                    <template x-if="recordLoading">
                                        <div class="p-2 space-y-1">
                                            <template x-for="i in 3" :key="i">
                                                <div class="flex items-center gap-3 px-3 py-2.5 animate-pulse">
                                                    <x-loading-skeleton class="w-8 h-8 rounded-md flex-shrink-0" />
                                                    <div class="flex-1 space-y-2">
                                                        <x-loading-skeleton class="h-2.5 rounded-full w-1/3" />
                                                        <x-loading-skeleton muted class="h-2 rounded-full w-1/2" />
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="!recordLoading && recordError">
                                        <div class="px-4 py-6 text-center">
                                            <p class="text-sm font-semibold text-red-600">{{ __('dashboard/strings.record_pin.error') }}</p>
                                            <button type="button" @click="searchRecords()" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-500 hover:text-primary-600 transition-colors !cursor-pointer">
                                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5"/>
                                                {{ __('dashboard/strings.record_pin.retry') }}
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="!recordLoading && !recordError && recordResults.length === 0">
                                        <x-empty-state size="lg"
                                                       icon="heroicon-o-magnifying-glass"
                                                       hint="{{ __('dashboard/strings.record_pin.empty') }}"
                                                       hint2="{{ __('dashboard/strings.record_pin.empty_hint') }}" />
                                    </template>

                                    <template x-if="!recordLoading && !recordError && recordResults.length > 0">
                                        <ul class="divide-y" :class="darkMode ? 'divide-white/5' : 'divide-slate-100'">
                                            <template x-for="rec in recordResults" :key="rec.key">
                                                <li class="group flex items-center gap-3 px-3 py-2.5 transition-colors" :class="darkMode ? 'hover:bg-white/5' : 'hover:bg-white'">
                                                    <span :class="['flex items-center justify-center w-8 h-8 rounded-md text-[11px] font-extrabold tracking-tight flex-shrink-0', pickerTheme]" x-text="initials(rec.label)"></span>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-semibold text-sm truncate" :class="darkMode ? 'text-slate-100' : 'text-slate-800'" x-text="rec.label"></p>
                                                        <p x-show="rec.subtitle" class="text-xs truncate" :class="darkMode ? 'text-slate-400' : 'text-slate-500'" x-text="rec.subtitle"></p>
                                                    </div>
                                                    <button type="button" @click="addRecord(rec)" :disabled="isRecordPinned(rec.key)" class="flex-shrink-0 inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-semibold transition-colors disabled:cursor-default !cursor-pointer" :class="isRecordPinned(rec.key) ? (darkMode ? 'bg-green-500/15 text-green-400' : 'bg-green-50 text-green-700') : (darkMode ? 'bg-primary-500/15 text-primary-300 hover:bg-primary-500/25' : 'bg-primary-50 text-primary-600 hover:bg-primary-100')">
                                                        <span x-show="!isRecordPinned(rec.key)" class="inline-flex items-center gap-1">
                                                            <x-heroicon-o-plus class="w-3.5 h-3.5"/>
                                                            {{ __('dashboard/strings.record_pin.add') }}
                                                        </span>
                                                        <span x-show="isRecordPinned(rec.key)" class="inline-flex items-center gap-1">
                                                            <x-heroicon-o-check class="w-3.5 h-3.5"/>
                                                            {{ __('dashboard/strings.record_pin.added') }}
                                                        </span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div x-show="recordPins.length && !showRecordPicker" x-cloak class="flex justify-end mt-2.5">
                            <button type="button" @click="showRecordPicker = true; recordResults.length === 0 && searchRecords()" class="{{ $ui['editBtn'] }}" :class="{!! $ui['editBtnTone'] !!}">
                                <x-heroicon-o-pencil-square class="w-3.5 h-3.5"/>
                                {{ __('dashboard/strings.section_add') }}
                            </button>
                        </div>

    </x-accordion-header>
</div>
