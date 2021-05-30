<?php
declare(strict_types=1);

namespace Cron\Tests;

use Cron\NextRunDateTime;
use PHPUnit\Framework\TestCase;

class NextRunDateTimeTest extends TestCase
{

    /**
     * Create a DateTimeImmutable that represents the given exact moment in time.
     * This is a bit finicky because DateTime likes to override the timezone with the offset even when it's valid
     *  and in some cases necessary during DST changes.
     * Assertions verify no unexpected behavior changes in PHP.
     */
    protected function createDateTimeExactly($dtString, \DateTimeZone $timezone)
    {
        $dt = \DateTimeImmutable::createFromFormat("!Y-m-d H:iO", $dtString, $timezone);
        $dt = $dt->setTimezone($timezone);
        $this->assertEquals($dtString, $dt->format("Y-m-d H:iP"));
        $this->assertEquals($timezone->getName(), $dt->format("e"));
        return $dt;
    }


    public function testSetHourUTCForwards(): void
    {
        $tz = new \DateTimeZone("UTC");
        $dt = $this->createDateTimeExactly("2021-03-27 00:03+00:00", $tz);
        $nextRun = new NextRunDateTime($dt, false);

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-27 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(2);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(15);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 15:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(3);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 03:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());
    }


    public function testSetHourUTCBackwards(): void
    {
        $tz = new \DateTimeZone("UTC");
        $dt = $this->createDateTimeExactly("2021-03-29 15:03+00:00", $tz);
        $nextRun = new NextRunDateTime($dt, true);

        $nextRun->setHour(2);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 02:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 01:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(15);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 15:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(3);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 03:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());
    }


    public function testSetHourLondonForwardsSpring(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $dt = $this->createDateTimeExactly("2021-03-27 23:03+00:00", $tz);
        $nextRun = new NextRunDateTime($dt, false);

        $nextRun->setHour(0);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 00:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(3600, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(2);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(15);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 15:00+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());
    }


    public function testSetHourLondonForwardsFall(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $dt = $this->createDateTimeExactly("2020-10-24 00:03+01:00", $tz);
        $nextRun = new NextRunDateTime($dt, false);

        $nextRun->setHour(0);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 00:00+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(-3600, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(2);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 02:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(15);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 15:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(3);
        $dtExpected = $this->createDateTimeExactly("2020-10-26 03:00+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());
    }


    public function testSetHourLondonBackwardsSpring(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $dt = $this->createDateTimeExactly("2021-03-29 15:03+01:00", $tz);
        $nextRun = new NextRunDateTime($dt, true);

        $nextRun->setHour(2);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 02:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-29 01:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(3);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 03:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(0);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 00:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(-3600, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(15);
        $dtExpected = $this->createDateTimeExactly("2021-03-27 15:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());
    }


    public function testSetHourLondonBackwardsFall(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $dt = $this->createDateTimeExactly("2020-10-26 00:03+00:00", $tz);
        $nextRun = new NextRunDateTime($dt, true);

        $nextRun->setHour(3);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 03:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(2);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 02:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:59+00:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(1);
        $dtExpected = $this->createDateTimeExactly("2020-10-24 01:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(3600, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(0);
        $dtExpected = $this->createDateTimeExactly("2020-10-24 00:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());

        $nextRun->setHour(3);
        $dtExpected = $this->createDateTimeExactly("2020-10-23 03:59+01:00", $tz);
        $this->assertEquals($dtExpected, $nextRun->getDateTime());
        $this->assertEquals(0, $nextRun->getLastChangeOffsetChange());
    }


}
