import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const STORAGE_KEY = 'apleqz_onboarding_step';
const PADDING = 8;

export function buildOnboardingSteps() {
    return [
        {
            id: 'areas',
            routeName: 'areas.index',
            routeParams: [],
            target: '[data-onboarding="area-manager"]',
            titleKey: 'app.onboarding.areas.title',
            bodyKey: 'app.onboarding.areas.body',
        },
        {
            id: 'waves',
            routeName: 'waves.index',
            routeParams: [],
            target: '[data-onboarding="wave-manager"]',
            titleKey: 'app.onboarding.waves.title',
            bodyKey: 'app.onboarding.waves.body',
        },
        {
            id: 'create_application',
            routeName: 'applications.create',
            routeParams: [],
            target: '[data-onboarding="application-form"]',
            titleKey: 'app.onboarding.create_application.title',
            bodyKey: 'app.onboarding.create_application.body',
        },
        {
            id: 'manage_application',
            routeName: 'applications.index',
            routeParams: [],
            target: '[data-onboarding="applications-list"]',
            titleKey: 'app.onboarding.manage_application_list.title',
            bodyKey: 'app.onboarding.manage_application_list.body',
        },
    ];
}

export function hasApplicationWaves(page) {
    if (page.props.onboarding?.hasWaves) {
        return true;
    }

    if (page.props.selectedWave) {
        return true;
    }

    if ((page.props.waves?.length ?? 0) > 0) {
        return true;
    }

    if (typeof document !== 'undefined') {
        const manager = document.querySelector('[data-onboarding="wave-manager"]');

        if (manager?.querySelector('ul li')) {
            return true;
        }
    }

    return false;
}

export function stepPrerequisites(page, step) {
    if (!step) {
        return true;
    }

    if (step.id === 'create_application' || step.id === 'manage_application') {
        return hasApplicationWaves(page);
    }

    return true;
}

export function normalizeStepIndex(page, steps, index) {
    let normalized = Math.max(0, Math.min(index, steps.length - 1));

    while (normalized > 0 && !stepPrerequisites(page, steps[normalized])) {
        normalized -= 1;
    }

    return normalized;
}

export function prerequisiteMessageKey(step) {
    if (!step) {
        return null;
    }

    if (step.id === 'create_application' || step.id === 'manage_application') {
        return 'app.onboarding.waves.create_first';
    }

    return null;
}

export function stepRouteHref(step) {
    return step.routeParams.length
        ? route(step.routeName, ...step.routeParams)
        : route(step.routeName);
}

export function stepRoutePath(step) {
    return new URL(stepRouteHref(step), window.location.origin).pathname;
}

export function isOnStepRoute(step, pageUrl = null) {
    if (!step) {
        return false;
    }

    if (step.routeName === 'applications.edit' && step.routeParams.length) {
        return route().current('applications.edit', step.routeParams[0]);
    }

    if (route().current(step.routeName)) {
        return true;
    }

    const currentPath = pageUrl ?? window.location.pathname;

    return currentPath === stepRoutePath(step);
}

export function readOnboardingStepIndex() {
    if (typeof window === 'undefined') {
        return 0;
    }

    const raw = localStorage.getItem(STORAGE_KEY);
    const parsed = Number.parseInt(raw ?? '0', 10);

    if (Number.isNaN(parsed) || parsed < 0) {
        return 0;
    }

    return parsed;
}

export function writeOnboardingStepIndex(index) {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(STORAGE_KEY, String(index));
}

export function clearOnboardingStepIndex() {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.removeItem(STORAGE_KEY);
}

