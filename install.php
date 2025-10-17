<?php

declare(strict_types=1);

/**
 * Hotel PMS install script.
 *
 * This script creates the initial MySQL tables required for the PMS prototype.
 * It is intentionally framework-agnostic so it can be executed before Laravel
 * is fully set up.
 *
 * Usage (CLI):
 *   php install.php
 *
 * Usage (Browser):
 *   Place this file on the server and open it. Ensure database credentials are
 *   configured via environment variables first.
 */

if (!extension_loaded('pdo_mysql')) {
    respond('PDO MySQL extension is required. Please enable it before running the installer.', true);
}

$config = loadConfiguration();

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    );
} catch (PDOException $exception) {
    respond('Connection failed: ' . $exception->getMessage(), true);
}

$statements = getSchemaStatements();
$results = [];

foreach ($statements as $table => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = sprintf('✓ Created/verified table `%s`', $table);
    } catch (PDOException $exception) {
        respond(sprintf('Error creating table %s: %s', $table, $exception->getMessage()), true, $results);
    }
}

try {
    if (!columnExists($pdo, 'guests', 'company_id')) {
        $pdo->exec('ALTER TABLE guests ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER country');
        $results[] = '✓ Added column `company_id` to `guests`';
    } else {
        $results[] = '• Column `company_id` already present on `guests`';
    }
} catch (PDOException $exception) {
    respond('Error ensuring company column on guests: ' . $exception->getMessage(), true, $results);
}

try {
    if (!foreignKeyExists($pdo, 'guests', 'fk_guests_company')) {
        $pdo->exec('ALTER TABLE guests ADD CONSTRAINT fk_guests_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL');
        $results[] = '✓ Added foreign key `fk_guests_company`';
    } else {
        $results[] = '• Foreign key `fk_guests_company` already present';
    }
} catch (PDOException $exception) {
    respond('Error ensuring guest/company relation: ' . $exception->getMessage(), true, $results);
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM reservations LIKE 'status'");
    $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $type = $column['Type'] ?? '';
    if (strpos($type, "'paid'") === false) {
        $pdo->exec("ALTER TABLE reservations MODIFY status ENUM('tentative','confirmed','checked_in','paid','checked_out','cancelled','no_show') NOT NULL DEFAULT 'tentative'");
        $results[] = '✓ Updated reservation status options';
    } else {
        $results[] = '• Reservation status options already current';
    }
} catch (PDOException $exception) {
    respond('Error updating reservation status enum: ' . $exception->getMessage(), true, $results);
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM reservation_status_logs LIKE 'status'");
    $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    $type = $column['Type'] ?? '';
    if (strpos($type, "'paid'") === false) {
        $pdo->exec("ALTER TABLE reservation_status_logs MODIFY status ENUM('tentative','confirmed','checked_in','paid','checked_out','cancelled','no_show') NOT NULL");
        $results[] = '✓ Updated reservation status log options';
    } else {
        $results[] = '• Reservation status log options already current';
    }
} catch (PDOException $exception) {
    respond('Error updating reservation status log enum: ' . $exception->getMessage(), true, $results);
}

try {
    if (!columnExists($pdo, 'room_types', 'base_rate')) {
        $pdo->exec("ALTER TABLE room_types ADD COLUMN base_rate DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER description");
        $results[] = '✓ Added column `base_rate` to `room_types`';
    } else {
        $results[] = '• Column `base_rate` already present on `room_types`';
    }
} catch (PDOException $exception) {
    respond('Error ensuring room type base rate column: ' . $exception->getMessage(), true, $results);
}

try {
    if (!columnExists($pdo, 'invoices', 'correction_number')) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN correction_number VARCHAR(100) NULL UNIQUE AFTER invoice_number");
        $results[] = '✓ Added column `correction_number` to `invoices`';
    } else {
        $results[] = '• Column `correction_number` already present on `invoices`';
    }

    if (!columnExists($pdo, 'invoices', 'type')) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN type ENUM('invoice','correction') NOT NULL DEFAULT 'invoice' AFTER correction_number");
        $results[] = '✓ Added column `type` to `invoices`';
    } else {
        $results[] = '• Column `type` already present on `invoices`';
    }

    if (!columnExists($pdo, 'invoices', 'parent_invoice_id')) {
        $pdo->exec('ALTER TABLE invoices ADD COLUMN parent_invoice_id BIGINT UNSIGNED NULL AFTER type');
        $results[] = '✓ Added column `parent_invoice_id` to `invoices`';
    } else {
        $results[] = '• Column `parent_invoice_id` already present on `invoices`';
    }

    if (!foreignKeyExists($pdo, 'invoices', 'fk_invoices_parent')) {
        $pdo->exec('ALTER TABLE invoices ADD CONSTRAINT fk_invoices_parent FOREIGN KEY (parent_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL');
        $results[] = '✓ Added foreign key `fk_invoices_parent`';
    } else {
        $results[] = '• Foreign key `fk_invoices_parent` already present';
    }
} catch (PDOException $exception) {
    respond('Error updating invoice metadata columns: ' . $exception->getMessage(), true, $results);
}

