<?php

declare(strict_types=1);

/**
 * Lightweight update endpoint to pull the latest changes for the current git branch.
 *
 * The script requires a secret token that must be provided either as the first CLI
 * argument or as a `token` query parameter when called via HTTP. Set the secret
 * using the `PMS_UPDATE_SECRET` environment variable.
 */

if (PHP_SAPI === 'cli') {
    $providedToken = $argv[1] ?? null;
} else {
    header('Content-Type: application/json');
    $providedToken = $_GET['token'] ?? null;
}

$expectedToken = getenv('PMS_UPDATE_SECRET');
if ($expectedToken === false || $expectedToken === '') {
    respond(false, 'Missing PMS_UPDATE_SECRET environment variable. Define it to enable updates.');
}

if ($providedToken === null || !hash_equals($expectedToken, $providedToken)) {
    respond(false, 'Invalid or missing update token.', 401);
}

$repositoryPath = realpath(__DIR__ . '/..');
if ($repositoryPath === false) {
    respond(false, 'Unable to resolve repository path.');
}

$outputLog = [];

$gitAvailable = is_dir($repositoryPath . '/.git')
    && function_exists('proc_open')
    && function_exists('proc_close');

if ($gitAvailable) {
    chdir($repositoryPath);

    [$gitVersionExit, $gitVersionOutput] = runCommand('git --version');
    $outputLog[] = ['Git availability', 'git --version', $gitVersionExit, $gitVersionOutput];

    if ($gitVersionExit === 0) {
        $commands = [
            ['git status --short', 'Repository status'],
            ['git rev-parse --abbrev-ref HEAD', 'Current branch'],
        ];

        $branch = null;

        foreach ($commands as [$command, $label]) {
            [$exitCode, $output] = runCommand($command);
            $outputLog[] = [$label, $command, $exitCode, $output];
            if ($exitCode !== 0) {
                respond(false, sprintf('Command failed: %s', $command), 500, $outputLog);
            }

            if ($label === 'Current branch') {
                $branch = trim($output);
            }
        }

        if ($branch === null || $branch === '') {
            respond(false, 'Could not determine the current branch.', 500, $outputLog);
        }

        $pullCommand = sprintf('git pull origin %s', escapeshellarg($branch));
        [$pullExitCode, $pullOutput] = runCommand($pullCommand);
        $outputLog[] = ['Pull latest changes', $pullCommand, $pullExitCode, $pullOutput];

        if ($pullExitCode !== 0) {
            respond(false, 'Update failed. Review the output for details.', 500, $outputLog);
        }

        respond(true, 'Repository updated successfully via git pull.', 200, $outputLog);
    }

    $gitAvailable = $gitVersionExit === 0;
}

$repoSlug = getenv('PMS_UPDATE_REPO_SLUG') ?: 'rinkelzz/realpms';
$branch = getenv('PMS_UPDATE_BRANCH') ?: 'main';

$outputLog[] = ['Git pull fallback', null, null, sprintf('Falling back to archive download (%s@%s)', $repoSlug, $branch)];
$archiveUrl = getenv('PMS_UPDATE_ARCHIVE_URL') ?: sprintf(
    'https://codeload.github.com/%s/zip/refs/heads/%s',
    $repoSlug,
    rawurlencode($branch)
);

if (!class_exists('ZipArchive')) {
    respond(false, 'ZipArchive extension is required for archive updates.', 500, $outputLog);
}

$tempArchive = tempnam(sys_get_temp_dir(), 'realpms_update_');
if ($tempArchive === false) {
    respond(false, 'Could not create temporary file for archive download.', 500, $outputLog);
}

$downloadContext = stream_context_create([
    'http' => ['timeout' => 60],
    'https' => ['timeout' => 60],
]);

$archiveData = @file_get_contents($archiveUrl, false, $downloadContext);
if ($archiveData === false) {
    respond(false, sprintf('Failed to download archive from %s', $archiveUrl), 500, $outputLog);
}

if (file_put_contents($tempArchive, $archiveData) === false) {
    respond(false, 'Failed to write archive to temporary location.', 500, $outputLog);
}