export function useOnboardingTour() {
    const page = usePage();
    const { t } = useI18n();

    const steps = computed(() => buildOnboardingSteps());
    const stepIndex = ref(
        normalizeStepIndex(page, steps.value, readOnboardingStepIndex()),
    );
    writeOnboardingStepIndex(stepIndex.value);

    const hole = ref(null);
    const tooltipPosition = ref({ top: 0, left: 0 });
    const centeredTooltip = ref(false);
    const prerequisiteHint = ref(null);
    const isNavigating = ref(false);
    let targetPollId = null;
    let resizeObserver = null;

    const shouldShow = computed(() => Boolean(page.props.onboarding?.show));

    const currentStep = computed(() => steps.value[stepIndex.value] ?? null);

    const isLastStep = computed(() => stepIndex.value >= steps.value.length - 1);

    const canAdvance = computed(() => {
        if (isLastStep.value) {
            return true;
        }

        if (currentStep.value?.id === 'waves' && !hasApplicationWaves(page)) {
            return false;
        }

        const next = steps.value[stepIndex.value + 1];

        return !next || stepPrerequisites(page, next);
    });

    const nextDisabledHintKey = computed(() =>
        currentStep.value?.id === 'waves' && !hasApplicationWaves(page)
            ? 'app.onboarding.next_requires_wave'
            : null,
    );

    const tooltipBodyKey = computed(() => {
        if (prerequisiteHint.value) {
            return prerequisiteHint.value;
        }

        if (currentStep.value?.id === 'waves' && !hasApplicationWaves(page)) {
            return 'app.onboarding.waves.create_first';
        }

        return currentStep.value?.bodyKey ?? '';
    });

    const overlayPanels = computed(() => {
        if (!hole.value) {
            return [];
        }

        const { x, y, width, height } = hole.value;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const left = Math.max(0, x - PADDING);
        const top = Math.max(0, y - PADDING);
        const right = Math.min(viewportWidth, x + width + PADDING);
        const bottom = Math.min(viewportHeight, y + height + PADDING);

        return [
            { top: 0, left: 0, width: viewportWidth, height: top },
            { top, left: 0, width: left, height: bottom - top },
            { top, left: right, width: viewportWidth - right, height: bottom - top },
            { top: bottom, left: 0, width: viewportWidth, height: viewportHeight - bottom },
        ];
    });

    const highlightStyle = computed(() => {
        if (!hole.value) {
            return {};
        }

        const { x, y, width, height } = hole.value;

        return {
            top: `${y - PADDING}px`,
            left: `${x - PADDING}px`,
            width: `${width + PADDING * 2}px`,
            height: `${height + PADDING * 2}px`,
        };
    });

    const updateTooltipPosition = () => {
        const tooltipWidth = 320;
        const margin = 16;

        if (!hole.value) {
            centeredTooltip.value = true;
            tooltipPosition.value = {
                top: Math.max(margin, window.innerHeight / 2 - 120),
                left: Math.max(margin, window.innerWidth / 2 - tooltipWidth / 2),
            };

            return;
        }

        centeredTooltip.value = false;

        const { x, y, width, height } = hole.value;
        const spaceBelow = window.innerHeight - (y + height + PADDING);
        const spaceAbove = y - PADDING;

        if (spaceBelow >= 180 || spaceBelow >= spaceAbove) {
            tooltipPosition.value = {
                top: y + height + PADDING + margin,
                left: Math.min(
                    Math.max(margin, x + width / 2 - tooltipWidth / 2),
                    window.innerWidth - tooltipWidth - margin,
                ),
            };
        } else {
            tooltipPosition.value = {
                top: Math.max(margin, y - PADDING - margin - 160),
                left: Math.min(
                    Math.max(margin, x + width / 2 - tooltipWidth / 2),
                    window.innerWidth - tooltipWidth - margin,
                ),
            };
        }
    };

    const measureTarget = () => {
        const step = currentStep.value;

        if (!step || !shouldShow.value) {
            hole.value = null;
            return false;
        }

        const element = document.querySelector(step.target);

        if (!element) {
            hole.value = null;
            updateTooltipPosition();
            return false;
        }

        const rect = element.getBoundingClientRect();

        if (rect.width <= 0 || rect.height <= 0) {
            hole.value = null;
            updateTooltipPosition();
            return false;
        }

        hole.value = {
            x: rect.left,
            y: rect.top,
            width: rect.width,
            height: rect.height,
        };

        updateTooltipPosition();

        return true;
    };

    const stopTargetPolling = () => {
        if (targetPollId !== null) {
            window.clearInterval(targetPollId);
            targetPollId = null;
        }
    };

    const startTargetPolling = () => {
        stopTargetPolling();

        if (!shouldShow.value || !currentStep.value) {
            return;
        }

        const attempt = () => {
            if (measureTarget()) {
                stopTargetPolling();
            }
        };

        attempt();
        targetPollId = window.setInterval(attempt, 150);
    };

    const disconnectResizeObserver = () => {
        resizeObserver?.disconnect();
        resizeObserver = null;
    };

    const connectResizeObserver = () => {
        disconnectResizeObserver();

        const step = currentStep.value;

        if (!step) {
            return;
        }

        const element = document.querySelector(step.target);

        if (!element || typeof ResizeObserver === 'undefined') {
            return;
        }

        resizeObserver = new ResizeObserver(() => {
            measureTarget();
        });
        resizeObserver.observe(element);
    };

    const ensureStepRoute = async () => {
        const step = currentStep.value;

        if (!step) {
            isNavigating.value = false;
            return;
        }

        if (!stepPrerequisites(page, step)) {
            const wavesStepIndex = steps.value.findIndex((item) => item.id === 'waves');

            if (wavesStepIndex >= 0 && stepIndex.value !== wavesStepIndex) {
                stepIndex.value = wavesStepIndex;
                writeOnboardingStepIndex(wavesStepIndex);
            }

            prerequisiteHint.value = prerequisiteMessageKey(step);
            isNavigating.value = false;
            await nextTick();
            startTargetPolling();
            return;
        }

        prerequisiteHint.value = null;

        if (isOnStepRoute(step, page.url)) {
            isNavigating.value = false;
            await nextTick();
            startTargetPolling();
            connectResizeObserver();
            return;
        }

        isNavigating.value = true;
        hole.value = null;
        updateTooltipPosition();

        router.visit(stepRouteHref(step), {
            preserveState: false,
            preserveScroll: false,
            onFinish: () => {
                isNavigating.value = false;

                if (!isOnStepRoute(step, window.location.pathname)) {
                    const wavesStepIndex = steps.value.findIndex((item) => item.id === 'waves');

                    if (wavesStepIndex >= 0 && !hasApplicationWaves(page)) {
                        stepIndex.value = wavesStepIndex;
                        writeOnboardingStepIndex(wavesStepIndex);
                        prerequisiteHint.value = prerequisiteMessageKey(step);
                    }

                    startTargetPolling();
                    return;
                }

                startTargetPolling();
                connectResizeObserver();
            },
        });
    };

    const completeOnboarding = () => {
        clearOnboardingStepIndex();
        hole.value = null;
        stopTargetPolling();
        disconnectResizeObserver();

        router.post(route('onboarding.complete'), {}, {
            preserveScroll: true,
        });
    };

    const goToStep = async (index) => {
        stepIndex.value = index;
        writeOnboardingStepIndex(index);
        disconnectResizeObserver();
        await ensureStepRoute();
    };

    const nextStep = () => {
        if (isLastStep.value) {
            completeOnboarding();
            return;
        }

        if (!canAdvance.value) {
            return;
        }

        prerequisiteHint.value = null;
        goToStep(stepIndex.value + 1);
    };

    const skipTour = () => {
        completeOnboarding();
    };

    const onWindowChange = () => {
        measureTarget();
    };

    watch(
        () => [
            shouldShow.value,
            page.url,
            page.props.onboarding?.hasWaves,
            page.props.selectedWave?.id,
            page.props.waves?.length,
        ],
        async () => {
            if (!shouldShow.value) {
                hole.value = null;
                stopTargetPolling();
                disconnectResizeObserver();
                return;
            }

            if (hasApplicationWaves(page)) {
                prerequisiteHint.value = null;
            }

            await ensureStepRoute();
        },
        { immediate: true },
    );

    onMounted(() => {
        window.addEventListener('resize', onWindowChange);
        window.addEventListener('scroll', onWindowChange, true);
    });

    onUnmounted(() => {
        stopTargetPolling();
        disconnectResizeObserver();
        window.removeEventListener('resize', onWindowChange);
        window.removeEventListener('scroll', onWindowChange, true);
    });

    return {
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
        centeredTooltip,
        tooltipBodyKey,
        canAdvance,
        nextDisabledHintKey,
        nextStep,
        skipTour,
        t,
    };
}
