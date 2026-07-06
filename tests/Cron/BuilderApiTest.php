<?php

declare(strict_types=1);

namespace Cron\Tests;

use ArgumentCountError;
use BadMethodCallException;
use Closure;
use Cron\CronExpression;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CronExpression::class)]
#[CoversMethod(CronExpression::class, '__call')]
#[CoversMethod(CronExpression::class, '__callStatic')]
#[CoversMethod(CronExpression::class, 'minutely')]
#[CoversMethod(CronExpression::class, 'dailyAt')]
#[CoversMethod(CronExpression::class, 'weeklyOn')]
#[CoversMethod(CronExpression::class, 'monthlyOn')]
#[CoversMethod(CronExpression::class, 'yearlyOn')]
#[CoversMethod(CronExpression::class, 'every')]
#[CoversMethod(CronExpression::class, 'everyInRange')]
#[CoversMethod(CronExpression::class, 'range')]
#[CoversMethod(CronExpression::class, 'list')]
final class BuilderApiTest extends TestCase
{
    #[Test]
    public function it_casts_integer_components_to_strings(): void
    {
        $this->assertSame('0 0 * * *', CronExpression::dailyAt(hour: 0, minute: 0)->getExpression());
        $this->assertSame('30 9 * * *', CronExpression::dailyAt(hour: '9', minute: '30')->getExpression());
        $this->assertSame('0 8 * * 1', CronExpression::weeklyOn(dayOfWeek: 1, hour: 8, minute: 0)->getExpression());
        $this->assertSame('0 8 1 * *', CronExpression::monthlyOn(dayOfMonth: 1, hour: 8, minute: 0)->getExpression());
        $this->assertSame('5 8 1 NOV *', CronExpression::yearlyOn(month: 'NOV', dayOfMonth: 1, hour: 8, minute: 5)->getExpression());
        $this->assertSame('0 0 1 NOV *', CronExpression::yearlyOn(month: 'NOV', dayOfMonth: 1)->getExpression());
    }

    #[Test]
    #[DataProvider('presetProvider')]
    public function it_resolves_known_presets(string $alias): void
    {
        $staticMethodName = substr($alias, 1);

        $this->assertSame(
            (new CronExpression($alias))->getExpression(),
            CronExpression::$staticMethodName()->getExpression()
        );
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string}>
     */
    public static function presetProvider(): array
    {
        return [
            'daily' => ['@daily'],
            'hourly' => ['@hourly'],
            'weekly' => ['@weekly'],
            'monthly' => ['@monthly'],
            'yearly' => ['@yearly'],
            'annually' => ['@annually'],
        ];
    }

