<?php
declare(strict_types=1);

namespace Cron\Tests;

use Cron\CronExpression;
use PHPUnit\Framework\TestCase;

class DaylightSavingsTest extends TestCase
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


    public function testOffsetIncrementsNextRunDate(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $cron = new CronExpression("0 1 * * 0");

        $dtCurrent = $this->createDateTimeExactly("2021-03-21 00:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-21 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-21 02:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 00:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 02:05+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-04-04 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));
    }


    public function testOffsetIncrementsPreviousRunDate(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $cron = new CronExpression("0 1 * * 0");

        $dtCurrent = $this->createDateTimeExactly("2021-04-04 02:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-04-04 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-04-04 00:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 03:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 00:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-21 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));
    }


    public function testOffsetDecrementsNextRunDate(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $tzUtc = new \DateTimeZone("UTC");
        $cron = new CronExpression("0 1 * * 0");

        // Input in Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-18 00:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-18 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-18 01:05+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 00:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:05+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:05+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        // Input in UTC
        // Same as: 2020-10-25 01:00+01:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 00:00+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        // Same as: 2020-10-25 01:05+01:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 00:05+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        // Same as: 2020-10-25 01:00+00:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        // Same as: 2020-10-25 01:05+00:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:05+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));
    }


    /**
     * The fact that crons will run twice using this setup is expected.
     * This can be avoided by using disallowing the current date or with additional checks outside this library
     */
    public function testOffsetDecrementsNextRunDateDisallowCurrent(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $tzUtc = new \DateTimeZone("UTC");
        $cron = new CronExpression("0 1 * * 0");

        // Input in Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-18 00:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-18 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-18 01:05+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 00:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:05+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:05+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        // Input in UTC
        // Same as: 2020-10-25 01:00+01:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 00:00+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        // Same as: 2020-10-25 01:05+01:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 00:05+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        // Same as: 2020-10-25 01:00+00:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:00+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));

        // Same as: 2020-10-25 01:05+00:00 Europe/London
        $dtCurrent = $this->createDateTimeExactly("2020-10-25 01:05+00:00", $tzUtc);
        $dtExpected = $this->createDateTimeExactly("2020-11-01 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));
    }


    public function testOffsetDecrementsPreviousRunDate(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $cron = new CronExpression("0 1 * * 0");

        $dtCurrent = $this->createDateTimeExactly("2021-04-04 02:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-04-04 01:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-04-04 00:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 03:00+01:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-28 02:00+01:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = $this->createDateTimeExactly("2021-03-28 00:00+00:00", $tz);
        $dtExpected = $this->createDateTimeExactly("2021-03-21 01:00+00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));
    }


    public function testIssue111(): void
    {
        $expression = "0 1 * * 0";
        $cron = new CronExpression($expression);
        $tz = new \DateTimeZone("Europe/London");
        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 14:55:03", $tz);
        $result = $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName());
        $this->assertNotNull($result);
    }
}
