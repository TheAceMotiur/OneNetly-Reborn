<?php

use App\Support\Installer\EnvFile;
use App\Support\Installer\Installer;

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/onenetly-installer-'.bin2hex(random_bytes(5));

    mkdir($this->basePath, 0777, true);
});

afterEach(function (): void {
    installerRemoveDirectory($this->basePath);
});

it('detects a fresh install when there is no app key', function (): void {
    $installer = new Installer($this->basePath);

    expect($installer->mode())->toBe(Installer::MODE_INSTALL)
        ->and($installer->hasApplicationKey())->toBeFalse();
});

it('stays in install mode when the key was written but the install did not finish', function (): void {
    (new EnvFile($this->basePath.'/.env'))->set(['APP_KEY' => 'base64:'.str_repeat('a', 44)]);

    $installer = new Installer($this->basePath);

    expect($installer->mode())->toBe(Installer::MODE_INSTALL)
        ->and($installer->status()['installed'])->toBeFalse()
        ->and($installer->status()['steps'])->toHaveCount(8);
});

it('detects update mode once the install lock exists', function (): void {
    (new EnvFile($this->basePath.'/.env'))->set(['APP_KEY' => 'base64:'.str_repeat('a', 44)]);
    mkdir($this->basePath.'/storage/app', 0777, true);
    file_put_contents($this->basePath.'/storage/app/install.lock', '{}');

    $installer = new Installer($this->basePath);

    expect($installer->mode())->toBe(Installer::MODE_UPDATE)
        ->and($installer->status()['mode'])->toBe(Installer::MODE_UPDATE)
        ->and($installer->status()['steps'])->toHaveCount(10);
});

it('keeps the existing app key when an install is resumed', function (): void {
    $key = 'base64:'.str_repeat('c', 44);
    (new EnvFile($this->basePath.'/.env'))->set(['APP_KEY' => $key]);

    $installer = new Installer($this->basePath);
    $result = $installer->run(Installer::MODE_INSTALL, 'key');

    expect($installer->env()->get('APP_KEY'))->toBe($key)
        ->and($result['message'])->toContain('already');
});

it('keeps every other .env line intact when writing values', function (): void {
    $path = $this->basePath.'/.env';

    file_put_contents($path, "# comment\nAPP_NAME=Old\nDB_HOST=localhost\nMAIL_FROM_NAME=\"\${APP_NAME}\"\n");

    $env = new EnvFile($path);
    $env->set(['APP_NAME' => 'OneNetly', 'APP_URL' => 'https://example.com', 'DB_PASSWORD' => 'pa ss#word']);

    expect(file_get_contents($path))->toBe(
        "# comment\nAPP_NAME=OneNetly\nDB_HOST=localhost\nMAIL_FROM_NAME=\"\${APP_NAME}\"\nAPP_URL=https://example.com\nDB_PASSWORD=\"pa ss#word\"\n"
    );

    expect($env->get('DB_PASSWORD'))->toBe('pa ss#word')
        ->and($env->get('MAIL_FROM_NAME'))->toBe('${APP_NAME}')
        ->and($env->get('APP_URL'))->toBe('https://example.com');
});

it('creates a missing .env from the first available template', function (): void {
    file_put_contents($this->basePath.'/.env.example', "APP_NAME=Example\nAPP_KEY=\n");

    $env = new EnvFile($this->basePath.'/.env');
    $env->createFrom($this->basePath.'/.env.production.example');
    $env->createFrom($this->basePath.'/.env.example');

    expect($env->exists())->toBeTrue()
        ->and($env->get('APP_NAME'))->toBe('Example');
});

it('reports changes when nothing has been recorded yet', function (): void {
    (new EnvFile($this->basePath.'/.env'))->set(['APP_KEY' => 'base64:'.str_repeat('b', 44)]);

    $installer = new Installer($this->basePath);

    expect($installer->isUpToDate())->toBeFalse()
        ->and($installer->changes())->toHaveCount(1);
});

it('lists the install steps in execution order', function (): void {
    $ids = array_column((new Installer($this->basePath))->installSteps(), 'id');

    expect($ids)->toBe([
        'preflight',
        'environment',
        'migrate',
        'seed',
        'admin',
        'storage-link',
        'key',
        'finalize',
    ]);
});

function installerRemoveDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}
