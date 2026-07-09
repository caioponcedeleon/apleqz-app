<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    id: { type: String, default: undefined },
    ariaLabel: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    searchable: { type: Boolean, default: false },
    noResultsText: { type: String, default: 'No results' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open = ref(false);
const root = ref(null);
const inputRef = ref(null);
const query = ref('');
const highlightedIndex = ref(-1);

const normalizedOptions = computed(() =>
    props.options.map((option) =>
        typeof option === 'object' && option !== null
            ? { value: String(option.value), label: option.label }
            : { value: String(option), label: String(option) },
    ),
);

const selectedOption = computed(() =>
    normalizedOptions.value.find((option) => option.value === String(props.modelValue ?? '')) ?? null,
);

const hasSelection = computed(
    () => props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined && selectedOption.value,
);

const filteredOptions = computed(() => {
    if (!props.searchable) {
        return normalizedOptions.value;
    }

    const trimmed = query.value.trim().toLowerCase();

    if (trimmed === '') {
        return normalizedOptions.value;
    }

    return normalizedOptions.value.filter(
        (option) =>
            option.label.toLowerCase().includes(trimmed)
            || option.value.toLowerCase().includes(trimmed),
    );
});

const listOptions = computed(() => filteredOptions.value);

const inputDisplayValue = computed(() => {
    if (open.value && props.searchable) {
        return query.value;
    }

    return selectedOption.value?.label ?? '';
});

const select = (value) => {
    emit('update:modelValue', value);
    emit('change', value);
    query.value = '';
    highlightedIndex.value = -1;
    open.value = false;
};

const openDropdown = () => {
    if (props.disabled) {
        return;
    }

    open.value = true;
    highlightedIndex.value = -1;

    if (props.searchable) {
        query.value = selectedOption.value?.label ?? '';
        nextTick(() => {
            inputRef.value?.focus();
            inputRef.value?.select();
        });
    }
};

const toggle = () => {
    if (props.disabled) {
        return;
    }

    if (open.value) {
        close();
    } else {
        openDropdown();
    }
};

const close = () => {
    open.value = false;
    query.value = '';
    highlightedIndex.value = -1;
};

const onClickOutside = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
};

const onEscape = (event) => {
    if (event.key === 'Escape' && open.value) {
        event.preventDefault();
        close();
        inputRef.value?.blur();
    }
};

const onSearchInput = (event) => {
    query.value = event.target.value;
    open.value = true;
    highlightedIndex.value = -1;
};

const onSearchFocus = () => {
    if (!open.value) {
        openDropdown();
    }
};

const highlightNext = () => {
    if (listOptions.value.length === 0) {
        highlightedIndex.value = -1;

        return;
    }

    highlightedIndex.value = (highlightedIndex.value + 1) % listOptions.value.length;
};

const highlightPrevious = () => {
    if (listOptions.value.length === 0) {
        highlightedIndex.value = -1;

        return;
    }

    highlightedIndex.value = highlightedIndex.value <= 0
        ? listOptions.value.length - 1
        : highlightedIndex.value - 1;
};

const onSearchKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!open.value) {
            openDropdown();
        }
        highlightNext();

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!open.value) {
            openDropdown();
        }
        highlightPrevious();

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();

        if (highlightedIndex.value >= 0 && listOptions.value[highlightedIndex.value]) {
            select(listOptions.value[highlightedIndex.value].value);

            return;
        }

        if (listOptions.value.length === 1) {
            select(listOptions.value[0].value);
        }

        return;
    }

    if (event.key === 'Escape') {
        onEscape(event);
    }
};

watch(listOptions, () => {
    if (highlightedIndex.value >= listOptions.value.length) {
        highlightedIndex.value = listOptions.value.length > 0 ? 0 : -1;
    }
});

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <div
            v-if="searchable"
            class="flex w-full min-w-0 items-center gap-2 rounded-lg border bg-white text-left transition dark:bg-gray-800"
            :class="[
                compact ? 'px-2 py-1' : 'px-3 py-2',
                open
                    ? 'border-indigo-400 ring-2 ring-indigo-100 dark:border-indigo-500 dark:ring-indigo-900/40'
                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                disabled ? 'cursor-not-allowed opacity-60' : '',
            ]"
        >
            <input
                :id="id"
                ref="inputRef"
                type="text"
                class="min-w-0 flex-1 bg-transparent text-sm text-gray-700 outline-none placeholder:text-gray-500 dark:text-gray-200 dark:placeholder:text-gray-400"
                :class="hasSelection && !open ? 'font-medium' : ''"
                :value="inputDisplayValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-label="ariaLabel"
                :aria-expanded="open"
                aria-autocomplete="list"
                aria-haspopup="listbox"
                role="combobox"
                @focus="onSearchFocus"
                @input="onSearchInput"
                @keydown="onSearchKeydown"
            >
            <button
                type="button"
                class="shrink-0 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                :disabled="disabled"
                tabindex="-1"
                :aria-label="open ? 'Close options' : 'Open options'"
                @click="toggle"
            >
                <svg
                    class="size-4 transition"
                    :class="{ 'rotate-180': open }"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>

        <button
            v-else
            :id="id"
            type="button"
            class="flex w-full min-w-0 items-center gap-2 rounded-lg border bg-white text-left transition dark:bg-gray-800"
            :class="[
                compact ? 'px-2 py-1' : 'px-3 py-2',
                open
                    ? 'border-indigo-400 ring-2 ring-indigo-100 dark:border-indigo-500 dark:ring-indigo-900/40'
                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            ]"
            :disabled="disabled"
            :aria-expanded="open"
            :aria-label="ariaLabel"
            aria-haspopup="listbox"
            @click="toggle"
        >
            <span
                v-if="hasSelection"
                class="truncate text-sm font-medium text-gray-700 dark:text-gray-200"
            >
                {{ selectedOption.label }}
            </span>
            <span
                v-else
                class="truncate text-sm text-gray-500 dark:text-gray-400"
            >
                {{ placeholder }}
            </span>
            <svg
                class="ms-auto size-4 shrink-0 text-gray-400 transition dark:text-gray-500"
                :class="{ 'rotate-180': open }"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute z-[100] mt-1 max-h-60 w-full min-w-[10rem] overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            role="listbox"
        >
            <button
                v-if="placeholder && !searchable"
                type="button"
                class="flex w-full items-center px-3 py-2 text-left text-sm text-gray-500 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-900/50"
                :class="{ 'bg-gray-50 font-medium text-gray-700 dark:bg-gray-900/50 dark:text-gray-200': !hasSelection }"
                role="option"
                :aria-selected="!hasSelection"
                @click="select('')"
            >
                {{ placeholder }}
            </button>
            <p
                v-if="searchable && listOptions.length === 0"
                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ noResultsText }}
            </p>
            <button
                v-for="(option, index) in listOptions"
                :key="option.value"
                type="button"
                class="flex w-full items-center px-3 py-2 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-gray-900/50"
                :class="[
                    option.value === String(modelValue)
                        ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300'
                        : 'text-gray-700 dark:text-gray-200',
                    searchable && highlightedIndex === index
                        ? 'bg-gray-50 dark:bg-gray-900/50'
                        : '',
                ]"
                role="option"
                :aria-selected="option.value === String(modelValue)"
                @mouseenter="highlightedIndex = index"
                @click="select(option.value)"
            >
                <span class="truncate">{{ option.label }}</span>
            </button>
        </div>
    </div>
</template>
