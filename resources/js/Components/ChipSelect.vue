<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    id: { type: String, default: undefined },
    ariaLabel: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open = ref(false);
const root = ref(null);

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

const select = (value) => {
    emit('update:modelValue', value);
    emit('change', value);
    open.value = false;
};

const toggle = () => {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;
};

const close = () => {
    open.value = false;
};

const onClickOutside = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
};

const onEscape = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

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
        <button
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
            class="absolute z-50 mt-1 max-h-60 w-full min-w-[10rem] overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            role="listbox"
        >
            <button
                v-if="placeholder"
                type="button"
                class="flex w-full items-center px-3 py-2 text-left text-sm text-gray-500 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-900/50"
                :class="{ 'bg-gray-50 font-medium text-gray-700 dark:bg-gray-900/50 dark:text-gray-200': !hasSelection }"
                role="option"
                :aria-selected="!hasSelection"
                @click="select('')"
            >
                {{ placeholder }}
            </button>
            <button
                v-for="option in normalizedOptions"
                :key="option.value"
                type="button"
                class="flex w-full items-center px-3 py-2 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-gray-900/50"
                :class="option.value === String(modelValue)
                    ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300'
                    : 'text-gray-700 dark:text-gray-200'"
                role="option"
                :aria-selected="option.value === String(modelValue)"
                @click="select(option.value)"
            >
                <span class="truncate">{{ option.label }}</span>
            </button>
        </div>
    </div>
</template>
