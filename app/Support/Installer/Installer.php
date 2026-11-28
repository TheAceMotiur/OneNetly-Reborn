<?php

declare(strict_types=1);

namespace App\Support\Installer;

use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Hash;
use PDO;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Shared engine behind public/install.php and the `app:update` command.
 *
 * The first run (no APP_KEY) installs the application. Every later run only
 * updates it: dependencies, migrations, caches and symlinks, behind maintenance
 * mode and after a database backup.
 */
final class Installer
{
    public const MODE_INSTALL = 'install';

    public const MODE_UPDATE = 'update';

    private ?Application $application;

    public function __construct(
        private readonly string $basePath,
        ?Application $application = null,
    ) {
        $this->application = $application;
    }

    public static function make(): self
    {
        $application = null;

        if (class_exists(Container::class)) {
            $instance = Container::getInstance();

            if ($instance instanceof Application) {
                $application = $instance;
            }
        }

        return new self(dirname(__DIR__, 3), $application);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function env(): EnvFile
    {
        return new EnvFile($this->basePath.'/.env');
    }

    public function hasApplicationKey(): bool
    {
        $key = $this->env()->get('APP_KEY');

        return is_string($key) && trim($key) !== '';
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath());
    }

    public function mode(): string
    {
        return $this->hasApplicationKey() ? self::MODE_UPDATE : self::MODE_INSTALL;
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function installSteps(): array
    {
        return [
            ['id' => 'preflight', 'label' => 'Check server', 'description' => 'Verify PHP, extensions and writable folders.'],
            ['id' => 'environment', 'label' => 'Save settings', 'description' => 'Write .env and test the database connection.'],
            ['id' => 'migrate', 'label' => 'Create tables', 'description' => 'Run all database migrations.'],
            ['id' => 'seed', 'label' => 'Install plans', 'description' => 'Seed the subscription plans.'],
            ['id' => 'admin', 'label' => 'Create administrator', 'description' => 'Create your admin account.'],
            ['id' => 'storage-link', 'label' => 'Link storage', 'description' => 'Publish uploaded files under /storage.'],
            ['id' => 'key', 'label' => 'Generate app key', 'description' => 'Create the encryption key for sessions and cookies.'],
            ['id' => 'finalize', 'label' => 'Finish', 'description' => 'Clear caches and write the install lock.'],
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function updateSteps(): array
    {
        return [
            ['id' => 'preflight', 'label' => 'Check server', 'description' => 'Verify requirements and the database connection.'],
            ['id' => 'backup', 'label' => 'Back up database', 'description' => 'Dump the database into storage/backups.'],
            ['id' => 'maintenance-on', 'label' => 'Maintenance mode on', 'description' => 'Show a maintenance page to visitors.'],
            ['id' => 'composer', 'label' => 'Composer install', 'description' => 'Install PHP dependencies when composer.lock changed.'],
            ['id' => 'migrate', 'label' => 'Run migrations', 'description' => 'Apply pending database migrations.'],
            ['id' => 'storage-link', 'label' => 'Link storage', 'description' => 'Refresh the public storage symlink.'],
            ['id' => 'optimize', 'label' => 'Rebuild caches', 'description' => 'Clear and rebuild config, route and view caches.'],
            ['id' => 'queue-restart', 'label' => 'Restart workers', 'description' => 'Signal queue workers to pick up new code.'],
            ['id' => 'maintenance-off', 'label' => 'Back online', 'description' => 'Disable maintenance mode.'],
            ['id' => 'finalize', 'label' => 'Finish', 'description' => 'Record this release and prune old backups.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $mode = $this->mode();
        $env = $this->env();
        $state = $this->state();

        return [
            'mode' => $mode,
            'installed' => $this->isLocked() && $this->hasApplicationKey(),
            'locked' => $this->isLocked(),
            'steps' => $mode === self::MODE_INSTALL ? $this->installSteps() : $this->updateSteps(),
            'requirements' => $this->requirements(),
            'php' => PHP_VERSION,
            'vendor' => is_file($this->basePath.'/vendor/autoload.php'),
            'envFile' => $env->exists(),
            'appName' => $env->get('APP_NAME') ?: 'OneNetly',
            'appUrl' => $env->get('APP_URL'),
            'appEnv' => $env->get('APP_ENV'),
            'build' => is_file($this->basePath.'/public/build/manifest.json'),
            'upToDate' => $mode === self::MODE_UPDATE && $this->isUpToDate(),
            'changes' => $mode === self::MODE_UPDATE ? $this->changes() : [],
            'maintenance' => is_file($this->basePath.'/storage/framework/down'),
            'installedAt' => $state['installed_at'] ?? null,
            'lastUpdate' => $state['last_update'] ?? null,
            'lockedFor' => 0,
        ];
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string, required: bool}>
     */
    public function requirements(): array
    {
        $checks = [];
        $checks[] = $this->check('PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION);

        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml', 'zip'] as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = $this->check('Extension: '.$extension, $loaded, $loaded ? 'loaded' : 'missing');
        }

        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache', 'public'] as $relative) {
            $path = $this->basePath.'/'.$relative;
            $ok = is_dir($path) && is_writable($path);
            $checks[] = $this->check(
                'Writable: '.$relative,
                $ok,
                $ok ? 'writable' : (is_dir($path) ? 'not writable' : 'missing'),
            );
        }

        $root = $this->basePath;
        $checks[] = $this->check(
            'Writable: project root (.env)',
            is_writable($root),
            is_writable($root) ? 'writable' : 'not writable',
        );

        $vendor = is_file($this->basePath.'/vendor/autoload.php');
        $checks[] = $this->check('Composer dependencies', $vendor, $vendor ? 'vendor/ found' : 'run composer install and upload vendor/');

        $build = is_file($this->basePath.'/public/build/manifest.json');
        $checks[] = [
            'label' => 'Compiled front-end assets',
            'ok' => $build,
            'detail' => $build ? 'public/build found' : 'upload public/build from your local build',
            'required' => false,
        ];

        return $checks;
    }

    /**
     * Execute a single step. Each HTTP request runs exactly one step so a slow
     * migration can never hit a PHP-FPM timeout.
     *
     * @param  array<string, mixed>  $input
     * @return array{message: string, output: string}
     */
    public function run(string $mode, string $step, array $input = []): array
    {
        $this->log("step {$step} (mode {$mode})");

        if ($mode === self::MODE_INSTALL) {
            if ($this->hasApplicationKey()) {
                throw new RuntimeException('This application already has an APP_KEY, so it is already installed. Reload the page to use update mode.');
            }

            return $this->installStep($step, $input);
        }

        if (! $this->hasApplicationKey()) {
            throw new RuntimeException('This application is not installed yet.');
        }

        return $this->updateStep($step);
    }

    public function verifyAdmin(string $email, string $password): bool
    {
        if ($email === '' || $password === '') {
            return false;
        }

        $this->bootstrapApplication();

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || $user->is_admin !== true) {
            return false;
        }

        return Hash::check($password, (string) $user->getAuthPassword());
    }

    /**
     * Bring the site back online after a failed update.
     *
     * @return array{message: string, output: string}
     */
    public function cancelMaintenance(): array
    {
        try {
            $this->runArtisan('up');
        } catch (Throwable) {
            @unlink($this->basePath.'/storage/framework/down');
        }

        $this->log('maintenance mode cancelled by an administrator');

        return ['message' => 'Maintenance mode is off. The site is reachable again.', 'output' => ''];
    }

    public function isUpToDate(): bool
    {
        $stored = $this->state()['fingerprint'] ?? null;

        return is_array($stored) && $stored === $this->fingerprint();
    }

    /**
     * @return list<string>
     */
    public function changes(): array
    {
        $current = $this->fingerprint();
        $stored = $this->state()['fingerprint'] ?? null;

        if (! is_array($stored)) {
            return ['No previous update record — this is the first update run.'];
        }

        $changes = [];

        foreach ([
            'composer.lock' => 'PHP dependencies changed (composer.lock)',
            'package.json' => 'Front-end dependencies changed (package.json)',
            'public/build/manifest.json' => 'Compiled front-end assets changed',
        ] as $key => $label) {
            if (($stored[$key] ?? null) !== ($current[$key] ?? null)) {
                $changes[] = $label;
            }
        }

        if (($stored['migrations'] ?? null) !== ($current['migrations'] ?? null)) {
            $changes[] = 'Database migrations changed ('.$current['migrations'].' files)';
        }

        return $changes === [] ? ['No file changes since the last update.'] : $changes;
    }

    public function log(string $message): void
    {
        $directory = $this->basePath.'/storage/logs';

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $actor = isset($_SERVER['REMOTE_ADDR']) ? ' ip='.$_SERVER['REMOTE_ADDR'] : '';
        $line = '['.date('Y-m-d H:i:s').']'.$actor.' '.$message.PHP_EOL;

        @file_put_contents($directory.'/install.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{message: string, output: string}
     */
    private function installStep(string $step, array $input): array
    {
        return match ($step) {
            'preflight' => $this->installPreflight(),
            'environment' => $this->installEnvironment($input),
            'migrate' => $this->installMigrate(),
            'seed' => $this->installSeed(),
            'admin' => $this->installAdmin($input),
            'storage-link' => $this->installStorageLink(),
            'key' => $this->installKey(),
            'finalize' => $this->installFinalize(),
            default => throw new RuntimeException("Unknown install step [{$step}]."),
        };
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateStep(string $step): array
    {
        return match ($step) {
            'preflight' => $this->updatePreflight(),
            'backup' => $this->updateBackup(),
            'maintenance-on' => $this->updateMaintenanceOn(),
            'composer' => $this->updateComposer(),
            'migrate' => $this->updateMigrate(),
            'storage-link' => $this->updateStorageLink(),
            'optimize' => $this->updateOptimize(),
            'queue-restart' => $this->updateQueueRestart(),
            'maintenance-off' => $this->updateMaintenanceOff(),
            'finalize' => $this->updateFinalize(),
            default => throw new RuntimeException("Unknown update step [{$step}]."),
        };
    }

    /**
     * @return array{message: string, output: string}
     */
    private function installPreflight(): array
    {
        $this->ensureDirectories();

        $failed = array_values(array_filter(
            $this->requirements(),
            static fn (array $check): bool => $check['required'] && ! $check['ok'],
        ));

        if ($failed !== []) {
            throw new RuntimeException('Fix these before continuing: '.implode(', ', array_column($failed, 'label')).'.');
        }

        return ['message' => 'Server requirements satisfied.', 'output' => 'PHP '.PHP_VERSION];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{message: string, output: string}
     */
    private function installEnvironment(array $input): array
    {
        $name = trim((string) ($input['app_name'] ?? '')) ?: 'OneNetly';
        $url = rtrim(trim((string) ($input['app_url'] ?? '')), '/');

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $url) !== 1) {
            throw new RuntimeException('Enter your full site URL, for example https://example.com');
        }

        $driver = strtolower(trim((string) ($input['db_connection'] ?? 'mysql')));

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported database driver. Choose MySQL or SQLite.');
        }

        $host = trim((string) ($input['db_host'] ?? '127.0.0.1')) ?: '127.0.0.1';
        $port = trim((string) ($input['db_port'] ?? '')) ?: ($driver === 'pgsql' ? '5432' : '3306');
        $database = trim((string) ($input['db_database'] ?? ''));
        $username = trim((string) ($input['db_username'] ?? ''));
        $password = (string) ($input['db_password'] ?? '');

        if ($driver === 'sqlite') {
            $database = $database !== '' ? $database : $this->basePath.'/database/database.sqlite';

            if (! str_starts_with($database, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $database) !== 1) {
                $database = $this->basePath.'/'.ltrim($database, '/\\');
            }

            if (! is_dir(dirname($database))) {
                @mkdir(dirname($database), 0775, true);
            }

            if (! is_file($database)) {
                @touch($database);
            }

            $username = '';
            $password = '';
            $host = '';
            $port = '';
        }

        if ($driver !== 'sqlite' && $database === '') {
            throw new RuntimeException('Enter the database name.');
        }

        $this->assertDatabaseReachable($driver, $host, $port, $database, $username, $password);

        $env = $this->env();
        $env->createFrom($this->basePath.'/.env.production.example');
        $env->createFrom($this->basePath.'/.env.example');

        $values = [
            'APP_NAME' => $name,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $url,
            'DB_CONNECTION' => $driver === 'mariadb' ? 'mysql' : $driver,
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'LOG_LEVEL' => 'warning',
        ];

        if ($driver === 'sqlite') {
            $values['DB_DATABASE'] = $database;
            $values['DB_HOST'] = '';
            $values['DB_PORT'] = '';
            $values['DB_USERNAME'] = '';
            $values['DB_PASSWORD'] = '';
        } else {
            $values['DB_HOST'] = $host;
            $values['DB_PORT'] = $port;
            $values['DB_DATABASE'] = $database;
            $values['DB_USERNAME'] = $username;
            $values['DB_PASSWORD'] = $password;
        }

        $env->set($values);

        // A template placeholder is not a real key: keep the app uninstalled so
        // the wizard can be retried if a later step fails.
        $key = (string) $env->get('APP_KEY', '');
        if ($key !== '' && preg_match('/^base64:.{40,}$/', $key) !== 1) {
            $env->set(['APP_KEY' => '']);
        }

        $this->clearCachedConfig();

        $this->log('wrote .env for '.$url.' using '.$driver);

        $detail = $driver === 'sqlite'
            ? 'Database: SQLite at '.$database
            : 'Database: '.$database.' on '.$host.':'.$port;

        return [
            'message' => 'Settings saved. '.$detail.'.',
            'output' => $detail.PHP_EOL.'URL: '.$url,
        ];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function installMigrate(): array
    {
        $output = $this->runArtisan('migrate', ['--force' => true]);

        return ['message' => 'Database tables are up to date.', 'output' => $output];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function installSeed(): array
    {
        $output = $this->runArtisan('db:seed', ['--class' => 'PlanSeeder', '--force' => true]);

        return ['message' => 'Subscription plans installed.', 'output' => $output];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{message: string, output: string}
     */
    private function installAdmin(array $input): array
    {
        $name = trim((string) ($input['admin_name'] ?? '')) ?: 'Administrator';
        $email = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $password = (string) ($input['admin_password'] ?? '');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Enter a valid administrator email address.');
        }

        $this->assertStrongPassword($password);

        $this->bootstrapApplication();

        $user = User::query()->where('email', $email)->first() ?? new User;

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'terms_accepted_at' => $user->terms_accepted_at ?? now(),
        ])->save();

        $this->log('administrator ready: '.$email);

        return [
            'message' => 'Administrator '.$email.' is ready.',
            'output' => 'User #'.$user->getKey().' has administrator access.',
        ];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function installStorageLink(): array
    {
        try {
            $output = $this->runArtisan('storage:link', ['--force' => true]);
        } catch (Throwable $exception) {
            return [
                'message' => 'Storage link skipped: '.$exception->getMessage(),
                'output' => 'Create the symlink manually from public/storage to ../storage/app/public.',
            ];
        }

        return ['message' => 'Storage link is ready.', 'output' => $output];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function installKey(): array
    {
        $output = $this->runArtisan('key:generate', ['--force' => true]);

        if (! $this->hasApplicationKey()) {
            throw new RuntimeException('APP_KEY was not written. Check that .env is writable by the web server.');
        }

        return ['message' => 'Application key generated.', 'output' => $output];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function installFinalize(): array
    {
        $now = date('c');
        $state = $this->state();

        $this->writeState([
            'installed_at' => $state['installed_at'] ?? $now,
            'last_update' => $now,
            'fingerprint' => $this->fingerprint(),
        ]);

        @file_put_contents($this->lockPath(), json_encode([
            'installed_at' => $now,
            'php' => PHP_VERSION,
        ], JSON_PRETTY_PRINT));

        try {
            $this->runArtisan('optimize:clear');
        } catch (Throwable $exception) {
            $this->log('optimize:clear failed: '.$exception->getMessage());
        }

        $this->log('installation finished');

        return [
            'message' => 'Installation complete. Delete install.php or keep it — it now only performs updates behind an admin login.',
            'output' => 'Installed at '.$now,
        ];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updatePreflight(): array
    {
        $this->ensureDirectories();

        $failed = array_values(array_filter(
            $this->requirements(),
            static fn (array $check): bool => $check['required'] && ! $check['ok'],
        ));

        if ($failed !== []) {
            throw new RuntimeException('Fix these before continuing: '.implode(', ', array_column($failed, 'label')).'.');
        }

        $status = $this->runArtisan('migrate:status');

        return ['message' => 'Server is ready to update.', 'output' => $status];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateBackup(): array
    {
        $directory = $this->basePath.'/storage/backups';

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create storage/backups.');
        }

        $stamp = date('Ymd-His');
        $driver = (string) $this->env()->get('DB_CONNECTION', 'mysql');
        $notes = [];

        $envPath = $this->basePath.'/.env';
        if (is_file($envPath)) {
            @copy($envPath, $directory.'/env-'.$stamp.'.bak');
            $notes[] = 'env-'.$stamp.'.bak';
        }

        if ($driver === 'sqlite') {
            $database = (string) $this->env()->get('DB_DATABASE', $this->basePath.'/database/database.sqlite');

            if (! str_starts_with($database, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $database) !== 1) {
                $database = $this->basePath.'/'.ltrim($database, '/\\');
            }

            if (is_file($database) && @copy($database, $directory.'/database-'.$stamp.'.sqlite')) {
                $notes[] = 'database-'.$stamp.'.sqlite';
            } else {
                $notes[] = 'SQLite file could not be copied';
            }
        } else {
            $file = $driver === 'pgsql' ? 'pg_dump' : 'mysqldump';
            $binary = $this->findBinary($file);

            if ($binary === null) {
                $notes[] = $file.' is not available on this server — no SQL dump was taken';
            } else {
                $target = $directory.'/database-'.$stamp.($driver === 'pgsql' ? '.sql' : '.sql');
                $notes[] = $this->dumpDatabase($driver, $binary, $target)
                    ? basename($target)
                    : $file.' failed — no SQL dump was taken';
            }
        }

        $this->pruneBackups($directory, 5);

        $this->log('backup: '.implode(', ', $notes));

        return [
            'message' => 'Backup finished: '.implode(', ', $notes).'.',
            'output' => 'Backups live in storage/backups.',
        ];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateMaintenanceOn(): array
    {
        try {
            $output = $this->runArtisan('down');
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not enable maintenance mode: '.$exception->getMessage());
        }

        return ['message' => 'Maintenance mode is on.', 'output' => $output];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateComposer(): array
    {
        $lock = $this->basePath.'/composer.lock';
        $installed = $this->basePath.'/vendor/composer/installed.json';

        if (! is_file($installed)) {
            throw new RuntimeException('vendor/ is missing. Run composer install and upload the vendor directory.');
        }

        if (is_file($lock) && filemtime($lock) <= filemtime($installed)) {
            return ['message' => 'PHP dependencies already match composer.lock.', 'output' => ''];
        }

        $command = null;
        $composer = $this->findBinary('composer');

        if ($composer !== null) {
            $command = [$composer];
        } elseif (is_file($this->basePath.'/composer.phar')) {
            $command = [PHP_BINARY, $this->basePath.'/composer.phar'];
        }

        if ($command === null) {
            return [
                'message' => 'Composer is not available on the server, so dependencies were not installed.',
                'output' => 'Upload the vendor/ folder built on your machine, or run composer install over SSH.',
            ];
        }

        $output = $this->runProcess([
            ...$command,
            'install',
            '--no-dev',
            '--no-interaction',
            '--prefer-dist',
            '--no-progress',
            '--optimize-autoloader',
        ], 900);

        return ['message' => 'PHP dependencies installed.', 'output' => $output];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateMigrate(): array
    {
        $output = $this->runArtisan('migrate', ['--force' => true]);

        return [
            'message' => str_contains($output, 'Nothing to migrate') ? 'No pending migrations.' : 'Migrations applied.',
            'output' => $output,
        ];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateStorageLink(): array
    {
        try {
            $output = $this->runArtisan('storage:link', ['--force' => true]);

            return ['message' => 'Storage link refreshed.', 'output' => $output];
        } catch (Throwable $exception) {
            return ['message' => 'Storage link skipped: '.$exception->getMessage(), 'output' => ''];
        }
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateOptimize(): array
    {
        $output = $this->runArtisan('optimize:clear');

        try {
            $output .= PHP_EOL.$this->runArtisan('optimize');

            return ['message' => 'Caches rebuilt.', 'output' => trim($output)];
        } catch (Throwable $exception) {
            $this->log('optimize failed: '.$exception->getMessage());

            return [
                'message' => 'Caches cleared, but rebuilding them failed: '.$exception->getMessage(),
                'output' => trim($output),
            ];
        }
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateQueueRestart(): array
    {
        try {
            $output = $this->runArtisan('queue:restart');

            return ['message' => 'Queue workers will restart after their current job.', 'output' => $output];
        } catch (Throwable $exception) {
            return ['message' => 'Queue restart skipped: '.$exception->getMessage(), 'output' => ''];
        }
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateMaintenanceOff(): array
    {
        $output = $this->runArtisan('up');

        return ['message' => 'The site is back online.', 'output' => $output];
    }

    /**
     * @return array{message: string, output: string}
     */
    private function updateFinalize(): array
    {
        $now = date('c');
        $state = $this->state();
        $installedAt = $state['installed_at'] ?? $now;

        $this->writeState([
            'installed_at' => $installedAt,
            'last_update' => $now,
            'fingerprint' => $this->fingerprint(),
        ]);

        @file_put_contents($this->lockPath(), json_encode([
            'installed_at' => $installedAt,
            'last_update' => $now,
        ], JSON_PRETTY_PRINT));

        $this->pruneBackups($this->basePath.'/storage/backups', 5);

        $this->log('update finished');

        return ['message' => 'Update complete.', 'output' => 'Updated at '.$now];
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprint(): array
    {
        $print = [];

        foreach (['composer.lock', 'package.json', 'public/build/manifest.json'] as $relative) {
            $path = $this->basePath.'/'.$relative;
            $print[$relative] = is_file($path) ? substr((string) hash_file('sha256', $path), 0, 16) : null;
        }

        $migrations = glob($this->basePath.'/database/migrations/*.php') ?: [];
        sort($migrations);

        $print['migrations'] = count($migrations);
        $print['latest_migration'] = $migrations === [] ? null : basename((string) end($migrations));

        return $print;
    }

    private function ensureDirectories(): void
    {
        foreach ([
            'storage',
            'storage/app',
            'storage/app/public',
            'storage/framework',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'storage/backups',
            'bootstrap/cache',
        ] as $relative) {
            $path = $this->basePath.'/'.$relative;

            if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
                throw new RuntimeException("Could not create [{$relative}]. Create it manually and give the web server write access.");
            }
        }
    }

    private function assertDatabaseReachable(
        string $driver,
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
    ): void {
        $dsn = match ($driver) {
            'sqlite' => 'sqlite:'.$database,
            'pgsql' => 'pgsql:host='.$host.';port='.$port.';dbname='.$database,
            default => 'mysql:host='.$host.';port='.$port.';dbname='.$database,
        };

        try {
            new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $exception) {
            $message = $exception->getMessage();

            if ($driver === 'mysql' && str_contains($message, 'Unknown database')) {
                throw new RuntimeException('The database "'.$database.'" does not exist. Create it in HestiaCP, then try again.');
            }

            throw new RuntimeException(
                'Could not connect to the '.$driver.' database: '.$message.PHP_EOL
                .'Check the host, database name, user and password from HestiaCP. Passwords with # or spaces must match exactly.',
            );
        }
    }

    private function assertStrongPassword(string $password): void
    {
        if (strlen($password) < 12
            || preg_match('/[A-Za-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
            || preg_match('/[A-Z]/', $password) !== 1) {
            throw new RuntimeException('Use at least 12 characters with upper case, lower case and a number for the administrator password.');
        }
    }

    private function bootstrapApplication(): void
    {
        $this->consoleKernel()->bootstrap();
    }

    private function consoleKernel(): Kernel
    {
        return $this->application()->make(Kernel::class);
    }

    private function application(): Application
    {
        if ($this->application instanceof Application) {
            return $this->application;
        }

        $autoload = $this->basePath.'/vendor/autoload.php';

        if (! is_file($autoload)) {
            throw new RuntimeException('Composer dependencies are missing. Run composer install locally and upload the vendor/ directory.');
        }

        if (! class_exists(Application::class)) {
            require_once $autoload;
        }

        $this->clearCachedConfig();

        $application = require $this->basePath.'/bootstrap/app.php';

        if (! $application instanceof Application) {
            throw new RuntimeException('bootstrap/app.php did not return a Laravel application.');
        }

        return $this->application = $application;
    }

    private function clearCachedConfig(): void
    {
        foreach (glob($this->basePath.'/bootstrap/cache/*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(string $command, array $parameters = []): string
    {
        $kernel = $this->consoleKernel();
        $status = $kernel->call($command, $parameters);
        $output = trim((string) $kernel->output());

        $this->log('artisan '.$command.' exited with '.$status);

        if ($status !== 0) {
            throw new RuntimeException(
                '`php artisan '.$command.'` failed with exit code '.$status.'.'.PHP_EOL
                .($output !== '' ? $output : 'No output was returned.'),
            );
        }

        return $output;
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, int $timeout): string
    {
        $process = new Process($command, $this->basePath);
        $process->setTimeout($timeout);
        $process->run();

        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Command failed: '.implode(' ', array_slice($command, 0, 2)).PHP_EOL.$output,
            );
        }

        return $output;
    }

    private function findBinary(string $name): ?string
    {
        $paths = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));

        foreach ($paths as $path) {
            foreach ([$name, $name.'.exe', $name.'.bat'] as $file) {
                $candidate = rtrim($path, '\\/').DIRECTORY_SEPARATOR.$file;

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function dumpDatabase(string $driver, string $binary, string $target): bool
    {
        $env = $this->env();
        $database = (string) $env->get('DB_DATABASE', '');
        $username = (string) $env->get('DB_USERNAME', '');
        $password = (string) $env->get('DB_PASSWORD', '');
        $host = (string) $env->get('DB_HOST', '127.0.0.1');
        $port = (string) $env->get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');

        $command = $driver === 'pgsql'
            ? [$binary, '--host='.$host, '--port='.$port, '--username='.$username, '--no-password', '--file='.$target, $database]
            : [$binary, '--host='.$host, '--port='.$port, '--user='.$username, '--result-file='.$target, $database];

        $process = new Process($command, $this->basePath, [
            'MYSQL_PWD' => $password,
            'PGPASSWORD' => $password,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->log('database dump failed: '.trim($process->getErrorOutput()));

            return false;
        }

        return is_file($target);
    }

    private function pruneBackups(string $directory, int $keep): void
    {
        foreach (['database-', 'env-'] as $prefix) {
            $files = glob($directory.'/'.$prefix.'*') ?: [];
            rsort($files);

            foreach (array_slice($files, $keep) as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        if (! is_file($this->statePath())) {
            return [];
        }

        $contents = @file_get_contents($this->statePath());
        $decoded = $contents === false ? null : json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(array $state): void
    {
        $directory = dirname($this->statePath());

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($this->statePath(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * @return array{label: string, ok: bool, detail: string, required: bool}
     */
    private function check(string $label, bool $ok, string $detail): array
    {
        return ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'required' => true];
    }

    private function lockPath(): string
    {
        return $this->basePath.'/storage/app/install.lock';
    }

    private function statePath(): string
    {
        return $this->basePath.'/storage/app/install.state.json';
    }
}
