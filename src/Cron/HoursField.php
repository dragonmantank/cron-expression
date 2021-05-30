<?php

declare(strict_types=1);

namespace Cron;

use DateTimeInterface;
use DateTimeZone;

/**
 * Hours field.  Allows: * , / -.
 */
class HoursField extends AbstractField
{
    /**
     * {@inheritdoc}
     */
    protected $rangeStart = 0;

    /**
     * {@inheritdoc}
     */
    protected $rangeEnd = 23;

    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(NextRunDateTime $date, $value): bool
    {
        $checkValue = (int) $date->format('H');
        $retval = $this->isSatisfied($checkValue, $value);
        print "HoursField: isSatisfied check: {$checkValue}\n";
        if ($retval) {
            return $retval;
        }

        $offsetChange = $date->getLastChangeOffsetChange();
        if ($offsetChange === null) {
            // Initial check - we don't know if the offset just changed
            print "HoursField: Manually determining offset\n";
            $newOffset = $date->getOffset();
            $prevDate = clone $date;
            $prevDate->modify(($date->isMovingBackwards() ? '+' : '-') .'1 hours');
            $offsetChange = ($newOffset - $prevDate->getOffset());
        }

        if ($offsetChange === 0) {
            print "HoursField: offsetChange === 0 :: b? ". ($date->isMovingBackwards() ? 'true' : 'false') ."\n";
            if ($date->isMovingBackwards()) {
                $dtNextIncrementTest = clone $date;
                $dtNextIncrementTest->modify("-1 hour");
                $nextOffsetChange = $date->getOffset() - $dtNextIncrementTest->getOffset();
                print "HoursField: nextOffsetChange: {$nextOffsetChange}\n";
                if ($nextOffsetChange > 0) {
                    $checkValue -= 1;
                    print "HoursField: isSatisfied check (DST b): {$checkValue}\n";
                    $retval = $this->isSatisfied($checkValue, $value);
                }
            }

            return $retval;
        }

        if ($date->isMovingBackwards()) {
            print "HoursField: backwards\n";
            return $retval;
        }

        $change = (int)floor($offsetChange / 3600);
        $checkValue -= $change;
        print "HoursField: isSatisfied check (DST): {$checkValue}\n";
        return $this->isSatisfied($checkValue, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null                  $parts
     */
    public function increment(NextRunDateTime $date, $invert = false, $parts = null): FieldInterface
    {
        // Change timezone to UTC temporarily. This will
        // allow us to go back or forwards and hour even
        // if DST will be changed between the hours.
        if (null === $parts || '*' === $parts) {
            $date->modify(($invert ? '-' : '+') . '1 hour');
            return $this;
        }

        $parts = false !== strpos($parts, ',') ? explode(',', $parts) : [$parts];
        $hours = [];
        foreach ($parts as $part) {
            $hours = array_merge($hours, $this->getRangeForExpression($part, 23));
        }

        $current_hour = (int) $date->format('H');
        $position = $invert ? \count($hours) - 1 : 0;
        $countHours = \count($hours);
        if ($countHours > 1) {
            for ($i = 0; $i < $countHours - 1; ++$i) {
                if ((!$invert && $current_hour >= $hours[$i] && $current_hour < $hours[$i + 1]) ||
                    ($invert && $current_hour > $hours[$i] && $current_hour <= $hours[$i + 1])) {
                    $position = $invert ? $i : $i + 1;

                    break;
                }
            }
        }

        $target = (int) $hours[$position];
        $date->setHour($target);

        return $this;
    }
}
