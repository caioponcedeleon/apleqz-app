<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    id: { type: String, default: undefined },
    clearAllLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change', 'blur']);

const inputRef = ref(null);
const inputValue = ref('');

const chips = computed(() => parseLines(props.modelValue));

const parseLines = (value) =>
    String(value ?? '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '');

const syncValue = (nextChips) => {
    const serialized = nextChips.join('\n');

    if (serialized !== props.modelValue) {
        emit('update:modelValue', serialized);
        emit('change');
    }
};

const commitInput = () => {
    const trimmed = inputValue.value.trim().replace(/,+$/, '').trim();

    if (trimmed === '') {
        inputValue.value = '';

        return;
    }

    if (!chips.value.includes(trimmed)) {
        syncValue([...chips.value, trimmed]);
    }

    inputValue.value = '';
};

const removeChip = (index) => {
    syncValue(chips.value.filter((_, chipIndex) => chipIndex !== index));
};

const onKeydown = (event) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commitInput();

        return;
    }

    if (event.key === 'Backspace' && inputValue.value === '' && chips.value.length > 0) {
        removeChip(chips.value.length - 1);
    }
};

const onInput = (event) => {
    const value = event.target.value;
    const commaIndex = value.indexOf(',');

    if (commaIndex === -1) {
        return;
    }

    const before = value.slice(0, commaIndex).trim();
    const after = value.slice(commaIndex + 1);

    if (before !== '' && !chips.value.includes(before)) {
        syncValue([...chips.value, before]);
    }

    inputValue.value = after.trimStart();
};

const onBlur = () => {
    commitInput();
    emit('blur');
};

const onKeyup = () => {
    emit('change');
};

const focusInput = () => {
    inputRef.value?.focus();
};

const clearAll = () => {
    inputValue.value = '';

    if (props.modelValue === '') {
        return;
    }

    emit('update:modelValue', '');
    emit('change');
    emit('blur');
};
</script>

<template>
    <div class="space-y-1">
        <div v-if="chips.length > 0 && clearAllLabel" class="flex justify-end">
            <button
                type="button"
                class="text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                @click="clearAll"
            >
                {{ clearAllLabel }}
            </button>
        </div>

        <div
            class="flex min-h-[2.75rem] w-full flex-wrap items-center gap-2 rounded-md border border-gray-300 bg-white px-2 py-2 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
            @click="focusInput"
        >
        <span
            v-for="(chip, index) in chips"
            :key="`${chip}-${index}`"
            class="inline-flex max-w-full items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-1 font-mono text-xs text-indigo-900 dark:bg-indigo-950 dark:text-indigo-100"
        >
            <span class="truncate">{{ chip }}</span>
            <button
                type="button"
                class="rounded-full p-0.5 text-indigo-700 hover:bg-indigo-200 dark:text-indigo-200 dark:hover:bg-indigo-900"
                :aria-label="`Remove ${chip}`"
                @click.stop="removeChip(index)"
            >
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                    />
                </svg>
            </button>
        </span>

        <input
            :id="id"
            ref="inputRef"
            v-model="inputValue"
            type="text"
            class="min-w-[8rem] flex-1 border-0 bg-transparent font-mono text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100 dark:placeholder:text-gray-500"
            :placeholder="chips.length === 0 ? placeholder : ''"
            @keydown="onKeydown"
            @input="onInput"
            @keyup="onKeyup"
            @blur="onBlur"
        />
        </div>
    </div>
</template>
