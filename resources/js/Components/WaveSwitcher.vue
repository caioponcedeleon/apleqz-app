<script setup>
import WaveCreateModal from '@/Components/WaveCreateModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const showCreateModal = ref(false);

const waves = computed(() => page.props.waves ?? []);
const selectedWaveId = computed(() => page.props.selectedWave?.id ?? '');

const selectWave = (event) => {
    const waveId = event.target.value;

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
        <div
            v-if="waves.length"
            class="flex items-center rounded-lg border border-gray-200 bg-white p-0.5 text-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <label class="sr-only" for="wave-switcher">{{ t('app.waves.select') }}</label>
            <select
                id="wave-switcher"
                :value="selectedWaveId"
                class="max-w-[9rem] cursor-pointer truncate rounded-md border-0 bg-transparent py-1 pe-7 ps-2 text-sm font-medium text-gray-700 focus:ring-indigo-500 sm:max-w-[11rem] dark:text-gray-200"
                @change="selectWave"
            >
                <option v-for="wave in waves" :key="wave.id" :value="wave.id">
                    {{ wave.name }}
                </option>
            </select>
        </div>

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
