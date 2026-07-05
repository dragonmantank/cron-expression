<?php

declare(strict_types=1);

namespace Cron;

use BadMethodCallException;
use InvalidArgumentException;

/**
 * @method self everyMinute(int $step)
 * @method self everyHour(int $step)
 * @method self everyDayOfMonth(int $step)
 * @method self everyMonth(int $step)
 * @method self everyDayOfWeek(int $step)
 * @method self rangeMinute(string|int $start, string|int $end)
 * @method self rangeHour(string|int $start, string|int $end)
 * @method self rangeDayOfMonth(string|int $start, string|int $end)
 * @method self rangeMonth(string|int $start, string|int $end)
 * @method self rangeDayOfWeek(string|int $start, string|int $end)
 * @method self everyInRangeMinute(string|int $start, string|int $end, int $step)
 * @method self everyInRangeHour(string|int $start, string|int $end, int $step)
 * @method self everyInRangeDayOfMonth(string|int $start, string|int $end, int $step)
 * @method self everyInRangeMonth(string|int $start, string|int $end, int $step)
 * @method self everyInRangeDayOfWeek(string|int $start, string|int $end, int $step)
 * @method self listMinute(iterable $list)
 * @method self listHour(iterable $list)
 * @method self listDayOfMonth(iterable $list)
 * @method self listMonth(iterable $list)
 * @method self listDayOfWeek(iterable $list)
 * @method static self yearly()
 * @method static self annually()
 * @method static self monthly()
 * @method static self weekly()
 * @method static self daily()
 * @method static self hourly()
 * @method static self midnight()
 */
class Cron
{
    protected string $minute;
    protected string $hour;
    protected string $day;
    protected string $month;
    protected string $weekday;

    /**
     * Creates a new cron definition with the provided field values.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        string|int $minute = '*',
        string|int $hour = '*',
        string|int $dayOfMonth = '*',
        string|int $month = '*',
        string|int $dayOfWeek = '*',
    ) {
        $this
            ->minute($minute)
            ->hour($hour)
            ->monthday($dayOfMonth)
            ->month($month)
            ->weekday($dayOfWeek);
    }

    /**
     * @Builds a CronExpression instance from the current configuration.
     */
    public function toExpression(): CronExpression
    {
        return new CronExpression(implode(' ', [
            $this->minute,
            $this->hour,
            $this->day,
            $this->month,
            $this->weekday,
        ]));
    }

    /**
     * Creates a new builder from an existing cron expression.
     *
     * @throws InvalidArgumentException
     */
    public static function fromExpression(CronExpression|string $expression): self
    {
        if (!$expression instanceof CronExpression) {
            $expression = new CronExpression($expression);
        }

        return new self(...$expression->getParts());
    }

    /**
     * Creates a cron schedule that runs every day at a specific time.
     *
     * @throws InvalidArgumentException
     */
    public static function dailyAt(string|int $hour, string|int $minute): self
    {
        return new self(minute: $minute, hour: $hour);
    }

    /**
     * Creates a cron schedule that runs weekly on a specific weekday and time.
     *
     * @throws InvalidArgumentException
     */
    public static function weeklyOn(string|int $dayOfWeek, string|int $hour, string|int $minute): self
    {
        return new self(minute: $minute, hour: $hour, dayOfWeek: $dayOfWeek);
    }

    /**
     * Creates a cron schedule that runs monthly on a specific day and time.
     *
     * @throws InvalidArgumentException
     */
    public static function monthlyOn(string|int $dayOfWeek, string|int $hour, string|int $minute): self
    {
        return new self(minute: $minute, hour: $hour, dayOfMonth: $dayOfWeek);
    }

    /**
     * Creates a cron schedule that runs yearly on a specific month and day.
     *
     * Hour and minute default to midnight.
     *
     * @throws InvalidArgumentException
     */
    public static function yearlyOn(string|int $month, string|int $day, string|int $hour = 0, string|int $minute = 0): self
    {
        return new self(minute: $minute, hour: $hour, dayOfMonth: $day, month: $month);
    }

