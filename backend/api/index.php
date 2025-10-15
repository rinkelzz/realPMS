<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

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
            'housekeeping/tasks',
            'invoices',
            'payments',
            'reports',
            'users',
            'roles',
            'permissions',
            'integrations',
            'guest-portal',
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
    case 'rooms':
        handleRooms($method, $segments);
        break;
    case 'reservations':
        handleReservations($method, $segments);
        break;
    case 'guests':
        handleGuests($method, $segments);
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

        $stmt = $pdo->prepare('INSERT INTO room_types (name, description, base_occupancy, max_occupancy, base_rate, currency, created_at, updated_at) VALUES (:name, :description, :base_occupancy, :max_occupancy, :base_rate, :currency, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_occupancy' => $data['base_occupancy'] ?? 1,
            'max_occupancy' => $data['max_occupancy'] ?? ($data['base_occupancy'] ?? 1),
            'base_rate' => $data['base_rate'] ?? null,
            'currency' => $data['currency'] ?? 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['name', 'description', 'base_occupancy', 'max_occupancy', 'base_rate', 'currency'];
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

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM rate_plans WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $plan = $stmt->fetch();
            if (!$plan) {
                jsonResponse(['error' => 'Rate plan not found.'], 404);
            }
            jsonResponse($plan);
        }

        $stmt = $pdo->query('SELECT * FROM rate_plans ORDER BY name');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST') {
        $data = parseJsonBody();
        if (empty($data['name'])) {
            jsonResponse(['error' => 'Name is required.'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO rate_plans (name, description, base_price, currency, cancellation_policy, created_at, updated_at) VALUES (:name, :description, :base_price, :currency, :cancellation_policy, :created_at, :updated_at)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'base_price' => $data['base_price'] ?? 0,
            'currency' => $data['currency'] ?? 'EUR',
            'cancellation_policy' => $data['cancellation_policy'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        jsonResponse(['id' => $pdo->lastInsertId()], 201);
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['name', 'description', 'base_price', 'currency', 'cancellation_policy'];
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

        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();

        $stmt = $pdo->prepare(sprintf('UPDATE rate_plans SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleRooms(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT rooms.*, room_types.name AS room_type_name FROM rooms JOIN room_types ON rooms.room_type_id = room_types.id WHERE rooms.id = :id');
            $stmt->execute(['id' => $id]);
            $room = $stmt->fetch();
            if (!$room) {
                jsonResponse(['error' => 'Room not found.'], 404);
            }
            jsonResponse($room);
        }

        $query = 'SELECT rooms.*, room_types.name AS room_type_name FROM rooms JOIN room_types ON rooms.room_type_id = room_types.id';
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
            $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $guest = $stmt->fetch();
            if (!$guest) {
                jsonResponse(['error' => 'Guest not found.'], 404);
            }
            jsonResponse($guest);
        }

        $query = 'SELECT * FROM guests';
        $params = [];
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $query .= ' WHERE CONCAT(first_name, " ", last_name) LIKE :search OR email LIKE :search';
            $params['search'] = '%' . $_GET['search'] . '%';
        }
        $query .= ' ORDER BY last_name, first_name';
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

        $stmt = $pdo->prepare('INSERT INTO guests (first_name, last_name, email, phone, address, city, country, notes, created_at, updated_at) VALUES (:first_name, :last_name, :email, :phone, :address, :city, :country, :notes, :created_at, :updated_at)');
        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
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
        $fields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country', 'notes'];
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
        $updates[] = 'updated_at = :updated_at';
        $params['updated_at'] = now();
        $stmt = $pdo->prepare(sprintf('UPDATE guests SET %s WHERE id = :id', implode(', ', $updates)));
        $stmt->execute($params);
        jsonResponse(['updated' => true]);
    }

    jsonResponse(['error' => 'Unsupported method.'], 405);
}

