<?php

declare(strict_types=1);

namespace Cron\Tests;

use BadMethodCallException;
use Closure;
use Cron\Cron;
use Cron\CronField;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function substr;

#[CoversClass(Cron::class)]
#[CoversClass(CronField::class)]
final class CronTest extends TestCase
{
    private function expression(Cron $cron): string
    {
        return $cron->toExpression()->getExpression();
    }

    #[Test]
    public function it_defaults_every_component_to_a_wildcard(): void
    {
        $this->assertSame('* * * * *', $this->expression(new Cron()));
    }

    #[Test]
    public function it_builds_an_expression_from_all_named_components(): void
    {
        $cron = new Cron(minute: 5, hour: 4, dayOfMonth: 3, month: 2, dayOfWeek: 1);

        $this->assertSame('5 4 3 2 1', $this->expression($cron));
    }

    #[Test]
    public function it_casts_integer_components_to_strings(): void
    {
        $cron = new Cron(minute: 0, hour: 0);

        $this->assertSame('0 0 * * *', $this->expression($cron));
    }

    #[Test]
    public function it_accepts_valid_complex_component_expressions(): void
    {
        $cron = new Cron(minute: '*/15', hour: '9-17', dayOfMonth: '1,15', dayOfWeek: '1-5');

        $this->assertSame('*/15 9-17 1,15 * 1-5', $this->expression($cron));
    }

    #[Test]
    public function daily_at_only_sets_the_minute_and_hour(): void
    {
        $this->assertSame('30 9 * * *', $this->expression(Cron::dailyAt(hour: 9, minute: 30)));
    }

    #[Test]
    public function weekly_on_sets_the_minute_hour_and_weekday(): void
    {
        $this->assertSame('0 8 * * 1', $this->expression(Cron::weeklyOn(dayOfWeek: 1, hour: 8, minute: 0)));
    }

    #[Test]
    #[DataProvider('presetProvider')]
    public function it_resolves_known_presets(string $alias, string $expected): void
    {
        $staticMethodName = substr($alias, 1);

        $this->assertSame($expected, $this->expression(Cron::fromExpression($alias)));
        $this->assertSame($expected, $this->expression(Cron::$staticMethodName()));
    }

    public static function presetProvider(): array
    {
        return [
            'with leading @' => ['@daily', '0 0 * * *'],
            'hourly' => ['@hourly', '0 * * * *'],
            'weekly' => ['@weekly', '0 0 * * 0'],
            'monthly' => ['@monthly', '0 0 1 * *'],
            'yearly' => ['@yearly', '0 0 1 1 *'],
            'annually alias' => ['@annually', '0 0 1 1 *'],
            'midnight alias' => ['@midnight', '0 0 * * *'],
        ];
    }

    #[Test]
    public function it_rejects_an_unknown_preset(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cron::fromExpression('@never');
    }

    #[Test]
    public function to_expression_returns_a_matching_cron_expression(): void
    {
        $cron = Cron::dailyAt(hour: 9, minute: 30);

        $this->assertSame('30 9 * * *', $this->expression($cron));
    }

    #[Test]
    public function fluent_setters_mutate_the_instance_and_return_self(): void
    {
        $cron = new Cron();

        $returned = $cron
            ->minute(5)
            ->hour(4)
            ->monthday(3)
            ->month(2)
            ->weekday(1);

        $this->assertSame($cron, $returned);
        $this->assertSame('5 4 3 2 1', $this->expression($cron));
    }

    #[Test]
    #[DataProvider('invalidComponentProvider')]
    public function it_rejects_invalid_components(string $method, string $value, string $componentName): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid value `$value` for CRON field $componentName");

