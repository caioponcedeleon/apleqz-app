<?php

namespace App\Support;

use App\Enums\ApplicationReminderFrequency;
use Carbon\Carbon;

class ReminderSchedule
{
    /**
     * @return list<string>
     */
    public static function timeSlotOptions(): array
    {
        $slots = [];

        for ($hour = 0; $hour < 24; $hour++) {
            foreach ([0, 30] as $minute) {
                $slots[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return $slots;
    }

    /**
     * @return list<int>
     */
    public static function weekdayOptions(): array
    {
        return range(0, 6);
    }

    /**
     * @return list<int>
     */
    public static function dayOfMonthOptions(): array
    {
        return range(1, 31);
    }

    public static function combineDateAndTime(string $date, string $time): Carbon
    {
        return Carbon::parse("{$date} {$time}:00");
    }

    public static function combineDailyTime(string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return Carbon::now()->setTime($hour, $minute, 0);
    }

    public static function combineWeekdayAndTime(int $weekday, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return Carbon::now()
            ->startOfWeek(Carbon::SUNDAY)
            ->addDays($weekday)
            ->setTime($hour, $minute, 0);
    }

    public static function combineDayOfMonthAndTime(int $day, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $now = Carbon::now();
        $day = min($day, $now->daysInMonth);

        return $now->copy()->day($day)->setTime($hour, $minute, 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function combineFromRequest(array $data): Carbon
    {
        $frequency = ApplicationReminderFrequency::from($data['frequency']);
        $time = $data['remind_time'];

        return match ($frequency) {
            ApplicationReminderFrequency::Once => self::combineDateAndTime($data['remind_at'], $time),
            ApplicationReminderFrequency::Daily => self::combineDailyTime($time),
            ApplicationReminderFrequency::Weekly => self::combineWeekdayAndTime((int) $data['remind_weekday'], $time),
            ApplicationReminderFrequency::Monthly => self::combineDayOfMonthAndTime((int) $data['remind_day_of_month'], $time),
        };
    }

    public static function snapToNearestSlot(Carbon $value): Carbon
    {
        $minutes = (int) $value->format('i');
        $roundedMinutes = $minutes < 15 ? 0 : ($minutes < 45 ? 30 : 0);
        $addHour = $minutes >= 45 ? 1 : 0;

        return $value->copy()
            ->startOfHour()
            ->addHours($addHour)
            ->addMinutes($roundedMinutes);
    }
}
