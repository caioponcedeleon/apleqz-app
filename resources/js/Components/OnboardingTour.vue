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
    tooltipBodyKey,
    canAdvance,
    nextDisabledHintKey,
    nextStep,
    skipTour,
    t,
} = useOnboardingTour();

const showTooltip = computed(
    () => shouldShow.value && currentStep.value && !isNavigating.value,
);

const showSpotlight = computed(() => showTooltip.value && Boolean(hole.value));

const stepLabel = computed(() =>
    t('app.onboarding.step_of', {
        current: stepIndex.value + 1,
        total: steps.value.length,
    }),
);

const nextLabel = computed(() =>
    isLastStep.value ? t('app.onboarding.finish') : t('app.onboarding.next'),
);

const continueDisabled = computed(() => !canAdvance.value);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="shouldShow && currentStep"
            class="pointer-events-none fixed inset-0 z-[200]"
            aria-live="polite"
        >
            <template v-if="showSpotlight">
                <div
                    v-for="(panel, index) in overlayPanels"
                    :key="index"
                    class="pointer-events-none fixed bg-black/70"
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
            </template>

            <div
                v-else-if="showTooltip"
                class="pointer-events-none fixed inset-0 bg-black/70"
            />

            <div
                v-if="showTooltip"
                class="pointer-events-auto fixed z-[210] w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900"
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
                    {{ t(tooltipBodyKey) }}
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="text-sm text-gray-500 transition hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                        @click="skipTour"
                    >
                        {{ t('app.onboarding.skip') }}
                    </button>
                    <span class="group relative inline-flex">
                        <PrimaryButton
                            type="button"
                            :disabled="continueDisabled"
                            :aria-describedby="continueDisabled && nextDisabledHintKey ? 'onboarding-next-hint' : undefined"
                            @click="nextStep"
                        >
                            {{ nextLabel }}
                        </PrimaryButton>
                        <span
                            v-if="continueDisabled && nextDisabledHintKey"
                            class="absolute inset-0 z-10 cursor-not-allowed"
                            :aria-label="t(nextDisabledHintKey)"
                        />
                        <span
                            v-if="continueDisabled && nextDisabledHintKey"
                            id="onboarding-next-hint"
                            role="tooltip"
                            class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 hidden w-52 rounded-md bg-gray-900 px-3 py-2 text-center text-xs leading-snug text-white shadow-lg group-hover:block dark:bg-gray-700"
                        >
                            {{ t(nextDisabledHintKey) }}
                        </span>
                    </span>
                </div>
            </div>

            <div
                v-else-if="isNavigating"
                class="pointer-events-none fixed inset-0 bg-black/70"
            />
        </div>
    </Teleport>
</template>
