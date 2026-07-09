import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const STORAGE_KEY = 'apleqz_onboarding_step';
const PADDING = 8;

export function buildOnboardingSteps(page) {
    const manageApplicationId = page.props.onboarding?.manageApplicationId ?? null;

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
            routeName: manageApplicationId ? 'applications.edit' : 'applications.index',
            routeParams: manageApplicationId ? [manageApplicationId] : [],
            target: manageApplicationId
                ? '[data-onboarding="application-manage"]'
                : '[data-onboarding="applications-list"]',
            titleKey: manageApplicationId
                ? 'app.onboarding.manage_application.title'
                : 'app.onboarding.manage_application_list.title',
            bodyKey: manageApplicationId
                ? 'app.onboarding.manage_application.body'
                : 'app.onboarding.manage_application_list.body',
        },
    ];
}

export function stepRouteHref(step) {
    return step.routeParams.length
        ? route(step.routeName, ...step.routeParams)
        : route(step.routeName);
}

export function isOnStepRoute(step) {
    if (!step) {
        return false;
    }

    if (step.routeName === 'applications.edit' && step.routeParams.length) {
        return route().current('applications.edit', step.routeParams[0]);
    }

    return route().current(step.routeName);
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

    const stepIndex = ref(readOnboardingStepIndex());
    const hole = ref(null);
    const tooltipPosition = ref({ top: 0, left: 0 });
    const tooltipPlacement = ref('bottom');
    const isNavigating = ref(false);
    let targetPollId = null;
    let resizeObserver = null;

    const shouldShow = computed(() => Boolean(page.props.onboarding?.show));

    const steps = computed(() => buildOnboardingSteps(page));

    const currentStep = computed(() => steps.value[stepIndex.value] ?? null);

    const isLastStep = computed(() => stepIndex.value >= steps.value.length - 1);

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
        if (!hole.value) {
            return;
        }

        const { x, y, width, height } = hole.value;
        const tooltipWidth = 320;
        const margin = 16;
        const spaceBelow = window.innerHeight - (y + height + PADDING);
        const spaceAbove = y - PADDING;

        if (spaceBelow >= 180 || spaceBelow >= spaceAbove) {
            tooltipPlacement.value = 'bottom';
            tooltipPosition.value = {
                top: y + height + PADDING + margin,
                left: Math.min(
                    Math.max(margin, x + width / 2 - tooltipWidth / 2),
                    window.innerWidth - tooltipWidth - margin,
                ),
            };
        } else {
            tooltipPlacement.value = 'top';
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
            return false;
        }

        const rect = element.getBoundingClientRect();

        if (rect.width <= 0 || rect.height <= 0) {
            hole.value = null;
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

        if (!step || isOnStepRoute(step)) {
            isNavigating.value = false;
            await nextTick();
            startTargetPolling();
            connectResizeObserver();
            return;
        }

        isNavigating.value = true;
        hole.value = null;

        router.visit(stepRouteHref(step), {
            preserveState: false,
            preserveScroll: false,
            onFinish: () => {
                isNavigating.value = false;
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

        goToStep(stepIndex.value + 1);
    };

    const skipTour = () => {
        completeOnboarding();
    };

    const onWindowChange = () => {
        measureTarget();
    };

    watch(
        () => [shouldShow.value, page.url, stepIndex.value, page.props.onboarding?.manageApplicationId],
        async () => {
            if (!shouldShow.value) {
                hole.value = null;
                stopTargetPolling();
                disconnectResizeObserver();
                return;
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
        tooltipPlacement,
        nextStep,
        skipTour,
        t,
    };
}
