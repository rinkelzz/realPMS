<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/rate_calendar_helpers.php';

const RESERVATION_STATUSES = [
    'tentative',
    'confirmed',
    'checked_in',
    'paid',
    'checked_out',
    'cancelled',
    'no_show',
];

const CALENDAR_COLOR_STATUSES = [
    'tentative',
    'confirmed',
    'checked_in',
    'paid',
    'checked_out',
    'cancelled',
    'no_show',
];

const DEFAULT_CALENDAR_COLORS = [
    'tentative' => '#f97316',
    'confirmed' => '#2563eb',
    'checked_in' => '#16a34a',
    'paid' => '#0ea5e9',
    'checked_out' => '#6b7280',
    'cancelled' => '#ef4444',
    'no_show' => '#7c3aed',
];

const ARTICLE_CHARGE_SCHEMES = [
    'per_person_per_day',
    'per_room_per_day',
    'per_stay',
    'per_person',
    'per_day',
];

const GERMAN_VAT_STANDARD = 19.0;
const GERMAN_VAT_REDUCED = 7.0;
const DEFAULT_NIGHTLY_RATE = 99.0;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo !== '') {
    $path = trim($pathInfo, '/');
} else {
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';
    $path = trim($path, '/');
    $scriptName = trim($_SERVER['SCRIPT_NAME'] ?? '', '/');
    if ($scriptName !== '' && str_starts_with($path, $scriptName)) {
        $path = trim(substr($path, strlen($scriptName)), '/');
    } else {
        $scriptDir = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = trim(substr($path, strlen($scriptDir)), '/');
        }
    }
}
$segments = $path === '' ? [] : explode('/', $path);
$resource = $segments[0] ?? '';

if ($resource === '') {
    jsonResponse([
        'name' => 'realPMS Prototype API',
        'version' => '0.1.0',
        'resources' => [
            'room-types',
            'rate-plans',
        'rooms',
        'reservations',
        'guests',
        'companies',
        'housekeeping/tasks',
        'invoices',
        'payments',
        'reports',
        'users',
        'roles',
        'permissions',
        'integrations',
        'guest-portal',
        'settings',
        'articles',
    ],
]);
}

$publicResources = ['guest-portal'];
if (!in_array($resource, $publicResources, true)) {
    requireApiKey();
}

switch ($resource) {
    case 'room-types':
        handleRoomTypes($method, $segments);
        break;
    case 'rate-plans':
        handleRatePlans($method, $segments);
        break;
    case 'rate-calendars':
        handleRateCalendars($method, $segments);
        break;
    case 'rate-calendar-rules':
        handleRateCalendarRules($method, $segments);
        break;
    case 'rooms':
        handleRooms($method, $segments);
        break;
    case 'reservations':
        handleReservations($method, $segments);
        break;
    case 'guests':
        handleGuests($method, $segments);
        break;
    case 'companies':
        handleCompanies($method, $segments);
        break;
    case 'housekeeping':
        handleHousekeeping($method, $segments);
        break;
    case 'invoices':
        handleInvoices($method, $segments);
        break;
    case 'payments':
        handlePayments($method, $segments);
        break;
    case 'reports':
        handleReports($method, $segments);
        break;
    case 'articles':
        handleArticles($method, $segments);
        break;
    case 'cancellation-policies':
        handleCancellationPolicies($method, $segments);
        break;
    case 'users':
        handleUsers($method, $segments);
        break;
    case 'roles':
        handleRoles($method, $segments);
        break;
    case 'permissions':
        handlePermissions($method, $segments);
        break;
    case 'integrations':
        handleIntegrations($method, $segments);
        break;
    case 'guest-portal':
        handleGuestPortal($method, $segments);
        break;
    case 'settings':
        handleSettings($method, $segments);
        break;
    default:
        jsonResponse(['error' => 'Resource not found.'], 404);
}

