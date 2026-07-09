export function shouldShowDashboardPreview(page, statistics) {
    return Boolean(page.props.onboarding?.show)
        && (statistics?.summary?.total_applications ?? 0) === 0;
}

export function shouldShowApplicationsPreview(page, applications) {
    return Boolean(page.props.onboarding?.show)
        && (applications?.data?.length ?? 0) === 0;
}

export function buildDashboardPreviewStatistics(t) {
    const applicationTimeline = [
        { date: '2026-01-06', daily: 1, cumulative: 2 },
        { date: '2026-01-13', daily: 2, cumulative: 4 },
        { date: '2026-01-20', daily: 2, cumulative: 6 },
        { date: '2026-01-27', daily: 3, cumulative: 9 },
        { date: '2026-02-03', daily: 3, cumulative: 12 },
    ];

    const interviewTimeline = [
        { date: '2026-01-13', daily: 0, cumulative: 1 },
        { date: '2026-01-20', daily: 1, cumulative: 2 },
        { date: '2026-01-27', daily: 1, cumulative: 3 },
        { date: '2026-02-03', daily: 1, cumulative: 4 },
    ];

    return {
        summary: {
            total_applications: 12,
            total_rejections: 3,
            total_interviews: 4,
            total_offers: 1,
            total_waiting: 4,
            total_waiting_to_apply: 2,
            total_declined_by_me: 1,
            avg_days_to_rejection: 18,
            avg_applications_per_day: 1.7,
            pct_rejections: 0.25,
            pct_interviews: 0.3333,
            pct_offers: 0.0833,
            pct_waiting: 0.3333,
            pct_waiting_to_apply: 0.1667,
            pct_declined_by_me: 0.0833,
        },
        application_timeline: applicationTimeline,
        interview_timeline: interviewTimeline,
        by_area: [
            {
                area_id: 'preview-1',
                area_name: t('app.onboarding.preview.area'),
                applied: 7,
                rejections: 2,
                interviews: 3,
                waiting: 2,
                offers: 1,
                declined_by_me: 0,
                withdrawn: 0,
                cancelled: 0,
                pct_rejections: 0.29,
                pct_interviews: 0.43,
            },
            {
                area_id: 'preview-2',
                area_name: t('app.onboarding.preview.area_secondary'),
                applied: 5,
                rejections: 1,
                interviews: 1,
                waiting: 2,
                offers: 0,
                declined_by_me: 1,
                withdrawn: 0,
                cancelled: 0,
                pct_rejections: 0.2,
                pct_interviews: 0.2,
            },
        ],
    };
}

export function buildApplicationsPreviewRows(t) {
    return [
        {
            id: 'preview-1',
            position: t('app.onboarding.preview.applications.0.position'),
            company: t('app.onboarding.preview.applications.0.company'),
            wave: { name: t('app.onboarding.preview.wave') },
            area: { name: t('app.onboarding.preview.area') },
            applied_at: '2026-03-01',
            status: 'esperando',
            is_favourite: true,
        },
        {
            id: 'preview-2',
            position: t('app.onboarding.preview.applications.1.position'),
            company: t('app.onboarding.preview.applications.1.company'),
            wave: { name: t('app.onboarding.preview.wave') },
            area: { name: t('app.onboarding.preview.area_secondary') },
            applied_at: '2026-02-20',
            status: 'oferta',
            is_favourite: false,
        },
        {
            id: 'preview-3',
            position: t('app.onboarding.preview.applications.2.position'),
            company: t('app.onboarding.preview.applications.2.company'),
            wave: { name: t('app.onboarding.preview.wave') },
            area: { name: t('app.onboarding.preview.area') },
            applied_at: '2026-02-10',
            status: 'a_candidatar',
            is_favourite: false,
        },
    ];
}
