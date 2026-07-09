<script setup>
import StatCard from '@/Components/StatCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    buildDashboardPreviewStatistics,
    shouldShowDashboardPreview,
} from '@/composables/useOnboardingPreview';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
);

const { t } = useI18n();
const page = usePage();
const props = defineProps({
    statistics: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
});

const showPreview = computed(() => shouldShowDashboardPreview(page, props.statistics));
const displayStatistics = computed(() =>
    showPreview.value ? buildDashboardPreviewStatistics(t) : props.statistics,
);

const summary = computed(() => displayStatistics.value.summary);
const byArea = computed(() => displayStatistics.value.by_area ?? []);
const areaLabels = computed(() => byArea.value.map((row) => row.area_name));
const hasAreaData = computed(() => byArea.value.length > 0);

const applicationChart = computed(() => ({
    labels: displayStatistics.value.application_timeline.map((r) => r.date),
    datasets: [
        {
            label: t('app.dashboard.cumulative'),
            data: displayStatistics.value.application_timeline.map((r) => r.cumulative),
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.3,
        },
        {
            label: t('app.dashboard.daily'),
            data: displayStatistics.value.application_timeline.map((r) => r.daily),
            borderColor: '#10b981',
            tension: 0.3,
        },
    ],
}));

const interviewChart = computed(() => ({
    labels: displayStatistics.value.interview_timeline.map((r) => r.date),
    datasets: [
        {
            label: t('app.dashboard.cumulative'),
            data: displayStatistics.value.interview_timeline.map((r) => r.cumulative),
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: true,
            tension: 0.3,
        },
    ],
}));

const statusByAreaChart = computed(() => ({
    labels: areaLabels.value,
    datasets: [
        {
            label: t('app.status.a_candidatar'),
            data: byArea.value.map((row) => row.waiting_to_apply),
            backgroundColor: '#0ea5e9',
            stack: 'status',
        },
        {
            label: t('app.status.esperando'),
            data: byArea.value.map((row) => row.waiting),
            backgroundColor: '#f59e0b',
            stack: 'status',
        },
        {
            label: t('app.status.rejeitado'),
            data: byArea.value.map((row) => row.rejections),
            backgroundColor: '#ef4444',
            stack: 'status',
        },
        {
            label: t('app.status.oferta'),
            data: byArea.value.map((row) => row.offers),
            backgroundColor: '#10b981',
            stack: 'status',
        },
        {
            label: t('app.status.recusado'),
            data: byArea.value.map((row) => row.declined_by_me),
            backgroundColor: '#a855f7',
            stack: 'status',
        },
        {
            label: t('app.status.retirada'),
            data: byArea.value.map((row) => row.withdrawn ?? 0),
            backgroundColor: '#64748b',
            stack: 'status',
        },
        {
            label: t('app.status.cancelada'),
            data: byArea.value.map((row) => row.cancelled ?? 0),
            backgroundColor: '#71717a',
            stack: 'status',
        },
    ],
}));

const offersByAreaChart = computed(() => ({
    labels: areaLabels.value,
    datasets: [
        {
            label: t('app.dashboard.offers_by_area'),
            data: byArea.value.map((row) => row.offers),
            backgroundColor: '#10b981',
        },
    ],
}));

const interviewsByAreaChart = computed(() => ({
    labels: areaLabels.value,
    datasets: [
        {
            label: t('app.dashboard.interviews_by_area'),
            data: byArea.value.map((row) => row.interviews),
            backgroundColor: '#10b981',
        },
    ],
}));

const interviewsByAreaRelativeChart = computed(() => ({
    labels: areaLabels.value,
    datasets: [
        {
            label: t('app.dashboard.interviews_by_area_relative'),
            data: byArea.value.map((row) => Math.round(row.pct_interviews * 100)),
            backgroundColor: '#3b82f6',
        },
    ],
}));

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};

const stackedBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: {
        x: { stacked: true },
        y: {
            stacked: true,
            beginAtZero: true,
            ticks: { stepSize: 1, precision: 0 },
        },
    },
};

const countBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1, precision: 0 },
        },
    },
};

const percentBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: (value) => `${value}%`,
            },
        },
    },
};
</script>

