<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;

final class VersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-guardian:version';

    /**
     * The console command description.
     */
    protected $description = 'Display the Laravel API Guardian version and environment information';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $composer = $this->getComposerData();

        $this->components->info('Laravel API Guardian');
        $this->newLine();

        $this->components->twoColumnDetail('Package Version', $composer['version'] ?? 'dev-main');
        $this->components->twoColumnDetail('Laravel Version', app()->version());
        $this->components->twoColumnDetail('PHP Version', PHP_VERSION);
        $this->newLine();

        $this->components->info('Configuration:');
        $this->components->twoColumnDetail('Default Format', config('api-guardian.default_format'));
        $this->components->twoColumnDetail('Monitoring', config('api-guardian.monitoring.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>');
        $this->components->twoColumnDetail('Recovery', config('api-guardian.recovery.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>');
        $this->components->twoColumnDetail('Circuit Breaker', config('api-guardian.circuit_breaker.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>');
        $this->components->twoColumnDetail('Dashboard', config('api-guardian.dashboard.enabled') ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>');
        $this->newLine();

        $this->components->info('Environment:');
        $this->components->twoColumnDetail('App Environment', app()->environment());
        $this->components->twoColumnDetail('Debug Mode', config('app.debug') ? '<fg=yellow>Enabled</>' : '<fg=green>Disabled</>');
        $this->components->twoColumnDetail('Timezone', config('api-guardian.dashboard.timezone', config('app.timezone')));
        $this->newLine();

        if (config('api-guardian.dashboard.enabled')) {
            $this->components->info('Dashboard URL:');
            $prefix = config('api-guardian.ui.frameworks.livewire.route_prefix', 'api-guardian');
            $this->line(sprintf('  <fg=blue>http://your-domain.com/%s/dashboard</>', $prefix));
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Get composer.json data.
     */
    private function getComposerData(): array
    {
        $composerPath = __DIR__.'/../../composer.json';

        if (! file_exists($composerPath)) {
            return [];
        }

        $contents = file_get_contents($composerPath);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }
}
