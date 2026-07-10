<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    watchKey: { type: [String, Number, Boolean, null], default: null },
});

const topRef = ref(null);
const bottomRef = ref(null);
const contentRef = ref(null);
const spacerWidth = ref(0);

let syncing = false;
let resizeObserver;

const measure = () => {
    spacerWidth.value = contentRef.value?.scrollWidth ?? 0;
};

const syncFromTop = () => {
    if (syncing || !topRef.value || !bottomRef.value) {
        return;
    }

    syncing = true;
    bottomRef.value.scrollLeft = topRef.value.scrollLeft;
    syncing = false;
};

const syncFromBottom = () => {
    if (syncing || !topRef.value || !bottomRef.value) {
        return;
    }

    syncing = true;
    topRef.value.scrollLeft = bottomRef.value.scrollLeft;
    syncing = false;
};

onMounted(() => {
    measure();

    if (contentRef.value) {
        resizeObserver = new ResizeObserver(() => measure());
        resizeObserver.observe(contentRef.value);
    }
});

onUnmounted(() => {
    resizeObserver?.disconnect();
});

watch(
    () => props.watchKey,
    () => nextTick(measure),
);
</script>

<template>
    <div>
        <div
            ref="topRef"
            class="h-4 overflow-x-auto overflow-y-hidden border-b border-gray-200 dark:border-gray-700"
            @scroll="syncFromTop"
        >
            <div class="h-px" :style="{ width: `${spacerWidth}px` }" />
        </div>
        <div
            ref="bottomRef"
            class="overflow-x-auto overscroll-x-contain"
            @scroll="syncFromBottom"
        >
            <div ref="contentRef">
                <slot />
            </div>
        </div>
    </div>
</template>
