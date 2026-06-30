<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy;

use FilesystemIterator;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Dhirimetaa\ProdDeploy\Support\Output;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

final class DeployKernel
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public function relativePath(string $absolutePath, string $root): string
    {
        $absolutePath = $this->normalizePath(realpath($absolutePath) ?: $absolutePath);
        $root = $this->normalizePath(realpath($root) ?: $root);
        $root = rtrim($root, '/');

        if (! str_starts_with($absolutePath, $root)) {
            return $this->normalizePath($absolutePath);
        }

        return ltrim(substr($absolutePath, strlen($root)), '/');
    }

    /** @return array<string, string> */
    public function loadEnvFile(string $path): array
    {
        if (! is_file($path)) {
            Output::fail("Missing config file: {$path}");
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }

        return $values;
    }

    /** @return array<string, string> */
    public function loadDeployEnv(): array
    {
        $path = $this->app->configDir().'/deploy.env';
        $values = $this->loadEnvFile($path);

        foreach (['PROD_SSH_HOST', 'PROD_SSH_USER', 'PROD_SSH_PORT', 'PROD_REMOTE_PATH'] as $required) {
            if (empty($values[$required])) {
                Output::fail("deploy/deploy.env is missing {$required}. Run prod-deploy init first.");
            }
        }

        return $values;
    }

    /** @return list<string> */
    public function loadExcludePatterns(string $filename): array
    {
        $path = $this->app->excludeFilePath($filename);
        if (! is_file($path)) {
            return [];
        }

        $patterns = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '!')) {
                continue;
            }
            $patterns[] = $line;
        }

        return $patterns;
    }

    public function pathMatchesExclude(string $relativePath, array $patterns): bool
    {
        $relativePath = $this->normalizePath($relativePath);

        foreach ($patterns as $pattern) {
            $pattern = $this->normalizePath($pattern);
            if (str_starts_with($pattern, '/')) {
                $pattern = ltrim($pattern, '/');
            }
            if ($pattern === '') {
                continue;
            }

            if (str_contains($pattern, '/')) {
                $dirPattern = rtrim($pattern, '/');
                if (str_ends_with($pattern, '/')) {
                    if (str_starts_with($relativePath, $dirPattern.'/') || $relativePath === $dirPattern) {
                        return true;
                    }
                }
                if (fnmatch($pattern, $relativePath, FNM_CASEFOLD)) {
                    return true;
                }
                continue;
            }

            if (fnmatch($pattern, $relativePath, FNM_CASEFOLD)
                || fnmatch($pattern, basename($relativePath), FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $command */
    public function runProcess(array $command, ?string $cwd = null, bool $allowFailure = false): int
    {
        $cmd = implode(' ', array_map(static fn (string $p): string => escapeshellarg($p), $command));
        Output::info("running {$cmd}".($cwd ? " (in {$cwd})" : ''));

        $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, $cwd ?? $this->app->projectRoot());
        if (! is_resource($process)) {
            Output::fail('Failed to start process.');
        }
        fclose($pipes[0]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 && ! $allowFailure) {
            Output::fail("Command failed with exit code {$exitCode}.");
        }

        return $exitCode;
    }

    /** @return list<string> */
    public function collectFiles(string $root, array $excludePatterns, string $excludePathPrefix = ''): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = $this->relativePath($file->getPathname(), $root);
            $excludeKey = $excludePathPrefix !== ''
                ? $this->normalizePath($excludePathPrefix.'/'.$relative)
                : $relative;

            if ($this->pathMatchesExclude($excludeKey, $excludePatterns)) {
                continue;
            }
            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    public function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            Output::fail("Could not create directory: {$path}");
        }
    }

    public function ensureStorageSkeleton(string $prodfiles): void
    {
        foreach ([
            'storage/app/public', 'storage/app/private',
            'storage/framework/cache/data', 'storage/framework/sessions',
            'storage/framework/views', 'storage/logs', 'bootstrap/cache',
        ] as $dir) {
            $this->ensureDirectory($prodfiles.'/'.$dir);
        }

        $logFile = $prodfiles.'/storage/logs/laravel.log';
        if (is_file($logFile)) {
            unlink($logFile);
        }
    }

    public function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @chmod($item->getPathname(), 0666);
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }

    public function resetDirectoryExcept(string $path, array $preserveTopLevel): void
    {
        if (! is_dir($path)) {
            $this->ensureDirectory($path);

            return;
        }

        $preserve = array_map(fn (string $n): string => trim($this->normalizePath($n), '/'), $preserveTopLevel);

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $preserve, true)) {
                continue;
            }
            $full = $path.'/'.$entry;
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }
    }

    public function copyFile(string $source, string $destination): void
    {
        $this->ensureDirectory(dirname($destination));
        if (! copy($source, $destination)) {
            Output::fail("Failed to copy {$source} to {$destination}");
        }
    }

    public function composerDependencyHash(): string
    {
        $root = $this->app->projectRoot();
        $json = $root.'/composer.json';
        $lock = $root.'/composer.lock';
        if (! is_file($json) || ! is_file($lock)) {
            Output::fail('composer.json and composer.lock are required.');
        }

        return hash('sha256', (string) file_get_contents($json).(string) file_get_contents($lock));
    }

    /** @return array<string, mixed> */
    public function loadBuildManifest(): array
    {
        $path = $this->app->buildManifestPath();
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function saveBuildManifest(): void
    {
        file_put_contents(
            $this->app->buildManifestPath(),
            json_encode([
                'built_at' => date('c'),
                'composer_hash' => $this->composerDependencyHash(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    public function vendorIsFresh(string $prodfiles): bool
    {
        if (! is_file($prodfiles.'/vendor/autoload.php')) {
            return false;
        }

        return ($this->loadBuildManifest()['composer_hash'] ?? '') === $this->composerDependencyHash();
    }

    /** @return list<string> */
    public function composerCommand(array $args): array
    {
        $candidates = [
            'C:/laragon/bin/composer/composer.phar',
            getenv('LOCALAPPDATA').'/ComposerSetup/bin/composer.phar',
            getenv('HOME').'/AppData/Local/ComposerSetup/bin/composer.phar',
        ];

        foreach ($candidates as $phar) {
            if ($phar && is_file($phar)) {
                return array_merge([PHP_BINARY, $phar], $args);
            }
        }

        return array_merge(['composer'], $args);
    }

    public function isVendorPath(string $relativePath): bool
    {
        return str_starts_with($this->normalizePath($relativePath), 'vendor/');
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    public function createVendorZip(string $prodfiles): string
    {
        if (! class_exists(ZipArchive::class)) {
            Output::fail('PHP zip extension (ext-zip) is required.');
        }

        $vendorDir = $prodfiles.'/vendor';
        if (! is_dir($vendorDir)) {
            Output::fail('prodfiles/vendor/ not found. Run prod-deploy build first.');
        }

        $zipPath = $this->app->vendorZipPath();
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            Output::fail('Could not create deploy/vendor.zip');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vendorDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = 'vendor/'.$this->relativePath($file->getPathname(), $vendorDir);
            if (! $zip->addFile($file->getPathname(), $relative)) {
                $zip->close();
                Output::fail("Could not add file to zip: {$relative}");
            }
        }

        if (! $zip->close()) {
            Output::fail('Could not finalize deploy/vendor.zip');
        }

        return $zipPath;
    }

    public function vendorExtractInstructions(string $remoteBase): string
    {
        return "cd {$remoteBase} && unzip -o vendor.zip && rm vendor.zip";
    }

    /** @return array{files: array<string, string>, uploaded_at?: string, remote_path?: string} */
    public function loadPushManifest(bool $full): array
    {
        $path = $this->app->pushManifestPath();
        if ($full || ! is_file($path)) {
            return ['files' => []];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! isset($decoded['files']) || ! is_array($decoded['files'])) {
            return ['files' => []];
        }

        return $decoded;
    }

    public function resolveRemoteBase(bool $dryRun): string
    {
        $remoteBase = '/home/cpanel_username/laravel';
        $envFile = $this->app->configDir().'/deploy.env';

        if (is_file($envFile)) {
            $remoteBase = $this->loadEnvFile($envFile)['PROD_REMOTE_PATH'] ?? $remoteBase;
        } elseif ($dryRun) {
            $example = $this->app->stubPath('deploy.env.example');
            if (is_file($example)) {
                $remoteBase = $this->loadEnvFile($example)['PROD_REMOTE_PATH'] ?? $remoteBase;
            }
        }

        return rtrim(str_replace('\\', '/', $remoteBase), '/');
    }

    /**
     * @param  'app'|'vendor'|'all'  $scope
     * @return array<string, string>
     */
    public function diffChangedFiles(
        string $prodfiles,
        array $excludePatterns,
        array $previousManifest,
        bool $full,
        string $scope
    ): array {
        $toUpload = [];

        foreach ($this->collectFiles($prodfiles, $excludePatterns) as $relative) {
            $isVendor = $this->isVendorPath($relative);
            if ($scope === 'app' && $isVendor) {
                continue;
            }
            if ($scope === 'vendor' && ! $isVendor) {
                continue;
            }

            $hash = md5_file($prodfiles.'/'.$relative);
            if ($hash === false) {
                Output::fail("Could not hash file: {$relative}");
            }

            if ($full || ! isset($previousManifest[$relative]) || $previousManifest[$relative] !== $hash) {
                $toUpload[$relative] = $hash;
            }
        }

        ksort($toUpload);

        return $toUpload;
    }

    /** @return array<string, string> */
    public function loadDeployEnvOrEmpty(): array
    {
        $path = $this->app->configDir().'/deploy.env';

        return is_file($path) ? $this->loadEnvFile($path) : [];
    }

    public function connectSftp(array $env): SFTP
    {
        $host = $env['PROD_SSH_HOST'];
        $port = (int) $env['PROD_SSH_PORT'];
        $user = $env['PROD_SSH_USER'];

        Output::info("Connecting to {$user}@{$host}:{$port}...");

        $sftp = new SFTP($host, $port);
        $sftp->setTimeout(120);

        if (! $this->login($sftp, $env, 'SFTP')) {
            Output::fail('SFTP login failed. Set PROD_SSH_PASSWORD or PROD_SSH_KEY in deploy/deploy.env.');
        }

        return $sftp;
    }

    public function connectSsh(array $env): SSH2
    {
        $host = $env['PROD_SSH_HOST'];
        $port = (int) $env['PROD_SSH_PORT'];
        $user = $env['PROD_SSH_USER'];

        Output::info("Connecting to {$user}@{$host}:{$port}...");

        $ssh = new SSH2($host, $port);
        $ssh->setTimeout(120);

        if (! $this->login($ssh, $env, 'SSH')) {
            Output::fail('SSH login failed. Set PROD_SSH_PASSWORD or PROD_SSH_KEY in deploy/deploy.env.');
        }

        return $ssh;
    }

    private function login(SFTP|SSH2 $client, array $env, string $label): bool
    {
        if (! empty($env['PROD_SSH_KEY']) && is_file($env['PROD_SSH_KEY'])) {
            $key = PublicKeyLoader::load((string) file_get_contents($env['PROD_SSH_KEY']));
            if ($client->login($env['PROD_SSH_USER'], $key)) {
                return true;
            }
        }

        if (isset($env['PROD_SSH_PASSWORD']) && $env['PROD_SSH_PASSWORD'] !== '') {
            if ($client->login($env['PROD_SSH_USER'], $env['PROD_SSH_PASSWORD'])) {
                return true;
            }
        }

        return false;
    }

    public function runRemoteShell(SSH2 $ssh, string $remotePath, string $shellCommand): int
    {
        $remotePath = rtrim(str_replace('\\', '/', $remotePath), '/');
        $output = $ssh->exec('cd '.escapeshellarg($remotePath).' && '.$shellCommand);

        if ($output !== '') {
            echo $output;
            if (! str_ends_with($output, "\n")) {
                echo PHP_EOL;
            }
        }

        $exitCode = $ssh->getExitStatus();

        return $exitCode === false ? 0 : (int) $exitCode;
    }

    /** @param list<string> $args */
    public function runRemoteArtisan(array $args): int
    {
        if ($args === []) {
            Output::fail('Usage: prod-deploy artisan <command> [options]');
        }

        $env = $this->loadDeployEnv();
        $remotePath = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');
        $artisanCommand = 'php artisan '.implode(' ', array_map('escapeshellarg', $args));

        Output::info('Running remote: php artisan '.implode(' ', $args));

        $ssh = $this->connectSsh($env);
        $exitCode = $this->runRemoteShell($ssh, $remotePath, $artisanCommand);

        if ($exitCode !== 0) {
            Output::fail("Remote command failed with exit code {$exitCode}.");
        }

        return 0;
    }

    /** @param list<string> $toUpload */
    public function uploadFiles(SFTP $sftp, string $prodfiles, string $remoteBase, array $toUpload): int
    {
        $total = count($toUpload);
        if ($total === 0) {
            return 0;
        }

        $uploaded = 0;
        foreach ($toUpload as $relative => $hash) {
            unset($hash);
            $localPath = $prodfiles.'/'.$relative;
            $remotePath = $remoteBase.'/'.str_replace('\\', '/', $relative);
            $remoteDir = dirname($remotePath);

            if ($remoteDir !== '.' && $remoteDir !== $remoteBase && ! $sftp->is_dir($remoteDir)) {
                if (! $sftp->mkdir($remoteDir, -1, true)) {
                    Output::fail("Could not create remote directory: {$remoteDir}");
                }
            }

            if (! $sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
                Output::fail("Upload failed: {$relative}");
            }

            $uploaded++;
            if ($uploaded === $total || $uploaded % 25 === 0) {
                Output::info("Uploaded {$uploaded}/{$total} item(s) (".max(0, $total - $uploaded).' remaining)...');
            }
        }

        return $uploaded;
    }

    /** @param array<string, string> $previousManifest @param array<string, string> $updates @param list<string> $localFiles */
    public function savePushManifest(
        array $previousManifest,
        array $updates,
        array $localFiles,
        string $remoteBase,
        ?string $note = null
    ): void {
        $manifest = [
            'uploaded_at' => date('c'),
            'remote_path' => $remoteBase,
            'files' => array_merge($previousManifest, $updates),
        ];

        if ($note !== null) {
            $manifest['last_note'] = $note;
        }

        foreach (array_keys($previousManifest) as $existing) {
            if (! in_array($existing, $localFiles, true)) {
                unset($manifest['files'][$existing]);
            }
        }

        file_put_contents(
            $this->app->pushManifestPath(),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /** @return array<string, string> */
    public function resolveTargetFileMap(
        string $relative,
        array $buildExcludes,
        bool $bypassPushExcludes
    ): array {
        $root = $this->app->projectRoot();
        $prodfiles = $this->app->prodfilesDir();
        $map = [];
        $source = $root.'/'.$relative;
        $staging = $prodfiles.'/'.$relative;

        $scanDirs = [];
        if (is_dir($source)) {
            $scanDirs[] = ['base' => $source, 'prefix' => $relative, 'excludes' => $buildExcludes];
        }
        if (is_dir($staging)) {
            $scanDirs[] = ['base' => $staging, 'prefix' => $relative, 'excludes' => []];
        }

        foreach ($scanDirs as $scan) {
            foreach ($this->collectFiles($scan['base'], $scan['excludes'], $scan['prefix']) as $fileRelative) {
                $fullRelative = $this->normalizePath($scan['prefix'].'/'.$fileRelative);
                $map[$fullRelative] = $scan['base'].'/'.$fileRelative;
            }
        }

        if (is_file($source)) {
            $map[$relative] = $source;
        } elseif (is_file($staging)) {
            $map[$relative] = $staging;
        }

        if ($bypassPushExcludes) {
            return $map;
        }

        foreach (array_keys($map) as $path) {
            if ($this->pathMatchesExclude($path, $this->loadExcludePatterns('exclude-push.txt'))) {
                unset($map[$path]);
            }
        }

        return $map;
    }

    /** @param array<string, string> $toUpload */
    public function dryRunList(array $toUpload): void
    {
        foreach (array_keys($toUpload) as $relative) {
            echo "  would upload: {$relative}".PHP_EOL;
        }
    }

    public function countVendorFiles(string $prodfiles, array $excludePatterns): int
    {
        $count = 0;
        foreach ($this->collectFiles($prodfiles, $excludePatterns) as $relative) {
            if ($this->isVendorPath($relative)) {
                $count++;
            }
        }

        return $count;
    }
}
