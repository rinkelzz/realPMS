<?php

declare(strict_types=1);

require __DIR__ . '/../api/rate_calendar_helpers.php';

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $expectedJson = json_encode($expected, JSON_PRETTY_PRINT);
        $actualJson = json_encode($actual, JSON_PRETTY_PRINT);
        $prefix = $message !== '' ? $message . ' - ' : '';
        throw new RuntimeException($prefix . sprintf('expected %s but got %s', $expectedJson, $actualJson));
    }
}

function assertTrue(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException($message !== '' ? $message : 'Assertion failed.');
    }
}

function assertFloatEquals(float $expected, float $actual, float $delta, string $message = ''): void
{
    if (abs($expected - $actual) > $delta) {
        $prefix = $message !== '' ? $message . ' - ' : '';
        throw new RuntimeException($prefix . sprintf('expected %.4f but got %.4f', $expected, $actual));
    }
}

function testNormalizeWeekdays(): void
{
    assertSameValue([1, 2, 3, 4, 5, 6, 0], normalizeWeekdayValuesInput(['1', '6', '0', '4', '2', '3', '5', '5']));
    assertSameValue([], normalizeWeekdayValuesInput(['8', null, -1]));
    assertSameValue('1,2,5', serializeWeekdays([5, 2, 1, 2]));
    assertSameValue(null, serializeWeekdays([]));
    assertSameValue([1, 5, 0], deserializeWeekdayValues('5,1,0,10'));
}

function testExpandDateRange(): void
{
    $range = expandDateRange('2024-02-28', '2024-03-02');
    assertSameValue(['2024-02-28', '2024-02-29', '2024-03-01', '2024-03-02'], $range);
    assertSameValue([], expandDateRange('2024-03-02', '2024-02-28'));
}

function testBuildDailyRateMap(): void
{
    $rules = [
        [
            'id' => 1,
            'rate_calendar_id' => 10,
            'calendar_name' => 'Winter',
            'start_date' => '2024-01-05',
            'end_date' => '2024-01-31',
            'price' => 150.0,
            'weekdays' => [],
            'cancellation_policy_id' => 3,
            'cancellation_policy_name' => 'Standard',
            'closed_for_arrival' => 0,
            'closed_for_departure' => 0,
        ],
        [
            'id' => 2,
            'rate_calendar_id' => 10,
            'calendar_name' => 'Winter',
            'start_date' => '2024-01-05',
            'end_date' => '2024-01-07',
            'price' => 200.0,
            'weekdays' => [6, 0],
            'cancellation_policy_id' => 4,
            'cancellation_policy_name' => 'Weekend Special',
            'closed_for_arrival' => 0,
            'closed_for_departure' => 1,
        ],
        [
            'id' => 3,
            'rate_calendar_id' => 10,
            'calendar_name' => 'Winter',
            'start_date' => '2024-01-10',
            'end_date' => '2024-01-10',
            'price' => 95.0,
            'weekdays' => [3],
            'cancellation_policy_id' => null,
            'cancellation_policy_name' => null,
            'closed_for_arrival' => 1,
            'closed_for_departure' => 0,
        ],
    ];

    $daily = buildDailyRateMap($rules, '2024-01-04', '2024-01-10', 120.0, 'EUR');

    assertSameValue(7, count($daily));

    assertFloatEquals(120.0, $daily['2024-01-04']['price'], 0.0001, 'Base rate should be used without rules');
    assertSameValue(null, $daily['2024-01-04']['rule_id']);
    assertTrue($daily['2024-01-04']['is_weekend'] === false, 'Weekday should not be marked as weekend');

    assertSameValue(1, $daily['2024-01-05']['rule_id']);
    assertFloatEquals(150.0, $daily['2024-01-05']['price'], 0.0001);

    assertSameValue(2, $daily['2024-01-06']['rule_id']);
    assertFloatEquals(200.0, $daily['2024-01-06']['price'], 0.0001);
    assertSameValue('Weekend Special', $daily['2024-01-06']['cancellation_policy_name']);
    assertTrue($daily['2024-01-06']['is_weekend'], 'Saturday should be recognised as weekend');
    assertTrue($daily['2024-01-07']['closed_for_departure'], 'Weekend rule should enforce departure restriction');

    assertSameValue(3, $daily['2024-01-10']['rule_id']);
    assertTrue($daily['2024-01-10']['closed_for_arrival'], 'Specific rule should mark closed_for_arrival');
    assertTrue($daily['2024-01-10']['is_weekend'] === false, 'Wednesday should not be weekend');
}

function runTests(): void
{
    testNormalizeWeekdays();
    testExpandDateRange();
    testBuildDailyRateMap();
}

try {
    runTests();
    fwrite(STDOUT, "All rate calendar helper tests passed." . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Test failure: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
