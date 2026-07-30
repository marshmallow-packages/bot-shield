<?php

declare(strict_types=1);

namespace Marshmallow\BotShield;

use Illuminate\Support\ServiceProvider;
use Marshmallow\BotShield\Console\Commands\BotShieldCommand;
use Marshmallow\BotShield\Contracts\BotDetector;
use Marshmallow\BotShield\Detectors\UserAgentBotDetector;

class BotShieldServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bot-shield.php', 'bot-shield');

        $this->app->singleton(BotDetector::class, UserAgentBotDetector::class);

        $this->app->singleton(BotShield::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bot-shield');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'bot-shield');

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
            BotShieldCommand::class,
        ]);
    }
}