$zip = new ZipArchive();
if ($zip->open($tempArchive) !== true) {
    respond(false, 'Could not open downloaded archive.', 500, $outputLog);
}

$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'realpms_extract_' . uniqid();
if (!mkdir($extractDir, 0775) && !is_dir($extractDir)) {
    $zip->close();
    respond(false, 'Failed to create extraction directory.', 500, $outputLog);
}

$rootEntryName = $zip->getNameIndex(0);

if ($zip->extractTo($extractDir) === false) {
    $zip->close();
    respond(false, 'Failed to extract archive contents.', 500, $outputLog);
}

$zip->close();
$extractedRoot = $rootEntryName !== false
    ? rtrim($extractDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . rtrim($rootEntryName, "\/")
    : $extractDir;

if (!is_dir($extractedRoot)) {
    $extractedRoot = $extractDir;
}

try {
    recursiveCopy($extractedRoot, $repositoryPath);
} catch (RuntimeException $exception) {
    @unlink($tempArchive);
    deleteDirectory($extractDir);
    respond(false, $exception->getMessage(), 500, $outputLog);
}

$outputLog[] = ['Archive update', $archiveUrl, 0, 'Files synchronized from downloaded archive'];

@unlink($tempArchive);
deleteDirectory($extractDir);

respond(true, 'Repository updated successfully via archive download.', 200, $outputLog);

function runCommand(string $command): array
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes);

    if (!is_resource($process)) {
        return [1, 'Could not execute command.'];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    foreach ($pipes as $pipe) {
        fclose($pipe);
    }

    $exitCode = proc_close($process);
    $output = trim($stdout . ($stderr !== '' ? PHP_EOL . $stderr : ''));

    return [$exitCode, $output];
}

function respond(bool $success, string $message, int $status = 200, array $log = []): void
{
    $payload = [
        'success' => $success,
        'message' => $message,
        'log' => array_map(
            static fn ($entry) => [
                'label' => $entry[0] ?? null,
                'command' => $entry[1] ?? null,
                'exit_code' => $entry[2] ?? null,
                'output' => $entry[3] ?? null,
            ],
            $log,
        ),
    ];

    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL);
        exit($success ? 0 : 1);
    }

    $format = determineResponseFormat();
    http_response_code($status);

    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo renderHtmlResponse($payload);
    exit;
}

function recursiveCopy(string $source, string $destination): void
{
    if (is_dir($source)) {
        if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $destination));
        }

        $items = scandir($source);
        if ($items === false) {
            throw new RuntimeException(sprintf('Failed to read directory: %s', $source));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $item;
            $destPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($srcPath)) {
                recursiveCopy($srcPath, $destPath);
            } else {
                if (!copy($srcPath, $destPath)) {
                    throw new RuntimeException(sprintf('Failed to copy %s to %s', $srcPath, $destPath));
                }
            }
        }

        return;
    }

    if (!copy($source, $destination)) {
        throw new RuntimeException(sprintf('Failed to copy %s to %s', $source, $destination));
    }
}

function deleteDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $target = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($target)) {
            deleteDirectory($target);
        } else {
            @unlink($target);
        }
    }

    @rmdir($path);
}

function determineResponseFormat(): string
{
    if (isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json') {
        return 'json';
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if ($accept === '') {
        return 'html';
    }

    if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
        return 'json';
    }

    return 'html';
}

