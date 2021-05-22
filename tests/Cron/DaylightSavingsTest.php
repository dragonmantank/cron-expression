<?php
declare(strict_types=1);

namespace Cron\Tests;

use Cron\CronExpression;
use PHPUnit\Framework\TestCase;

class DaylightSavingsTest extends TestCase
{


    public function testOffsetIncrementsNextRunDate(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $cron = new CronExpression("0 1 * * 0");

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-21 00:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-21 01:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-21 02:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 02:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 00:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 02:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 01:03:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-04-04 01:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 02:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 02:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, true, $tz->getName()));

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 02:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-04-04 01:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getNextRunDate($dtCurrent, 0, false, $tz->getName()));
    }


    public function testOffsetIncrementsPreviousRunDate(): void
    {
        $tz = new \DateTimeZone("Europe/London");
        $cron = new CronExpression("0 1 * * 0");

        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-04-04 02:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-04-04 01:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $cron = new CronExpression("0 1 * * 0");
        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-04-04 00:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 02:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $cron = new CronExpression("0 1 * * 0");
        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 03:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 01:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));

        $cron = new CronExpression("0 1 * * 0");
        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 00:00:00", $tz);
        $dtExpected = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-21 01:00:00", $tz);
        $this->assertEquals($dtExpected, $cron->getPreviousRunDate($dtCurrent, 0, true, $tz->getName()));
    }


    public function testIssue111(): void
    {
        $expression = "0 1 * * 0";
        $cronex = new CronExpression($expression);
        $tz = new \DateTimeZone("Europe/London");
        $dtCurrent = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2021-03-28 14:55:03", $tz);
        $result = $cronex->getPreviousRunDate($dtCurrent, 0, true, $tz->getName());
        $this->assertNotNull($result);
    }
}
