<?php

declare(strict_types=1);

namespace Cron\Tests;

use Cron\DayOfWeekField;
use Cron\NextRunDateTime;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class DayOfWeekFieldTest extends TestCase
{
    /**
     * @covers \Cron\DayOfWeekField::validate
     */
    public function testValidatesField(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->validate('1'));
        $this->assertTrue($f->validate('01'));
        $this->assertTrue($f->validate('00'));
        $this->assertTrue($f->validate('*'));
        $this->assertTrue($f->validate('?'));
        $this->assertFalse($f->validate('*/3,1,1-12'));
        $this->assertTrue($f->validate('SUN-2'));
        $this->assertFalse($f->validate('1.'));
    }

    /**
     * @covers \Cron\DayOfWeekField::isSatisfiedBy
     */
    public function testChecksIfSatisfied(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime(), false), '?'));
    }

    /**
     * @covers \Cron\DayOfWeekField::increment
     */
    public function testIncrementsDate(): void
    {
        $dt = new DateTime('2011-03-15 11:15:00');
        $d = new NextRunDateTime($dt, false);
        $f = new DayOfWeekField();
        $f->increment($d);
        $this->assertSame('2011-03-16 00:00:00', $d->format('Y-m-d H:i:s'));

        $dt = new DateTime('2011-03-15 11:15:00');
        $d = new NextRunDateTime($dt, true);
        $f->increment($d, true);
        $this->assertSame('2011-03-14 23:59:00', $d->format('Y-m-d H:i:s'));
    }

    /**
     * @covers \Cron\DayOfWeekField::isSatisfiedBy
     */
    public function testValidatesHashValueWeekday(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Weekday must be a value between 0 and 7. 12 given');
        $f = new DayOfWeekField();
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime( new DateTime(), false), '12#1'));
    }

    /**
     * @covers \Cron\DayOfWeekField::isSatisfiedBy
     */
    public function testValidatesHashValueNth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There are never more than 5 or less than 1 of a given weekday in a month');
        $f = new DayOfWeekField();
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime(), false), '3#6'));
    }

    /**
     * @covers \Cron\DayOfWeekField::validate
     */
    public function testValidateWeekendHash(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->validate('MON#1'));
        $this->assertTrue($f->validate('TUE#2'));
        $this->assertTrue($f->validate('WED#3'));
        $this->assertTrue($f->validate('THU#4'));
        $this->assertTrue($f->validate('FRI#5'));
        $this->assertTrue($f->validate('SAT#1'));
        $this->assertTrue($f->validate('SUN#3'));
        $this->assertTrue($f->validate('MON#1,MON#3'));
    }

    /**
     * @covers \Cron\DayOfWeekField::isSatisfiedBy
     */
    public function testHandlesZeroAndSevenDayOfTheWeekValues(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2011-09-04 00:00:00'), false), '0-2'));
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2011-09-04 00:00:00'), false), '6-0'));

        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2014-04-20 00:00:00'), false), 'SUN'));
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2014-04-20 00:00:00'), false), 'SUN#3'));
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2014-04-20 00:00:00'), false), '0#3'));
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2014-04-20 00:00:00'), false), '7#3'));
    }

    /**
     * @covers \Cron\DayOfWeekField::isSatisfiedBy
     */
    public function testHandlesLastWeekdayOfTheMonth(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2018-12-28 00:00:00'), false), 'FRIL'));
        $this->assertTrue($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2018-12-28 00:00:00'), false), '5L'));
        $this->assertFalse($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2018-12-21 00:00:00'), false), 'FRIL'));
        $this->assertFalse($f->isSatisfiedBy(new NextRunDateTime(new DateTime('2018-12-21 00:00:00'), false), '5L'));
    }

    /**
     * @see https://github.com/mtdowling/cron-expression/issues/47
     */
    public function testIssue47(): void
    {
        $f = new DayOfWeekField();
        $this->assertFalse($f->validate('mon,'));
        $this->assertFalse($f->validate('mon-'));
        $this->assertFalse($f->validate('*/2,'));
        $this->assertFalse($f->validate('-mon'));
        $this->assertFalse($f->validate(',1'));
        $this->assertFalse($f->validate('*-'));
        $this->assertFalse($f->validate(',-'));
    }

    /**
     * @see https://github.com/laravel/framework/commit/07d160ac3cc9764d5b429734ffce4fa311385403
     */
    public function testLiteralsExpandProperly(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->validate('MON-FRI'));
        $this->assertSame([1, 2, 3, 4, 5], $f->getRangeForExpression('MON-FRI', 7));
    }

    /**
     * Incoming literals should ignore case
     *
     * @author Chris Tankersley <chris@ctankersley.com?
     * @since 2019-07-29
     * @see https://github.com/dragonmantank/cron-expression/issues/24
     */
    public function testLiteralsIgnoreCasingProperly(): void
    {
        $f = new DayOfWeekField();
        $this->assertTrue($f->validate('MON'));
        $this->assertTrue($f->validate('Mon'));
        $this->assertTrue($f->validate('mon'));
        $this->assertTrue($f->validate('Mon,Wed,Fri'));
    }
}