<template>
    <Head :title="t('app.dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ t('app.dashboard.title') }}
                <span
                    v-if="page.props.selectedWave"
                    class="mt-1 block text-sm font-normal text-gray-500 dark:text-gray-400"
                >
                    {{ page.props.selectedWave.name }}
                </span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <div
                    v-if="showPreview"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-indigo-200 bg-indigo-50/70 px-4 py-3 dark:border-indigo-900/60 dark:bg-indigo-950/20"
                >
                    <span
                        class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
                    >
                        {{ t('app.onboarding.preview.badge') }}
                    </span>
                    <p class="text-sm text-indigo-900/80 dark:text-indigo-200/80">
                        {{ t('app.onboarding.preview.disclaimer') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        :label="t('app.dashboard.total_applications')"
                        :value="summary.total_applications"
                    />
                    <StatCard
                        tone="red"
                        :label="t('app.dashboard.total_rejections')"
                        :value="summary.total_rejections"
                        :percentage="summary.pct_rejections"
                    />
                    <StatCard
                        tone="amber"
                        :label="t('app.dashboard.total_interviews')"
                        :value="summary.total_interviews"
                        :percentage="summary.pct_interviews"
                    />
                    <StatCard
                        tone="emerald"
                        :label="t('app.dashboard.total_offers')"
                        :value="summary.total_offers"
                        :percentage="summary.pct_offers"
                    />
                    <StatCard
                        tone="amber"
                        :label="t('app.dashboard.total_waiting')"
                        :value="summary.total_waiting"
                        :percentage="summary.pct_waiting"
                    />
                    <StatCard
                        tone="slate"
                        :label="t('app.dashboard.total_waiting_to_apply')"
                        :value="summary.total_waiting_to_apply"
                        :percentage="summary.pct_waiting_to_apply"
                    />
                    <StatCard
                        tone="red"
                        :label="t('app.dashboard.total_declined_by_me')"
                        :value="summary.total_declined_by_me"
                        :percentage="summary.pct_declined_by_me"
                    />
                    <StatCard
                        tone="red"
                        :label="t('app.dashboard.avg_days_to_rejection')"
                        :value="summary.avg_days_to_rejection ?? '—'"
                    />
                    <StatCard
                        :label="t('app.dashboard.avg_applications_per_day')"
                        :value="summary.avg_applications_per_day ?? '—'"
                    />
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.applications_over_time') }}
                        </h3>
                        <div class="h-64">
                            <Line
                                v-if="displayStatistics.application_timeline.length"
                                :data="applicationChart"
                                :options="lineChartOptions"
                            />
                            <p v-else class="text-sm text-gray-500">—</p>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.interviews_over_time') }}
                        </h3>
                        <div class="h-64">
                            <Line
                                v-if="displayStatistics.interview_timeline.length"
                                :data="interviewChart"
                                :options="lineChartOptions"
                            />
                            <p v-else class="text-sm text-gray-500">—</p>
                        </div>
                    </div>
                </div>

                <div v-if="hasAreaData" class="grid gap-6 lg:grid-cols-2">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.status_by_area') }}
                        </h3>
                        <div class="h-72">
                            <Bar
                                :data="statusByAreaChart"
                                :options="stackedBarOptions"
                            />
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.interviews_by_area') }}
                        </h3>
                        <div class="h-72">
                            <Bar
                                :data="interviewsByAreaChart"
                                :options="countBarOptions"
                            />
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.offers_by_area') }}
                        </h3>
                        <div class="h-72">
                            <Bar
                                :data="offersByAreaChart"
                                :options="countBarOptions"
                            />
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.interviews_by_area_relative') }}
                        </h3>
                        <div class="h-72">
                            <Bar
                                :data="interviewsByAreaRelativeChart"
                                :options="percentBarOptions"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ t('app.dashboard.by_area') }}
                        </h3>
                    </div>
                    <div v-if="hasAreaData" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600">
                                        {{ t('app.applications.area') }}
                                    </th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-600">
                                        {{ t('app.dashboard.total_applications') }}
                                    </th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-600">
                                        {{ t('app.dashboard.total_rejections') }}
                                    </th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-600">
                                        {{ t('app.dashboard.total_interviews') }}
                                    </th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-600">
                                        {{ t('app.dashboard.total_waiting') }}
                                    </th>
                                    <th class="px-4 py-3 text-right font-medium text-gray-600">
                                        {{ t('app.dashboard.total_offers') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="row in byArea"
                                    :key="row.area_id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-900/30"
                                >
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ row.area_name }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ row.applied }}</td>
                                    <td class="px-4 py-3 text-right">
                                        {{ row.rejections }}
                                        <span class="text-gray-400">
                                            ({{ Math.round(row.pct_rejections * 100) }}%)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ row.interviews }}
                                        <span class="text-gray-400">
                                            ({{ Math.round(row.pct_interviews * 100) }}%)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ row.waiting }}</td>
                                    <td class="px-4 py-3 text-right">{{ row.offers }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-8 text-center text-sm text-gray-500">
                        —
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
