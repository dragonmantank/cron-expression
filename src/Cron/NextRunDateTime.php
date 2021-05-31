<?php
declare(strict_types=1);

namespace Cron;

use DateTime;
use DateTimeZone;

class NextRunDateTime
{

    /**
     * @var DateTime
     */
    protected $dt;

    /**
     * @var bool
     */
    protected $moveBackwards;

    /**
     * @var array|null Transitions returned by DateTimeZone::getTransitions()
     */
    protected $transitions = null;

    /**
     * @var int|null Timestamp of the start of the transitions range
     */
    protected $transitionsStart = null;

    /**
     * @var int|null Timestamp of the end of the transitions range
     */
    protected $transitionsEnd = null;

    /**
     * @var DateTimeZone (Always UTC)
     */
    protected $tzUtc;


    public function __construct(\DateTimeInterface $dateTime, bool $moveBackwards)
    {
        $timezone = $dateTime->getTimezone();
        // Clone the date/time value, but zero-out fields we don't care about
        // Don't use setTime because it can cause an offset change: https://bugs.php.net/bug.php?id=81074
        $this->dt = DateTime::createFromFormat("!Y-m-d H:iO", $dateTime->format("Y-m-d H:iP"), $timezone);
        $this->dt->setTimezone($timezone);
        $this->tzUtc = new DateTimeZone("UTC");
        $this->moveBackwards = $moveBackwards;
    }


    public function isMovingBackwards(): bool
    {
        return $this->moveBackwards;
    }


    public function getDateTime(): DateTime
    {
        return clone $this->dt;
    }


    public function getPastTransition(): ?array
    {
        $currentTimestamp = $this->getTimestamp();
        if (
            ($this->transitions === null)
            || ((! $this->moveBackwards) && ($this->transitionsStart < ($currentTimestamp + 86400)))
            || ($this->moveBackwards && ($this->transitionsEnd > ($currentTimestamp - 86400)))
        ) {
            // We start a day before current time so we can differentiate between the first transition entry
            // and a change that happens now
            $dtLimitStart = clone $this->dt;
            if (! $this->moveBackwards) {
                $dtLimitStart = $dtLimitStart->modify("-2 days");
                $dtLimitEnd = clone $dtLimitStart;
                $dtLimitEnd = $dtLimitEnd->modify('+12 months');
            } else {
                $dtLimitStart = $dtLimitStart->modify("-12 months");
                $dtLimitEnd = clone $this->dt;
                $dtLimitEnd = $dtLimitEnd->modify('+2 days');
            }

            $this->transitions = $this->dt->getTimezone()->getTransitions(
                $dtLimitStart->getTimestamp(),
                $dtLimitEnd->getTimestamp()
            );
            $this->transitionsStart = $dtLimitStart->getTimestamp();
            $this->transitionsEnd = $dtLimitEnd->getTimestamp();
        }

        $nextTransition = null;
        $currentTimestamp = $this->getTimestamp();
        foreach ($this->transitions as $transition) {
            if ($transition["ts"] > $currentTimestamp) {
                continue;
            }

            if (($nextTransition !== null) && ($transition["ts"] < $nextTransition["ts"])) {
                continue;
            }

            $nextTransition = $transition;
        }

        return ($nextTransition ?? null);
    }


    protected function timezoneSafeModify(string $modification): void
    {
        $timezone = $this->dt->getTimezone();
        $this->dt->setTimezone($this->tzUtc);
        $this->dt->modify($modification);
        $this->dt->setTimezone($timezone);
    }


    public function modify(string $modification): void
    {
        $this->timezoneSafeModify($modification);
    }


    public function setMinutes(int $target): void
    {
        $originalMinute = (int)$this->dt->format("i");

        if (! $this->moveBackwards) {
            if ($originalMinute >= $target) {
                $distance = 60 - $originalMinute;
                $this->timezoneSafeModify("+{$distance} minutes");

                $originalMinute = (int)$this->dt->format("i");
            }

            $distance = $target - $originalMinute;
            $this->timezoneSafeModify("+{$distance} minutes");
        } else {
            if ($originalMinute <= $target) {
                $distance = ($originalMinute + 1);
                $this->timezoneSafeModify("-{$distance} minutes");

                $originalMinute = (int)$this->dt->format("i");
            }

            $distance = $originalMinute - $target;
            $this->timezoneSafeModify("-{$distance} minutes");
        }
    }


