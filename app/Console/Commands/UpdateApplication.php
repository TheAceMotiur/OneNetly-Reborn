<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Installer\Installer;
use Illuminate\Console\Command;
use Throwable;

class UpdateApplication extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:update {--force : Run without asking for confirmation}';

    /**
     * @var string
     */
    protected $description = 'Deploy local changes: back up the database, run migrations, rebuild caches';

    public function handle(): int
    {
        $installer = Installer::make();

        if ($installer->mode() === Installer::MODE_INSTALL) {
            $this->components->error('This application is not installed yet. Open /install.php in a browser first.');

            return self::FAILURE;
        }

        $name = $installer->env()->get('APP_NAME') ?: 'the application';

        $this->components->info('Updating '.$name.'.');

        foreach ($installer->changes() as $change) {
            $this->line('  • '.$change);
        }

        if (! $this->option('force') && ! $this->confirm('Run the update now?', true)) {
            $this->components->warn('Update cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $inMaintenance = false;

        foreach ($installer->updateSteps() as $step) {
            if ($step['id'] === 'maintenance-on') {
                $inMaintenance = true;
            }

            try {
                $this->components->task($step['label'], function () use ($installer, $step): bool {
                    $result = $installer->run(Installer::MODE_UPDATE, $step['id']);

                    if (trim((string) $result['output']) !== '') {
                        $this->line('  <fg=gray>'.str_replace("\n", "\n  ", trim((string) $result['output'])).'</>');
                    }

                    return true;
                });
            } catch (Throwable $exception) {
                $this->newLine();
                $this->components->error($exception->getMessage());

                if ($step['id'] === 'maintenance-off') {
                    $inMaintenance = false;
                }

                if ($inMaintenance) {
                    $installer->cancelMaintenance();
                    $this->components->warn('Maintenance mode was turned off again. The site is reachable.');
                }

                return self::FAILURE;
            }
        }

        if ($installer->isUpToDate()) {
            $this->components->info('Update finished. The application now matches the uploaded files.');
        } else {
            $this->components->warn('Update finished, but some files changed during the run. Run app:update again if you expected more.');
        }

        return self::SUCCESS;
    }
}
