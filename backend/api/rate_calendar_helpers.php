<?php

declare(strict_types=1);

function normalizeWeekdayValuesInput($input): array
{
    if ($input === null) {
        return [];
    }
    $values = [];
    if (is_array($input)) {
        $values = $input;
    } elseif (is_string($input)) {
        $values = explode(',', $input);
    } else {
        $values = [$input];
    }

    $normalized = [];
    foreach ($values as $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $intValue = (int) $value;
        if ($intValue < 0 || $intValue > 6) {
            continue;
        }
        $normalized[$intValue] = true;
    }

    $weekdayOrder = [1, 2, 3, 4, 5, 6, 0];
    $result = [];
    foreach ($weekdayOrder as $weekday) {
        if (isset($normalized[$weekday])) {
            $result[] = $weekday;
        }
    }

    return $result;
}

function serializeWeekdays(array $weekdays): ?string
{
    $normalized = normalizeWeekdayValuesInput($weekdays);
    return $normalized ? implode(',', $normalized) : null;
}

function deserializeWeekdayValues(?string $value): array
{
    if ($value === null || $value === '') {
        return [];
    }
    return normalizeWeekdayValuesInput(explode(',', $value));
}

function expandDateRange(string $start, string $end): array
{
    $startDate = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);
    if ($endDate < $startDate) {
        return [];
    }
    $dates = [];
    for ($cursor = $startDate; $cursor <= $endDate; $cursor = $cursor->modify('+1 day')) {
        $dates[] = $cursor->format('Y-m-d');
    }

    return $dates;
}

function buildDailyRateMap(array $rules, string $startDate, string $endDate, float $basePrice, string $currency): array
{
    $days = expandDateRange($startDate, $endDate);
    $daily = [];
    foreach ($days as $day) {
        $dateTime = new DateTimeImmutable($day);
        $daily[$day] = [
            'date' => $day,
            'price' => $basePrice,
            'currency' => $currency,
            'calendar_id' => null,
            'calendar_name' => null,
            'rule_id' => null,
            'cancellation_policy_id' => null,
            'cancellation_policy_name' => null,
            'closed_for_arrival' => false,
            'closed_for_departure' => false,
            'is_weekend' => (int) $dateTime->format('w') === 0 || (int) $dateTime->format('w') === 6,
        ];
    }

    usort($rules, static function (array $a, array $b): int {
        $countA = isset($a['weekdays']) && is_array($a['weekdays']) ? count($a['weekdays']) : 0;
        $countB = isset($b['weekdays']) && is_array($b['weekdays']) ? count($b['weekdays']) : 0;
        if ($countA !== $countB) {
            return $countB <=> $countA;
        }
        $updatedA = $a['updated_at'] ?? $a['created_at'] ?? '';
        $updatedB = $b['updated_at'] ?? $b['created_at'] ?? '';
        return strcmp($updatedB, $updatedA);
    });

    foreach ($rules as $rule) {
        if (empty($rule['start_date']) || empty($rule['end_date'])) {
            continue;
        }
        $ruleDates = expandDateRange($rule['start_date'], $rule['end_date']);
        $weekdays = isset($rule['weekdays']) && is_array($rule['weekdays'])
            ? normalizeWeekdayValuesInput($rule['weekdays'])
            : [];

        foreach ($ruleDates as $date) {
            if (!isset($daily[$date])) {
                continue;
            }
            if ($weekdays) {
                $weekday = (int) (new DateTimeImmutable($date))->format('w');
                if (!in_array($weekday, $weekdays, true)) {
                    continue;
                }
            }
            if ($daily[$date]['rule_id'] !== null) {
                continue;
            }
            $daily[$date]['rule_id'] = $rule['id'] ?? null;
            $daily[$date]['calendar_id'] = $rule['rate_calendar_id'] ?? null;
            $daily[$date]['calendar_name'] = $rule['calendar_name'] ?? null;
            if (isset($rule['price']) && $rule['price'] !== null) {
                $daily[$date]['price'] = (float) $rule['price'];
            }
            if (isset($rule['cancellation_policy_id'])) {
                $daily[$date]['cancellation_policy_id'] = $rule['cancellation_policy_id'];
            }
            if (isset($rule['cancellation_policy_name'])) {
                $daily[$date]['cancellation_policy_name'] = $rule['cancellation_policy_name'];
            }
            $daily[$date]['closed_for_arrival'] = !empty($rule['closed_for_arrival']);
            $daily[$date]['closed_for_departure'] = !empty($rule['closed_for_departure']);
        }
    }

    return $daily;
}