    public function incrementHour(): void
    {
        $originalTimestamp = $this->getTimestamp();

        $this->timezoneSafeModify(($this->isMovingBackwards() ? "-" : "+") ."1 hour");
        $this->setTimeHour($originalTimestamp);
    }


    protected function setTimeHour(int $originalTimestamp): void
    {
        $this->dt->setTime((int)$this->dt->format('H'), ($this->moveBackwards ? 59 : 0));

        // setTime caused the offset to change, moving time in the wrong direction
        $actualTimestamp = $this->getTimestamp();
        if ((! $this->moveBackwards) && ($actualTimestamp <= $originalTimestamp)) {
            $this->timezoneSafeModify("+1 hour");
        } elseif ($this->moveBackwards && ($actualTimestamp >= $originalTimestamp)) {
            $this->timezoneSafeModify("-1 hour");
        }
    }


    public function setHour(int $target): void
    {
        $originalHour = (int)$this->dt->format('H');

        $originalDay = (int)$this->dt->format('d');
        $originalTimestamp = $this->getTimestamp();
        $previousOffset = $this->dt->getOffset();

        if (! $this->moveBackwards) {
            if ($originalHour >= $target) {
                $distance = 24 - $originalHour;
                $this->timezoneSafeModify("+{$distance} hours");

                $actualDay = (int)$this->dt->format('d');
                $actualHour = (int)$this->dt->format('H');
                if (($actualDay !== ($originalDay + 1)) && ($actualHour !== 0)) {
                    $offsetChange = ($previousOffset - $this->dt->getOffset());
                    $this->timezoneSafeModify("+{$offsetChange} seconds");
                }

                $originalHour = (int)$this->dt->format('H');
            }

            $distance = $target - $originalHour;
            $this->timezoneSafeModify("+{$distance} hours");
        } else {
            if ($originalHour <= $target) {
                $distance = ($originalHour + 1);
                $this->timezoneSafeModify("-" . $distance . " hours");

                $actualDay = (int)$this->dt->format('d');
                $actualHour = (int)$this->dt->format('H');
                if (($actualDay !== ($originalDay - 1)) && ($actualHour !== 23)) {
                    $offsetChange = ($previousOffset - $this->dt->getOffset());
                    $this->timezoneSafeModify("+{$offsetChange} seconds");
                }

                $originalHour = (int)$this->dt->format('H');
            }

            $distance = $originalHour - $target;
            $this->timezoneSafeModify("-{$distance} hours");
        }

        $this->setTimeHour($originalTimestamp);

        $actualHour = (int)$this->dt->format('H');
        if ($this->moveBackwards && ($actualHour === ($target - 1) || (($actualHour === 23) && ($target === 0)))) {
            $this->timezoneSafeModify("+1 hour");
        }
    }


    public function incrementDay(): void
    {
        if (! $this->moveBackwards) {
            $this->timezoneSafeModify('+1 day');
            $this->dt->setTime(0, 0);
        } else {
            $this->timezoneSafeModify('-1 day');
            $this->dt->setTime(23, 59);
        }
    }


    public function incrementMonth(): void
    {
        if (! $this->moveBackwards) {
            $this->dt->modify('first day of next month');
            $this->dt->setTime(0, 0);
        } else {
            $this->dt->modify('last day of previous month');
            $this->dt->setTime(23, 59);
        }
    }


    public function getRemainingDaysInMonth(): int
    {
        $daysInMonth = (int) $this->format('t');
        return $daysInMonth - (int)$this->dt->format('d');
    }


    /**
     * @inheritDoc
     */
    public function diff($targetObject, $absolute = false)
    {
        return $this->dt->diff($targetObject, $absolute);
    }


    /**
     * @inheritDoc
     */
    public function format($format)
    {
        return $this->dt->format($format);
    }


    /**
     * @inheritDoc
     */
    public function getOffset()
    {
        return $this->dt->getOffset();
    }


    /**
     * Note: Prior to PHP 8.1, ->getTimestamp may modify the offset (and thus time) stored in the object
     * even for a DateTimeImmutable.
     *
     * @return int
     */
    public function getTimestamp()
    {
        return (int) $this->dt->format('U');
    }


    /**
     * @inheritDoc
     */
    public function getTimezone()
    {
        return $this->dt->getTimezone();
    }


    public function __clone()
    {
        $this->dt = clone $this->dt;
    }

}
