<?php

namespace Cron;

enum CronField
{
    case Minute;
    case Hour;
    case DayOfMonth;
    case Month;
    case DayOfWeek;

    public static function tryFromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Minute => 'minute',
            self::Hour => 'hour',
            self::DayOfMonth => 'day of month',
            self::Month => 'month',
            self::DayOfWeek => 'day of week',
        };
    }

    public function index(): int
    {
        return match ($this) {
            self::Minute => CronExpression::MINUTE,
            self::Hour => CronExpression::HOUR,
            self::DayOfMonth => CronExpression::DAY,
            self::Month => CronExpression::MONTH,
            self::DayOfWeek => CronExpression::WEEKDAY,
        };
    }

    public function rule(): FieldInterface
    {
        return match ($this) {
            self::Minute => new MinutesField(),
            self::Hour => new HoursField(),
            self::DayOfMonth => new DayOfMonthField(),
            self::Month => new MonthField(),
            self::DayOfWeek => new DayOfWeekField()
        };
    }
}
