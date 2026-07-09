<script setup>
import ChipSelect from '@/Components/ChipSelect.vue';
import WaveCreateModal from '@/Components/WaveCreateModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const showCreateModal = ref(false);

const waves = computed(() => page.props.waves ?? []);
const selectedWaveId = computed(() => page.props.selectedWave?.id ?? '');

const waveOptions = computed(() =>
    waves.value.map((wave) => ({
        value: wave.id,
        label: wave.name,
    })),
);

const selectWave = (waveId) => {
    if (!waveId || waveId === selectedWaveId.value) {
        return;
    }

    router.post(
        route('wave.select'),
        { wave_id: waveId },
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="flex items-center gap-1">
        <ChipSelect
            v-if="waves.length"
            id="wave-switcher"
            compact
            class="max-w-[9rem] sm:max-w-[11rem]"
            :model-value="selectedWaveId"
            :options="waveOptions"
            :aria-label="t('app.waves.select')"
            @change="selectWave"
        />

        <Link
            v-else
            :href="route('waves.index')"
            class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-sm font-medium text-amber-900 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-950/60"
        >
            {{ t('app.waves.setup_first') }}
        </Link>

        <button
            type="button"
            class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-base font-semibold leading-none text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            :title="t('app.waves.add')"
            @click="showCreateModal = true"
        >
            +
        </button>

        <WaveCreateModal :show="showCreateModal" @close="showCreateModal = false" />
    </div>
</template>