function renderHtmlResponse(array $payload): string
{
    $success = $payload['success'] ?? false;
    $message = htmlspecialchars((string) ($payload['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $statusClass = $success ? 'status-success' : 'status-error';
    $statusLabel = $success ? 'Erfolg' : 'Fehler';

    try {
        $timestamp = (new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('d.m.Y H:i:s');
    } catch (Throwable $exception) {
        $timestamp = date('d.m.Y H:i:s');
    }

    $logRows = '';
    foreach (($payload['log'] ?? []) as $index => $entry) {
        $label = ($entry['label'] ?? '') !== ''
            ? (string) $entry['label']
            : sprintf('Schritt %d', $index + 1);
        $step = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $command = htmlspecialchars((string) ($entry['command'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $exitCode = $entry['exit_code'] ?? null;
        $exitCodeText = $exitCode === null || $exitCode === ''
            ? '—'
            : htmlspecialchars((string) $exitCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $output = htmlspecialchars((string) ($entry['output'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $outputHtml = $output !== ''
            ? '<pre>' . nl2br($output) . '</pre>'
            : '<span class="muted">Keine Ausgabe</span>';

        $logRows .= sprintf(
            '<tr><td>%s</td><td><code>%s</code></td><td class="exit-code">%s</td><td>%s</td></tr>',
            $step,
            $command,
            $exitCodeText,
            $outputHtml,
        );
    }

    if ($logRows === '') {
        $logRows = '<tr><td colspan="4" class="muted">Keine Protokolleinträge vorhanden.</td></tr>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System-Update · realPMS</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: "Inter", "Segoe UI", system-ui, sans-serif;
            --surface: #ffffff;
            --surface-alt: #f8fafc;
            --border: #d1d5db;
            --muted: #64748b;
            --success: #16a34a;
            --error: #dc2626;
        }

        body {
            margin: 0;
            padding: 2rem 1.25rem;
            background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
            color: #0f172a;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            background: var(--surface);
            border-radius: 1rem;
            box-shadow: 0 25px 45px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            border: 1px solid rgba(99, 102, 241, 0.15);
        }

        header {
            padding: 1.75rem 2rem 1.25rem;
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            color: #ffffff;
        }

        header h1 {
            margin: 0 0 .35rem;
            font-size: 1.75rem;
        }

        header p {
            margin: 0;
            opacity: 0.8;
        }

        main {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .status-card {
            border-radius: 1rem;
            padding: 1.5rem;
            background: var(--surface-alt);
            border: 1px solid var(--border);
        }

        .status-card h2 {
            margin: 0 0 .5rem;
        }

        .status-card p {
            margin: 0;
        }

        .status-card .timestamp {
            display: block;
            margin-top: .5rem;
            color: var(--muted);
            font-size: .9rem;
        }

        .status-card .status-success {
            color: var(--success);
            font-weight: 600;
        }

        .status-card .status-error {
            color: var(--error);
            font-weight: 600;
        }

        section h3 {
            margin: 0 0 .75rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: .75rem;
            border: 1px solid var(--border);
        }

        thead {
            background: #e0e7ff;
            color: #1e1b4b;
            text-align: left;
        }

        th,
        td {
            padding: .85rem 1rem;
            vertical-align: top;
            border-bottom: 1px solid var(--border);
            font-size: .95rem;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        code {
            background: rgba(15, 23, 42, 0.05);
            padding: .15rem .4rem;
            border-radius: .35rem;
            font-size: .85rem;
            display: inline-block;
        }

        pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: "JetBrains Mono", "Fira Code", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            background: rgba(15, 23, 42, 0.05);
            padding: .75rem;
            border-radius: .5rem;
            line-height: 1.4;
        }

        .muted {
            color: var(--muted);
        }

        .exit-code {
            width: 5rem;
            text-align: center;
            font-weight: 600;
        }

        footer {
            padding: 0 2rem 2rem;
            color: var(--muted);
            font-size: .85rem;
        }

        @media (max-width: 720px) {
            main {
                padding: 1.5rem;
            }

            th,
            td {
                padding: .75rem;
            }

            code {
                word-break: break-all;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>System-Update</h1>
            <p>Aktualisierungsbericht für realPMS</p>
        </header>
        <main>
            <section class="status-card">
                <h2>Status: <span class="$statusClass">$statusLabel</span></h2>
                <p>$message</p>
                <span class="timestamp">Ausgeführt am $timestamp</span>
            </section>
            <section>
                <h3>Protokoll</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Schritt</th>
                            <th>Befehl</th>
                            <th>Exit-Code</th>
                            <th>Ausgabe</th>
                        </tr>
                    </thead>
                    <tbody>
                        $logRows
                    </tbody>
                </table>
            </section>
        </main>
        <footer>
            realPMS · Automatisiertes Update-Skript
        </footer>
    </div>
</body>
</html>
HTML;
}
