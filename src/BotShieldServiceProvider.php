<?php

declare(strict_types=1);

namespace Marshmallow\BotShield;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Captcha\CaptchaRenderer;
use Marshmallow\BotShield\Console\Commands\ScoresCommand;
use Marshmallow\BotShield\Console\Commands\StatsCommand;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Detectors\BotDetectorFactory;
use Marshmallow\BotShield\Honeypot\SpatieHoneypotConfigurator;

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
            ScoresCommand::class,
            StatsCommand::class,
        ]);
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
