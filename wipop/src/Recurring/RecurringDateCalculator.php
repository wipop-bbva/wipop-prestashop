<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use DateTimeImmutable;

final class RecurringDateCalculator
{
    public function next(DateTimeImmutable $anchor, RecurringPeriod $period): DateTimeImmutable
    {
        return match ($period) {
            RecurringPeriod::MONTHLY => $this->addMonths($anchor, 1),
            RecurringPeriod::YEARLY => $this->addMonths($anchor, 12),
        };
    }

    private function addMonths(DateTimeImmutable $anchor, int $months): DateTimeImmutable
    {
        $month = (int) $anchor->format('n') + $months;
        $year = (int) $anchor->format('Y') + intdiv($month - 1, 12);
        $month = (($month - 1) % 12) + 1;
        $lastDay = (int) $anchor->setDate($year, $month, 1)->modify('last day of this month')->format('j');
        $day = min((int) $anchor->format('j'), $lastDay);

        return $anchor->setDate($year, $month, $day);
    }
}
