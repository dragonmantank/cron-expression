<?php

declare(strict_types=1);

namespace Cron\Tests;

use Cron\HoursField;
use Cron\NextRunDateTime;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class HoursFieldTest extends TestCase
{
    /**
     * @covers \Cron\HoursField::validate
     */
    public function testValidatesField(): void
    {
        $f = new HoursField();
        $this->assertTrue($f->validate('1'));
        $this->assertTrue($f->validate('00'));
        $this->assertTrue($f->validate('01'));
        $this->assertTrue($f->validate('*'));
        $this->assertFalse($f->validate('*/3,1,1-12'));
        $this->assertFalse($f->validate('1/10'));
    }

    /**
     * @covers \Cron\HoursField::increment
     */
    public function testIncrementsDate(): void
    {
        $dt = new DateTime('2011-03-15 11:15:00');
        $d = new NextRunDateTime($dt, false);
        $f = new HoursField();
        $f->increment($d, false, null);
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));

        $d = new NextRunDateTime($dt, true);
        $f->increment($d, true, null);
        $this->assertSame('2011-03-15 10:59:00', $d->format('Y-m-d H:i:s'));
    }

    /**
     * @covers \Cron\HoursField::increment
     */
    public function testIncrementsDateWithThirtyMinuteOffsetTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/St_Johns');
        $dt = new DateTime('2011-03-15 11:15:00');
        $d = new NextRunDateTime($dt, false);
        $f = new HoursField();
        $f->increment($d);
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));

        $dt->setTime(11, 15, 0);
        $d = new NextRunDateTime($dt, true);
        $f->increment($d, true);
        $this->assertSame('2011-03-15 10:59:00', $d->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    /**
     * @covers \Cron\HoursField::increment
     */
    public function testIncrementDateWithFifteenMinuteOffsetTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $dt = new DateTime('2011-03-15 11:15:00');
        $d = new NextRunDateTime($dt, false);
        $f = new HoursField();
        $f->increment($d);
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));

        $dt->setTime(11, 15, 0);
        $d = new NextRunDateTime($dt, true);
        $f->increment($d, true);
        $this->assertSame('2011-03-15 10:59:00', $d->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    /**
     * @covers \Cron\HoursField::increment
     */
    public function testIncrementAcrossDstChange(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $dt = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-27 23:00:00", $tz);
        $d = new NextRunDateTime($dt, false);
        $f = new HoursField();
        $f->increment($d);
        $this->assertSame("2021-03-28 00:00:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d);
        $this->assertSame("2021-03-28 02:00:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d);
        $this->assertSame("2021-03-28 03:00:00", $d->format("Y-m-d H:i:s"));

        $d = new NextRunDateTime($d->getDateTime(), true);
        $f->increment($d, true);
        $this->assertSame("2021-03-28 02:59:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d, true);
        $this->assertSame("2021-03-28 00:59:00", $d->format("Y-m-d H:i:s"));
        $f->increment($d, true);
        $this->assertSame("2021-03-27 23:59:00", $d->format("Y-m-d H:i:s"));
    }

}