        (new Cron())->{$method}($value);
    }

    public static function invalidComponentProvider(): array
    {
        return [
            'minute out of range' => ['minute', '60', 'minute'],
            'minute non-numeric' => ['minute', 'invalid', 'minute'],
            'hour out of range' => ['hour', '24', 'hour'],
            'day out of range' => ['monthday', '32', 'day of month'],
            'month out of range' => ['month', '13', 'month'],
            'weekday out of range' => ['weekday', '8', 'day of week'],
        ];
    }

    #[Test]
    public function it_rejects_invalid_components_at_construction_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value `nope` for CRON field minute');

        new Cron(minute: 'nope');
    }

    #[Test]
    public function validation_failures_wrap_the_underlying_exception(): void
    {
        try {
            (new Cron())->minute('invalid');
            $this->fail('Expected an InvalidArgumentException to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNull($exception->getPrevious());
        }
    }

    #[Test]
    #[DataProvider('everyProvider')]
    public function it_every_builds_step_expression(
        int $step,
        CronField $position,
        string $expected
    ): void {
        $cron = (new Cron())->every($step, $position);

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function everyProvider(): array
    {
        return [
            'wildcard start' => [
                5,
                CronField::Minute,
                '*/5 * * * *',
            ],
        ];
    }

    #[Test]
    #[DataProvider('betweenProvider')]
    public function it_between_builds_range_expression(
        string|int $start,
        string|int $end,
        CronField $position,
        string $expected
    ): void {
        $cron = (new Cron())->range($start, $end, $position);

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function betweenProvider(): array
    {
        return [
            'simple range' => [
                1,
                5,
                CronField::Hour,
                '* 1-5 * * *',
            ],
            'string range' => [
                'MON',
                'FRI',
                CronField::DayOfWeek,
                '* * * * MON-FRI',
            ],
        ];
    }

    #[Test]
    #[DataProvider('listProvider')]
    public function it_list_builds_comma_separated_expression(
        array $values,
        CronField $position,
        string $expected
    ): void {
        $cron = (new Cron())->list($values, $position);

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function listProvider(): array
    {
        return [
            'integers' => [
                [1, 2, 4],
                CronField::Hour,
                '* 1,2,4 * * *',
            ],
            'mixed values' => [
                ['MON', 'WED', 'FRI'],
                CronField::DayOfWeek,
                '* * * * MON,WED,FRI',
            ],
        ];
    }

    #[Test]
    #[DataProvider('betweenStepProvider')]
    public function it_between_step_builds_step_range_expression(
        string|int $start,
        string|int $end,
        int $step,
        CronField $position,
        string $expected
    ): void {
        $cron = (new Cron())->everyInRange($start, $end, $step, $position);

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function betweenStepProvider(): array
    {
        return [
            'numeric range step' => [
                1,
                5,
                2,
                CronField::Hour,
                '* 1-5/2 * * *',
            ],
            'weekday step range' => [
                'MON',
                'FRI',
                1,
                CronField::DayOfWeek,
                '* * * * MON-FRI/1',
            ],
        ];
    }

    #[Test]
    public function it_rejects_invalid_primitives_in_between(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Cron())->range('1/2', 5, CronField::Hour);
    }

    #[Test]
    public function it_rejects_invalid_list_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Cron())->list(['1/2'], CronField::Hour);
    }

    #[Test]
    #[DataProvider('monthlyOnProvider')]
    public function it_builds_monthly_on_expression(
        string|int $day,
        string|int $hour,
        string|int $minute,
        string $expected,
    ): void {
        $cron = Cron::monthlyOn($day, $hour, $minute);

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function monthlyOnProvider(): array
    {
        return [
            'simple monthly' => [
                15,
                10,
                30,
                '30 10 15 * *',
            ],
            'midnight first day' => [
                1,
                0,
                0,
                '0 0 1 * *',
            ],
            'string day' => [
                13,
                8,
                0,
                '0 8 13 * *',
            ],
        ];
    }

    #[Test]
    public function it_rejects_invalid_monthly_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cron::monthlyOn('1/2', 10, 30);
    }

    #[Test]
    #[DataProvider('yearlyOnProvider')]
    public function it_builds_yearly_on_expression(
        string|int $month,
        string|int $day,
        string|int $hour,
        string|int $minute,
        string $expected,
    ): void {
        $cron = Cron::yearlyOn(month: $month, day: $day, hour: $hour, minute: $minute);

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function yearlyOnProvider(): array
    {
        return [
            'christmas' => [
                12,
                25,
                9,
                0,
                '0 9 25 12 *',
            ],
            'new year' => [
                1,
                1,
                0,
                0,
                '0 0 1 1 *',
            ],
            'spring event' => [
                '3',
                15,
                14,
                30,
                '30 14 15 3 *',
            ],
        ];
    }

    #[Test]
    public function it_rejects_invalid_yearly_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cron::yearlyOn('12,01', 25, 9, 61);
    }

    /**
     * @param Closure(Cron): Cron $build
     * @param string $expected
     */
    #[Test]
    #[DataProvider('grammarMatrixProvider')]
    public function it_respects_cron_grammar_combinations(
        Closure $build,
        string $expected
    ): void {
        $cron = $build(new Cron());

        $this->assertSame($expected, $this->expression($cron));
    }

    public static function grammarMatrixProvider(): iterable
    {
        yield 'every + between (hour)' => [
            fn(Cron $c) => $c
                ->every(5, CronField::Minute)
                ->range(9, 17, CronField::Hour),
            '*/5 9-17 * * *',
        ];

        yield 'every + list (weekday)' => [
            fn(Cron $c) => $c
                ->every(10, CronField::Minute)
                ->list(['MON', 'WED', 'FRI'], CronField::DayOfWeek),
            '*/10 * * * MON,WED,FRI',
        ];

        yield 'between + list' => [
            fn(Cron $c) => $c
                ->range(1, 10, CronField::Hour)
                ->list([1, 3, 5], CronField::Minute),
            '1,3,5 1-10 * * *',
        ];

        yield 'everyBetween + list' => [
            fn(Cron $c) => $c
                ->everyInRange(1, 20, 2, CronField::Minute)
                ->list(['MON', 'FRI'], CronField::DayOfWeek),
            '1-20/2 * * * MON,FRI',
        ];

        yield 'monthlyOn + every + list' => [
            fn(Cron $c) => $c
                ->every(15, CronField::Minute)
                ->list([1, 15, 30], CronField::DayOfMonth)
                ->monthlyOn(10, 8, 0),
            '0 8 10 * *',
        ];

        yield 'weekday range + step' => [
            fn(Cron $c) => $c
                ->everyInRange('MON', 'FRI', 2, CronField::DayOfWeek)
                ->every(10, CronField::Minute),
            '*/10 * * * MON-FRI/2',
        ];

        yield 'full composition' => [
            fn (Cron $c) => $c
                ->every(5, CronField::Minute)
                ->range(8, 18, CronField::Hour)
                ->list([1, 15], CronField::DayOfMonth)
                ->everyInRange(1, 12, 2, CronField::Month)
                ->list(['MON', 'WED'], CronField::DayOfWeek),
            '*/5 8-18 1,15 1-12/2 MON,WED',
        ];

        yield 'overwriting same position' => [
            fn (Cron $c) => $c
                ->range(1, 10, CronField::Hour)
                ->range(20, 22, CronField::Hour),
            '* 20-22 * * *',
        ];
    }

    #[Test]
    #[DataProvider('dynamicMethodProvider')]
    public function it_resolves_dynamic_methods(
        string $method,
        array $arguments,
        string $expectedExpression,
    ): void {
        $cron = (new Cron())->$method(...$arguments);

        self::assertSame($expectedExpression, $this->expression($cron));
    }

    public static function dynamicMethodProvider(): iterable
    {
        yield 'every minute' => [
            'everyMinute',
            [5],
            '*/5 * * * *',
        ];

        yield 'every hour' => [
            'everyHour',
            [2],
            '* */2 * * *',
        ];

        yield 'range hour' => [
            'rangeHour',
            [9, 17],
            '* 9-17 * * *',
        ];

        yield 'range month' => [
            'rangeMonth',
            [1, 6],
            '* * * 1-6 *',
        ];

        yield 'every in range day of month' => [
            'everyInRangeDayOfMonth',
            [1, 31, 2],
            '* * 1-31/2 * *',
        ];

        yield 'every in range day of week' => [
            'everyInRangeDayOfWeek',
            ['MON', 'FRI', 2],
            '* * * * MON-FRI/2',
        ];

        yield 'list day of week' => [
            'listDayOfWeek',
            [['MON', 'WED', 'FRI']],
            '* * * * MON,WED,FRI',
        ];

        yield 'list month' => [
            'listMonth',
            [[1, 3, 6]],
            '* * * 1,3,6 *',
        ];
    }

    #[Test]
    #[DataProvider('invalidDynamicMethodProvider')]
    public function it_throws_for_unknown_dynamic_methods(
        string $method,
        array $arguments,
    ): void {
        $this->expectException(BadMethodCallException::class);

        (new Cron())->$method(...$arguments);
    }

    public static function invalidDynamicMethodProvider(): iterable
    {
        yield 'unknown operation' => [
            'foobarMinute',
            [5],
        ];

        yield 'unknown field' => [
            'everyFoo',
            [5],
        ];

        yield 'invalid range field' => [
            'rangeSomething',
            [1, 5],
        ];

        yield 'empty field suffix' => [
            'everyIn',
            [5],
        ];
    }
}
