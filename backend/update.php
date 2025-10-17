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

    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
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
