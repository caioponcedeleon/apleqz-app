<script setup>
import StatCard from '@/Components/StatCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
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

const props = defineProps({
    statistics: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
});

const { t } = useI18n();
const summary = computed(() => props.statistics.summary);

const applicationChart = computed(() => ({
    labels: props.statistics.application_timeline.map((r) => r.date),
    datasets: [
        {
            label: t('app.dashboard.cumulative'),
            data: props.statistics.application_timeline.map((r) => r.cumulative),
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.3,
        },
        {
            label: t('app.dashboard.daily'),
            data: props.statistics.application_timeline.map((r) => r.daily),
            borderColor: '#10b981',
            tension: 0.3,
        },
    ],
}));

const interviewChart = computed(() => ({
    labels: props.statistics.interview_timeline.map((r) => r.date),
    datasets: [
        {
            label: t('app.dashboard.cumulative'),
            data: props.statistics.interview_timeline.map((r) => r.cumulative),
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: true,
            tension: 0.3,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};
</script>

<template>
    <Head :title="t('app.dashboard.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ t('app.dashboard.title') }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        :label="t('app.dashboard.total_applications')"
                        :value="summary.total_applications"
                    />
                    <StatCard
                        :label="t('app.dashboard.total_rejections')"
                        :value="summary.total_rejections"
                    />
                    <StatCard
                        :label="t('app.dashboard.total_interviews')"
                        :value="summary.total_interviews"
                    />
                    <StatCard
                        :label="t('app.dashboard.total_offers')"
                        :value="summary.total_offers"
                    />
                    <StatCard
                        :label="t('app.dashboard.total_waiting')"
                        :value="summary.total_waiting"
                    />
                    <StatCard
                        :label="t('app.dashboard.total_waiting_to_apply')"
                        :value="summary.total_waiting_to_apply"
                    />
                    <StatCard
                        :label="t('app.dashboard.total_declined_by_me')"
                        :value="summary.total_declined_by_me"
                    />
                    <StatCard
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
                                v-if="statistics.application_timeline.length"
                                :data="applicationChart"
                                :options="chartOptions"
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
                                v-if="statistics.interview_timeline.length"
                                :data="interviewChart"
                                :options="chartOptions"
                            />
                            <p v-else class="text-sm text-gray-500">—</p>
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
                    <div class="overflow-x-auto">
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
                                    v-for="row in statistics.by_area"
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
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