try {
    $results[] = ensureSequenceSeed($pdo, 'sequence_reservation', 'reservations');
    $results[] = ensureSequenceSeed($pdo, 'sequence_invoice', 'invoices');
    $results[] = ensureSequenceSeed($pdo, 'sequence_invoice_correction', 'invoices');
} catch (PDOException $exception) {
    respond('Error initialising numbering sequences: ' . $exception->getMessage(), true, $results);
}

respond('Installation completed successfully.', false, $results);

/**
 * Load configuration from environment variables or an optional install config file.
 */
function loadConfiguration(): array
{
    $config = [
        'host' => getEnvValue('DB_HOST', '127.0.0.1'),
        'port' => getEnvValue('DB_PORT', '3306'),
        'database' => getEnvValue('DB_DATABASE'),
        'username' => getEnvValue('DB_USERNAME'),
        'password' => getEnvValue('DB_PASSWORD'),
    ];

    $configPath = __DIR__ . '/install.config.php';
    if (file_exists($configPath)) {
        /** @var array $fileConfig */
        $fileConfig = require $configPath;
        $config = array_merge($config, array_filter($fileConfig, static fn ($value) => $value !== null));
    }

    $requiredKeys = ['host', 'port', 'database', 'username', 'password'];
    foreach ($requiredKeys as $key) {
        $value = $config[$key] ?? null;
        if ($value === null || $value === '') {
            respond(sprintf('Missing configuration value for %s. Set the corresponding environment variable or update install.config.php.', strtoupper($key)), true);
        }
    }

    return $config;
}

/**
 * Basic .env style lookup that prefers runtime environment variables.
 */
function getEnvValue(string $key, ?string $default = null): ?string
{
    if (getenv($key) !== false) {
        return getenv($key);
    }

    static $cachedEnv;
    if ($cachedEnv === null) {
        $cachedEnv = loadDotEnv(__DIR__ . '/.env');
    }

    return $cachedEnv[$key] ?? $default;
}

function loadDotEnv(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $values[$name] = trim($value, "'\"");
    }

    return $values;
}

function ensureSequenceSeed(PDO $pdo, string $key, string $table, string $column = 'id'): string
{
    $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key');
    $stmt->execute(['key' => $key]);
    $existing = $stmt->fetchColumn();

    $maxQuery = sprintf('SELECT MAX(`%s`) FROM `%s`', $column, $table);
    $maxStmt = $pdo->query($maxQuery);
    $maxValue = $maxStmt ? (int) $maxStmt->fetchColumn() : 0;

    $timestamp = date('Y-m-d H:i:s');

    if ($existing === false) {
        $seed = $maxValue > 0 ? $maxValue : 0;
        $insert = $pdo->prepare('INSERT INTO settings (`key`, `value`, created_at, updated_at) VALUES (:key, :value, :created_at, :updated_at)');
        $insert->execute([
            'key' => $key,
            'value' => (string) $seed,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return sprintf('✓ Initialised sequence `%s` at %d', $key, $seed);
    }

    $current = (int) $existing;
    if ($maxValue > $current) {
        $update = $pdo->prepare('UPDATE settings SET `value` = :value, updated_at = :updated_at WHERE `key` = :key');
        $update->execute([
            'value' => (string) $maxValue,
            'updated_at' => $timestamp,
            'key' => $key,
        ]);

        return sprintf('✓ Raised sequence `%s` to %d', $key, $maxValue);
    }

    return sprintf('• Sequence `%s` already initialised', $key);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $query = sprintf('SHOW COLUMNS FROM `%s` LIKE %s', $table, $pdo->quote($column));
    $stmt = $pdo->query($query);
    return $stmt !== false && $stmt->fetch() !== false;
}

function foreignKeyExists(PDO $pdo, string $table, string $constraintName): bool
{
    $sql = 'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'table' => $table,
        'constraint' => $constraintName,
    ]);
    return $stmt->fetchColumn() !== false;
}

