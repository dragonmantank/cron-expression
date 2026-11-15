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

    public function testNearestWeekdayDoesNotMatchMissingDays(): void
    {
        $f = new DayOfMonthField();

        foreach (['2023-02', '2024-02', '2023-04', '2023-06'] as $month) {
            $first = new DateTime($month . '-01', new \DateTimeZone('UTC'));
            $lastDay = (int) $first->format('t');

            for ($targetDay = $lastDay + 1; $targetDay <= 31; ++$targetDay) {
                for ($day = 1; $day <= $lastDay; ++$day) {
                    $date = (clone $first)->setDate((int) $first->format('Y'), (int) $first->format('m'), $day);
                    $this->assertFalse(
                        $f->isSatisfiedBy($date, $targetDay . 'W', false),
                        $targetDay . 'W must not match ' . $date->format('Y-m-d')
                    );
                }
            }
        }
    }

    public function testNearestWeekdayStillMatchesExistingDays(): void
    {
        $f = new DayOfMonthField();

        $this->assertTrue($f->isSatisfiedBy(new DateTime('2024-02-29'), '29W', false));
        $this->assertTrue($f->isSatisfiedBy(new DateTime('2023-04-28'), '30W', false));
        $this->assertTrue($f->isSatisfiedBy(new DateTime('2024-03-29'), '31W', false));
        $this->assertTrue($f->isSatisfiedBy(new DateTime('2023-05-31'), '31W', false));
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
}