function handleRoomTypes(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM room_types WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $type = $stmt->fetch();
            if (!$type) {
                jsonResponse(['error' => 'Room type not found.'], 404);
            }
            jsonResponse($type);
        }

        $stmt = $pdo->query('SELECT * FROM room_types ORDER BY name');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'Name is required.'], 422);
        }
        if (!array_key_exists('max_occupancy', $data)) {
            jsonResponse(['error' => 'Max occupancy is required.'], 422);
        }

        $baseOccupancy = isset($data['base_occupancy']) ? (int) $data['base_occupancy'] : 1;
        $maxOccupancy = (int) $data['max_occupancy'];
        if ($baseOccupancy <= 0) {
            jsonResponse(['error' => 'Base occupancy must be greater than 0.'], 422);
        }
        if ($maxOccupancy <= 0) {
            jsonResponse(['error' => 'Max occupancy must be greater than 0.'], 422);
        }
        if ($baseOccupancy > $maxOccupancy) {
            jsonResponse(['error' => 'Base occupancy cannot exceed max occupancy.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO room_types (name, description, base_occupancy, max_occupancy, currency, created_at, updated_at) VALUES (:name, :description, :base_occupancy, :max_occupancy, :currency, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_occupancy' => $baseOccupancy,
            'max_occupancy' => $maxOccupancy,
            'currency' => $data['currency'] ?? 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();

        $stmt = $pdo->prepare('SELECT base_occupancy, max_occupancy FROM room_types WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetch();
        if (!$current) {
            jsonResponse(['error' => 'Room type not found.'], 404);
        }

        $fields = ['name', 'description', 'base_occupancy', 'max_occupancy', 'currency'];
        $updates = [];
        $params = ['id' => $id];
        $baseOccupancy = (int) ($current['base_occupancy'] ?? 1);
        $maxOccupancy = (int) ($current['max_occupancy'] ?? 1);
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'base_occupancy' || $field === 'max_occupancy') {
                if ($data[$field] === null || $data[$field] === '') {
                    jsonResponse(['error' => sprintf('%s is required.', ucfirst(str_replace('_', ' ', $field)))], 422);
                }
                $value = (int) $data[$field];
                if ($value <= 0) {
                    jsonResponse(['error' => sprintf('%s must be greater than 0.', ucfirst(str_replace('_', ' ', $field)))], 422);
                }
                if ($field === 'base_occupancy') {
                    $baseOccupancy = $value;
                }
                if ($field === 'max_occupancy') {
                    $maxOccupancy = $value;
                }
                $params[$field] = $value;
            } else {
                $params[$field] = $data[$field];
            }
            $updates[] = sprintf('%s = :%s', $field, $field);
        }

        if ($baseOccupancy > $maxOccupancy) {
            jsonResponse(['error' => 'Base occupancy cannot exceed max occupancy.'], 422);
        }

        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();

        $stmt = $pdo->prepare(sprintf('UPDATE room_types SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleRatePlans(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($id !== null && isset($segments[2]) && $segments[2] === 'calendar') {
        if ($method !== 'GET') {
            jsonResponse(['error' => 'Unsupported method.'], 405);
        }
        handleRatePlanCalendar($pdo, (int) $id);
        return;
    }

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT rp.*, cp.name AS cancellation_policy_name FROM rate_plans rp LEFT JOIN cancellation_policies cp ON cp.id = rp.cancellation_policy_id WHERE rp.id = :id');
            $stmt->execute(['id' => $id]);
            $plan = $stmt->fetch();
            if (!$plan) {
                jsonResponse(['error' => 'Rate plan not found.'], 404);
            }
            jsonResponse($plan);
        }

        $stmt = $pdo->query('SELECT rp.*, cp.name AS cancellation_policy_name FROM rate_plans rp LEFT JOIN cancellation_policies cp ON cp.id = rp.cancellation_policy_id ORDER BY rp.name');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'Name is required.'], 422);
        }

        $cancellationPolicyId = null;
        if (!empty($data['cancellation_policy_id'])) {
            $policyStmt = $pdo->prepare('SELECT id FROM cancellation_policies WHERE id = :id');
            $policyStmt->execute(['id' => $data['cancellation_policy_id']]);
            if (!$policyStmt->fetchColumn()) {
                jsonResponse(['error' => 'Cancellation policy not found.'], 422);
            }
            $cancellationPolicyId = (int) $data['cancellation_policy_id'];
        }

        $stmt = $pdo->prepare('INSERT INTO rate_plans (name, description, base_price, currency, cancellation_policy, cancellation_policy_id, created_at, updated_at) VALUES (:name, :description, :base_price, :currency, :cancellation_policy, :cancellation_policy_id, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'] ?? 0,
            'currency' => $data['currency'] ?? 'EUR',
            'cancellation_policy' => $data['cancellation_policy'] ?? null,
            'cancellation_policy_id' => $cancellationPolicyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['name', 'description', 'base_price', 'currency', 'cancellation_policy', 'cancellation_policy_id'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'cancellation_policy_id') {
                    if ($data[$field] === null || $data[$field] === '') {
                        $params[$field] = null;
                    } else {
                        $policyStmt = $pdo->prepare('SELECT id FROM cancellation_policies WHERE id = :id');
                        $policyStmt->execute(['id' => $data[$field]]);
                        if (!$policyStmt->fetchColumn()) {
                            jsonResponse(['error' => 'Cancellation policy not found.'], 422);
                        }
                        $params[$field] = (int) $data[$field];
                    }
                } else {
                    $params[$field] = $data[$field];
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
            }
        }

        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();

        $stmt = $pdo->prepare(sprintf('UPDATE rate_plans SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    if ($method === 'DELETE' && $id !== null) {
        $stmt = $pdo->prepare('DELETE FROM rate_plans WHERE id = :id');
        try {
            $stmt->execute(['id' => $id]);
        } catch (PDOException $exception) {
            jsonResponse(['error' => 'Rate plan cannot be deleted while in use.'], 409);
        }
        jsonResponse(['deleted' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleCancellationPolicies(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM cancellation_policies WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $policy = $stmt->fetch();
            if (!$policy) {
                jsonResponse(['error' => 'Cancellation policy not found.'], 404);
            }
            jsonResponse($policy);
        }

        $stmt = $pdo->query('SELECT * FROM cancellation_policies ORDER BY name');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'Name is required.'], 422);
        }
        $penaltyType = $data['penalty_type'] ?? 'percent';
        $allowedTypes = ['percent', 'fixed', 'nights'];
        if (!in_array($penaltyType, $allowedTypes, true)) {
            jsonResponse(['error' => 'Invalid penalty_type.'], 422);
        }
        $penaltyValue = isset($data['penalty_value']) ? (float) $data['penalty_value'] : 0.0;
        if ($penaltyValue < 0) {
            jsonResponse(['error' => 'penalty_value must be positive.'], 422);
        }
        $freeUntil = isset($data['free_until_days']) && $data['free_until_days'] !== ''
            ? max(0, (int) $data['free_until_days'])
            : null;

        $stmt = $pdo->prepare('INSERT INTO cancellation_policies (name, description, free_until_days, penalty_type, penalty_value, created_at, updated_at) VALUES (:name, :description, :free_until_days, :penalty_type, :penalty_value, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'free_until_days' => $freeUntil,
            'penalty_type' => $penaltyType,
            'penalty_value' => $penaltyValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['name', 'description', 'free_until_days', 'penalty_type', 'penalty_value'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'penalty_type') {
                    $allowedTypes = ['percent', 'fixed', 'nights'];
                    if (!in_array($data[$field], $allowedTypes, true)) {
                        jsonResponse(['error' => 'Invalid penalty_type.'], 422);
                    }
                }
                if ($field === 'penalty_value') {
                    $value = (float) $data[$field];
                    if ($value < 0) {
                        jsonResponse(['error' => 'penalty_value must be positive.'], 422);
                    }
                }
                if ($field === 'free_until_days') {
                    $params[$field] = $data[$field] === null || $data[$field] === ''
                        ? null
                        : max(0, (int) $data[$field]);
                    $updates[] = sprintf('%s = :%s', $field, $field);
                    continue;
                }
                $params[$field] = $data[$field];
                $updates[] = sprintf('%s = :%s', $field, $field);
            }
        }

        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE cancellation_policies SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    if ($method === 'DELETE' && $id !== null) {
        $stmt = $pdo->prepare('DELETE FROM cancellation_policies WHERE id = :id');
        try {
            $stmt->execute(['id' => $id]);
        } catch (PDOException $exception) {
            jsonResponse(['error' => 'Cancellation policy cannot be deleted while in use.'], 409);
        }
        jsonResponse(['deleted' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleRateCalendars(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($id !== null && isset($segments[2]) && $segments[2] === 'rules') {
        $extra = array_slice($segments, 3);
        handleRateCalendarRulesForCalendar($pdo, $method, (int) $id, $extra);
        return;
    }

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT rc.*, COUNT(rcr.id) AS rule_count FROM rate_calendars rc LEFT JOIN rate_calendar_rules rcr ON rcr.rate_calendar_id = rc.id WHERE rc.id = :id GROUP BY rc.id');
            $stmt->execute(['id' => $id]);
            $calendar = $stmt->fetch();
            if (!$calendar) {
                jsonResponse(['error' => 'Calendar not found.'], 404);
            }
            jsonResponse($calendar);
        }

        $query = 'SELECT rc.*, COUNT(rcr.id) AS rule_count FROM rate_calendars rc LEFT JOIN rate_calendar_rules rcr ON rcr.rate_calendar_id = rc.id';
        $conditions = [];
        $params = [];
        if (isset($_GET['rate_plan_id'])) {
            $conditions[] = 'rc.rate_plan_id = :rate_plan_id';
            $params['rate_plan_id'] = (int) $_GET['rate_plan_id'];
        }
        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' GROUP BY rc.id ORDER BY rc.name';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['name']) || empty($data['rate_plan_id'])) {
            jsonResponse(['error' => 'name and rate_plan_id are required.'], 422);
        }
        $planStmt = $pdo->prepare('SELECT id FROM rate_plans WHERE id = :id');
        $planStmt->execute(['id' => $data['rate_plan_id']]);
        if (!$planStmt->fetchColumn()) {
            jsonResponse(['error' => 'Rate plan not found.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO rate_calendars (rate_plan_id, name, description, created_at, updated_at) VALUES (:rate_plan_id, :name, :description, :created_at, :updated_at)');
        $stmt->execute([
            'rate_plan_id' => $data['rate_plan_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['name', 'description', 'rate_plan_id'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'rate_plan_id') {
                    if ($data[$field] === null || $data[$field] === '') {
                        jsonResponse(['error' => 'rate_plan_id cannot be empty.'], 422);
                    }
                    $planStmt = $pdo->prepare('SELECT id FROM rate_plans WHERE id = :id');
                    $planStmt->execute(['id' => $data[$field]]);
                    if (!$planStmt->fetchColumn()) {
                        jsonResponse(['error' => 'Rate plan not found.'], 422);
                    }
                    $params[$field] = $data[$field];
                } else {
                    $params[$field] = $data[$field];
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
            }
        }

        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE rate_calendars SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    if ($method === 'DELETE' && $id !== null) {
        $stmt = $pdo->prepare('DELETE FROM rate_calendars WHERE id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(['deleted' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleRateCalendarRulesForCalendar(PDO $pdo, string $method, int $calendarId, array $extraSegments): void
{
    $calendarStmt = $pdo->prepare('SELECT rc.*, rp.currency FROM rate_calendars rc JOIN rate_plans rp ON rp.id = rc.rate_plan_id WHERE rc.id = :id');
    $calendarStmt->execute(['id' => $calendarId]);
    $calendar = $calendarStmt->fetch();
    if (!$calendar) {
        jsonResponse(['error' => 'Calendar not found.'], 404);
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT rcr.*, cp.name AS cancellation_policy_name, cp.penalty_type, cp.penalty_value, cp.free_until_days, :currency AS currency FROM rate_calendar_rules rcr LEFT JOIN cancellation_policies cp ON cp.id = rcr.cancellation_policy_id WHERE rcr.rate_calendar_id = :calendar_id ORDER BY rcr.start_date, rcr.end_date');
        $stmt->execute([
            'calendar_id' => $calendarId,
            'currency' => $calendar['currency'] ?? 'EUR',
        ]);
        $rules = $stmt->fetchAll();
        foreach ($rules as &$rule) {
            $rule['weekdays'] = deserializeWeekdayValues($rule['weekdays'] ?? null);
            $rule['closed_for_arrival'] = (bool) ($rule['closed_for_arrival'] ?? 0);
            $rule['closed_for_departure'] = (bool) ($rule['closed_for_departure'] ?? 0);
        }
        jsonResponse($rules);
    }

    if ($method === 'POST' && empty($extraSegments)) {
        $data = parseJsonBody();
        foreach (['start_date', 'end_date'] as $required) {
            if (empty($data[$required]) || !validateDate($data[$required])) {
                jsonResponse(['error' => sprintf('%s must be a valid date (Y-m-d).', $required)], 422);
            }
        }
        if ($data['end_date'] < $data['start_date']) {
            jsonResponse(['error' => 'end_date must be after start_date.'], 422);
        }
        $weekdays = isset($data['weekdays']) ? normalizeWeekdayValuesInput($data['weekdays']) : [];
        $serializedWeekdays = serializeWeekdays($weekdays);
        $cancellationPolicyId = null;
        if (!empty($data['cancellation_policy_id'])) {
            $policyStmt = $pdo->prepare('SELECT id FROM cancellation_policies WHERE id = :id');
            $policyStmt->execute(['id' => $data['cancellation_policy_id']]);
            if (!$policyStmt->fetchColumn()) {
                jsonResponse(['error' => 'Cancellation policy not found.'], 422);
            }
            $cancellationPolicyId = (int) $data['cancellation_policy_id'];
        }
        $stmt = $pdo->prepare('INSERT INTO rate_calendar_rules (rate_calendar_id, start_date, end_date, price, weekdays, cancellation_policy_id, closed_for_arrival, closed_for_departure, created_at, updated_at) VALUES (:calendar_id, :start_date, :end_date, :price, :weekdays, :cancellation_policy_id, :closed_for_arrival, :closed_for_departure, :created_at, :updated_at)');
        $stmt->execute([
            'calendar_id' => $calendarId,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'price' => isset($data['price']) ? $data['price'] : null,
            'weekdays' => $serializedWeekdays,
            'cancellation_policy_id' => $cancellationPolicyId,
            'closed_for_arrival' => !empty($data['closed_for_arrival']) ? 1 : 0,
            'closed_for_departure' => !empty($data['closed_for_departure']) ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if ($method === 'POST' && isset($extraSegments[0]) && $extraSegments[0] === 'batch') {
        $data = parseJsonBody();
        foreach (['start_date', 'end_date'] as $required) {
            if (empty($data[$required]) || !validateDate($data[$required])) {
                jsonResponse(['error' => sprintf('%s must be a valid date (Y-m-d).', $required)], 422);
            }
        }
        if ($data['end_date'] < $data['start_date']) {
            jsonResponse(['error' => 'end_date must be after start_date.'], 422);
        }
        if (!isset($data['price'])) {
            jsonResponse(['error' => 'price is required for batch updates.'], 422);
        }
        $weekdays = isset($data['weekdays']) ? normalizeWeekdayValuesInput($data['weekdays']) : [];
        if (!empty($data['weekend_only']) && !$weekdays) {
            $weekdays = normalizeWeekdayValuesInput([6, 0]);
        }
        $serializedWeekdays = serializeWeekdays($weekdays);
        $cancellationPolicyId = null;
        if (!empty($data['cancellation_policy_id'])) {
            $policyStmt = $pdo->prepare('SELECT id FROM cancellation_policies WHERE id = :id');
            $policyStmt->execute(['id' => $data['cancellation_policy_id']]);
            if (!$policyStmt->fetchColumn()) {
                jsonResponse(['error' => 'Cancellation policy not found.'], 422);
            }
            $cancellationPolicyId = (int) $data['cancellation_policy_id'];
        }

        $start = new DateTimeImmutable($data['start_date']);
        $end = new DateTimeImmutable($data['end_date']);
        $periods = [];
        if (!empty($data['split_by_month'])) {
            $cursor = $start;
            while ($cursor <= $end) {
                $monthEnd = (new DateTimeImmutable($cursor->format('Y-m-01')))->modify('last day of this month');
                if ($monthEnd > $end) {
                    $monthEnd = $end;
                }
                $periods[] = [$cursor->format('Y-m-d'), $monthEnd->format('Y-m-d')];
                $cursor = $monthEnd->modify('+1 day');
            }
        } else {
            $periods[] = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO rate_calendar_rules (rate_calendar_id, start_date, end_date, price, weekdays, cancellation_policy_id, closed_for_arrival, closed_for_departure, created_at, updated_at) VALUES (:calendar_id, :start_date, :end_date, :price, :weekdays, :cancellation_policy_id, :closed_for_arrival, :closed_for_departure, :created_at, :updated_at)');
            foreach ($periods as [$periodStart, $periodEnd]) {
                $stmt->execute([
                    'calendar_id' => $calendarId,
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'price' => $data['price'],
                    'weekdays' => $serializedWeekdays,
                    'cancellation_policy_id' => $cancellationPolicyId,
                    'closed_for_arrival' => !empty($data['closed_for_arrival']) ? 1 : 0,
                    'closed_for_departure' => !empty($data['closed_for_departure']) ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            jsonResponse(['error' => $exception->getMessage()], 500);
        }

        jsonResponse(['created' => count($periods)]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleRateCalendarRules(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($id === null) {
        jsonResponse(['error' => 'Rule id required.'], 400);
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT * FROM rate_calendar_rules WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $rule = $stmt->fetch();
        if (!$rule) {
            jsonResponse(['error' => 'Rule not found.'], 404);
        }
        $rule['weekdays'] = deserializeWeekdayValues($rule['weekdays'] ?? null);
        jsonResponse($rule);
    }

    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM rate_calendar_rules WHERE id = :id');
        $stmt->execute(['id' => $id]);
        jsonResponse(['deleted' => true]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $data = parseJsonBody();
        $fields = ['start_date', 'end_date', 'price', 'weekdays', 'cancellation_policy_id', 'closed_for_arrival', 'closed_for_departure'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ($field === 'weekdays') {
                $params['weekdays'] = serializeWeekdays(normalizeWeekdayValuesInput($data[$field] ?? []));
                $updates[] = 'weekdays = :weekdays';
                continue;
            }
            if ($field === 'cancellation_policy_id') {
                if ($data[$field] === null || $data[$field] === '') {
                    $params[$field] = null;
                } else {
                    $policyStmt = $pdo->prepare('SELECT id FROM cancellation_policies WHERE id = :id');
                    $policyStmt->execute(['id' => $data[$field]]);
                    if (!$policyStmt->fetchColumn()) {
                        jsonResponse(['error' => 'Cancellation policy not found.'], 422);
                    }
                    $params[$field] = (int) $data[$field];
                }
                $updates[] = 'cancellation_policy_id = :cancellation_policy_id';
                continue;
            }
            if (in_array($field, ['closed_for_arrival', 'closed_for_departure'], true)) {
                $params[$field] = !empty($data[$field]) ? 1 : 0;
                $updates[] = sprintf('%s = :%s', $field, $field);
                continue;
            }
            if (in_array($field, ['start_date', 'end_date'], true)) {
                if (!validateDate($data[$field])) {
                    jsonResponse(['error' => sprintf('%s must be a valid date (Y-m-d).', $field)], 422);
                }
            }
            $params[$field] = $data[$field];
            $updates[] = sprintf('%s = :%s', $field, $field);
        }

        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE rate_calendar_rules SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleRatePlanCalendar(PDO $pdo, int $planId): void
{
    $stmt = $pdo->prepare('SELECT * FROM rate_plans WHERE id = :id');
    $stmt->execute(['id' => $planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        jsonResponse(['error' => 'Rate plan not found.'], 404);
    }

    $year = isset($_GET['year']) ? (int) $_GET['year'] : null;
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    if ($year) {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
    }
    if (!$start || !$end) {
        $currentYear = (int) date('Y');
        $start = sprintf('%04d-01-01', $currentYear);
        $end = sprintf('%04d-12-31', $currentYear);
    }
    if (!validateDate($start) || !validateDate($end)) {
        jsonResponse(['error' => 'start and end must be valid dates (Y-m-d).'], 422);
    }
    if ($end < $start) {
        jsonResponse(['error' => 'end must be after start.'], 422);
    }

    $stmt = $pdo->prepare('SELECT rcr.*, rc.name AS calendar_name, rc.id AS rate_calendar_id, cp.name AS cancellation_policy_name, cp.penalty_type, cp.penalty_value, cp.free_until_days FROM rate_calendar_rules rcr JOIN rate_calendars rc ON rc.id = rcr.rate_calendar_id LEFT JOIN cancellation_policies cp ON cp.id = rcr.cancellation_policy_id WHERE rc.rate_plan_id = :plan_id AND rcr.end_date >= :start AND rcr.start_date <= :end');
    $stmt->execute([
        'plan_id' => $planId,
        'start' => $start,
        'end' => $end,
    ]);
    $rules = $stmt->fetchAll();
    foreach ($rules as &$rule) {
        $rule['weekdays'] = deserializeWeekdayValues($rule['weekdays'] ?? null);
    }

    $dailyMap = buildDailyRateMap($rules, $start, $end, (float) ($plan['base_price'] ?? 0.0), $plan['currency'] ?? 'EUR');
    $days = array_values($dailyMap);
    jsonResponse([
        'rate_plan_id' => (int) $planId,
        'currency' => $plan['currency'] ?? 'EUR',
        'base_price' => (float) ($plan['base_price'] ?? 0.0),
        'start' => $start,
        'end' => $end,
        'days' => $days,
    ]);
}

function handleRooms(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT rooms.*, room_types.name AS room_type_name, room_types.base_occupancy, room_types.max_occupancy FROM rooms JOIN room_types ON rooms.room_type_id = room_types.id WHERE rooms.id = :id');
            $stmt->execute(['id' => $id]);
            $room = $stmt->fetch();
            if (!$room) {
                jsonResponse(['error' => 'Room not found.'], 404);
            }
            jsonResponse($room);
        }

        $query = 'SELECT rooms.*, room_types.name AS room_type_name, room_types.base_occupancy, room_types.max_occupancy FROM rooms JOIN room_types ON rooms.room_type_id = room_types.id';
        $conditions = [];
        $params = [];
        if (isset($_GET['status'])) {
            $conditions[] = 'rooms.status = :status';
            $params['status'] = $_GET['status'];
        }
        if (isset($_GET['room_type_id'])) {
            $conditions[] = 'rooms.room_type_id = :room_type_id';
            $params['room_type_id'] = $_GET['room_type_id'];
        }
        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY rooms.room_number';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['room_number']) || empty($data['room_type_id'])) {
            jsonResponse(['error' => 'room_number and room_type_id are required.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO rooms (room_number, room_type_id, floor, status, notes, created_at, updated_at) VALUES (:room_number, :room_type_id, :floor, :status, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'room_number' => $data['room_number'],
            'room_type_id' => $data['room_type_id'],
            'floor' => $data['floor'] ?? null,
            'status' => $data['status'] ?? 'available',
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['room_number', 'room_type_id', 'floor', 'status', 'notes'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $data[$field];
            }
        }

        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        if (isset($data['status'])) {
            logHousekeepingStatus((int) $id, $data['status'], $data['notes'] ?? null, $data['recorded_by'] ?? null);
        }

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();

        $stmt = $pdo->prepare(sprintf('UPDATE rooms SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleGuests(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT g.*, c.name AS company_name FROM guests g LEFT JOIN companies c ON c.id = g.company_id WHERE g.id = :id');
            $stmt->execute(['id' => $id]);
            $guest = $stmt->fetch();
            if (!$guest) {
                jsonResponse(['error' => 'Guest not found.'], 404);
            }
            jsonResponse($guest);
        }

        $query = 'SELECT g.*, c.name AS company_name FROM guests g LEFT JOIN companies c ON c.id = g.company_id';
        $params = [];
        $limit = null;
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $query .= ' WHERE CONCAT(g.first_name, " ", g.last_name) LIKE :search OR g.email LIKE :search';
            $params['search'] = '%' . $_GET['search'] . '%';
        }
        $query .= ' ORDER BY g.last_name, g.first_name';
        if (isset($_GET['limit'])) {
            $limitValue = (int) $_GET['limit'];
            if ($limitValue > 0) {
                $limit = min($limitValue, 100);
            }
        }
        if ($limit !== null) {
            $query .= ' LIMIT ' . $limit;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        foreach (['first_name', 'last_name'] as $field) {
            if (empty($data[$field])) {
                jsonResponse(['error' => sprintf('%s is required.', $field)], 422);
            }
        }

        $companyId = normalizeCompanyId($pdo, $data['company_id'] ?? null);

        $stmt = $pdo->prepare('INSERT INTO guests (first_name, last_name, email, phone, address, city, country, company_id, notes, created_at, updated_at) VALUES (:first_name, :last_name, :email, :phone, :address, :city, :country, :company_id, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'company_id' => $companyId,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country', 'company_id', 'notes'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'company_id') {
                    $params[$field] = normalizeCompanyId($pdo, $data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
            }
        }
        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }
        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE guests SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleCompanies(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $company = $stmt->fetch();
            if (!$company) {
                jsonResponse(['error' => 'Company not found.'], 404);
            }
            jsonResponse($company);
        }

        $query = 'SELECT * FROM companies';
        $params = [];
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $query .= ' WHERE name LIKE :search OR email LIKE :search';
            $params['search'] = '%' . $_GET['search'] . '%';
        }
        $query .= ' ORDER BY name';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'Name is required.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO companies (name, email, phone, address, city, country, notes, created_at, updated_at) VALUES (:name, :email, :phone, :address, :city, :country, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['name', 'email', 'phone', 'address', 'city', 'country', 'notes'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'name' && empty($data[$field])) {
                    jsonResponse(['error' => 'Name is required.'], 422);
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $data[$field];
            }
        }
        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }
        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE companies SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleArticles(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $article = $stmt->fetch();
            if (!$article) {
                jsonResponse(['error' => 'Article not found.'], 404);
            }
            jsonResponse($article);
        }

        $includeInactive = isset($_GET['include_inactive']) && ($_GET['include_inactive'] === '1' || strtolower((string) $_GET['include_inactive']) === 'true');
        $query = 'SELECT * FROM articles';
        if (!$includeInactive) {
            $query .= ' WHERE is_active = 1';
        }
        $query .= ' ORDER BY name';
        $stmt = $pdo->query($query);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        $normalized = normalizeArticlePayload($data);

        $stmt = $pdo->prepare('INSERT INTO articles (name, description, charge_scheme, unit_price, tax_rate, is_active, created_at, updated_at) VALUES (:name, :description, :charge_scheme, :unit_price, :tax_rate, :is_active, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $normalized['name'],
            'description' => $normalized['description'],
            'charge_scheme' => $normalized['charge_scheme'],
            'unit_price' => $normalized['unit_price'],
            'tax_rate' => $normalized['tax_rate'],
            'is_active' => $normalized['is_active'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $normalized = normalizeArticlePayload($data, false);
        if (!$normalized) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $fields = [];
        $params = ['id' => $id];
        foreach ($normalized as $key => $value) {
            $fields[] = sprintf('%s = :%s', $key, $key);
            $params[$key] = $value;
        }
        $fields[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();

        $sql = sprintf('UPDATE articles SET %s WHERE id = :id', implode(', ', $fields));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['updated' => true]);
    }

    if ($method === 'DELETE' && $id !== null) {
        $stmt = $pdo->prepare('UPDATE articles SET is_active = 0, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'updated_at' => now(),
            'id' => $id,
        ]);
        jsonResponse(['deleted' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleReservations(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT r.*, g.first_name, g.last_name, g.email, g.phone, g.company_id, c.name AS company_name, rp.name AS rate_plan_name, rp.base_price AS rate_plan_base_price, rp.currency AS rate_plan_currency FROM reservations r JOIN guests g ON g.id = r.guest_id LEFT JOIN companies c ON c.id = g.company_id LEFT JOIN rate_plans rp ON rp.id = r.rate_plan_id WHERE r.id = :id');
            $stmt->execute(['id' => $id]);
            $reservation = $stmt->fetch();
            if (!$reservation) {
                jsonResponse(['error' => 'Reservation not found.'], 404);
            }
            $reservation['rooms'] = fetchReservationRooms((int) $id);
            $reservation['room_requests'] = fetchReservationRoomRequests((int) $id, $pdo);
            $reservation['documents'] = fetchReservationDocuments((int) $id);
            $reservation['status_history'] = fetchReservationStatusLogs((int) $id);
            $reservation['articles'] = fetchReservationArticles((int) $id);
            $reservation['invoices'] = fetchInvoicesForReservation((int) $id);
            jsonResponse($reservation);
        }

        $conditions = [];
        $params = [];
        $query = 'SELECT r.*, g.first_name, g.last_name, g.company_id, c.name AS company_name, rp.name AS rate_plan_name, rp.base_price AS rate_plan_base_price, rp.currency AS rate_plan_currency FROM reservations r JOIN guests g ON g.id = r.guest_id LEFT JOIN companies c ON c.id = g.company_id LEFT JOIN rate_plans rp ON rp.id = r.rate_plan_id';
        if (isset($_GET['status'])) {
            $conditions[] = 'r.status = :status';
            $params['status'] = $_GET['status'];
        }
        if (isset($_GET['from']) && validateDate($_GET['from'])) {
            $conditions[] = 'r.check_in_date >= :from_date';
            $params['from_date'] = $_GET['from'];
        }
        if (isset($_GET['to']) && validateDate($_GET['to'])) {
            $conditions[] = 'r.check_out_date <= :to_date';
            $params['to_date'] = $_GET['to'];
        }
        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY r.check_in_date DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reservations = $stmt->fetchAll();
        foreach ($reservations as &$reservation) {
            $reservation['rooms'] = fetchReservationRooms((int) $reservation['id']);
            $reservation['room_requests'] = fetchReservationRoomRequests((int) $reservation['id'], $pdo);
        }
        jsonResponse($reservations);
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        validateReservationPayload($pdo, $data);

        $guestCount = calculateGuestCount($data['adults'] ?? 1, $data['children'] ?? 0);
        $roomRequests = normalizeReservationRoomRequests($pdo, $data['room_requests'] ?? []);
        if (empty($roomRequests) && (empty($data['rooms']) || !is_array($data['rooms']) || count($data['rooms']) === 0)) {
            jsonResponse(['error' => 'Bitte mindestens eine Zimmerkategorie oder ein Zimmer zuweisen.'], 422);
        }
        if (!empty($roomRequests)) {
            ensureRoomRequestCapacity($roomRequests, $guestCount);
        }

        $pdo->beginTransaction();
        try {
            $guestId = $data['guest_id'] ?? null;
            if ($guestId === null) {
                $guestId = createGuest($pdo, $data['guest']);
            } else {
                $guestId = (int) $guestId;
                if ($guestId <= 0) {
                    throw new InvalidArgumentException('guest_id must reference an existing guest.');
                }
            }

            $confirmation = $data['confirmation_number'] ?? generateConfirmationNumber();
            $statusValue = isset($data['status']) ? normalizeReservationStatus((string) $data['status']) : 'tentative';
            if ($statusValue === null) {
                throw new InvalidArgumentException('Unsupported reservation status.');
            }
            $stmt = $pdo->prepare('INSERT INTO reservations (confirmation_number, guest_id, status, check_in_date, check_out_date, adults, children, rate_plan_id, total_amount, currency, booked_via, notes, created_at, updated_at) VALUES (:confirmation_number, :guest_id, :status, :check_in_date, :check_out_date, :adults, :children, :rate_plan_id, :total_amount, :currency, :booked_via, :notes, :created_at, :updated_at)');
            $stmt->execute([
                'confirmation_number' => $confirmation,
                'guest_id' => $guestId,
                'status' => $statusValue,
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'rate_plan_id' => normalizeRatePlanId($pdo, $data['rate_plan_id'] ?? null),
                'total_amount' => $data['total_amount'] ?? null,
                'currency' => $data['currency'] ?? 'EUR',
                'booked_via' => $data['booked_via'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $reservationId = (int) $pdo->lastInsertId();
            $roomIds = extractRoomIdsFromSelection($data['rooms'] ?? []);
            if (!empty($roomIds)) {
                assignRoomsToReservation(
                    $pdo,
                    $reservationId,
                    $data['rooms'] ?? [],
                    $data['check_in_date'],
                    $data['check_out_date'],
                    $guestCount
                );
            }
            $roomCount = count($roomIds);
            if ($roomCount === 0) {
                $roomCount = calculateRoomRequestQuantity($roomRequests);
            }
            $articleSelections = isset($data['articles']) && is_array($data['articles']) ? $data['articles'] : [];
            syncReservationArticles(
                $pdo,
                $reservationId,
                $articleSelections,
                $data['check_in_date'],
                $data['check_out_date'],
                $guestCount,
                $roomCount
            );
            syncReservationRoomRequests($pdo, $reservationId, $roomRequests);
            logReservationStatus($pdo, $reservationId, $statusValue, $data['status_notes'] ?? null, $data['recorded_by'] ?? null);
            updateRoomsForReservationStatus($pdo, $reservationId, $statusValue, $data['status_notes'] ?? null, $data['recorded_by'] ?? null);

            $pdo->commit();
            jsonResponse(['id' => $reservationId, 'confirmation_number' => $confirmation], 201);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $status = ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) ? 422 : 500;
            jsonResponse(['error' => $exception->getMessage()], $status);
        }
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['status', 'check_in_date', 'check_out_date', 'adults', 'children', 'rate_plan_id', 'total_amount', 'currency', 'booked_via', 'notes', 'guest_id'];
        $updates = [];
        $params = ['id' => $id];
        $stmt = $pdo->prepare('SELECT guest_id, adults, children, check_in_date, check_out_date FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $currentReservation = $stmt->fetch();
        if (!$currentReservation) {
            jsonResponse(['error' => 'Reservation not found.'], 404);
        }

        $targetAdults = array_key_exists('adults', $data) ? (int) $data['adults'] : (int) $currentReservation['adults'];
        $targetChildren = array_key_exists('children', $data) ? (int) $data['children'] : (int) $currentReservation['children'];
        $targetCheckIn = array_key_exists('check_in_date', $data) ? $data['check_in_date'] : $currentReservation['check_in_date'];
        $targetCheckOut = array_key_exists('check_out_date', $data) ? $data['check_out_date'] : $currentReservation['check_out_date'];
        $targetGuestId = array_key_exists('guest_id', $data) ? (int) $data['guest_id'] : (int) $currentReservation['guest_id'];

        if ($targetGuestId <= 0) {
            jsonResponse(['error' => 'guest_id must reference an existing guest.'], 422);
        }

        if (!validateDate($targetCheckIn) || !validateDate($targetCheckOut)) {
            jsonResponse(['error' => 'Ungültige Datumsangaben für An- oder Abreise.'], 422);
        }

        $checkInDate = new DateTimeImmutable($targetCheckIn);
        $checkOutDate = new DateTimeImmutable($targetCheckOut);
        if ($checkOutDate <= $checkInDate) {
            jsonResponse(['error' => 'Das Abreisedatum muss nach dem Anreisedatum liegen.'], 422);
        }

        $guestCount = calculateGuestCount($targetAdults, $targetChildren);
        if ($guestCount < 1) {
            jsonResponse(['error' => 'At least one guest is required for a reservation.'], 422);
        }

        $roomSelection = !empty($data['rooms']) ? $data['rooms'] : fetchReservationRooms((int) $id);
        $roomIds = extractRoomIdsFromSelection($roomSelection);
        $roomCount = count($roomIds);
        $existingRequests = fetchReservationRoomRequests((int) $id, $pdo);
        $roomRequestsChanged = array_key_exists('room_requests', $data);
        $roomRequests = $roomRequestsChanged
            ? normalizeReservationRoomRequests($pdo, is_array($data['room_requests']) ? $data['room_requests'] : [])
            : $existingRequests;

        if ($roomCount > 0) {
            ensureRoomCapacity($pdo, $roomIds, $guestCount);
        } elseif (!empty($roomRequests)) {
            ensureRoomRequestCapacity($roomRequests, $guestCount);
        } else {
            jsonResponse(['error' => 'Bitte mindestens eine Zimmerkategorie oder ein Zimmer zuweisen.'], 422);
        }

        if ($roomCount > 0 && empty($data['rooms']) && (array_key_exists('check_in_date', $data) || array_key_exists('check_out_date', $data))) {
            foreach ($roomIds as $roomId) {
                if (!isRoomAvailable($pdo, $roomId, $targetCheckIn, $targetCheckOut, (int) $id)) {
                    jsonResponse(['error' => sprintf('Room %d is not available for the updated stay dates.', $roomId)], 422);
                }
            }
        }

        $roomRequestCount = calculateRoomRequestQuantity($roomRequests);
        if ($roomCount === 0) {
            $roomCount = $roomRequestCount;
        }

        $roomsChanged = !empty($data['rooms']);
        $datesChanged = array_key_exists('check_in_date', $data) || array_key_exists('check_out_date', $data);
        $guestChanged = array_key_exists('adults', $data) || array_key_exists('children', $data);

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if (($field === 'check_in_date' || $field === 'check_out_date') && !validateDate($data[$field])) {
                    jsonResponse(['error' => sprintf('Invalid date for %s', $field)], 422);
                }
                if ($field === 'guest_id') {
                    $guestValue = (int) $data[$field];
                    if ($guestValue <= 0) {
                        jsonResponse(['error' => 'guest_id must reference an existing guest.'], 422);
                    }
                    $params[$field] = $guestValue;
                } elseif ($field === 'status') {
                    $normalizedStatus = normalizeReservationStatus((string) $data[$field]);
                    if ($normalizedStatus === null) {
                        jsonResponse(['error' => 'Unsupported reservation status.'], 422);
                    }
                    $params[$field] = $normalizedStatus;
                } elseif ($field === 'rate_plan_id') {
                    $params[$field] = normalizeRatePlanId($pdo, $data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
            }
        }

        if (!$updates && empty($data['rooms']) && empty($data['guest']) && !$roomRequestsChanged) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $pdo->beginTransaction();
        try {
            if ($updates) {
                $updates[] = 'updated_at = :updated_at';
                $params['updated_at'] = now();
                $stmt = $pdo->prepare(sprintf('UPDATE reservations SET %s WHERE id = :id', implode(', ', $updates)));
                $stmt->execute($params);
                if (isset($params['status'])) {
                    $normalizedStatus = $params['status'];
                    logReservationStatus($pdo, (int) $id, $normalizedStatus, $data['status_notes'] ?? null, $data['recorded_by'] ?? null);
                    updateRoomsForReservationStatus($pdo, (int) $id, $normalizedStatus, $data['status_notes'] ?? null, $data['recorded_by'] ?? null);
                }
            }

            if (isset($data['guest']) && is_array($data['guest'])) {
                $guestFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country', 'company_id', 'notes'];
                $guestUpdates = [];
                $guestParams = ['id' => $targetGuestId];
                foreach ($guestFields as $field) {
                    if (array_key_exists($field, $data['guest'])) {
                        $guestUpdates[] = sprintf('%s = :%s', $field, $field);
                        if ($field === 'company_id') {
                            $guestParams[$field] = normalizeCompanyId($pdo, $data['guest'][$field], false);
                        } else {
                            $guestParams[$field] = $data['guest'][$field];
                        }
                    }
                }
                if ($guestUpdates) {
                    $guestUpdates[] = 'updated_at = :updated_at';
                    $guestParams['updated_at'] = now();
                    $guestSql = sprintf('UPDATE guests SET %s WHERE id = :id', implode(', ', $guestUpdates));
                    $pdo->prepare($guestSql)->execute($guestParams);
                }
            }

            if (!empty($data['rooms'])) {
                $pdo->prepare('DELETE FROM reservation_rooms WHERE reservation_id = :id')->execute(['id' => $id]);
                assignRoomsToReservation(
                    $pdo,
                    (int) $id,
                    $data['rooms'],
                    $targetCheckIn,
                    $targetCheckOut,
                    $guestCount,
                    (int) $id
                );
            }

            if ($roomRequestsChanged) {
                syncReservationRoomRequests($pdo, (int) $id, $roomRequests);
            }

            if (array_key_exists('articles', $data)) {
                $articleSelections = is_array($data['articles']) ? $data['articles'] : [];
                syncReservationArticles(
                    $pdo,
                    (int) $id,
                    $articleSelections,
                    $targetCheckIn,
                    $targetCheckOut,
                    $guestCount,
                    $roomCount
                );
            } elseif ($roomsChanged || $datesChanged || $guestChanged || $roomRequestsChanged) {
                recalculateReservationArticles(
                    $pdo,
                    (int) $id,
                    $targetCheckIn,
                    $targetCheckOut,
                    $guestCount,
                    $roomCount
                );
            }

            $pdo->commit();
            jsonResponse(['updated' => true]);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $status = ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) ? 422 : 500;
            jsonResponse(['error' => $exception->getMessage()], $status);
        }
    }

    if ($method === 'POST' && $id !== null && isset($segments[2])) {
        $action = $segments[2];
        if ($action === 'status') {
            $data = parseJsonBody();
            $targetStatus = $data['status'] ?? null;
            if (!is_string($targetStatus)) {
                jsonResponse(['error' => 'status is required.'], 422);
            }
            handleReservationStatusChange((int) $id, $targetStatus, $data);
        } elseif ($action === 'check-in') {
            $data = parseJsonBody();
            handleReservationStatusChange((int) $id, 'checked_in', $data);
        } elseif ($action === 'check-out') {
            $data = parseJsonBody();
            handleReservationStatusChange((int) $id, 'checked_out', $data);
        } elseif ($action === 'pay') {
            $data = parseJsonBody();
            handleReservationStatusChange((int) $id, 'paid', $data);
        } elseif ($action === 'no-show') {
            $data = parseJsonBody();
            handleReservationStatusChange((int) $id, 'no_show', $data);
        } elseif ($action === 'invoice') {
            $data = parseJsonBody();
            try {
                $invoice = createReservationInvoice((int) $id, $data);
                jsonResponse($invoice, 201);
            } catch (Throwable $exception) {
                $status = $exception instanceof InvalidArgumentException ? 422 : 500;
                jsonResponse(['error' => $exception->getMessage()], $status);
            }
        } elseif ($action === 'invoice-pay') {
            $data = parseJsonBody();
            try {
                $result = payReservationInvoice((int) $id, $data);
                jsonResponse($result);
            } catch (Throwable $exception) {
                $status = $exception instanceof InvalidArgumentException ? 422 : 500;
                jsonResponse(['error' => $exception->getMessage()], $status);
            }
        } elseif ($action === 'documents') {
            $data = parseJsonBody();
            addReservationDocument((int) $id, $data);
        } else {
            jsonResponse(['error' => 'Unknown reservation action.'], 400);
        }
        return;
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleReservationStatusChange(int $reservationId, string $status, ?array $payload = null): void
{
    $normalizedStatus = normalizeReservationStatus($status);
    if ($normalizedStatus === null) {
        jsonResponse(['error' => 'Unsupported reservation status.'], 422);
    }

    $pdo = db();
    $exists = $pdo->prepare('SELECT id FROM reservations WHERE id = :id');
    $exists->execute(['id' => $reservationId]);
    if ($exists->fetchColumn() === false) {
        jsonResponse(['error' => 'Reservation not found.'], 404);
    }

    $data = $payload ?? parseJsonBody();
    $notes = $data['notes'] ?? null;
    $recordedBy = $data['recorded_by'] ?? null;

    $pdo->beginTransaction();
    try {
        applyReservationStatusChange($pdo, $reservationId, $normalizedStatus, $notes, $recordedBy);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Unable to update reservation status.', 'details' => $exception->getMessage()], 500);
    }

    jsonResponse(['status' => $normalizedStatus]);
}

function applyReservationStatusChange(PDO $pdo, int $reservationId, string $status, ?string $notes, $recordedBy): void
{
    $stmt = $pdo->prepare('UPDATE reservations SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'updated_at' => now(),
        'id' => $reservationId,
    ]);

    logReservationStatus($pdo, $reservationId, $status, $notes, $recordedBy);
    updateRoomsForReservationStatus($pdo, $reservationId, $status, $notes, $recordedBy);
}

function validateReservationPayload(PDO $pdo, array $data): void
{
    foreach (['check_in_date', 'check_out_date'] as $field) {
        if (empty($data[$field]) || !validateDate($data[$field])) {
            jsonResponse(['error' => sprintf('Invalid or missing %s', $field)], 422);
        }
    }

    $checkIn = new DateTimeImmutable($data['check_in_date']);
    $checkOut = new DateTimeImmutable($data['check_out_date']);
    if ($checkOut <= $checkIn) {
        jsonResponse(['error' => 'check_out_date must be after check_in_date.'], 422);
    }

    if (!isset($data['guest_id']) && empty($data['guest'])) {
        jsonResponse(['error' => 'Guest information is required.'], 422);
    }

    if (isset($data['guest'])) {
        foreach (['first_name', 'last_name'] as $field) {
            if (empty($data['guest'][$field])) {
                jsonResponse(['error' => sprintf('Guest field %s is required.', $field)], 422);
            }
        }
    }

    if (isset($data['rooms']) && !is_array($data['rooms'])) {
        jsonResponse(['error' => 'rooms must be provided as an array when supplied.'], 422);
    }

    if (isset($data['room_requests']) && !is_array($data['room_requests'])) {
        jsonResponse(['error' => 'room_requests must be provided as an array when supplied.'], 422);
    }

    $guestCount = calculateGuestCount($data['adults'] ?? 1, $data['children'] ?? 0);
    if ($guestCount < 1) {
        jsonResponse(['error' => 'At least one guest is required for a reservation.'], 422);
    }

    if (isset($data['status']) && normalizeReservationStatus((string) $data['status']) === null) {
        jsonResponse(['error' => 'Unsupported reservation status.'], 422);
    }

    if (array_key_exists('rate_plan_id', $data)) {
        normalizeRatePlanId($pdo, $data['rate_plan_id']);
    }

    if (isset($data['articles'])) {
        if (!is_array($data['articles'])) {
            jsonResponse(['error' => 'articles must be an array.'], 422);
        }
        foreach ($data['articles'] as $article) {
            if (!is_array($article) || empty($article['article_id'])) {
                jsonResponse(['error' => 'Each article selection requires an article_id.'], 422);
            }
            if (isset($article['multiplier']) && (float) $article['multiplier'] < 0) {
                jsonResponse(['error' => 'Article multiplier cannot be negative.'], 422);
            }
            if (isset($article['quantity']) && (float) $article['quantity'] < 0) {
                jsonResponse(['error' => 'Article quantity cannot be negative.'], 422);
            }
        }
    }
}

function calculateGuestCount($adults, $children): int
{
    $adultCount = max(0, (int) $adults);
    $childCount = max(0, (int) $children);

    return $adultCount + $childCount;
}

function normalizeArticlePayload(array $data, bool $requireAll = true): array
{
    if (!is_array($data)) {
        jsonResponse(['error' => 'Invalid payload.'], 422);
    }

    $normalized = [];

    if ($requireAll || array_key_exists('name', $data)) {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' && $requireAll) {
            jsonResponse(['error' => 'Name is required.'], 422);
        }
        if ($name !== '') {
            $normalized['name'] = $name;
        }
    }

    if ($requireAll || array_key_exists('description', $data)) {
        $description = isset($data['description']) ? trim((string) $data['description']) : null;
        $normalized['description'] = $description !== '' ? $description : null;
    }

    if ($requireAll || array_key_exists('charge_scheme', $data)) {
        $scheme = $data['charge_scheme'] ?? null;
        try {
            $normalizedScheme = normalizeChargeScheme($scheme, !$requireAll);
        } catch (InvalidArgumentException $exception) {
            jsonResponse(['error' => $exception->getMessage()], 422);
        }
        if ($normalizedScheme !== null) {
            $normalized['charge_scheme'] = $normalizedScheme;
        } elseif ($requireAll) {
            $normalized['charge_scheme'] = 'per_person_per_day';
        }
    }

    if ($requireAll || array_key_exists('unit_price', $data)) {
        $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : 0.0;
        if ($unitPrice < 0) {
            jsonResponse(['error' => 'unit_price must be zero or positive.'], 422);
        }
        $normalized['unit_price'] = round($unitPrice, 2);
    }

    if ($requireAll || array_key_exists('tax_rate', $data)) {
        $taxRate = isset($data['tax_rate']) ? (float) $data['tax_rate'] : GERMAN_VAT_STANDARD;
        if ($taxRate < 0) {
            jsonResponse(['error' => 'tax_rate must be zero or positive.'], 422);
        }
        $normalized['tax_rate'] = round($taxRate, 2);
    }

    if ($requireAll || array_key_exists('is_active', $data)) {
        $isActive = isset($data['is_active']) ? (int) filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : 1;
        $normalized['is_active'] = $isActive ? 1 : 0;
    }

    if ($requireAll) {
        return $normalized;
    }

    return array_filter(
        $normalized,
        static fn ($value) => $value !== null && $value !== '' && $value !== []
    );
}

function normalizeChargeScheme($value, bool $allowNull = false): ?string
{
    if ($value === null || $value === '') {
        return $allowNull ? null : 'per_person_per_day';
    }

    $scheme = strtolower((string) $value);
    if (!in_array($scheme, ARTICLE_CHARGE_SCHEMES, true)) {
        throw new InvalidArgumentException('Unsupported article charge scheme.');
    }

    return $scheme;
}

function normalizeRoomAssignments(array $rooms): array
{
    $assignments = [];
    foreach ($rooms as $room) {
        if (is_array($room)) {
            $roomId = (int) ($room['room_id'] ?? 0);
            $nightlyRate = null;
            if (array_key_exists('nightly_rate', $room) && $room['nightly_rate'] !== null && $room['nightly_rate'] !== '') {
                if (!is_numeric((string) $room['nightly_rate'])) {
                    throw new InvalidArgumentException('nightly_rate must be numeric when provided.');
                }
                $nightlyRate = round((float) $room['nightly_rate'], 2);
                if ($nightlyRate < 0) {
                    throw new InvalidArgumentException('nightly_rate must be zero or positive.');
                }
            }
            $currency = null;
            if (array_key_exists('currency', $room) && $room['currency'] !== null) {
                $currencyValue = strtoupper(substr(trim((string) $room['currency']), 0, 3));
                $currency = $currencyValue !== '' ? $currencyValue : null;
            }
        } else {
            $roomId = (int) $room;
            $nightlyRate = null;
            $currency = null;
        }

        if ($roomId === 0) {
            throw new InvalidArgumentException('room_id is required for each room assignment.');
        }

        $assignments[] = [
            'room_id' => $roomId,
            'nightly_rate' => $nightlyRate,
            'currency' => $currency,
        ];
    }

    return $assignments;
}

function extractRoomIdsFromSelection(array $rooms): array
{
    return array_column(normalizeRoomAssignments($rooms), 'room_id');
}

function ensureRoomCapacity(PDO $pdo, array $roomIds, int $guestCount): void
{
    $uniqueRoomIds = array_values(array_unique(array_map('intval', $roomIds)));
    if (empty($uniqueRoomIds)) {
        throw new InvalidArgumentException('At least one room must be selected.');
    }
    if ($guestCount < 1) {
        throw new InvalidArgumentException('At least one guest is required for a reservation.');
    }

    $placeholders = implode(', ', array_fill(0, count($uniqueRoomIds), '?'));
    $sql = <<<SQL
        SELECT rooms.id, rooms.room_number, room_types.name AS room_type_name, room_types.max_occupancy
        FROM rooms
        JOIN room_types ON room_types.id = rooms.room_type_id
        WHERE rooms.id IN ($placeholders)
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($uniqueRoomIds);
    $records = $stmt->fetchAll();

    if (count($records) !== count($uniqueRoomIds)) {
        throw new RuntimeException('One or more selected rooms could not be found.');
    }

    $totalCapacity = 0;
    foreach ($records as $record) {
        $capacity = (int) ($record['max_occupancy'] ?? 0);
        if ($capacity <= 0) {
            $roomLabel = $record['room_number'] ?? ('#' . $record['id']);
            $typeLabel = $record['room_type_name'] ?? '';
            throw new RuntimeException(sprintf('Room %s%s has no defined capacity.', $roomLabel, $typeLabel ? ' (' . $typeLabel . ')' : ''));
        }
        $totalCapacity += $capacity;
    }

    if ($guestCount > $totalCapacity) {
        $labels = array_map(
            static function ($record): string {
                $roomLabel = $record['room_number'] ?? ('#' . $record['id']);
                return $record['room_type_name']
                    ? sprintf('%s (%s)', $roomLabel, $record['room_type_name'])
                    : $roomLabel;
            },
            $records
        );
        throw new RuntimeException(sprintf(
            'Selected rooms (%s) can accommodate up to %d guests, but %d were provided.',
            implode(', ', $labels),
            $totalCapacity,
            $guestCount
        ));
    }
}

function normalizeReservationRoomRequests(PDO $pdo, array $requests): array
{
    if (empty($requests)) {
        return [];
    }

    $normalized = [];
    $typeIds = [];
    foreach ($requests as $request) {
        if (!is_array($request)) {
            jsonResponse(['error' => 'room_requests entries must be objects with room_type_id and quantity.'], 422);
        }
        $roomTypeId = isset($request['room_type_id']) ? (int) $request['room_type_id'] : 0;
        if ($roomTypeId <= 0) {
            jsonResponse(['error' => 'room_type_id is required for each room request.'], 422);
        }
        $quantity = isset($request['quantity']) ? (int) $request['quantity'] : 1;
        if ($quantity <= 0) {
            jsonResponse(['error' => 'quantity must be at least 1 for each room request.'], 422);
        }
        $normalized[] = [
            'room_type_id' => $roomTypeId,
            'quantity' => $quantity,
        ];
        $typeIds[$roomTypeId] = true;
    }

    $placeholders = implode(', ', array_fill(0, count($typeIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name, base_occupancy, max_occupancy FROM room_types WHERE id IN ($placeholders)");
    $stmt->execute(array_keys($typeIds));
    $roomTypes = [];
    while ($row = $stmt->fetch()) {
        $roomTypes[(int) $row['id']] = $row;
    }

    if (count($roomTypes) !== count($typeIds)) {
        jsonResponse(['error' => 'Eine angegebene Zimmerkategorie wurde nicht gefunden.'], 422);
    }

    $aggregated = [];
    foreach ($normalized as $entry) {
        $type = $roomTypes[$entry['room_type_id']];
        $capacity = (int) ($type['max_occupancy'] ?? 0);
        if ($capacity <= 0) {
            $capacity = (int) ($type['base_occupancy'] ?? 0);
        }
        if ($capacity <= 0) {
            jsonResponse(['error' => sprintf('Für die Kategorie %s ist keine Kapazität hinterlegt.', $type['name'] ?? ('#' . $entry['room_type_id']))], 422);
        }

        if (!isset($aggregated[$entry['room_type_id']])) {
            $aggregated[$entry['room_type_id']] = [
                'room_type_id' => $entry['room_type_id'],
                'quantity' => $entry['quantity'],
                'room_type_name' => $type['name'] ?? null,
                'capacity_per_unit' => $capacity,
            ];
        } else {
            $aggregated[$entry['room_type_id']]['quantity'] += $entry['quantity'];
        }
    }

    return array_values($aggregated);
}

function calculateRoomRequestCapacity(array $requests): int
{
    $total = 0;
    foreach ($requests as $request) {
        $capacity = (int) ($request['capacity_per_unit'] ?? 0);
        $quantity = max(0, (int) ($request['quantity'] ?? 0));
        if ($capacity > 0 && $quantity > 0) {
            $total += $capacity * $quantity;
        }
    }

    return $total;
}

function calculateRoomRequestQuantity(array $requests): int
{
    $total = 0;
    foreach ($requests as $request) {
        $total += max(0, (int) ($request['quantity'] ?? 0));
    }

    return $total;
}

function ensureRoomRequestCapacity(array $requests, int $guestCount): void
{
    if ($guestCount < 1) {
        jsonResponse(['error' => 'At least one guest is required for a reservation.'], 422);
    }

    $capacity = calculateRoomRequestCapacity($requests);
    if ($capacity <= 0) {
        jsonResponse(['error' => 'Für die ausgewählten Kategorien ist keine Kapazität hinterlegt.'], 422);
    }

    if ($guestCount > $capacity) {
        $labels = array_map(
            static function (array $request): string {
                $name = $request['room_type_name'] ?? ('Kategorie #' . $request['room_type_id']);
                $quantity = max(1, (int) ($request['quantity'] ?? 1));
                return sprintf('%s × %d', $name, $quantity);
            },
            $requests
        );

        jsonResponse([
            'error' => sprintf(
                'Die ausgewählten Kategorien (%s) bieten insgesamt Platz für %d Gäste, angefragt wurden jedoch %d.',
                implode(', ', $labels),
                $capacity,
                $guestCount
            ),
        ], 422);
    }
}

function syncReservationRoomRequests(PDO $pdo, int $reservationId, array $requests): void
{
    $pdo->prepare('DELETE FROM reservation_room_requests WHERE reservation_id = :id')->execute(['id' => $reservationId]);

    if (empty($requests)) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO reservation_room_requests (reservation_id, room_type_id, quantity, created_at, updated_at) VALUES (:reservation_id, :room_type_id, :quantity, :created_at, :updated_at)');
    foreach ($requests as $request) {
        $stmt->execute([
            'reservation_id' => $reservationId,
            'room_type_id' => $request['room_type_id'],
            'quantity' => max(1, (int) $request['quantity']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function fetchReservationRoomRequests(int $reservationId, ?PDO $pdo = null): array
{
    $pdo = $pdo ?? db();
    $sql = <<<SQL
        SELECT rrr.id, rrr.room_type_id, rrr.quantity, rt.name AS room_type_name, rt.base_occupancy, rt.max_occupancy
        FROM reservation_room_requests rrr
        JOIN room_types rt ON rt.id = rrr.room_type_id
        WHERE rrr.reservation_id = :reservation_id
        ORDER BY rt.name
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['reservation_id' => $reservationId]);
    $records = $stmt->fetchAll();

    return array_map(
        static function (array $record): array {
            $capacity = (int) ($record['max_occupancy'] ?? 0);
            if ($capacity <= 0) {
                $capacity = (int) ($record['base_occupancy'] ?? 0);
            }
            return [
                'id' => $record['id'],
                'room_type_id' => (int) $record['room_type_id'],
                'quantity' => (int) $record['quantity'],
                'room_type_name' => $record['room_type_name'],
                'capacity_per_unit' => $capacity > 0 ? $capacity : null,
            ];
        },
        $records
    );
}

function createGuest(PDO $pdo, array $guest): int
{
    $companyId = normalizeCompanyId($pdo, $guest['company_id'] ?? null, false);

    $stmt = $pdo->prepare('INSERT INTO guests (first_name, last_name, email, phone, address, city, country, company_id, notes, created_at, updated_at) VALUES (:first_name, :last_name, :email, :phone, :address, :city, :country, :company_id, :notes, :created_at, :updated_at)');
    $stmt->execute([
        'first_name' => $guest['first_name'],
        'last_name' => $guest['last_name'],
        'email' => $guest['email'] ?? null,
        'phone' => $guest['phone'] ?? null,
        'address' => $guest['address'] ?? null,
        'city' => $guest['city'] ?? null,
        'country' => $guest['country'] ?? null,
        'company_id' => $companyId,
        'notes' => $guest['notes'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) $pdo->lastInsertId();
}

function normalizeCompanyId(PDO $pdo, mixed $value, bool $allowResponse = true): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $value = $trimmed;
    }

    $companyId = (int) $value;
    if ($companyId <= 0) {
        if ($allowResponse) {
            jsonResponse(['error' => 'company_id must reference an existing company.'], 422);
        }
        throw new InvalidArgumentException('company_id must reference an existing company.');
    }

    ensureCompanyExists($pdo, $companyId, $allowResponse);

    return $companyId;
}

function normalizeRatePlanId(PDO $pdo, mixed $value, bool $allowResponse = true): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $value = $trimmed;
    }

    $ratePlanId = (int) $value;
    if ($ratePlanId <= 0) {
        if ($allowResponse) {
            jsonResponse(['error' => 'rate_plan_id must reference an existing rate plan.'], 422);
        }
        throw new InvalidArgumentException('rate_plan_id must reference an existing rate plan.');
    }

    ensureRatePlanExists($pdo, $ratePlanId, $allowResponse);

    return $ratePlanId;
}

function ensureCompanyExists(PDO $pdo, int $companyId, bool $allowResponse = true): void
{
    $stmt = $pdo->prepare('SELECT id FROM companies WHERE id = :id');
    $stmt->execute(['id' => $companyId]);
    if ($stmt->fetchColumn() === false) {
        if ($allowResponse) {
            jsonResponse(['error' => 'company_id must reference an existing company.'], 422);
        }
        throw new InvalidArgumentException('company_id must reference an existing company.');
    }
}

function ensureRatePlanExists(PDO $pdo, int $ratePlanId, bool $allowResponse = true): void
{
    $stmt = $pdo->prepare('SELECT id FROM rate_plans WHERE id = :id');
    $stmt->execute(['id' => $ratePlanId]);
    if ($stmt->fetchColumn() === false) {
        if ($allowResponse) {
            jsonResponse(['error' => 'rate_plan_id must reference an existing rate plan.'], 422);
        }
        throw new InvalidArgumentException('rate_plan_id must reference an existing rate plan.');
    }
}

function assignRoomsToReservation(PDO $pdo, int $reservationId, array $rooms, string $checkIn, string $checkOut, int $guestCount, ?int $ignoreReservationId = null): void
{
    $assignments = normalizeRoomAssignments($rooms);
    $roomIds = array_column($assignments, 'room_id');

    ensureRoomCapacity($pdo, $roomIds, $guestCount);

    $reservationRateContext = fetchReservationRateContext($pdo, $reservationId);
    $roomRateDefaults = fetchRoomRateMetadata($pdo, $roomIds);

    foreach ($assignments as $assignment) {
        $roomId = $assignment['room_id'];
        if (!isRoomAvailable($pdo, $roomId, $checkIn, $checkOut, $ignoreReservationId ?? $reservationId)) {
            throw new RuntimeException(sprintf('Room %d is not available for the selected dates.', $roomId));
        }

        $nightlyRate = $assignment['nightly_rate'];
        if ($nightlyRate === null && isset($reservationRateContext['rate_plan_base_price'])) {
            $nightlyRate = $reservationRateContext['rate_plan_base_price'];
        }
        if ($nightlyRate === null && isset($roomRateDefaults[$roomId]['base_rate'])) {
            $nightlyRate = $roomRateDefaults[$roomId]['base_rate'];
        }
        $nightlyRate = $nightlyRate !== null ? round((float) $nightlyRate, 2) : null;

        $currency = $assignment['currency']
            ?? ($reservationRateContext['rate_plan_currency'] ?? null)
            ?? ($roomRateDefaults[$roomId]['currency'] ?? null)
            ?? ($reservationRateContext['currency'] ?? 'EUR');
        $currency = $currency !== null ? strtoupper(substr((string) $currency, 0, 3)) : null;

        $stmt = $pdo->prepare('INSERT INTO reservation_rooms (reservation_id, room_id, nightly_rate, currency) VALUES (:reservation_id, :room_id, :nightly_rate, :currency)');
        $stmt->execute([
            'reservation_id' => $reservationId,
            'room_id' => $roomId,
            'nightly_rate' => $nightlyRate,
            'currency' => $currency,
        ]);
    }
}

function fetchReservationRateContext(PDO $pdo, int $reservationId): array
{
    $stmt = $pdo->prepare('SELECT r.currency, r.rate_plan_id, rp.base_price AS rate_plan_base_price, rp.currency AS rate_plan_currency FROM reservations r LEFT JOIN rate_plans rp ON rp.id = r.rate_plan_id WHERE r.id = :id');
    $stmt->execute(['id' => $reservationId]);
    $record = $stmt->fetch() ?: [];

    $reservationCurrency = isset($record['currency']) ? trim((string) $record['currency']) : '';
    $ratePlanCurrency = isset($record['rate_plan_currency']) ? trim((string) $record['rate_plan_currency']) : '';

    return [
        'currency' => $reservationCurrency !== '' ? strtoupper(substr($reservationCurrency, 0, 3)) : 'EUR',
        'rate_plan_id' => isset($record['rate_plan_id']) ? (int) $record['rate_plan_id'] : null,
        'rate_plan_base_price' => isset($record['rate_plan_base_price']) ? (float) $record['rate_plan_base_price'] : null,
        'rate_plan_currency' => $ratePlanCurrency !== '' ? strtoupper(substr($ratePlanCurrency, 0, 3)) : null,
    ];
}

function fetchRoomRateMetadata(PDO $pdo, array $roomIds): array
{
    $uniqueIds = array_values(array_unique(array_map('intval', $roomIds)));
    if (empty($uniqueIds)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($uniqueIds), '?'));
    $sql = <<<SQL
        SELECT rooms.id, rt.base_rate, rt.currency
        FROM rooms
        LEFT JOIN room_types rt ON rt.id = rooms.room_type_id
        WHERE rooms.id IN ($placeholders)
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($uniqueIds);
    $records = $stmt->fetchAll();

    $metadata = [];
    foreach ($records as $record) {
        $roomId = (int) $record['id'];
        $metadata[$roomId] = [
            'base_rate' => isset($record['base_rate']) ? (float) $record['base_rate'] : null,
            'currency' => isset($record['currency']) && trim((string) $record['currency']) !== ''
                ? strtoupper(substr(trim((string) $record['currency']), 0, 3))
                : null,
        ];
    }

    return $metadata;
}

function isRoomAvailable(PDO $pdo, int $roomId, string $checkIn, string $checkOut, ?int $ignoreReservationId = null): bool
{
    $query = 'SELECT COUNT(*) FROM reservation_rooms rr JOIN reservations r ON rr.reservation_id = r.id WHERE rr.room_id = :room_id AND r.status NOT IN (\'cancelled\', \'no_show\') AND NOT (r.check_out_date <= :check_in OR r.check_in_date >= :check_out)';
    $params = [
        'room_id' => $roomId,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ];
    if ($ignoreReservationId !== null) {
        $query .= ' AND r.id <> :reservation_id';
        $params['reservation_id'] = $ignoreReservationId;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() === 0;
}

function fetchReservationRooms(int $reservationId): array
{
    $pdo = db();
    $sql = 'SELECT rr.*, rooms.room_number, rooms.room_type_id, rt.name AS room_type_name, rt.base_occupancy AS room_type_base_occupancy, rt.max_occupancy AS room_type_max_occupancy'
        . ' FROM reservation_rooms rr'
        . ' JOIN rooms ON rooms.id = rr.room_id'
        . ' LEFT JOIN room_types rt ON rt.id = rooms.room_type_id'
        . ' WHERE rr.reservation_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $reservationId]);
    return $stmt->fetchAll();
}

function fetchReservationDocuments(int $reservationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM reservation_documents WHERE reservation_id = :id ORDER BY uploaded_at DESC');
    $stmt->execute(['id' => $reservationId]);
    return $stmt->fetchAll();
}

function fetchReservationArticles(int $reservationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ra.*, a.name AS article_name FROM reservation_articles ra LEFT JOIN articles a ON a.id = ra.article_id WHERE ra.reservation_id = :id ORDER BY ra.id');
    $stmt->execute(['id' => $reservationId]);
    return $stmt->fetchAll();
}

function fetchReservationStatusLogs(int $reservationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM reservation_status_logs WHERE reservation_id = :id ORDER BY recorded_at DESC');
    $stmt->execute(['id' => $reservationId]);
    return $stmt->fetchAll();
}

function logReservationStatus(PDO $pdo, int $reservationId, string $status, ?string $notes, $recordedBy): void
{
    $stmt = $pdo->prepare('INSERT INTO reservation_status_logs (reservation_id, status, notes, recorded_by, recorded_at) VALUES (:reservation_id, :status, :notes, :recorded_by, :recorded_at)');
    $stmt->execute([
        'reservation_id' => $reservationId,
        'status' => $status,
        'notes' => $notes,
        'recorded_by' => $recordedBy,
        'recorded_at' => now(),
    ]);
}

function addReservationDocument(int $reservationId, array $data): void
{
    if (empty($data['document_type'])) {
        jsonResponse(['error' => 'document_type is required.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO reservation_documents (reservation_id, document_type, file_name, file_path, metadata, uploaded_by, uploaded_at) VALUES (:reservation_id, :document_type, :file_name, :file_path, :metadata, :uploaded_by, :uploaded_at)');
    $stmt->execute([
        'reservation_id' => $reservationId,
        'document_type' => $data['document_type'],
        'file_name' => $data['file_name'] ?? null,
        'file_path' => $data['file_path'] ?? null,
        'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        'uploaded_by' => $data['uploaded_by'] ?? null,
        'uploaded_at' => now(),
    ]);

    jsonResponse(['created' => true], 201);
}

function syncReservationArticles(PDO $pdo, int $reservationId, array $articles, string $checkIn, string $checkOut, int $guestCount, int $roomCount): void
{
    $pdo->prepare('DELETE FROM reservation_articles WHERE reservation_id = :id')->execute(['id' => $reservationId]);

    if (empty($articles)) {
        return;
    }

    $articleIds = [];
    foreach ($articles as $article) {
        $articleId = isset($article['article_id']) ? (int) $article['article_id'] : 0;
        if ($articleId <= 0) {
            throw new InvalidArgumentException('article_id must reference an existing article.');
        }
        $articleIds[] = $articleId;
    }

    if (empty($articleIds)) {
        return;
    }

    $uniqueIds = array_values(array_unique($articleIds));
    $placeholders = implode(', ', array_fill(0, count($uniqueIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id IN ($placeholders)");
    $stmt->execute($uniqueIds);
    $definitions = [];
    foreach ($stmt->fetchAll() as $definition) {
        $definitions[(int) $definition['id']] = $definition;
    }

    $insert = $pdo->prepare('INSERT INTO reservation_articles (reservation_id, article_id, description, charge_scheme, multiplier, quantity, unit_price, tax_rate, total_amount, created_at, updated_at) VALUES (:reservation_id, :article_id, :description, :charge_scheme, :multiplier, :quantity, :unit_price, :tax_rate, :total_amount, :created_at, :updated_at)');

    foreach ($articles as $article) {
        $articleId = (int) ($article['article_id'] ?? 0);
        if ($articleId <= 0 || !isset($definitions[$articleId])) {
            throw new InvalidArgumentException('article_id must reference an existing article.');
        }
        $definition = $definitions[$articleId];
        $scheme = normalizeChargeScheme($article['charge_scheme'] ?? $definition['charge_scheme']);
        $rawMultiplier = $article['multiplier'] ?? ($article['quantity'] ?? 1);
        $multiplier = max(0.0, (float) $rawMultiplier);
        if ($multiplier <= 0.0) {
            continue;
        }
        $unitPrice = array_key_exists('unit_price', $article) ? (float) $article['unit_price'] : (float) ($definition['unit_price'] ?? 0);
        if ($unitPrice < 0) {
            $unitPrice = 0;
        }
        $taxRate = array_key_exists('tax_rate', $article) ? (float) $article['tax_rate'] : (float) ($definition['tax_rate'] ?? GERMAN_VAT_STANDARD);
        if ($taxRate < 0) {
            $taxRate = 0;
        }

        $quantity = calculateReservationArticleQuantity($scheme, $checkIn, $checkOut, $guestCount, $roomCount, $multiplier);
        if ($quantity <= 0) {
            continue;
        }

        $insert->execute([
            'reservation_id' => $reservationId,
            'article_id' => $articleId,
            'description' => $definition['name'] ?? ('Artikel ' . $articleId),
            'charge_scheme' => $scheme,
            'multiplier' => round($multiplier, 2),
            'quantity' => round($quantity, 2),
            'unit_price' => round($unitPrice, 2),
            'tax_rate' => round($taxRate, 2),
            'total_amount' => round($quantity * $unitPrice, 2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function recalculateReservationArticles(PDO $pdo, int $reservationId, string $checkIn, string $checkOut, int $guestCount, int $roomCount): void
{
    $stmt = $pdo->prepare('SELECT id, charge_scheme, multiplier, unit_price FROM reservation_articles WHERE reservation_id = :id');
    $stmt->execute(['id' => $reservationId]);
    $records = $stmt->fetchAll();
    if (!$records) {
        return;
    }

    $update = $pdo->prepare('UPDATE reservation_articles SET quantity = :quantity, total_amount = :total_amount, updated_at = :updated_at WHERE id = :id');
    foreach ($records as $record) {
        $scheme = normalizeChargeScheme($record['charge_scheme'] ?? null);
        $multiplier = isset($record['multiplier']) ? (float) $record['multiplier'] : 1.0;
        if ($multiplier <= 0) {
            $quantity = 0.0;
            $total = 0.0;
        } else {
            $quantity = calculateReservationArticleQuantity($scheme, $checkIn, $checkOut, $guestCount, $roomCount, $multiplier);
            $unitPrice = isset($record['unit_price']) ? (float) $record['unit_price'] : 0.0;
            $total = round($quantity * $unitPrice, 2);
        }

        $update->execute([
            'quantity' => round($quantity, 2),
            'total_amount' => $total,
            'updated_at' => now(),
            'id' => $record['id'],
        ]);
    }
}

function calculateStayNights(string $checkIn, string $checkOut): int
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $checkIn);
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $checkOut);
    if (!$start || !$end) {
        return 0;
    }

    $diff = $start->diff($end);
    return max(0, (int) $diff->days);
}

function calculateReservationArticleQuantity(string $scheme, string $checkIn, string $checkOut, int $guestCount, int $roomCount, float $multiplier): float
{
    $multiplier = max(0.0, $multiplier);
    if ($multiplier === 0.0) {
        return 0.0;
    }

    $nights = calculateStayNights($checkIn, $checkOut);
    $guestCount = max(0, $guestCount);
    $roomCount = max(0, $roomCount);

    $base = 0.0;
    switch ($scheme) {
        case 'per_room_per_day':
            $base = $roomCount * $nights;
            break;
        case 'per_person_per_day':
            $base = $guestCount * $nights;
            break;
        case 'per_day':
            $base = $nights;
            break;
        case 'per_person':
            $base = $guestCount;
            break;
        case 'per_stay':
        default:
            $base = 1;
            break;
    }

    return max(0.0, $base * $multiplier);
}

function handleHousekeeping(string $method, array $segments): void
{
    $sub = $segments[1] ?? null;
    if ($sub !== 'tasks') {
        jsonResponse(['error' => 'Unknown housekeeping resource.'], 404);
    }

    $pdo = db();
    $id = $segments[2] ?? null;

    if ($method === 'GET') {
        $query = 'SELECT t.*, rooms.room_number FROM tasks t LEFT JOIN rooms ON rooms.id = t.room_id';
        $conditions = [];
        $params = [];
        if (isset($_GET['status'])) {
            $conditions[] = 't.status = :status';
            $params['status'] = $_GET['status'];
        }
        if ($conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY t.due_date IS NULL, t.due_date';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['title'])) {
            jsonResponse(['error' => 'title is required.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO tasks (room_id, assigned_to, title, description, status, due_date, created_at, updated_at) VALUES (:room_id, :assigned_to, :title, :description, :status, :due_date, :created_at, :updated_at)');
        $stmt->execute([
            'room_id' => $data['room_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'open',
            'due_date' => isset($data['due_date']) && validateDateTime($data['due_date']) ? $data['due_date'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['room_id', 'assigned_to', 'title', 'description', 'status', 'due_date', 'completed_at'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if (in_array($field, ['due_date', 'completed_at'], true) && $data[$field] !== null && !validateDateTime($data[$field])) {
                    jsonResponse(['error' => sprintf('Invalid datetime for %s', $field)], 422);
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $data[$field];
            }
        }
        if (!$updates) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }
        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE tasks SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);

        if (isset($data['room_status']) && isset($data['room_id'])) {
            logHousekeepingStatus((int) $data['room_id'], $data['room_status'], $data['description'] ?? null, $data['assigned_to'] ?? null);
        }

        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function logHousekeepingStatus(int $roomId, string $status, ?string $notes, $userId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO housekeeping_logs (room_id, status, notes, recorded_by, recorded_at) VALUES (:room_id, :status, :notes, :recorded_by, :recorded_at)');
    $stmt->execute([
        'room_id' => $roomId,
        'status' => $status,
        'notes' => $notes,
        'recorded_by' => $userId,
        'recorded_at' => now(),
    ]);
}

function getReservationRoomIds(PDO $pdo, int $reservationId): array
{
    $stmt = $pdo->prepare('SELECT room_id FROM reservation_rooms WHERE reservation_id = :reservation_id');
    $stmt->execute(['reservation_id' => $reservationId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'room_id'));
}

function normalizeReservationStatus(string $status): ?string
{
    $normalized = strtolower(trim($status));
    return in_array($normalized, RESERVATION_STATUSES, true) ? $normalized : null;
}

function updateRoomsForReservationStatus(PDO $pdo, int $reservationId, string $status, ?string $notes, $recordedBy): void
{
    $roomIds = getReservationRoomIds($pdo, $reservationId);
    if (!$roomIds) {
        return;
    }

    $roomStatus = null;
    $housekeepingStatus = null;

    if (in_array($status, ['checked_in', 'paid'], true)) {
        $roomStatus = 'occupied';
        $housekeepingStatus = 'occupied';
    } elseif ($status === 'checked_out') {
        $roomStatus = 'in_cleaning';
        $housekeepingStatus = 'in_cleaning';
    } elseif (in_array($status, ['cancelled', 'no_show'], true)) {
        $roomStatus = 'available';
        $housekeepingStatus = 'available';
    }

    if ($roomStatus !== null) {
        $updateStmt = $pdo->prepare('UPDATE rooms SET status = :status, updated_at = :updated_at WHERE id = :id');
        foreach ($roomIds as $roomId) {
            $updateStmt->execute([
                'status' => $roomStatus,
                'updated_at' => now(),
                'id' => $roomId,
            ]);
        }
    }

    if ($housekeepingStatus !== null) {
        foreach ($roomIds as $roomId) {
            logHousekeepingStatus($roomId, $housekeepingStatus, $notes, $recordedBy);
        }
    }
}

function getCalendarColorSettings(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'calendar_color_%'");
    $stmt->execute();
    $overrides = [];
    foreach ($stmt->fetchAll() as $row) {
        $status = substr($row['key'], strlen('calendar_color_'));
        if ($status && in_array($status, CALENDAR_COLOR_STATUSES, true) && is_string($row['value']) && $row['value'] !== '') {
            $overrides[$status] = $row['value'];
        }
    }

    return array_merge(DEFAULT_CALENDAR_COLORS, $overrides);
}

function saveCalendarColorSettings(PDO $pdo, array $colors): void
{
    if (!$colors) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`, updated_at) VALUES (:key, :value, :updated_at) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');
    foreach ($colors as $status => $color) {
        $stmt->execute([
            'key' => sprintf('calendar_color_%s', $status),
            'value' => $color,
            'updated_at' => now(),
        ]);
    }
}

function clearCalendarColorSettings(PDO $pdo): void
{
    $pdo->prepare("DELETE FROM settings WHERE `key` LIKE 'calendar_color_%'")->execute();
}

function normalizeHexColor(string $value): ?string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }
    $trimmed = ltrim($trimmed, '#');
    if (preg_match('/^[0-9a-fA-F]{6}$/', $trimmed) === 1) {
        return '#' . strtoupper($trimmed);
    }
    if (preg_match('/^[0-9a-fA-F]{3}$/', $trimmed) === 1) {
        return '#' . strtoupper(
            $trimmed[0] . $trimmed[0] .
            $trimmed[1] . $trimmed[1] .
            $trimmed[2] . $trimmed[2]
        );
    }

    return null;
}

function handleInvoices(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    if ($method === 'GET' && $id !== null && $action === 'pdf') {
        outputInvoicePdf((int) $id);
        return;
    }

    if ($method === 'GET') {
        if ($id !== null) {
            $invoice = getInvoiceWithRelations((int) $id);
            if (!$invoice) {
                jsonResponse(['error' => 'Invoice not found.'], 404);
            }
            jsonResponse($invoice);
        }

        $stmt = $pdo->query('SELECT * FROM invoices ORDER BY issue_date DESC');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        if (empty($data['reservation_id'])) {
            jsonResponse(['error' => 'reservation_id is required.'], 422);
        }

        $invoiceNumber = $data['invoice_number'] ?? generateInvoiceNumber();
        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $dueDate = $data['due_date'] ?? null;
        $status = $data['status'] ?? 'issued';
        $type = isset($data['type']) ? strtolower((string) $data['type']) : 'invoice';
        if (!in_array($type, ['invoice', 'correction'], true)) {
            jsonResponse(['error' => 'Unsupported invoice type.'], 422);
        }

        $parentInvoiceId = null;
        $correctionNumber = null;
        if ($type === 'correction') {
            $parentInvoiceId = isset($data['parent_invoice_id']) ? (int) $data['parent_invoice_id'] : null;
            if (!$parentInvoiceId) {
                jsonResponse(['error' => 'parent_invoice_id is required for corrections.'], 422);
            }
            $correctionNumber = $data['correction_number'] ?? generateInvoiceCorrectionNumber();
        }

        $items = [];
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = $data['items'];
        } else {
            $includeArticles = !isset($data['include_articles']) || filter_var($data['include_articles'], FILTER_VALIDATE_BOOLEAN);
            try {
                if ($type === 'correction' && $parentInvoiceId) {
                    $items = buildCorrectionItemsFromInvoice($parentInvoiceId);
                }
                if (!$items) {
                    $items = buildInvoiceItemsForReservation((int) $data['reservation_id'], $includeArticles);
                }
            } catch (Throwable $exception) {
                jsonResponse(['error' => $exception->getMessage()], 422);
            }
        }

        if (!$items) {
            jsonResponse(['error' => 'Unable to create invoice without any items.'], 422);
        }

        $totals = calculateInvoiceTotals($items);

        $stmt = $pdo->prepare('INSERT INTO invoices (reservation_id, invoice_number, correction_number, type, parent_invoice_id, issue_date, due_date, total_amount, tax_amount, status, created_at, updated_at) VALUES (:reservation_id, :invoice_number, :correction_number, :type, :parent_invoice_id, :issue_date, :due_date, :total_amount, :tax_amount, :status, :created_at, :updated_at)');
        $stmt->execute([
            'reservation_id' => $data['reservation_id'],
            'invoice_number' => $invoiceNumber,
            'correction_number' => $correctionNumber,
            'type' => $type,
            'parent_invoice_id' => $parentInvoiceId,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $totals['total'],
            'tax_amount' => $totals['tax'],
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = (int) $pdo->lastInsertId();
        storeInvoiceItems($invoiceId, $items);

        jsonResponse([
            'id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'correction_number' => $correctionNumber,
            'type' => $type,
        ], 201);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function fetchInvoiceItems(int $invoiceId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = :invoice_id');
    $stmt->execute(['invoice_id' => $invoiceId]);
    return $stmt->fetchAll();
}

function storeInvoiceItems(int $invoiceId, array $items): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, tax_rate, total_amount, created_at, updated_at) VALUES (:invoice_id, :description, :quantity, :unit_price, :tax_rate, :total_amount, :created_at, :updated_at)');
    foreach ($items as $item) {
        if (empty($item['description'])) {
            throw new InvalidArgumentException('Each invoice item requires a description.');
        }
        $quantity = (float) ($item['quantity'] ?? 1);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : null;
        $total = $quantity * $unitPrice * (1 + ($taxRate ?? 0) / 100);

        $stmt->execute([
            'invoice_id' => $invoiceId,
            'description' => $item['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'total_amount' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function buildCorrectionItemsFromInvoice(int $invoiceId): array
{
    $items = fetchInvoiceItems($invoiceId);
    if (!$items) {
        return [];
    }

    $corrections = [];
    foreach ($items as $item) {
        $quantity = (float) ($item['quantity'] ?? 0);
        $corrections[] = [
            'description' => $item['description'],
            'quantity' => $quantity > 0 ? -$quantity : $quantity,
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'tax_rate' => isset($item['tax_rate']) ? (float) $item['tax_rate'] : null,
        ];
    }

    return $corrections;
}

function calculateInvoiceTotals(array $items): array
{
    $subtotal = 0;
    $tax = 0;
    foreach ($items as $item) {
        $quantity = (float) ($item['quantity'] ?? 1);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $lineSubtotal = $quantity * $unitPrice;
        $subtotal += $lineSubtotal;
        $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0;
        $tax += $lineSubtotal * ($taxRate / 100);
    }

    return [
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'total' => round($subtotal + $tax, 2),
    ];
}

function fetchReservationBillingSnapshot(int $reservationId): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT r.*, g.first_name, g.last_name, g.email, g.phone, g.address, g.city, g.country, g.company_id,
               c.name AS company_name, c.address AS company_address, c.city AS company_city, c.country AS company_country,
               c.email AS company_email, c.phone AS company_phone,
               rp.name AS rate_plan_name, rp.base_price AS rate_plan_base_price, rp.currency AS rate_plan_currency
        FROM reservations r
        JOIN guests g ON g.id = r.guest_id
        LEFT JOIN companies c ON c.id = g.company_id
        LEFT JOIN rate_plans rp ON rp.id = r.rate_plan_id
        WHERE r.id = :id
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $reservationId]);
    $reservation = $stmt->fetch();
    if (!$reservation) {
        throw new InvalidArgumentException('Reservation not found for invoice generation.');
    }

    $reservation['rooms'] = fetchReservationRooms($reservationId);
    $reservation['room_requests'] = fetchReservationRoomRequests($reservationId, $pdo);
    $reservation['articles'] = fetchReservationArticles($reservationId);

    return $reservation;
}

function determineNightlyRate(array $room, array $reservation, int $nights): float
{
    $nightlyRate = isset($room['nightly_rate']) ? (float) $room['nightly_rate'] : 0.0;
    if ($nightlyRate <= 0 && isset($reservation['rate_plan_base_price'])) {
        $nightlyRate = (float) $reservation['rate_plan_base_price'];
    }
    if ($nightlyRate <= 0 && isset($reservation['total_amount'])) {
        $roomCount = max(1, count($reservation['rooms'] ?? []));
        $nightCount = max(1, $nights);
        $nightlyRate = ((float) $reservation['total_amount']) / ($roomCount * $nightCount);
    }

    if ($nightlyRate <= 0) {
        $nightlyRate = DEFAULT_NIGHTLY_RATE;
    }

    return max(0.0, $nightlyRate);
}

function fetchInvoicesForReservation(int $reservationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE reservation_id = :id ORDER BY created_at DESC, id DESC');
    $stmt->execute(['id' => $reservationId]);
    $invoices = $stmt->fetchAll();
    foreach ($invoices as &$invoice) {
        $invoice['items'] = fetchInvoiceItems((int) $invoice['id']);
    }
    unset($invoice);

    return $invoices;
}

function findLatestInvoiceForReservation(int $reservationId): ?array
{
    $invoices = fetchInvoicesForReservation($reservationId);
    return $invoices[0] ?? null;
}

function createReservationInvoice(int $reservationId, array $payload = []): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM reservations WHERE id = :id');
    $stmt->execute(['id' => $reservationId]);
    if ($stmt->fetchColumn() === false) {
        throw new InvalidArgumentException('Reservierung wurde nicht gefunden.');
    }

    $includeArticles = !isset($payload['include_articles']) || filter_var($payload['include_articles'], FILTER_VALIDATE_BOOLEAN);
    $items = buildInvoiceItemsForReservation($reservationId, $includeArticles);
    if (!$items) {
        throw new InvalidArgumentException('Für diese Reservierung sind keine abrechenbaren Posten hinterlegt.');
    }

    $invoiceNumber = $payload['invoice_number'] ?? generateInvoiceNumber();
    $issueDate = $payload['issue_date'] ?? date('Y-m-d');
    $dueDate = $payload['due_date'] ?? null;
    $status = $payload['status'] ?? 'issued';

    $totals = calculateInvoiceTotals($items);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO invoices (reservation_id, invoice_number, correction_number, type, parent_invoice_id, issue_date, due_date, total_amount, tax_amount, status, created_at, updated_at) VALUES (:reservation_id, :invoice_number, :correction_number, :type, :parent_invoice_id, :issue_date, :due_date, :total_amount, :tax_amount, :status, :created_at, :updated_at)');
        $stmt->execute([
            'reservation_id' => $reservationId,
            'invoice_number' => $invoiceNumber,
            'correction_number' => null,
            'type' => 'invoice',
            'parent_invoice_id' => null,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $totals['total'],
            'tax_amount' => $totals['tax'],
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = (int) $pdo->lastInsertId();
        storeInvoiceItems($invoiceId, $items);

        $pdo->commit();

        $invoice = getInvoiceWithRelations($invoiceId);
        return $invoice ?? ['id' => $invoiceId, 'invoice_number' => $invoiceNumber];
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function payReservationInvoice(int $reservationId, array $payload = []): array
{
    $pdo = db();
    $invoice = findLatestInvoiceForReservation($reservationId);
    if (!$invoice) {
        throw new InvalidArgumentException('Es liegt noch keine Rechnung für diese Reservierung vor.');
    }

    if (($invoice['status'] ?? '') === 'paid') {
        return ['invoice' => $invoice, 'message' => 'Rechnung bereits als bezahlt verbucht.'];
    }

    $amount = isset($payload['amount']) ? (float) $payload['amount'] : (float) ($invoice['total_amount'] ?? 0);
    if ($amount <= 0) {
        throw new InvalidArgumentException('Ungültiger Zahlungsbetrag.');
    }

    $method = $payload['method'] ?? 'cash';
    $paidAt = $payload['paid_at'] ?? now();
    $currency = $payload['currency'] ?? ($invoice['reservation_currency'] ?? 'EUR');
    $notes = $payload['notes'] ?? null;
    $recordedBy = $payload['recorded_by'] ?? null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO payments (invoice_id, method, amount, currency, paid_at, reference, notes, created_at, updated_at) VALUES (:invoice_id, :method, :amount, :currency, :paid_at, :reference, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'invoice_id' => $invoice['id'],
            'method' => $method,
            'amount' => $amount,
            'currency' => $currency,
            'paid_at' => $paidAt,
            'reference' => $payload['reference'] ?? null,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $update = $pdo->prepare('UPDATE invoices SET status = :status, updated_at = :updated_at WHERE id = :id');
        $update->execute([
            'status' => 'paid',
            'updated_at' => now(),
            'id' => $invoice['id'],
        ]);

        applyReservationStatusChange($pdo, $reservationId, 'paid', $notes, $recordedBy);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    $updatedInvoice = getInvoiceWithRelations((int) $invoice['id']);

    return [
        'invoice' => $updatedInvoice ?? $invoice,
        'payment' => [
            'invoice_id' => $invoice['id'],
            'amount' => $amount,
            'method' => $method,
            'currency' => $currency,
            'paid_at' => $paidAt,
        ],
    ];
}

function buildInvoiceItemsForReservation(int $reservationId, bool $includeArticles = true): array
{
    $reservation = fetchReservationBillingSnapshot($reservationId);
    $nights = calculateStayNights((string) $reservation['check_in_date'], (string) $reservation['check_out_date']);
    $nights = max(1, $nights);

    $items = [];

    foreach ($reservation['rooms'] as $room) {
        $rate = determineNightlyRate($room, $reservation, $nights);
        if ($rate <= 0) {
            continue;
        }
        $labelParts = [];
        $labelParts[] = $room['room_number'] ?? ('Zimmer ' . ($room['room_id'] ?? ''));
        if (!empty($room['room_type_name'])) {
            $labelParts[] = $room['room_type_name'];
        }
        $roomLabel = implode(' – ', array_filter($labelParts));
        $items[] = [
            'description' => sprintf('Zimmer %s · %d Nacht%s', $roomLabel, $nights, $nights === 1 ? '' : 'e'),
            'quantity' => $nights,
            'unit_price' => round($rate, 2),
            'tax_rate' => GERMAN_VAT_REDUCED,
        ];
    }

    if ($includeArticles) {
        foreach ($reservation['articles'] as $article) {
            $quantity = (float) ($article['quantity'] ?? 0);
            $unitPrice = isset($article['unit_price']) ? (float) $article['unit_price'] : 0.0;
            if ($quantity <= 0 || $unitPrice < 0) {
                continue;
            }
            $description = $article['description'] ?? ($article['article_name'] ?? 'Zusatzleistung');
            $taxRate = isset($article['tax_rate']) ? (float) $article['tax_rate'] : GERMAN_VAT_STANDARD;
            $items[] = [
                'description' => $description,
                'quantity' => round($quantity, 2),
                'unit_price' => round($unitPrice, 2),
                'tax_rate' => $taxRate,
            ];
        }
    }

    return $items;
}

function getInvoiceWithRelations(int $invoiceId): ?array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT i.*, r.confirmation_number, r.check_in_date, r.check_out_date, r.adults, r.children, r.currency AS reservation_currency,
               g.first_name, g.last_name, g.email, g.phone, g.address, g.city, g.country,
               c.name AS company_name, c.address AS company_address, c.city AS company_city, c.country AS company_country,
               c.email AS company_email, c.phone AS company_phone
        FROM invoices i
        JOIN reservations r ON r.id = i.reservation_id
        JOIN guests g ON g.id = r.guest_id
        LEFT JOIN companies c ON c.id = g.company_id
        WHERE i.id = :id
    SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        return null;
    }
    $invoice['items'] = fetchInvoiceItems($invoiceId);

    return $invoice;
}

function outputInvoicePdf(int $invoiceId): void
{
    $invoice = getInvoiceWithRelations($invoiceId);
    if (!$invoice) {
        jsonResponse(['error' => 'Invoice not found.'], 404);
    }

    require_once __DIR__ . '/../lib/fpdf.php';

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $logo = getInvoiceLogoDescriptor();
    if ($logo && is_file($logo['path'])) {
        $pdf->Image($logo['path'], 15, 15, 40);
        $pdf->SetY(20);
    }

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, pdfText('Rechnung'), 0, 1, 'R');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, pdfText('Rechnungsnummer: ' . ($invoice['invoice_number'] ?? '')), 0, 1, 'R');
    if (!empty($invoice['issue_date'])) {
        $pdf->Cell(0, 6, pdfText('Ausgestellt am: ' . formatGermanDate($invoice['issue_date'])), 0, 1, 'R');
    }
    if (!empty($invoice['due_date'])) {
        $pdf->Cell(0, 6, pdfText('Fällig am: ' . formatGermanDate($invoice['due_date'])), 0, 1, 'R');
    }

    $pdf->Ln(6);

    $guestName = trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''));
    $recipientLines = array_filter([
        $invoice['company_name'] ?? null,
        $guestName ?: null,
        $invoice['company_address'] ?? $invoice['address'] ?? null,
        trim(($invoice['company_city'] ?? $invoice['city'] ?? '') . ' ' . ($invoice['company_country'] ?? $invoice['country'] ?? '')),
    ]);

    foreach ($recipientLines as $line) {
        $pdf->Cell(0, 6, pdfText($line), 0, 1);
    }

    $pdf->Ln(4);

    if (!empty($invoice['check_in_date']) || !empty($invoice['check_out_date'])) {
        $stay = sprintf('%s – %s', formatGermanDate($invoice['check_in_date']), formatGermanDate($invoice['check_out_date']));
        $pdf->Cell(0, 6, pdfText('Aufenthalt: ' . $stay), 0, 1);
    }
    if (!empty($invoice['confirmation_number'])) {
        $pdf->Cell(0, 6, pdfText('Bestätigungsnummer: ' . $invoice['confirmation_number']), 0, 1);
    }

    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 11);
    $widths = [90, 20, 30, 20, 30];
    $headers = ['Beschreibung', 'Menge', 'Einzelpreis', 'MwSt', 'Gesamt'];
    $pdf->SetFillColor(240, 240, 240);
    foreach ($headers as $index => $header) {
        $align = $index === 0 ? 'L' : 'R';
        $pdf->Cell($widths[$index], 8, pdfText($header), 1, $index === count($headers) - 1 ? 1 : 0, $align, true);
    }

    $pdf->SetFont('Arial', '', 10);
    $currency = $invoice['reservation_currency'] ?? 'EUR';
    foreach ($invoice['items'] as $item) {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unit = (float) ($item['unit_price'] ?? 0);
        $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;
        $lineTotal = isset($item['total_amount']) ? (float) $item['total_amount'] : ($quantity * $unit * (1 + $taxRate / 100));
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        $desc = pdfText($item['description'] ?? '');
        $pdf->MultiCell($widths[0], 6, $desc, 1, 'L');
        $currentY = $pdf->GetY();
        $rowHeight = $currentY - $startY;
        $pdf->SetXY($startX + $widths[0], $startY);
        $pdf->Cell($widths[1], $rowHeight, pdfText(number_format($quantity, 2, ',', '.')), 1, 0, 'R');
        $pdf->Cell($widths[2], $rowHeight, pdfText(number_format($unit, 2, ',', '.') . ' ' . $currency), 1, 0, 'R');
        $pdf->Cell($widths[3], $rowHeight, pdfText(number_format($taxRate, 1, ',', '.') . '%'), 1, 0, 'R');
        $pdf->Cell($widths[4], $rowHeight, pdfText(number_format($lineTotal, 2, ',', '.') . ' ' . $currency), 1, 0, 'R');
        $pdf->SetY($currentY);
    }

    $pdf->Ln(2);

    $totals = calculateInvoiceTotals($invoice['items']);
    $taxTotal = isset($invoice['tax_amount']) ? (float) $invoice['tax_amount'] : $totals['tax'];
    $totalAmount = isset($invoice['total_amount']) ? (float) $invoice['total_amount'] : $totals['total'];
    $subtotal = $totalAmount - $taxTotal;

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(array_sum($widths) - $widths[4], 8, '', 0, 0);
    $pdf->Cell($widths[4], 8, pdfText('Zwischensumme: ' . number_format($subtotal, 2, ',', '.') . ' ' . $currency), 0, 1, 'R');
    $pdf->Cell(array_sum($widths) - $widths[4], 8, '', 0, 0);
    $pdf->Cell($widths[4], 8, pdfText('MwSt: ' . number_format($taxTotal, 2, ',', '.') . ' ' . $currency), 0, 1, 'R');
    $pdf->Cell(array_sum($widths) - $widths[4], 8, '', 0, 0);
    $pdf->Cell($widths[4], 8, pdfText('Gesamt: ' . number_format($totalAmount, 2, ',', '.') . ' ' . $currency), 0, 1, 'R');

    header('Content-Type: application/pdf');
    $filename = sprintf('Rechnung-%s.pdf', $invoice['invoice_number'] ?? $invoiceId);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    $pdf->Output('I', $filename);
    exit;
}

function handlePayments(string $method, array $segments): void
{
    $pdo = db();

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT payments.*, invoices.invoice_number FROM payments JOIN invoices ON invoices.id = payments.invoice_id ORDER BY paid_at DESC');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        foreach (['invoice_id', 'method', 'amount'] as $field) {
            if (empty($data[$field])) {
                jsonResponse(['error' => sprintf('%s is required.', $field)], 422);
            }
        }

        $stmt = $pdo->prepare('INSERT INTO payments (invoice_id, method, amount, currency, paid_at, reference, notes, created_at, updated_at) VALUES (:invoice_id, :method, :amount, :currency, :paid_at, :reference, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'invoice_id' => $data['invoice_id'],
            'method' => $data['method'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'EUR',
            'paid_at' => $data['paid_at'] ?? now(),
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleReports(string $method, array $segments): void
{
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Only GET supported for reports.'], 405);
    }

    $type = $segments[1] ?? null;
    if ($type === 'occupancy') {
        $start = $_GET['start'] ?? date('Y-m-d');
        $end = $_GET['end'] ?? $start;
        if (!validateDate($start) || !validateDate($end)) {
            jsonResponse(['error' => 'start and end must be valid dates (Y-m-d).'], 422);
        }
        jsonResponse(buildOccupancyReport($start, $end));
    }

    if ($type === 'revenue') {
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t');
        if (!validateDate($start) || !validateDate($end)) {
            jsonResponse(['error' => 'start and end must be valid dates (Y-m-d).'], 422);
        }
        jsonResponse(buildRevenueReport($start, $end));
    }

    if ($type === 'forecast') {
        $start = $_GET['start'] ?? date('Y-m-d');
        $end = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));
        if (!validateDate($start) || !validateDate($end)) {
            jsonResponse(['error' => 'start and end must be valid dates (Y-m-d).'], 422);
        }
        jsonResponse(buildForecastReport($start, $end));
    }

    jsonResponse(['error' => 'Unknown report type.'], 404);
}

function buildOccupancyReport(string $start, string $end): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT COUNT(*) FROM rooms');
    $totalRooms = (int) $stmt->fetchColumn();

    $period = new DatePeriod(new DateTimeImmutable($start), new DateInterval('P1D'), (new DateTimeImmutable($end))->modify('+1 day'));
    $report = [];
    foreach ($period as $date) {
        $formatted = $date->format('Y-m-d');
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT rr.room_id) FROM reservation_rooms rr JOIN reservations r ON rr.reservation_id = r.id WHERE r.status IN (\'confirmed\', \'checked_in\', \'paid\') AND :date >= r.check_in_date AND :date < r.check_out_date');
        $stmt->execute(['date' => $formatted]);
        $occupied = (int) $stmt->fetchColumn();
        $report[] = [
            'date' => $formatted,
            'occupied_rooms' => $occupied,
            'available_rooms' => $totalRooms,
            'occupancy_rate' => $totalRooms === 0 ? 0 : round(($occupied / $totalRooms) * 100, 2),
        ];
    }

    return $report;
}

function buildRevenueReport(string $start, string $end): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT SUM(total_amount) as invoice_total, SUM(tax_amount) as tax_total FROM invoices WHERE issue_date BETWEEN :start AND :end');
    $stmt->execute(['start' => $start, 'end' => $end]);
    $invoiceTotals = $stmt->fetch() ?: ['invoice_total' => 0, 'tax_total' => 0];

    $stmt = $pdo->prepare('SELECT method, SUM(amount) as total_amount FROM payments WHERE paid_at BETWEEN :start_dt AND :end_dt GROUP BY method');
    $stmt->execute([
        'start_dt' => $start . ' 00:00:00',
        'end_dt' => $end . ' 23:59:59',
    ]);
    $payments = $stmt->fetchAll();

    return [
        'period' => ['start' => $start, 'end' => $end],
        'invoices' => $invoiceTotals,
        'payments' => $payments,
    ];
}

function buildForecastReport(string $start, string $end): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT r.check_in_date, r.check_out_date, COUNT(rr.room_id) as rooms, COALESCE(r.total_amount, 0) as total_amount FROM reservations r LEFT JOIN reservation_rooms rr ON rr.reservation_id = r.id WHERE r.status IN (\'tentative\', \'confirmed\', \'paid\') AND r.check_in_date BETWEEN :start AND :end GROUP BY r.id');
    $stmt->execute(['start' => $start, 'end' => $end]);
    $rows = $stmt->fetchAll();

    $totalRooms = array_sum(array_column($rows, 'rooms'));
    $totalRevenue = array_sum(array_column($rows, 'total_amount'));

    return [
        'period' => ['start' => $start, 'end' => $end],
        'expected_rooms' => $totalRooms,
        'expected_revenue' => $totalRevenue,
        'reservations' => $rows,
    ];
}

function handleUsers(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT u.id, u.name, u.email, u.created_at, u.updated_at FROM users u ORDER BY u.name');
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            $stmtRoles = $pdo->prepare('SELECT r.id, r.name FROM roles r JOIN role_user ru ON ru.role_id = r.id WHERE ru.user_id = :user_id');
            $stmtRoles->execute(['user_id' => $user['id']]);
            $user['roles'] = $stmtRoles->fetchAll();
        }
        jsonResponse($users);
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        foreach (['name', 'email', 'password'] as $field) {
            if (empty($data[$field])) {
                jsonResponse(['error' => sprintf('%s is required.', $field)], 422);
            }
        }

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, created_at, updated_at) VALUES (:name, :email, :password, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (int) $pdo->lastInsertId();
        if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
            assignRolesToUser($userId, $data['role_ids']);
        }

        jsonResponse(['id' => $userId], 201);
    }

    if ($method === 'POST' && $id !== null && ($segments[2] ?? null) === 'roles') {
        $data = parseJsonBody();
        assignRolesToUser((int) $id, $data['role_ids'] ?? []);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function assignRolesToUser(int $userId, array $roleIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM role_user WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $stmt = $pdo->prepare('INSERT INTO role_user (user_id, role_id, assigned_at) VALUES (:user_id, :role_id, :assigned_at)');
    foreach ($roleIds as $roleId) {
        $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_at' => now(),
        ]);
    }
}

function handleRoles(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM roles ORDER BY name');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'name is required.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO roles (name, description, created_at, updated_at) VALUES (:name, :description, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if ($method === 'POST' && $id !== null && ($segments[2] ?? null) === 'permissions') {
        $data = parseJsonBody();
        assignPermissionsToRole((int) $id, $data['permission_ids'] ?? []);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function assignPermissionsToRole(int $roleId, array $permissionIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM permission_role WHERE role_id = :role_id')->execute(['role_id' => $roleId]);
    $stmt = $pdo->prepare('INSERT INTO permission_role (permission_id, role_id, granted_at) VALUES (:permission_id, :role_id, :granted_at)');
    foreach ($permissionIds as $permissionId) {
        $stmt->execute([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
            'granted_at' => now(),
        ]);
    }
}

function handlePermissions(string $method, array $segments): void
{
    $pdo = db();

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT * FROM permissions ORDER BY name');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'name is required.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO permissions (name, description, created_at, updated_at) VALUES (:name, :description, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleIntegrations(string $method, array $segments): void
{
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Only GET supported for integrations.'], 405);
    }

    $type = $segments[1] ?? null;
    if ($type === null) {
        jsonResponse([
            'channel_manager' => ['status' => 'not_connected', 'providers' => ['booking.com', 'expedia']],
            'door_lock' => ['status' => 'not_configured'],
            'pos' => ['status' => 'not_configured'],
            'accounting' => ['status' => 'not_configured'],
        ]);
    }

    switch ($type) {
        case 'channel-manager':
            jsonResponse(['status' => 'not_connected', 'message' => 'No channel manager connected yet. Configure credentials to enable automatic distribution.']);
        case 'door-locks':
            jsonResponse(['status' => 'not_configured', 'message' => 'Door lock integration placeholder.']);
        case 'pos':
            jsonResponse(['status' => 'not_configured', 'message' => 'POS integration placeholder.']);
        case 'accounting':
            jsonResponse(['status' => 'not_configured', 'message' => 'Accounting export placeholder.']);
    }

    jsonResponse(['error' => 'Unknown integration.'], 404);
}

function handleGuestPortal(string $method, array $segments): void
{
    $sub = $segments[1] ?? null;
    if ($sub !== 'reservations') {
        jsonResponse(['error' => 'Unsupported guest portal resource.'], 404);
    }

    $confirmation = $segments[2] ?? null;
    if ($confirmation === null) {
        jsonResponse(['error' => 'Confirmation number required.'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT r.*, g.first_name, g.last_name, g.email, g.company_id, c.name AS company_name, rp.name AS rate_plan_name, rp.base_price AS rate_plan_base_price, rp.currency AS rate_plan_currency FROM reservations r JOIN guests g ON g.id = r.guest_id LEFT JOIN companies c ON c.id = g.company_id LEFT JOIN rate_plans rp ON rp.id = r.rate_plan_id WHERE r.confirmation_number = :confirmation');
    $stmt->execute(['confirmation' => $confirmation]);
    $reservation = $stmt->fetch();
    if (!$reservation) {
        jsonResponse(['error' => 'Reservation not found.'], 404);
    }

    $action = $segments[3] ?? null;
    if ($method === 'GET' && $action === null) {
        $reservation['rooms'] = fetchReservationRooms((int) $reservation['id']);
        $reservation['documents'] = fetchReservationDocuments((int) $reservation['id']);
        jsonResponse($reservation);
    }

    if ($method === 'POST' && $action === 'check-in') {
        $data = parseJsonBody();
        $data['notes'] = $data['notes'] ?? 'Guest self check-in';
        handleReservationStatusChange((int) $reservation['id'], 'checked_in', $data);
        return;
    }

    if ($method === 'POST' && $action === 'documents') {
        $data = parseJsonBody();
        addReservationDocument((int) $reservation['id'], $data);
        return;
    }

    if ($method === 'POST' && $action === 'upsell') {
        $data = parseJsonBody();
        if (empty($data['service_type'])) {
            jsonResponse(['error' => 'service_type is required.'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO service_orders (reservation_id, service_type, status, notes, created_at, updated_at) VALUES (:reservation_id, :service_type, :status, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'reservation_id' => $reservation['id'],
            'service_type' => $data['service_type'],
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        jsonResponse(['service_order_id' => $pdo->lastInsertId()], 201);
        return;
    }

    jsonResponse(['error' => 'Unsupported guest portal action.'], 405);
}

function handleSettings(string $method, array $segments): void
{
    $pdo = db();
    $subresource = $segments[1] ?? null;

    if ($subresource === null) {
        if ($method === 'GET') {
            jsonResponse([
                'resources' => ['calendar-colors', 'invoice-logo'],
            ]);
        }
        jsonResponse(['error' => 'Unsupported method.'], 405);
    }

    if ($subresource === 'calendar-colors') {
        if ($method === 'GET') {
            jsonResponse(['colors' => getCalendarColorSettings($pdo)]);
        }

        if ($method === 'DELETE') {
            clearCalendarColorSettings($pdo);
            jsonResponse(['colors' => getCalendarColorSettings($pdo)]);
        }

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $data = parseJsonBody();
            $payload = [];
            if (isset($data['colors']) && is_array($data['colors'])) {
                $payload = $data['colors'];
            } elseif (is_array($data)) {
                $payload = $data;
            }

            if (!$payload) {
                jsonResponse(['error' => 'No colors supplied.'], 422);
            }

            $normalized = [];
            foreach ($payload as $status => $color) {
                if (!in_array($status, CALENDAR_COLOR_STATUSES, true)) {
                    continue;
                }
                $normalizedColor = normalizeHexColor((string) $color);
                if ($normalizedColor === null) {
                    jsonResponse(['error' => sprintf('Invalid colour value for %s', $status)], 422);
                }
                $normalized[$status] = $normalizedColor;
            }

            if (!$normalized) {
                jsonResponse(['error' => 'No valid colours supplied.'], 422);
            }

            saveCalendarColorSettings($pdo, $normalized);
            jsonResponse(['colors' => getCalendarColorSettings($pdo)]);
        }

        jsonResponse(['error' => 'Unsupported method.'], 405);
    }

    if ($subresource === 'invoice-logo') {
        if ($method === 'GET') {
            $descriptor = getInvoiceLogoDescriptor();
            if (!$descriptor) {
                jsonResponse(['logo' => null]);
            }
            $binary = @file_get_contents($descriptor['path']);
            if ($binary === false) {
                jsonResponse(['logo' => null]);
            }
            $dataUrl = sprintf('data:%s;base64,%s', $descriptor['mime'], base64_encode($binary));
            jsonResponse([
                'logo' => $dataUrl,
                'updated_at' => $descriptor['updated_at'] ?? null,
            ]);
        }

        if ($method === 'DELETE') {
            deleteInvoiceLogo();
            jsonResponse(['logo' => null]);
        }

        if ($method === 'POST' || $method === 'PUT') {
            $data = parseJsonBody();
            if (!empty($data['remove'])) {
                deleteInvoiceLogo();
                jsonResponse(['logo' => null]);
            }
            if (empty($data['image']) || !is_string($data['image'])) {
                jsonResponse(['error' => 'image payload is required.'], 422);
            }
            try {
                $descriptor = saveInvoiceLogoImage($data['image']);
            } catch (Throwable $exception) {
                jsonResponse(['error' => $exception->getMessage()], 422);
            }
            $binary = file_get_contents($descriptor['path']);
            $dataUrl = sprintf('data:%s;base64,%s', $descriptor['mime'], base64_encode($binary));
            jsonResponse([
                'logo' => $dataUrl,
                'updated_at' => $descriptor['updated_at'] ?? null,
            ]);
        }

        jsonResponse(['error' => 'Unsupported method.'], 405);
    }

    jsonResponse(['error' => 'Unknown settings resource.'], 404);
}

function generateReservationNumber(): string
{
    return formatSequenceNumber('RES-', nextSequenceValue('sequence_reservation'));
}

function generateConfirmationNumber(): string
{
    return generateReservationNumber();
}

function generateInvoiceNumber(): string
{
    return formatSequenceNumber('INV-', nextSequenceValue('sequence_invoice'));
}

function generateInvoiceCorrectionNumber(): string
{
    return formatSequenceNumber('COR-', nextSequenceValue('sequence_invoice_correction'));
}

function nextSequenceValue(string $key, int $start = 1): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key FOR UPDATE');
        $stmt->execute(['key' => $key]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            $next = $start;
            $insert = $pdo->prepare('INSERT INTO settings (`key`, `value`, created_at, updated_at) VALUES (:key, :value, :created_at, :updated_at)');
            $insert->execute([
                'key' => $key,
                'value' => (string) $next,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $currentValue = (int) $current;
            $next = max($start, $currentValue + 1);
            $update = $pdo->prepare('UPDATE settings SET `value` = :value, updated_at = :updated_at WHERE `key` = :key');
            $update->execute([
                'value' => (string) $next,
                'updated_at' => now(),
                'key' => $key,
            ]);
        }

        $pdo->commit();

        return $next;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function formatSequenceNumber(string $prefix, int $number, int $padding = 6): string
{
    $padding = max(1, $padding);
    return sprintf('%s%0' . $padding . 'd', $prefix, $number);
}

function pdfText(string $value): string
{
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
    if ($converted === false) {
        return utf8_decode($value);
    }

    return $converted;
}

function formatGermanDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $date = substr($date, 0, 10);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d.m.Y') : $date;
}

function decodeImagePayload(string $payload): array
{
    $data = $payload;
    $mime = 'image/png';
    if (str_contains($payload, ',')) {
        [$meta, $encoded] = explode(',', $payload, 2);
        $data = $encoded;
        if (preg_match('/data:(.*?);base64/', $meta, $matches)) {
            $mime = strtolower(trim($matches[1]));
        }
    }

    $binary = base64_decode($data, true);
    if ($binary === false) {
        throw new InvalidArgumentException('Bilddaten konnten nicht dekodiert werden.');
    }

    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
    ];
    if (!isset($allowed[$mime])) {
        throw new InvalidArgumentException('Nur PNG- oder JPEG-Dateien werden unterstützt.');
    }

    return [
        'binary' => $binary,
        'mime' => $mime,
        'extension' => $allowed[$mime],
    ];
}

function storagePath(string $relative = ''): string
{
    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        $base = __DIR__ . '/..';
    }
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage';
    if ($relative === '') {
        return $base;
    }

    return $base . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
}

function ensureStorageDirectory(string $relative): string
{
    $path = storagePath($relative);
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Verzeichnis konnte nicht erstellt werden: ' . $path);
        }
    }

    return $path;
}

function getSetting(string $key): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key');
    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

function setSetting(string $key, ?string $value): void
{
    $pdo = db();
    if ($value === null) {
        $stmt = $pdo->prepare('DELETE FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`, created_at, updated_at) VALUES (:key, :value, :created_at, :updated_at) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');
    $timestamp = now();
    $stmt->execute([
        'key' => $key,
        'value' => $value,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
}

function getInvoiceLogoDescriptor(): ?array
{
    $relative = getSetting('invoice_logo_path');
    if (!$relative) {
        return null;
    }
    $path = storagePath($relative);
    if (!is_file($path)) {
        return null;
    }
    $mime = getSetting('invoice_logo_mime') ?? (mime_content_type($path) ?: 'image/png');
    $updatedAt = getSetting('invoice_logo_updated_at');

    return [
        'path' => $path,
        'mime' => $mime,
        'updated_at' => $updatedAt,
    ];
}

function saveInvoiceLogoImage(string $payload): array
{
    $decoded = decodeImagePayload($payload);
    deleteInvoiceLogo();
    $directory = ensureStorageDirectory('invoice');
    $filename = 'logo.' . $decoded['extension'];
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    if (file_put_contents($path, $decoded['binary']) === false) {
        throw new RuntimeException('Logo konnte nicht gespeichert werden.');
    }
    $timestamp = now();
    setSetting('invoice_logo_path', 'invoice/' . $filename);
    setSetting('invoice_logo_mime', $decoded['mime']);
    setSetting('invoice_logo_updated_at', $timestamp);

    return [
        'path' => $path,
        'mime' => $decoded['mime'],
        'updated_at' => $timestamp,
    ];
}

function deleteInvoiceLogo(): void
{
    $descriptor = getInvoiceLogoDescriptor();
    if ($descriptor && is_file($descriptor['path'])) {
        @unlink($descriptor['path']);
    }
    setSetting('invoice_logo_path', null);
    setSetting('invoice_logo_mime', null);
    setSetting('invoice_logo_updated_at', null);
}
