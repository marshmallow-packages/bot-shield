<?php

declare(strict_types=1);

namespace Marshmallow\BotShield;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Captcha\CaptchaRenderer;
use Marshmallow\BotShield\Console\Commands\DoctorCommand;
use Marshmallow\BotShield\Console\Commands\InstallCommand;
use Marshmallow\BotShield\Console\Commands\RobotsCommand;
use Marshmallow\BotShield\Console\Commands\ScoresCommand;
use Marshmallow\BotShield\Console\Commands\StatsCommand;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Detectors\BotDetectorFactory;
use Marshmallow\BotShield\Hardening\ExceptionHardening;
use Marshmallow\BotShield\Honeypot\SpatieHoneypotConfigurator;
use Marshmallow\BotShield\Http\Middleware\BlockProbePaths;
use Marshmallow\BotShield\Http\Middleware\DenyAgents;
use Marshmallow\BotShield\Monitoring\EventRecorder;

class BotShieldServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bot-shield.php', 'bot-shield');

        $this->app->singleton(
            BotDetector::class,
            fn (): BotDetector => $this->app->make(BotDetectorFactory::class)->make(),
        );

        // The seam BotShield::fake() replaces with an in-memory collector.
        $this->app->singleton(RecordsEvents::class, EventRecorder::class);

        // A singleton so bot-shield:doctor can see whether handles() ever ran.
        $this->app->singleton(ExceptionHardening::class);

        $this->app->singleton(CaptchaManager::class);

        $this->app->singleton(BotShield::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bot-shield');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'bot-shield');

        $this->registerBladeExtensions();

        $this->app->make(SpatieHoneypotConfigurator::class)->apply();

        $this->registerMiddlewareAliases();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/bot-shield.php' => config_path('bot-shield.php'),
        ], ['bot-shield', 'bot-shield-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/bot-shield'),
        ], ['bot-shield', 'bot-shield-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/bot-shield'),
        ], ['bot-shield', 'bot-shield-lang']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['bot-shield', 'bot-shield-migrations']);

        $this->commands([
            DoctorCommand::class,
            InstallCommand::class,
            RobotsCommand::class,
            ScoresCommand::class,
            StatsCommand::class,
        ]);
    }

    /**
     * Aliases only. Neither middleware is applied for you: the probe path
     * firewall belongs first in the global stack, and refusing crawler page
     * views is a decision per site.
     */
    private function registerMiddlewareAliases(): void
    {
        $router = $this->app->make('router');

        $router->aliasMiddleware('bot-shield.probe-paths', BlockProbePaths::class);
        $router->aliasMiddleware('bot-shield.deny-agents', DenyAgents::class);
    }

    private function registerBladeExtensions(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->componentNamespace('Marshmallow\\BotShield\\View\\Components', 'bot-shield');

            $blade->directive('botShieldRecaptcha', static fn (string $expression): string => sprintf(
                '<?php echo app(%s::class)->render(%s); ?>',
                '\\'.CaptchaRenderer::class,
                $expression === '' ? '' : $expression,
            ));
        });
    }
}