    #[Test]
    #[DataProvider('everyProvider')]
    public function it_every_builds_step_expression(
        int $step,
        int $position,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            CronExpression::minutely()
                ->every($step, $position)
                ->getExpression()
        );
    }

    /**
     * @return array<non-empty-string, array{
     *     step: non-empty-string|int,
     *     position: int,
     *     expected: non-empty-string,
     * }>
     */
    public static function everyProvider(): array
    {
        return [
            'wildcard start' => [
                'step' => 5,
                'position' => CronExpression::MINUTE,
                'expected' => '*/5 * * * *',
            ],
            'normalization with step 1' => [
                'step' => 1,
                'position' => CronExpression::MINUTE,
                'expected' => '* * * * *',
            ],
        ];
    }

    #[Test]
    #[DataProvider('rangeProvider')]
    public function it_range_builds_range_expression(
        string|int $start,
        string|int $end,
        int $position,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            CronExpression::minutely()
                ->range($start, $end, $position)
                ->getExpression()
        );
    }

    /**
     * @return iterable<non-empty-string, array{
     *     start: non-empty-string|int,
     *     end: non-empty-string|int,
     *     position: int,
     *     expected: non-empty-string|int,
     * }>
     */
    public static function rangeProvider(): array
    {
        return [
            'simple range' => [
                'start' => 1,
                'end' => 5,
                'position' => CronExpression::HOUR,
                'expected' => '* 1-5 * * *',
            ],
            'string range' => [
                'start' => 'MON',
                'end' => 'FRI',
                'position' => CronExpression::WEEKDAY,
                'expected' => '* * * * MON-FRI',
            ],
        ];
    }

    #[Test]
    #[DataProvider('listProvider')]
    public function it_list_builds_comma_separated_expression(
        array $values,
        int $position,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            CronExpression::minutely()
                ->list($values, $position)
                ->getExpression()
        );
    }

    /**
     * @return iterable<non-empty-string, array{
     *     values: list<non-empty-string|int>,
     *     position: int,
     *     expected: non-empty-string,
     * }>
     */
    public static function listProvider(): array
    {
        return [
            'integers' => [
               'values' => [1, 2, 4],
               'position' => CronExpression::HOUR,
               'expected' => '* 1,2,4 * * *',
            ],
            'mixed values' => [
                'values' => ['MON', 'WED', 'FRI'],
                'position' => CronExpression::WEEKDAY,
                'expected' => '* * * * MON,WED,FRI',
            ],
        ];
    }

    #[Test]
    public function it_rejects_empty_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CronExpression::minutely()->listHours([]);
    }

    #[Test]
    #[DataProvider('rangeStepProvider')]
    public function it_range_step_builds_step_range_expression(
        string|int $start,
        string|int $end,
        int $step,
        int $position,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            CronExpression::minutely()
                ->everyInRange($start, $end, $step, $position)
                ->getExpression()
        );
    }

    /**
     * @return iterable<non-empty-string, array{
     *     start: non-empty-string|int,
     *     end: non-empty-string|int,
     *     step: int,
     *     position: int,
     *     expected: non-empty-string|int,
     * }>
     */
    public static function rangeStepProvider(): array
    {
        return [
            'numeric range step' => [
                'start' => 1,
                'end' => 5,
                'step' => 2,
                'position' => CronExpression::HOUR,
               'expected' => '* 1-5/2 * * *',
            ],
            'weekday step range' => [
                'start' => 'MON',
                'end' =>  'FRI',
                'step' => 1,
                'position' =>  CronExpression::WEEKDAY,
                'expected' => '* * * * MON-FRI',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidExpressionForModifierProvider')]
    public function it_rejects_invalid_primitives_in_range(string $expression): void
    {
        $this->expectException(InvalidArgumentException::class);

        CronExpression::minutely()->range($expression, 5, CronExpression::HOUR);
    }

    #[Test]
    #[DataProvider('invalidExpressionForModifierProvider')]
    public function it_rejects_invalid_primitives_in_list(string $expression): void
    {
        $this->expectException(InvalidArgumentException::class);

        CronExpression::minutely()->list([$expression], CronExpression::MINUTE);
    }

    /**
     * @return iterable<non-empty-string, array{
     *     expression: non-empty-string|int,
     * }>
     */
    public static function invalidExpressionForModifierProvider(): iterable
    {
         yield 'rejects invalid expression containing "/"' => [
            'expression' => '1/2',
        ];

        yield 'rejects invalid expression containing ","' => [
            'expression' => '1,2',
        ];
    }

    #[Test]
    #[DataProvider('monthlyOnProvider')]
    public function it_builds_monthly_on_expression(
        string|int $dayOfMonth,
        string|int $hour,
        string|int $minute,
        string $expected,
    ): void {
        $this->assertSame($expected, CronExpression::monthlyOn($dayOfMonth, $hour, $minute)->getExpression());
    }

    /**
     * @return iterable<non-empty-string, array{
     *     dayOfMonth: non-empty-string|int,
     *     hour: non-empty-string|int,
     *     minute: non-empty-string|int,
     *     expected: non-empty-string|int,
     * }>
     */
    public static function monthlyOnProvider(): iterable
    {
        yield 'simple monthly' => [
            'dayOfMonth' => 15,
            'hour' => 10,
            'minute' => 30,
            'expected' => '30 10 15 * *',
        ];

        yield 'midnight first day' => [
            'dayOfMonth' => 1,
            'hour' => 0,
            'minute' => 0,
            'expected' => '0 0 1 * *',
        ];

        yield 'string day' => [
            'dayOfMonth' => 13,
            'hour' => 8,
            'minute' => 0,
            'expected' => '0 8 13 * *',
        ];
    }

    #[Test]
    public function it_rejects_invalid_monthly_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CronExpression::monthlyOn('1/2', 10, 30);
    }

    #[Test]
    #[DataProvider('yearlyOnProvider')]
    public function it_builds_yearly_on_expression(
        string|int $month,
        string|int $dayOfMonth,
        string|int $hour,
        string|int $minute,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            CronExpression::yearlyOn(
                month: $month,
                dayOfMonth: $dayOfMonth,
                hour: $hour,
                minute: $minute
            )->getExpression()
        );
    }

    /**
     * @return iterable<non-empty-string, array{
     *     month: non-empty-string|int,
     *     dayOfMonth: non-empty-string|int,
     *     hour: non-empty-string|int,
     *     minute: non-empty-string|int,
     *     expected: non-empty-string|int,
     * }>
     */
    public static function yearlyOnProvider(): iterable
    {
        yield 'Christmas' => [
            'month' => 12,
            'dayOfMonth' => 25,
            'hour' => 9,
            'minute' => 0,
            'expected' => '0 9 25 12 *',
        ];

        yield 'New year' => [
            'month' => 1,
            'dayOfMonth' => 1,
            'hour' => 0,
            'minute' => 0,
            'expected' => '0 0 1 1 *',
        ];

        yield 'Spring event' => [
            'month' => 'MAR',
            'dayOfMonth' => 15,
            'hour' => 14,
            'minute' => 30,
            'expected' => '30 14 15 MAR *',
        ];
    }

    #[Test]
    public function it_rejects_invalid_yearly_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CronExpression::yearlyOn('12,01', 25, 9, 61);
    }

    /**
     * @param Closure(CronExpression): CronExpression $build
     * @param string $expected
     */
    #[Test]
    #[DataProvider('grammarMatrixProvider')]
    public function it_respects_cron_grammar_combinations(
        Closure $build,
        string $expected
    ): void {
        $this->assertSame($expected, $build(CronExpression::minutely())->getExpression());
    }

    /**
     * @return iterable<non-empty-string, array{
     *     build: (Closure(CronExpression): CronExpression),
     *     expected: non-empty-string
     * }>
     */
    public static function grammarMatrixProvider(): iterable
    {
        yield 'every + range (hour)' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->everyMinutes(5)
                ->rangeHours(9, 17),
            'expected' => '*/5 9-17 * * *',
        ];

        yield 'every + list (weekday)' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->everyMinutes(10)
                ->listDaysOfWeek(['MON', 'WED', 'FRI']),
            'expected' => '*/10 * * * MON,WED,FRI',
        ];

        yield 'range + list' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->rangeHours(1, 10)
                ->listMinutes([1, 3, 5]),
            'expected' => '1,3,5 1-10 * * *',
        ];

        yield 'everyRange + list' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->everyInRangeMinutes(1, 20, 2)
                ->listDaysOfWeek(['MON', 'FRI']),
            'expected' => '1-20/2 * * * MON,FRI',
        ];

        yield 'monthlyOn + every + list' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->everyMinutes(15)
                ->listDaysOfMonth([1, 15, 30]),
            'expected' => '*/15 * 1,15,30 * *',
        ];

        yield 'weekday range + step' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->everyInRangeDaysOfWeek('MON', 'FRI', 2)
                ->everyMinutes(10),
            'expected' => '*/10 * * * MON-FRI/2',
        ];

        yield 'full composition' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->everyMinutes(5)
                ->rangeHours(8, 18)
                ->listDaysOfMonth([1, 15])
                ->everyInRangeMonths(1, 12, 2)
                ->listDaysOfWeek(['MON', 'WED']),
            'expected' => '*/5 8-18 1,15 1-12/2 MON,WED',
        ];

        yield 'overwriting same position' => [
            'build' => fn (CronExpression $c): CronExpression => $c
                ->rangeHours(1, 10)
                ->rangeHours(20, 22),
            'expected' => '* 20-22 * * *',
        ];
    }

    #[Test]
    #[DataProvider('dynamicMethodProvider')]
    public function it_resolves_dynamic_methods(
        string $method,
        array $arguments,
        string $expected,
    ): void {
        $cron = CronExpression::minutely()->$method(...$arguments);

        self::assertSame($expected, $cron->getExpression());
    }

    /**
     * @return iterable<non-empty-string, array{
     *     method: non-empty-string,
     *     arguments: list<int|string>,
     *     expected: non-empty-string
     * }>
     */
    public static function dynamicMethodProvider(): iterable
    {
        yield 'every minute' => [
            'method' => 'everyMinutes',
            'arguments' => [5],
            'expected' => '*/5 * * * *',
        ];

        yield 'every hour' => [
            'method' => 'everyHours',
            'arguments' => [2],
            'expected' => '* */2 * * *',
        ];

        yield 'range hour' => [
            'method' => 'rangeHours',
            'arguments' => [9, 17],
            'expected' => '* 9-17 * * *',
        ];

        yield 'range month' => [
            'method' => 'rangeMonths',
            'arguments' => [1, 6],
            'expected' => '* * * 1-6 *',
        ];

        yield 'every in range day of month' => [
            'method' => 'everyInRangeDaysOfMonth',
            'arguments' => [1, 31, 2],
            'expected' => '* * 1-31/2 * *',
        ];

        yield 'every in range day of week' => [
            'method' => 'everyInRangeDaysOfWeek',
            'arguments' => ['MON', 'FRI', 2],
            'expected' => '* * * * MON-FRI/2',
        ];

        yield 'list day of week' => [
            'method' => 'listDaysOfWeek',
            'arguments' => [['MON', 'WED', 'FRI']],
            'expected' => '* * * * MON,WED,FRI',
        ];

        yield 'list month' => [
            'method' => 'listMonths',
            'arguments' => [[1, 3, 6]],
            'expected' => '* * * 1,3,6 *',
        ];
    }

    #[Test]
    #[DataProvider('invalidDynamicMethodProvider')]
    public function it_throws_for_unknown_dynamic_methods(
        string $method,
        array $arguments,
    ): void {
        $this->expectException(BadMethodCallException::class);

        CronExpression::minutely()->$method(...$arguments);
    }

    /**
     * @return iterable<non-empty-string, array{method: non-empty-string, arguments: list<int>}>
     */
    public static function invalidDynamicMethodProvider(): iterable
    {
        yield 'unknown foobar operation' => [
            'method' => 'foobarMinutes',
            'arguments' => [5],
        ];

        yield 'unknown Foo field' => [
            'method' => 'everyFoo',
            'arguments' => [5],
        ];
    }

    #[DataProvider('provideMagicSetMethods')]
    public function testMagicSetMethods(string $method, array $arguments, string $expected): void
    {
        self::assertSame(
            $expected,
            CronExpression::minutely()
                ->$method(...$arguments)
                ->getExpression()
        );
    }

    /**
     * @return iterable<non-empty-string, array{
     *     0: non-empty-string,
     *     1: array<non-empty-string>,
     *     2: non-empty-string
     * }>
     */
    public static function provideMagicSetMethods(): iterable
    {
        yield 'minute' => [
            'setMinutes',
            ['value' => '15'],
            '15 * * * *',
        ];

        yield 'minute positional' => [
            'setMinutes',
            ['15'],
            '15 * * * *',
        ];

        yield 'hour' => [
            'setHours',
            ['value' => '8'],
            '* 8 * * *',
        ];

        yield 'day of month' => [
            'setDaysOfMonth',
            ['value' => '10'],
            '* * 10 * *',
        ];

        yield 'month' => [
            'setMonths',
            ['value' => 'DEC'],
            '* * * DEC *',
        ];

        yield 'day of week' => [
            'setDaysOfWeek',
            ['value' => 'MON'],
            '* * * * MON',
        ];
    }

    #[DataProvider('provideInvalidMagicSetMethods')]
    public function testInvalidMagicSetMethods(
        string $method,
        array $arguments,
        string $exception
    ): void {
        $cron = CronExpression::minutely();
        $this->expectException($exception);

        $cron->$method(...$arguments);
    }

    /**
     * @return iterable<non-empty-string, array{
     *     0: non-empty-string,
     *     1: array<non-empty-string>,
     *     2: class-string<Exception>
     * }>
     */
    public static function provideInvalidMagicSetMethods(): iterable
    {
        yield 'unknown field' => [
            'setSeconds',
            ['value' => '1'],
            BadMethodCallException::class,
        ];

        yield 'invalid method' => [
            'foobar',
            [],
            BadMethodCallException::class,
        ];

        yield 'invalid minute' => [
            'setMinutes',
            ['value' => '61'],
            InvalidArgumentException::class,
        ];

        yield 'invalid month' => [
            'setMonths',
            ['value' => 'FOO'],
            InvalidArgumentException::class,
        ];

        yield 'missing argument' => [
            'setMinutes',
            [],
            ArgumentCountError::class,
        ];
    }

    #[DataProvider('getMagicMethodsProvider')]
    public function testMagicGetMethods(string $method, string $expected): void
    {
        $cron = CronExpression::minutely()
            ->setMinutes('5')
            ->setHours('8')
            ->setDaysOfMonth('10')
            ->setMonths('DEC')
            ->setDaysOfWeek('MON');

        self::assertSame($expected, $cron->$method());
    }

    /**
     * @return iterable<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function getMagicMethodsProvider(): iterable
    {
        yield 'minutes' => ['getMinutes', '5'];
        yield 'hours' => ['getHours', '8'];
        yield 'days of month' => ['getDaysOfMonth', '10'];
        yield 'months' => ['getMonths', 'DEC'];
        yield 'days of week' => ['getDaysOfWeek', 'MON'];
    }

    /**
     * @return iterable<non-empty-string, array{
     *     0: non-empty-string,
     *     1: class-string<Exception>,
     *     2: array<string|int>
     * }>
     */
    public static function invalidMagicGetMethodsProvider(): iterable
    {
        yield 'unknown field' => [
            'getSeconds',
            BadMethodCallException::class,
            [],
        ];

        yield 'unexpected argument' => [
            'getMinutes',
            ArgumentCountError::class,
            ['foo'],
        ];
    }

    #[DataProvider('invalidMagicGetMethodsProvider')]
    public function testInvalidMagicGetMethods(
        string $method,
        string $exceptionClass,
        array $arguments
    ): void {
        $cron = CronExpression::minutely();
        $this->expectException($exceptionClass);

        $cron->$method(...$arguments);
    }
}
