<?php

declare(strict_types=1);

if (!extension_loaded('pdo_mysql')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDO MySQL extension is required.']);
    exit;
}

/**
 * Retrieve configuration values merged from env/.env/install config.
 */
function config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'host' => getEnvValue('DB_HOST', '127.0.0.1'),
        'port' => getEnvValue('DB_PORT', '3306'),
        'database' => getEnvValue('DB_DATABASE'),
        'username' => getEnvValue('DB_USERNAME'),
        'password' => getEnvValue('DB_PASSWORD'),
        'api_token' => getEnvValue('API_TOKEN'),
    ];

    $configPath = __DIR__ . '/../install.config.php';
    if (file_exists($configPath)) {
        /** @var array $fileConfig */
        $fileConfig = require $configPath;
        $config = array_merge($config, array_filter($fileConfig, static fn ($value) => $value !== null));
    }

    foreach (['host', 'port', 'database', 'username', 'password'] as $requiredKey) {
        if (($config[$requiredKey] ?? null) === null || $config[$requiredKey] === '') {
            throw new RuntimeException(sprintf('Missing configuration for %s', $requiredKey));
        }
    }

    return $config;
}

function getEnvValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    static $cachedEnv;
    if ($cachedEnv === null) {
        $cachedEnv = loadDotEnv(__DIR__ . '/../.env');
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

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        $GLOBALS['__realpms_pdo'] = $pdo;
        return $pdo;
    }

    $cfg = config();
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $GLOBALS['__realpms_pdo'] = $pdo;

    return $pdo;
}

function rollbackActiveTransaction(): void
{
    if (!isset($GLOBALS['__realpms_pdo']) || !$GLOBALS['__realpms_pdo'] instanceof PDO) {
        return;
    }

    /** @var PDO $pdo */
    $pdo = $GLOBALS['__realpms_pdo'];
    if ($pdo->inTransaction()) {
        if (isDebugLogEnabled()) {
            debugLog('Rolling back open PDO transaction before sending response.', [
                'pdo_object_id' => spl_object_id($pdo),
                'request' => [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                    'uri' => $_SERVER['REQUEST_URI'] ?? null,
                ],
                'backtrace' => debugBacktraceSummary(),
            ]);
        }

        try {
            $pdo->rollBack();
        } catch (Throwable $exception) {
            // Ignore rollback failures so the original response can continue.
        }
    }
}

function isDebugLogEnabled(): bool
{
    static $enabled;

    if ($enabled === null) {
        $flag = getEnvValue('REALPMS_DEBUG_LOG');
        if ($flag === null) {
            $flag = getenv('REALPMS_DEBUG_LOG');
        }

        if ($flag === false || $flag === null) {
            $enabled = false;
        } else {
            $flag = strtolower((string) $flag);
            $enabled = in_array($flag, ['1', 'true', 'yes', 'on'], true);
        }
    }

    return $enabled;
}

function debugLog(string $message, array $context = []): void
{
    if (!isDebugLogEnabled()) {
        return;
    }

    $payload = [
        'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        'pid' => getmypid(),
        'message' => $message,
    ];

    if ($context !== []) {
        $payload['context'] = $context;
    }

    error_log('[realPMS] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function debugBacktraceSummary(int $limit = 5): array
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $summary = [];

    foreach (array_slice($trace, 1, $limit) as $frame) {
        $location = ($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? '?');
        $callable = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        $summary[] = trim($location . ' ' . $callable);
    }

    return $summary;
}

function jsonResponse($payload, int $status = 200): void
{
    rollbackActiveTransaction();

    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    return $data;
}

function requireApiKey(): void
{
    $config = config();
    $expected = $config['api_token'] ?? null;
    if ($expected === null || $expected === '') {
        jsonResponse(['error' => 'API token is not configured. Set API_TOKEN in the environment or install.config.php.'], 500);
    }

    $provided = null;
    $headerSource = function_exists('getallheaders') ? getallheaders() : [];
    $headers = array_change_key_case($headerSource ?: []);
    if (isset($headers['x-api-key'])) {
        $provided = $headers['x-api-key'];
    } elseif (isset($_GET['token'])) {
        $provided = $_GET['token'];
    }

    if ($provided === null || !hash_equals($expected, (string) $provided)) {
        jsonResponse(['error' => 'Unauthorized.'], 401);
    }
}

function now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function validateDate(string $value): bool
{
    return (bool) DateTimeImmutable::createFromFormat('Y-m-d', $value);
}

function validateDateTime(string $value): bool
{
    return (bool) DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
}
