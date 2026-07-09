<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useOnboardingTour } from '@/composables/useOnboardingTour';
import { computed } from 'vue';

const {
    shouldShow,
    currentStep,
    stepIndex,
    steps,
    isLastStep,
    isNavigating,
    hole,
    overlayPanels,
    highlightStyle,
    tooltipPosition,
    nextStep,
    skipTour,
    t,
} = useOnboardingTour();

const isVisible = computed(() => shouldShow.value && currentStep.value && hole.value && !isNavigating.value);

const stepLabel = computed(() =>
    t('app.onboarding.step_of', {
        current: stepIndex.value + 1,
        total: steps.value.length,
    }),
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="shouldShow && currentStep"
            class="fixed inset-0 z-[200]"
            aria-live="polite"
        >
            <template v-if="isVisible">
                <div
                    v-for="(panel, index) in overlayPanels"
                    :key="index"
                    class="fixed bg-black/70"
                    :style="{
                        top: `${panel.top}px`,
                        left: `${panel.left}px`,
                        width: `${panel.width}px`,
                        height: `${panel.height}px`,
                    }"
                />

                <div
                    class="pointer-events-none fixed rounded-lg ring-2 ring-indigo-400 ring-offset-2 ring-offset-transparent"
                    :style="highlightStyle"
                />

                <div
                    class="absolute z-[210] w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                    :style="{
                        top: `${tooltipPosition.top}px`,
                        left: `${tooltipPosition.left}px`,
                    }"
                    role="dialog"
                    aria-modal="true"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400">
                        {{ stepLabel }}
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ t(currentStep.titleKey) }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ t(currentStep.bodyKey) }}
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="text-sm text-gray-500 transition hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                            @click="skipTour"
                        >
                            {{ t('app.onboarding.skip') }}
                        </button>
                        <PrimaryButton type="button" @click="nextStep">
                            {{ isLastStep ? t('app.onboarding.finish') : t('app.onboarding.next') }}
                        </PrimaryButton>
                    </div>
                </div>
            </template>

            <div
                v-else-if="isNavigating || !hole"
                class="fixed inset-0 bg-black/70"
            />
        </div>
    </Teleport>
</template>
