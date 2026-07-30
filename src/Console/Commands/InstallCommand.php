<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Console\Commands;

use Illuminate\Console\Command;
use Marshmallow\BotShield\Installation\Installer;

class InstallCommand extends Command
{
    protected $signature = 'bot-shield:install
        {--force : Overwrite an already published config file}';

    protected $description = 'Publish the config, wire the exception handler, and add the env keys.';

    public function handle(Installer $installer): int
    {
        $this->components->info('Installing Bot Shield.');

        $this->publishConfig();
        $this->reportWiring($installer->wireExceptionHandler($this->laravel->basePath('bootstrap/app.php')));
        $this->reportEnvKeys($installer->addEnvKeys($this->laravel->basePath('.env.example')));

        $this->newLine();
        $this->components->info('Done. Run bot-shield:doctor to confirm.');

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $this->callSilently('vendor:publish', array_filter([
            '--tag' => 'bot-shield-config',
            '--force' => (bool) $this->option('force'),
        ]));

        $this->components->twoColumnDetail('Config', '<fg=green>published</>');
    }

    private function reportWiring(string $outcome): void
    {
        match ($outcome) {
            Installer::WIRED => $this->components->twoColumnDetail(
                'Exception handler',
                '<fg=green>wired into bootstrap/app.php</>',
            ),
            Installer::ALREADY_WIRED => $this->components->twoColumnDetail(
                'Exception handler',
                '<fg=gray>already wired</>',
            ),
            Installer::MISSING_FILE => $this->explainManualWiring('bootstrap/app.php not found'),
            default => $this->explainManualWiring('could not find withExceptions()'),
        };
    }

    private function explainManualWiring(string $reason): void
    {
        $this->components->twoColumnDetail('Exception handler', "<fg=yellow>{$reason}</>");

        $this->line('  Add this to bootstrap/app.php yourself:');
        $this->line('      ->withExceptions(function (Exceptions $exceptions) {');
        $this->line('          BotShield::handles($exceptions);');
        $this->line('      })');
        $this->line('  Or use the HardensAgainstBots trait if you still bind your own handler.');
    }

    /**
     * @param  list<string>  $added
     */
    private function reportEnvKeys(array $added): void
    {
        if ($added === []) {
            $this->components->twoColumnDetail('Env keys', '<fg=gray>nothing to add</>');

            return;
        }

        $this->components->twoColumnDetail('Env keys', '<fg=green>added '.count($added).' to .env.example</>');
        $this->line('  Set the real values in your own .env, and in Forge for deployed sites.');
    }
}
