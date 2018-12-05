<?php

namespace Cron\Tests;

use Cron\DayOfMonthField;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class DayOfMonthFieldTest extends TestCase
{
    /**
     * @covers \Cron\DayOfMonthField::validate
     */
    public function testValidatesField()
    {
        $dayOfMonthField = new DayOfMonthField();
        $this->assertTrue($dayOfMonthField->validate('1'));
        $this->assertTrue($dayOfMonthField->validate('*'));
        $this->assertTrue($dayOfMonthField->validate('L'));
        $this->assertTrue($dayOfMonthField->validate('5W'));
        $this->assertTrue($dayOfMonthField->validate('01'));
        $this->assertFalse($dayOfMonthField->validate('5W,L'));
        $this->assertFalse($dayOfMonthField->validate('1.'));
    }

    /**
     * @covers \Cron\DayOfMonthField::isSatisfiedBy
     */
    public function testChecksIfSatisfied()
    {
        $dayOfMonthField = new DayOfMonthField();
        $this->assertTrue($dayOfMonthField->isSatisfiedBy(new DateTime(), '?'));
    }

    /**
     * @covers \Cron\DayOfMonthField::isInIncrementsOfRanges
     * @expectedException \OutOfRangeException
     * @expectedExceptionMessage Invalid range start requested
     */
    public function testDateWithInvalidStartShouldThrowOutOfRangeException()
    {
        $dayOfMonthField = new DayOfMonthField();
        $dayOfMonthField->isSatisfiedBy(new DateTime(), '2018/03/02');
    }

    /**
     * @covers \Cron\DayOfMonthField::isInIncrementsOfRanges
     * @expectedException \OutOfRangeException
     * @expectedExceptionMessage Invalid range end requested
     */
    public function testDateWithInvalidEndShouldThrowOutOfRangeException()
    {
        $dayOfMonthField = new DayOfMonthField();
        $dayOfMonthField->isSatisfiedBy(new DateTime(), '7-2018/04:05:00');
    }

    /**
     * @covers \Cron\AbstractField::getRangeForExpression
     */
    public function testGetRangeForExpression()
    {
        $dayOfMonthField = new DayOfMonthField();
        $this->assertSame([], $dayOfMonthField->getRangeForExpression('2018-03-13 04:05:00', 5));
        $this->assertSame([], $dayOfMonthField->getRangeForExpression('2018/03/13 04:05:00', 5));
        $this->assertSame([3, 4, 5], $dayOfMonthField->getRangeForExpression('3-5-15', 15));
    }

    /**
     * @covers \Cron\AbstractField::validate
     */
    public function testValidateShouldReturnTrue()
    {
        $dayOfMonthField = new DayOfMonthField();
        $this->assertTrue($dayOfMonthField->validate('2,12'));
    }

    /**
     * @covers \Cron\DayOfMonthField::isSatisfiedBy
     */
    public function testIsSatipsfiedByOnLValue()
    {
        $dayOfMonthField = new DayOfMonthField();
        $this->assertFalse($dayOfMonthField->isSatisfiedBy(new DateTime(), 'L'));
    }

    /**
     * @covers \Cron\DayOfMonthField::increment
     */
    public function testIncrementsDate()
    {
        $dateTime = new DateTime('2011-03-15 11:15:00');
        $dayOfMonthField = new DayOfMonthField();
        $dayOfMonthField->increment($dateTime);
        $this->assertSame('2011-03-16 00:00:00', $dateTime->format('Y-m-d H:i:s'));

        $dateTime = new DateTime('2011-03-15 11:15:00');
        $dayOfMonthField->increment($dateTime, true);
        $this->assertSame('2011-03-14 23:59:00', $dateTime->format('Y-m-d H:i:s'));
    }

    /**
     * Day of the month cannot accept a 0 value, it must be between 1 and 31
     * See Github issue #120
     *
     * @since 2017-01-22
     */
    public function testDoesNotAccept0Date()
    {
        $dayOfMonthField = new DayOfMonthField();
        $this->assertFalse($dayOfMonthField->validate(0));
    }
}
