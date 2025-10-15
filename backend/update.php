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

$commands = [
    ['git status --short', 'Repository status'],
    ['git rev-parse --abbrev-ref HEAD', 'Current branch'],
];

chdir($repositoryPath);

$branch = null;
$outputLog = [];

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

respond(true, 'Repository updated successfully.', 200, $outputLog);

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

    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}
