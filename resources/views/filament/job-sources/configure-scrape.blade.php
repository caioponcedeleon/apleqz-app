<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Preview URL</x-slot>

            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="flex-1">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="preview-url">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">Sample listing page</span>
                    </label>
                    <input
                        id="preview-url"
                        type="url"
                        wire:model="previewUrl"
                        class="mt-1 block w-full rounded-lg border-none bg-white py-1.5 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                        placeholder="https://example.com/careers"
                    />
                </div>

                <x-filament::button wire:click="loadPreview" wire:loading.attr="disabled">
                    Load preview
                </x-filament::button>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-1">
                <x-filament::section>
                    <x-slot name="heading">Picker mode</x-slot>

                    <div class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" wire:model.live="pickerMode" value="item" class="text-primary-600" />
                                <span class="text-sm text-gray-950 dark:text-white">Select list item</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" wire:model.live="pickerMode" value="field" class="text-primary-600" />
                                <span class="text-sm text-gray-950 dark:text-white">Map field</span>
                            </label>
                        </div>

                        @if ($pickerMode === 'field')
                            <div>
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white" for="active-field">
                                    Field to map
                                </label>
                                <select
                                    id="active-field"
                                    wire:model.live="activeField"
                                    class="mt-1 block w-full rounded-lg border-none bg-white py-1.5 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                >
                                    <option value="">Choose a field…</option>
                                    @foreach ($this->getFieldOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">List item selector</p>
                            <p class="mt-1 break-all rounded-lg bg-gray-50 p-2 font-mono text-xs text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                {{ $itemSelector !== '' ? $itemSelector : '—' }}
                            </p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Mapped fields</x-slot>

                    @if ($fieldMappings === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">No fields mapped yet.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach ($fieldMappings as $field => $mapping)
                                <li class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                                {{ $this->getFieldOptions()[$field] ?? $field }}
                                            </p>
                                            <p class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-300">
                                                {{ $mapping['selector'] ?? '' }}
                                            </p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ ($mapping['extract'] ?? 'text') === 'attribute'
                                                    ? 'Attribute: '.($mapping['attribute'] ?? '')
                                                    : 'Text content' }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="removeFieldMapping('{{ $field }}')"
                                            class="text-xs text-danger-600 hover:underline"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button color="gray" wire:click="testExtraction">
                        Test extraction
                    </x-filament::button>
                    <x-filament::button wire:click="saveConfiguration">
                        Save configuration
                    </x-filament::button>
                </div>
            </div>

            <div class="xl:col-span-2">
                <x-filament::section class="h-full">
                    <x-slot name="heading">Page preview</x-slot>
                    <x-slot name="description">Click an element in the preview to capture selectors. Scripts are stripped for safety.</x-slot>

                    <div wire:ignore class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                        <iframe
                            id="job-source-preview-frame"
                            title="Job source preview"
                            class="h-[70vh] w-full bg-white"
                            sandbox="allow-scripts allow-same-origin"
                            srcdoc="<p style='font-family:system-ui;padding:1rem;color:#6b7280'>Load a preview to begin.</p>"
                        ></iframe>
                    </div>
                </x-filament::section>
            </div>
        </div>

        @if ($testResults !== [])
            <x-filament::section>
                <x-slot name="heading">Test extraction results</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2 font-medium">Title</th>
                                <th class="px-3 py-2 font-medium">URL</th>
                                <th class="px-3 py-2 font-medium">Company</th>
                                <th class="px-3 py-2 font-medium">Location</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($testResults as $row)
                                <tr>
                                    <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['title'] }}</td>
                                    <td class="max-w-xs truncate px-3 py-2 text-primary-600">{{ $row['url'] }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['company'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['location'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>

    @script
        <script>
            const frame = document.getElementById('job-source-preview-frame');

            function sendPickerConfig(mode, itemSelector) {
                if (!frame?.contentWindow) {
                    return;
                }

                frame.contentWindow.postMessage({
                    type: 'job-source-picker-config',
                    mode: mode || 'item',
                    itemSelector: itemSelector || '',
                }, '*');
            }

            window.addEventListener('message', (event) => {
                if (!event.data || event.data.type !== 'job-source-picker') {
                    return;
                }

                if (event.data.ready) {
                    sendPickerConfig(@js($pickerMode), @js($itemSelector));
                    return;
                }

                $wire.handlePickerSelection(event.data);
            });

            $wire.on('job-source-preview-loaded', ({ html }) => {
                if (frame) {
                    frame.srcdoc = html;
                }
            });

            $wire.on('job-source-picker-config', ({ mode, itemSelector }) => {
                sendPickerConfig(mode, itemSelector);
            });
        </script>
    @endscript
</x-filament-panels::page>
