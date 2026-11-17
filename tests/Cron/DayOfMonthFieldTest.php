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
     * A 'W' qualifier may be used on the members of a day-of-month list,
     * e.g. "1W,15W" meaning "the weekday nearest the 1st or the 15th".
     *
     * @see https://github.com/dragonmantank/cron-expression/issues/215
     */
    public function testIssue215ListMayContainWeekdayQualifiers(): void
    {
        $f = new DayOfMonthField();

        $this->assertTrue($f->validate('1W,15W'));
        $this->assertTrue($f->validate('15W,1W'));
        $this->assertTrue($f->validate('1W,15'));
        $this->assertTrue($f->validate('15,1W'));
        $this->assertTrue($f->validate('1W,15W,25W'));
        $this->assertTrue($f->validate('01W,15W'));
        $this->assertTrue($f->validate('1-5,15W'));
        $this->assertTrue($f->validate('1W,?')); // Preserve the existing skip marker in lists.
    }

    /**
     * Only a single numeric day may carry the 'W' qualifier inside a list, and
     * 'L' keeps its existing list behaviour.
     *
     * @see https://github.com/dragonmantank/cron-expression/issues/215
     */
    public function testIssue215InvalidWeekdayListsAreRejected(): void
    {
        $f = new DayOfMonthField();

        $this->assertFalse($f->validate('0W,15W'));
        $this->assertFalse($f->validate('32W,15W'));
        $this->assertFalse($f->validate('W,15W'));
        $this->assertFalse($f->validate('1W,'));
        $this->assertFalse($f->validate('1-5W,10W'));
        $this->assertFalse($f->validate('*/2W,10W'));
        $this->assertFalse($f->validate('1WW,10W'));

        $this->assertFalse($f->validate('5W,L'));
        $this->assertFalse($f->validate('1W,LW'));
        $this->assertFalse($f->validate('L,15'));
    }

    /**
     * Every 'W' member of a list is matched independently of its position.
     *
     * @see https://github.com/dragonmantank/cron-expression/issues/215
     */
    public function testIssue215ListOrderDoesNotAffectMatching(): void
    {
        $f = new DayOfMonthField();

        // 2026-11-01 is a Sunday, so 1W falls on Monday the 2nd.
        $first = new DateTimeImmutable('2026-11-02 00:00:00');
        // 2026-11-15 is a Sunday, so 15W falls on Monday the 16th.
        $second = new DateTimeImmutable('2026-11-16 00:00:00');
        $neither = new DateTimeImmutable('2026-11-10 00:00:00');

        foreach (['1-5,15W', '15W,1-5'] as $value) {
            $this->assertTrue($f->isSatisfiedBy($first, $value, false));
            $this->assertTrue($f->isSatisfiedBy($second, $value, false));
            $this->assertFalse($f->isSatisfiedBy($neither, $value, false));
        }

        foreach (['1W,15W', '15W,1W'] as $value) {
            $this->assertTrue($f->isSatisfiedBy($first, $value, false));
            $this->assertTrue($f->isSatisfiedBy($second, $value, false));
            $this->assertFalse($f->isSatisfiedBy($neither, $value, false));
        }
    }
}