    /**
     * Creates a cron schedule based on CronExpression registered aliases
     *
     * @see CronExpression::getAliases()
     *
     * The `@`is omitted when calling the method.
     *
     * @throws InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments = []): self
    {
        return self::fromExpression('@'.$name);
    }

    /**
     * Sets a field to run every N units using cron step syntax
     *
     * @throws InvalidArgumentException
     */
    public function every(int $step, CronField $field): self
    {
        return $this->field("*/$step", $field);
    }

    /**
     * Sets a field to a range of values using cron range syntax
     *
     * @throws InvalidArgumentException
     */
    public function range(string|int $start, string|int $end, CronField $field): self
    {
        return $this->field(self::validatePrimitive($start)."-".self::validatePrimitive($end), $field);
    }

    /**
     * Sets a field to run every N units within a range.
     *
     * @throws InvalidArgumentException
     */
    public function everyInRange(string|int $start, string|int $end, int $step, CronField $field): self
    {
        return $this->field(self::validatePrimitive($start) ."-".self::validatePrimitive($end) ."/$step", $field);
    }

    /**
     * Sets a field to a list of values using cron list syntax
     *
     * @param iterable<string|int> $list
     *
     * @throws InvalidArgumentException
     */
    public function list(iterable $list, CronField $field): self
    {
        $list = iterator_to_array($list, false);
        if ($list === []) {
            throw new InvalidArgumentException("List must be a non empty array.");
        }

        return $this->field(implode(",", array_map(self::validatePrimitive(...), $list)), $field);
    }

    /**
     * @throws InvalidArgumentException
     * @throws BadMethodCallException
     */
    public function __call(string $name, array $arguments): self
    {
        $methodList = ['everyInRange', 'every', 'range', 'list'];
        foreach ($methodList as $method) {
            if (!str_starts_with($name, $method)) {
                continue;
            }

            $cronField = CronField::tryFromName(substr($name, strlen($method)));
            if ($cronField === null) {
                continue;
            }

            $arguments['field'] = $cronField;

            return $this->$method(...$arguments);
        }

        throw new BadMethodCallException("the method $name does not exist.");
    }

    /**
     * Sets the minute field.
     *
     * @throws InvalidArgumentException
     */
    public function minute(string|int $value): self
    {
        $this->minute = self::validateField($value, CronField::Minute);

        return $this;
    }

    /**
     * Sets the hour field.
     *
     * @throws InvalidArgumentException
     */
    public function hour(string|int $value): self
    {
        $this->hour = self::validateField($value, CronField::Hour);

        return $this;
    }

    /**
     * Sets the day of the month field.
     *
     * @throws InvalidArgumentException
     */
    public function monthday(string|int $value): self
    {
        $this->day = self::validateField($value, CronField::DayOfMonth);

        return $this;
    }

    /**
     * Sets the month field.
     *
     * @throws InvalidArgumentException
     */
    public function month(string|int $value): self
    {
        $this->month = self::validateField($value, CronField::Month);

        return $this;
    }

    /**
     * Sets the weekday field.
     *
     * @throws InvalidArgumentException
     */
    public function weekday(string|int $value): self
    {
        $this->weekday = self::validateField($value, CronField::DayOfWeek);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    final protected function field(string|int $value, CronField $field): self
    {
        return match ($field) {
            CronField::Minute => $this->minute($value),
            CronField::Hour => $this->hour($value),
            CronField::DayOfMonth => $this->monthday($value),
            CronField::Month => $this->month($value),
            CronField::DayOfWeek => $this->weekday($value),
        };
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return non-empty-string
     */
    final protected static function validatePrimitive(string|int $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (str_contains($value, '/') || str_contains($value, ',')) {
            throw new InvalidArgumentException("Cron primitive '$value' must not contain expression operators '/' or ','.");
        }

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return non-empty-string
     */
    final protected static function validateField(string|int $value, CronField $field): string
    {
        $value = (string) $value;
        if (!$field->rule()->validate($value)) {
            throw new InvalidArgumentException(message: "Invalid value `$value` for CRON field {$field->label()}");
        }

        return $value;
    }
}