function getSchemaStatements(): array
{
    return [
        'roles' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'permissions' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'users' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                remember_token VARCHAR(100) NULL,
                last_login_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'role_user' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS role_user (
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, role_id),
                CONSTRAINT fk_role_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_role_user_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'permission_role' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS permission_role (
                permission_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                granted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (permission_id, role_id),
                CONSTRAINT fk_permission_role_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                CONSTRAINT fk_permission_role_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'settings' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(150) NOT NULL UNIQUE,
                `value` TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'companies' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS companies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(50) NULL,
                address VARCHAR(255) NULL,
                city VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'guests' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS guests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(150) NULL,
                phone VARCHAR(50) NULL,
                address VARCHAR(255) NULL,
                city VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                company_id BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_guests_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'room_types' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS room_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                base_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
                base_occupancy TINYINT UNSIGNED NOT NULL DEFAULT 1,
                max_occupancy TINYINT UNSIGNED NOT NULL DEFAULT 1,
                currency CHAR(3) NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'rooms' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS rooms (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_number VARCHAR(50) NOT NULL UNIQUE,
                room_type_id BIGINT UNSIGNED NOT NULL,
                floor VARCHAR(50) NULL,
                status ENUM('available','occupied','out_of_order','in_cleaning') NOT NULL DEFAULT 'available',
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_rooms_room_type FOREIGN KEY (room_type_id) REFERENCES room_types(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'rate_plans' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_plans (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                cancellation_policy TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'articles' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS articles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                charge_scheme ENUM('per_person_per_day','per_room_per_day','per_stay','per_person','per_day') NOT NULL DEFAULT 'per_person_per_day',
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 19.00,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'reservations' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reservations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                confirmation_number VARCHAR(100) NOT NULL UNIQUE,
                guest_id BIGINT UNSIGNED NOT NULL,
                status ENUM('tentative','confirmed','checked_in','paid','checked_out','cancelled','no_show') NOT NULL DEFAULT 'tentative',
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                adults SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                rate_plan_id BIGINT UNSIGNED NULL,
                total_amount DECIMAL(10,2) NULL,
                currency CHAR(3) NULL,
                booked_via VARCHAR(100) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_reservations_guest FOREIGN KEY (guest_id) REFERENCES guests(id),
                CONSTRAINT fk_reservations_rate_plan FOREIGN KEY (rate_plan_id) REFERENCES rate_plans(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'reservation_rooms' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reservation_rooms (
                reservation_id BIGINT UNSIGNED NOT NULL,
                room_id BIGINT UNSIGNED NOT NULL,
                nightly_rate DECIMAL(10,2) NULL,
                currency CHAR(3) NULL,
                PRIMARY KEY (reservation_id, room_id),
                CONSTRAINT fk_reservation_rooms_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                CONSTRAINT fk_reservation_rooms_room FOREIGN KEY (room_id) REFERENCES rooms(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'reservation_room_requests' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reservation_room_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id BIGINT UNSIGNED NOT NULL,
                room_type_id BIGINT UNSIGNED NOT NULL,
                quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_reservation_room_requests_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                CONSTRAINT fk_reservation_room_requests_type FOREIGN KEY (room_type_id) REFERENCES room_types(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'reservation_articles' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reservation_articles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id BIGINT UNSIGNED NOT NULL,
                article_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                charge_scheme ENUM('per_person_per_day','per_room_per_day','per_stay','per_person','per_day') NOT NULL,
                multiplier DECIMAL(10,2) NOT NULL DEFAULT 1,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 19.00,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_reservation_articles_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                CONSTRAINT fk_reservation_articles_article FOREIGN KEY (article_id) REFERENCES articles(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'invoices' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS invoices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id BIGINT UNSIGNED NOT NULL,
                invoice_number VARCHAR(100) NOT NULL UNIQUE,
                issue_date DATE NOT NULL,
                due_date DATE NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                tax_amount DECIMAL(10,2) NULL,
                status ENUM('draft','issued','paid','void') NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_invoices_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'payments' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id BIGINT UNSIGNED NOT NULL,
                method VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                paid_at DATETIME NULL,
                reference VARCHAR(150) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'service_orders' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS service_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id BIGINT UNSIGNED NULL,
                room_id BIGINT UNSIGNED NULL,
                service_type VARCHAR(100) NOT NULL,
                status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
                scheduled_at DATETIME NULL,
                completed_at DATETIME NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_service_orders_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
                CONSTRAINT fk_service_orders_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'invoice_items' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS invoice_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(5,2) NULL,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'reservation_documents' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reservation_documents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id BIGINT UNSIGNED NOT NULL,
                document_type VARCHAR(100) NOT NULL,
                file_name VARCHAR(255) NULL,
                file_path VARCHAR(255) NULL,
                metadata JSON NULL,
                uploaded_by BIGINT UNSIGNED NULL,
                uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_reservation_documents_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                CONSTRAINT fk_reservation_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'reservation_status_logs' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS reservation_status_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id BIGINT UNSIGNED NOT NULL,
                status ENUM('tentative','confirmed','checked_in','paid','checked_out','cancelled','no_show') NOT NULL,
                notes TEXT NULL,
                recorded_by BIGINT UNSIGNED NULL,
                recorded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_reservation_status_logs_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                CONSTRAINT fk_reservation_status_logs_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'housekeeping_logs' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS housekeeping_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_id BIGINT UNSIGNED NOT NULL,
                status ENUM('clean','dirty','in_progress','out_of_order','available','in_cleaning','occupied','maintenance') NOT NULL,
                notes TEXT NULL,
                recorded_by BIGINT UNSIGNED NULL,
                recorded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_housekeeping_logs_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
                CONSTRAINT fk_housekeeping_logs_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'tasks' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS tasks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_id BIGINT UNSIGNED NULL,
                assigned_to BIGINT UNSIGNED NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
                due_date DATETIME NULL,
                completed_at DATETIME NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                CONSTRAINT fk_tasks_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
                CONSTRAINT fk_tasks_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
        'audit_logs' => <<<'SQL'
            CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                action VARCHAR(150) NOT NULL,
                auditable_type VARCHAR(150) NOT NULL,
                auditable_id BIGINT UNSIGNED NULL,
                changes JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP NULL,
                CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL,
    ];
}

/**
 * Output helper compatible with CLI or browser execution.
 */
function respond(string $message, bool $isError = false, array $log = []): void
{
    $status = $isError ? 'error' : 'success';

    if (PHP_SAPI === 'cli') {
        foreach ($log as $line) {
            echo $line, PHP_EOL;
        }
        fwrite($isError ? STDERR : STDOUT, $message . PHP_EOL);
        exit($isError ? 1 : 0);
    }

    header('Content-Type: text/html; charset=utf-8');
    http_response_code($isError ? 500 : 200);

    $title = $isError ? 'Installation fehlgeschlagen' : 'Installation erfolgreich';
    $statusLabel = $isError ? 'Fehler' : 'Erfolg';
    $escapedMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $generatedAt = date('d.m.Y H:i');

    $logItems = '';
    if (!empty($log)) {
        $logItems .= '<ul class="steps">';
        foreach ($log as $line) {
            $logItems .= sprintf(
                '<li>%s</li>',
                htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }
        $logItems .= '</ul>';
    } else {
        $logItems = '<p class="no-steps">Es wurden keine weiteren Schritte protokolliert.</p>';
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 0; color: #111827; }
        main { max-width: 680px; margin: 48px auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); }
        h1 { margin-top: 0; font-size: 1.75rem; }
        .status { display: inline-block; padding: 6px 14px; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; }
        .status.success { background: #dcfce7; color: #166534; }
        .status.error { background: #fee2e2; color: #b91c1c; }
        .message { font-size: 1rem; margin-bottom: 24px; }
        .steps { list-style: none; padding-left: 0; margin: 0; }
        .steps li { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; background: #f9fafb; }
        .steps li:last-child { margin-bottom: 0; }
        .no-steps { margin: 0; color: #6b7280; }
        footer { margin-top: 32px; font-size: 0.85rem; color: #6b7280; }
    </style>
</head>
<body>
    <main>
        <span class="status {$status}">{$statusLabel}</span>
        <h1>{$title}</h1>
        <p class="message">{$escapedMessage}</p>
        {$logItems}
        <footer>
            realPMS Installationsskript &middot; Stand: {$generatedAt}
        </footer>
    </main>
</body>
</html>
HTML;

    echo $html;
    exit;
}