function handleReservations(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT r.*, g.first_name, g.last_name, g.email, g.phone FROM reservations r JOIN guests g ON g.id = r.guest_id WHERE r.id = :id');
            $stmt->execute(['id' => $id]);
            $reservation = $stmt->fetch();
            if (!$reservation) {
                jsonResponse(['error' => 'Reservation not found.'], 404);
            }
            $reservation['rooms'] = fetchReservationRooms((int) $id);
            $reservation['documents'] = fetchReservationDocuments((int) $id);
            $reservation['status_history'] = fetchReservationStatusLogs((int) $id);
            jsonResponse($reservation);
        }

        $conditions = [];
        $params = [];
        $query = 'SELECT r.*, g.first_name, g.last_name FROM reservations r JOIN guests g ON g.id = r.guest_id';
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
        }
        jsonResponse($reservations);
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        validateReservationPayload($data);

        $pdo->beginTransaction();
        try {
            $guestId = $data['guest_id'] ?? null;
            if ($guestId === null) {
                $guestId = createGuest($pdo, $data['guest']);
            }

            $confirmation = $data['confirmation_number'] ?? generateConfirmationNumber();
            $stmt = $pdo->prepare('INSERT INTO reservations (confirmation_number, guest_id, status, check_in_date, check_out_date, adults, children, rate_plan_id, total_amount, currency, booked_via, notes, created_at, updated_at) VALUES (:confirmation_number, :guest_id, :status, :check_in_date, :check_out_date, :adults, :children, :rate_plan_id, :total_amount, :currency, :booked_via, :notes, :created_at, :updated_at)');
            $stmt->execute([
                'confirmation_number' => $confirmation,
                'guest_id' => $guestId,
                'status' => $data['status'] ?? 'tentative',
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'rate_plan_id' => $data['rate_plan_id'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'currency' => $data['currency'] ?? 'EUR',
                'booked_via' => $data['booked_via'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $reservationId = (int) $pdo->lastInsertId();
            assignRoomsToReservation($pdo, $reservationId, $data['rooms'] ?? [], $data['check_in_date'], $data['check_out_date']);
            logReservationStatus($pdo, $reservationId, $data['status'] ?? 'tentative', $data['status_notes'] ?? null, $data['recorded_by'] ?? null);

            $pdo->commit();
            jsonResponse(['id' => $reservationId, 'confirmation_number' => $confirmation], 201);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            jsonResponse(['error' => $exception->getMessage()], 500);
        }
    }

    if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
        $data = parseJsonBody();
        $fields = ['status', 'check_in_date', 'check_out_date', 'adults', 'children', 'rate_plan_id', 'total_amount', 'currency', 'booked_via', 'notes'];
        $updates = [];
        $params = ['id' => $id];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if (($field === 'check_in_date' || $field === 'check_out_date') && !validateDate($data[$field])) {
                    jsonResponse(['error' => sprintf('Invalid date for %s', $field)], 422);
                }
                $updates[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $data[$field];
            }
        }

        if (!$updates && empty($data['rooms'])) {
            jsonResponse(['error' => 'No changes supplied.'], 422);
        }

        $pdo->beginTransaction();
        try {
            if ($updates) {
                $updates[] = 'updated_at = :updated_at';
                $params['updated_at'] = now();
                $stmt = $pdo->prepare(sprintf('UPDATE reservations SET %s WHERE id = :id', implode(', ', $updates)));
                $stmt->execute($params);
                if (isset($data['status'])) {
                    logReservationStatus($pdo, (int) $id, $data['status'], $data['status_notes'] ?? null, $data['recorded_by'] ?? null);
                }
            }

            if (!empty($data['rooms'])) {
                $pdo->prepare('DELETE FROM reservation_rooms WHERE reservation_id = :id')->execute(['id' => $id]);
                $stmt = $pdo->prepare('SELECT check_in_date, check_out_date FROM reservations WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $dates = $stmt->fetch();
                $checkIn = $data['check_in_date'] ?? $dates['check_in_date'];
                $checkOut = $data['check_out_date'] ?? $dates['check_out_date'];
                assignRoomsToReservation($pdo, (int) $id, $data['rooms'], $checkIn, $checkOut);
            }

            $pdo->commit();
            jsonResponse(['updated' => true]);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            jsonResponse(['error' => $exception->getMessage()], 500);
        }
    }

    if ($method === 'POST' && $id !== null && isset($segments[2])) {
        $action = $segments[2];
        if ($action === 'check-in') {
            $data = parseJsonBody();
            handleReservationStatusChange((int) $id, 'checked_in', $data);
        } elseif ($action === 'check-out') {
            $data = parseJsonBody();
            handleReservationStatusChange((int) $id, 'checked_out', $data);
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
    $pdo = db();
    $data = $payload ?? parseJsonBody();
    $notes = $data['notes'] ?? null;
    $recordedBy = $data['recorded_by'] ?? null;

    $stmt = $pdo->prepare('UPDATE reservations SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'updated_at' => now(),
        'id' => $reservationId,
    ]);

    logReservationStatus($pdo, $reservationId, $status, $notes, $recordedBy);

    if ($status === 'checked_in') {
        $pdo->prepare("UPDATE rooms SET status = 'occupied', updated_at = :updated_at WHERE id IN (SELECT room_id FROM reservation_rooms WHERE reservation_id = :id)")->execute([
            'updated_at' => now(),
            'id' => $reservationId,
        ]);
        foreach (getReservationRoomIds($pdo, $reservationId) as $roomId) {
            logHousekeepingStatus($roomId, 'occupied', $notes, $recordedBy);
        }
    }

    if ($status === 'checked_out') {
        $pdo->prepare("UPDATE rooms SET status = 'in_cleaning', updated_at = :updated_at WHERE id IN (SELECT room_id FROM reservation_rooms WHERE reservation_id = :id)")->execute([
            'updated_at' => now(),
            'id' => $reservationId,
        ]);
        foreach (getReservationRoomIds($pdo, $reservationId) as $roomId) {
            logHousekeepingStatus($roomId, 'in_cleaning', $notes, $recordedBy);
        }
    }

    jsonResponse(['status' => $status]);
}

function validateReservationPayload(array $data): void
{
    foreach (['check_in_date', 'check_out_date'] as $field) {
        if (empty($data[$field]) || !validateDate($data[$field])) {
            jsonResponse(['error' => sprintf('Invalid or missing %s', $field)], 422);
        }
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

    if (!empty($data['rooms']) && !is_array($data['rooms'])) {
        jsonResponse(['error' => 'rooms must be an array of room assignments.'], 422);
    }
}

function createGuest(PDO $pdo, array $guest): int
{
    $stmt = $pdo->prepare('INSERT INTO guests (first_name, last_name, email, phone, address, city, country, notes, created_at, updated_at) VALUES (:first_name, :last_name, :email, :phone, :address, :city, :country, :notes, :created_at, :updated_at)');
    $stmt->execute([
        'first_name' => $guest['first_name'],
        'last_name' => $guest['last_name'],
        'email' => $guest['email'] ?? null,
        'phone' => $guest['phone'] ?? null,
        'address' => $guest['address'] ?? null,
        'city' => $guest['city'] ?? null,
        'country' => $guest['country'] ?? null,
        'notes' => $guest['notes'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) $pdo->lastInsertId();
}

function assignRoomsToReservation(PDO $pdo, int $reservationId, array $rooms, string $checkIn, string $checkOut): void
{
    foreach ($rooms as $room) {
        $roomId = is_array($room) ? (int) ($room['room_id'] ?? 0) : (int) $room;
        if ($roomId === 0) {
            throw new InvalidArgumentException('room_id is required for each room assignment.');
        }

        if (!isRoomAvailable($pdo, $roomId, $checkIn, $checkOut, $reservationId)) {
            throw new RuntimeException(sprintf('Room %d is not available for the selected dates.', $roomId));
        }

        $nightlyRate = is_array($room) ? ($room['nightly_rate'] ?? null) : null;
        $currency = is_array($room) ? ($room['currency'] ?? null) : null;

        $stmt = $pdo->prepare('INSERT INTO reservation_rooms (reservation_id, room_id, nightly_rate, currency) VALUES (:reservation_id, :room_id, :nightly_rate, :currency)');
        $stmt->execute([
            'reservation_id' => $reservationId,
            'room_id' => $roomId,
            'nightly_rate' => $nightlyRate,
            'currency' => $currency,
        ]);
    }
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
    $stmt = $pdo->prepare('SELECT rr.*, rooms.room_number FROM reservation_rooms rr JOIN rooms ON rooms.id = rr.room_id WHERE rr.reservation_id = :id');
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

function handleInvoices(string $method, array $segments): void
{
    $pdo = db();
    $id = $segments[1] ?? null;

    if ($method === 'GET') {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $invoice = $stmt->fetch();
            if (!$invoice) {
                jsonResponse(['error' => 'Invoice not found.'], 404);
            }
            $invoice['items'] = fetchInvoiceItems((int) $id);
            jsonResponse($invoice);
        }

        $stmt = $pdo->query('SELECT * FROM invoices ORDER BY issue_date DESC');
        jsonResponse($stmt->fetchAll());
    }

    if ($method === 'POST' && $id === null) {
        $data = parseJsonBody();
        if (empty($data['reservation_id']) || empty($data['items']) || !is_array($data['items'])) {
            jsonResponse(['error' => 'reservation_id and items are required.'], 422);
        }

        $invoiceNumber = $data['invoice_number'] ?? generateInvoiceNumber();
        $issueDate = $data['issue_date'] ?? date('Y-m-d');
        $dueDate = $data['due_date'] ?? null;
        $status = $data['status'] ?? 'issued';

        $totals = calculateInvoiceTotals($data['items']);

        $stmt = $pdo->prepare('INSERT INTO invoices (reservation_id, invoice_number, issue_date, due_date, total_amount, tax_amount, status, created_at, updated_at) VALUES (:reservation_id, :invoice_number, :issue_date, :due_date, :total_amount, :tax_amount, :status, :created_at, :updated_at)');
        $stmt->execute([
            'reservation_id' => $data['reservation_id'],
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $totals['total'],
            'tax_amount' => $totals['tax'],
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = (int) $pdo->lastInsertId();
        storeInvoiceItems($invoiceId, $data['items']);

        jsonResponse(['id' => $invoiceId, 'invoice_number' => $invoiceNumber], 201);
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
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT rr.room_id) FROM reservation_rooms rr JOIN reservations r ON rr.reservation_id = r.id WHERE r.status IN (\'confirmed\', \'checked_in\') AND :date >= r.check_in_date AND :date < r.check_out_date');
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
    $stmt = $pdo->prepare('SELECT r.check_in_date, r.check_out_date, COUNT(rr.room_id) as rooms, COALESCE(r.total_amount, 0) as total_amount FROM reservations r LEFT JOIN reservation_rooms rr ON rr.reservation_id = r.id WHERE r.status IN (\'tentative\', \'confirmed\') AND r.check_in_date BETWEEN :start AND :end GROUP BY r.id');
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
    $stmt = $pdo->prepare('SELECT r.*, g.first_name, g.last_name, g.email FROM reservations r JOIN guests g ON g.id = r.guest_id WHERE r.confirmation_number = :confirmation');
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

function generateConfirmationNumber(): string
{
    return strtoupper(bin2hex(random_bytes(4)));
}

function generateInvoiceNumber(): string
{
    return 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
