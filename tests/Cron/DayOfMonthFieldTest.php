<?php

declare(strict_types=1);

namespace Cron\Tests;

use Cron\DayOfMonthField;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

/**
 * @author Michael Dowling <mtdowling@gmail.com>
 */
#[CoversFunction('increment')]
#[CoversFunction('validate')]
#[CoversFunction('isSatisfiedBy')]
class DayOfMonthFieldTest extends TestCase
{
    public function testValidatesField(): void
    {
        $f = new DayOfMonthField();
        $this->assertTrue($f->validate('1'));
        $this->assertTrue($f->validate('*'));
        $this->assertTrue($f->validate('L'));
        $this->assertTrue($f->validate('5W'));
        $this->assertTrue($f->validate('?'));
        $this->assertTrue($f->validate('01'));
        $this->assertFalse($f->validate('5W,L'));
        $this->assertFalse($f->validate('1.'));
    }

    public function testChecksIfSatisfied(): void
    {
        $f = new DayOfMonthField();
        $this->assertTrue($f->isSatisfiedBy(new DateTime(), '?', false));
        $this->assertTrue($f->isSatisfiedBy(new DateTimeImmutable(), '?', false));
    }

    public function testIncrementsDate(): void
    {
        $d = new DateTime('2011-03-15 11:15:00');
        $f = new DayOfMonthField();
        $f->increment($d);
        $this->assertSame('2011-03-16 00:00:00', $d->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        $f->increment($d, true);
        $this->assertSame('2011-03-14 23:59:00', $d->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateTimeImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        $f = new DayOfMonthField();
        $f->increment($d);
        $this->assertSame('2011-03-16 00:00:00', $d->format('Y-m-d H:i:s'));
    }

    /**
     * Day of the month cannot accept a 0 value, it must be between 1 and 31
     * See Github issue #120.
     *
     * @since 2017-01-22
     */
    public function testDoesNotAccept0Date(): void
    {
        $f = new DayOfMonthField();
        $this->assertFalse($f->validate('0'));
    }

    public function testIncrementAcrossDstChangeBerlin(): void
    {
        $tz = new \DateTimeZone("Europe/Berlin");
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 01:59:00", $tz);
        $f = new DayOfMonthField();
        $f->increment($d);
        $this->assertSame("2021-03-29 00:00:00", $d->format("Y-m-d H:i:s"));

        $f->increment($d, true);
        $this->assertSame("2021-03-28 23:59:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d, true);
        $this->assertSame("2021-03-27 23:59:00", $d->format("Y-m-d H:i:s"));
    }

    public function testIncrementAcrossDstChangeLondon(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 00:59:00", $tz);
        $f = new DayOfMonthField();
        $f->increment($d);
        $this->assertSame("2021-03-29 00:00:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d);
        $this->assertSame("2021-03-30 00:00:00", $d->format("Y-m-d H:i:s"));

        $f->increment($d, true);
        $this->assertSame("2021-03-29 23:59:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d, true);
        $this->assertSame("2021-03-28 23:59:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d, true);
        $this->assertSame("2021-03-27 23:59:00", $d->format("Y-m-d H:i:s"));
    }

    public function testIssue151DOMFieldSupportLW()
    {
        $f = new DayOfMonthField();
        $this->assertTrue($f->validate('LW'));
    }

    /**
     * 'L' (last day of the month) is a valid member of a day-of-month list,
     * e.g. "L,15". Only 'W' is restricted to a single day and must stay
     * invalid inside a list.
     *
     * @see https://github.com/dragonmantank/cron-expression/issues/189
     */
    public function testIssue189ListMayContainLastDayOfMonth(): void
    {
        $f = new DayOfMonthField();

        // 'L' is allowed as a list member.
        $this->assertTrue($f->validate('L,15'));
        $this->assertTrue($f->validate('15,L'));
        $this->assertTrue($f->validate('L,1-5'));
        $this->assertTrue($f->validate('L,15,20'));

        // 'W' remains single-day only and must stay invalid inside a list.
        $this->assertFalse($f->validate('5W,L'));
        $this->assertFalse($f->validate('5W,15'));
        $this->assertFalse($f->validate('15,5W'));
    }

    /**
     * An "L,15" day-of-month list must schedule on both the 15th and the last
     * day of each month (including February in leap and non-leap years).
     *
     * @see https://github.com/dragonmantank/cron-expression/issues/189
     */
    public function testIssue189ListWithLastDayOfMonthSchedules(): void
    {
        $cron = new \Cron\CronExpression('0 0 L,15 * *');

        $expected = [
            '2024-01-15 00:00:00',
            '2024-01-31 00:00:00',
            '2024-02-15 00:00:00',
            '2024-02-29 00:00:00', // leap year
            '2024-03-15 00:00:00',
            '2024-03-31 00:00:00',
        ];

        $current = new DateTimeImmutable('2024-01-01 00:00:00');
        foreach ($expected as $expectedRun) {
            $current = $cron->getNextRunDate($current, 0, false);
            $this->assertSame($expectedRun, $current->format('Y-m-d H:i:s'));
            $current = $current->modify('+1 second');
        }
    }
}
