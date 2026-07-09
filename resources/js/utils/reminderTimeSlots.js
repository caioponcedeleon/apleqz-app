export const REMINDER_TIME_SLOTS = Array.from({ length: 48 }, (_, index) => {
    const hour = String(Math.floor(index / 2)).padStart(2, '0');
    const minute = index % 2 === 0 ? '00' : '30';

    return `${hour}:${minute}`;
});

export const REMINDER_WEEKDAYS = ['0', '1', '2', '3', '4', '5', '6'];

export const REMINDER_DAYS_OF_MONTH = Array.from({ length: 31 }, (_, index) => String(index + 1));

export function defaultReminderTime() {
    const now = new Date();
    const minutes = now.getMinutes();
    const roundedMinutes = minutes < 30 ? 30 : 0;
    const hour = minutes < 30 ? now.getHours() : now.getHours() + 1;
    const normalizedHour = hour >= 24 ? 0 : hour;

    return `${String(normalizedHour).padStart(2, '0')}:${String(roundedMinutes).padStart(2, '0')}`;
}

function snapTimeToSlot(hours, minutes) {
    const roundedMinutes = minutes < 15 ? 0 : (minutes < 45 ? 30 : 0);
    let hour = minutes >= 45 ? hours + 1 : hours;

    if (hour >= 24) {
        hour = 0;
    }

    return `${String(hour).padStart(2, '0')}:${String(roundedMinutes).padStart(2, '0')}`;
}

export function splitReminderDateTime(value) {
    if (!value) {
        return { date: '', time: defaultReminderTime() };
    }

    const date = new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return {
        date: `${year}-${month}-${day}`,
        time: snapTimeToSlot(date.getHours(), date.getMinutes()),
    };
}

export function splitReminderSchedule(remindAt, frequency) {
    const parts = splitReminderDateTime(remindAt);
    const date = remindAt ? new Date(remindAt) : new Date();

    return {
        remind_at: parts.date,
        remind_time: parts.time,
        remind_weekday: String(date.getDay()),
        remind_day_of_month: String(date.getDate()),
        frequency,
    };
}

export function formatReminderDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export function formatReminderSchedule(remindAt, frequency, t) {
    if (!remindAt) {
        return '—';
    }

    const parts = splitReminderDateTime(remindAt);

    if (frequency === 'daily') {
        return t('app.notifications.schedule_daily', { time: parts.time });
    }

    if (frequency === 'weekly') {
        const weekday = String(new Date(remindAt).getDay());

        return t('app.notifications.schedule_weekly', {
            day: t(`app.notifications.weekdays.${weekday}`),
            time: parts.time,
        });
    }

    if (frequency === 'monthly') {
        const day = String(new Date(remindAt).getDate());

        return t('app.notifications.schedule_monthly', {
            day,
            time: parts.time,
        });
    }

    return formatReminderDateTime(remindAt);
}

export function reminderPayloadFromDraft(draft) {
    const payload = {
        type: draft.type,
        frequency: draft.frequency,
        remind_time: draft.remind_time,
        custom_message: draft.custom_message?.trim() || null,
        application_moment_id: draft.application_moment_id || null,
        is_active: draft.is_active ?? true,
    };

    if (draft.frequency === 'once') {
        payload.remind_at = draft.remind_at;
    }

    if (draft.frequency === 'weekly') {
        payload.remind_weekday = Number(draft.remind_weekday);
    }

    if (draft.frequency === 'monthly') {
        payload.remind_day_of_month = Number(draft.remind_day_of_month);
    }

    return payload;
}

export function defaultReminderDraft(reminderTypes = [], reminderFrequencies = []) {
    const now = new Date();

    return {
        type: reminderTypes[0] ?? 'check_in',
        frequency: reminderFrequencies[0] ?? 'once',
        remind_at: '',
        remind_time: defaultReminderTime(),
        remind_weekday: String(now.getDay()),
        remind_day_of_month: String(now.getDate()),
        custom_message: '',
        application_moment_id: '',
        is_active: true,
    };
}

export function canSubmitReminderDraft(draft) {
    if (!draft.remind_time) {
        return false;
    }

    if (draft.frequency === 'once' && !draft.remind_at) {
        return false;
    }

    if (draft.frequency === 'weekly' && draft.remind_weekday === '') {
        return false;
    }

    if (draft.frequency === 'monthly' && !draft.remind_day_of_month) {
        return false;
    }

    if (draft.type === 'custom' && !draft.custom_message?.trim()) {
        return false;
    }

    return true;
}
