<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Livewire\Livewire;
use Marshmallow\BotShield\Agents\AgentRules;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Contracts\RecordsEvents;
use Marshmallow\BotShield\Detectors\BotDetectorFactory;
use Marshmallow\BotShield\Guards\SpamGuard;
use Marshmallow\BotShield\Hardening\ExceptionHardening;
use Spatie\Honeypot\Honeypot;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'bot-shield:doctor';

    protected $description = 'Check that Bot Shield is wired up and configured correctly.';

    private int $failures = 0;

    private int $warnings = 0;

    /**
     * Nothing is injected here on purpose. Diagnosing a broken config is the
     * whole job, and an unknown detector or captcha driver throws while its
     * service is being resolved, which would take the command down with it.
     */
    public function handle(): int
    {
        $this->components->info('Bot Shield setup check.');

        if (! config('bot-shield.enabled')) {
            $this->components->warn('The master switch is off, so nothing below is active.');
        }

        /*
         * The detector goes first: both the exception hardening and the captcha
         * depend on it, so a broken driver would throw while they are being
         * resolved and there would be nothing to report.
         */
        $detectorHealthy = $this->checkDetector();

        $this->checkAgentRules($this->laravel->make(AgentRules::class));

        $detectorHealthy
            ? $this->checkExceptionWiring($this->laravel->make(ExceptionHardening::class))
            : $this->reportDetail('Exception hardening', 'not checked, fix the detector first');

        $detectorHealthy
            ? $this->checkCaptcha()
            : $this->reportDetail('Captcha', 'not checked, fix the detector first');

        $this->checkHoneypot($this->laravel->make(SpamGuard::class));
        $this->checkMonitoring($this->laravel->make(RecordsEvents::class));
        $this->checkLivewire();
        $this->checkTranslations();

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error("{$this->failures} problem(s) need attention.");

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->components->warn("{$this->warnings} thing(s) worth a look, nothing broken.");

            return self::SUCCESS;
        }

        $this->components->info('Everything checks out.');

        return self::SUCCESS;
    }

    private function checkExceptionWiring(ExceptionHardening $hardening): void
    {
        if (! config('bot-shield.exceptions.enabled')) {
            $this->reportDetail('Exception hardening', 'disabled in config');

            return;
        }

        if ($hardening->isRegistered()) {
            $this->reportPass('Exception hardening', 'wired');

            return;
        }

        $this->reportFailure(
            'Exception hardening',
            'not wired. Call BotShield::handles($exceptions) in bootstrap/app.php, '
            .'or use the HardensAgainstBots trait if you still bind your own handler.',
        );
    }

    private function checkDetector(): bool
    {
        $driver = (string) config('bot-shield.detector');

        if ($driver === BotDetectorFactory::CRAWLER_DETECT && ! class_exists(CrawlerDetect::class)) {
            $this->reportFailure('Detector', "[{$driver}] needs jaybizzle/crawler-detect");

            return false;
        }

        try {
            $this->laravel->make(BotDetectorFactory::class)->make();
        } catch (Throwable $exception) {
            $this->reportFailure('Detector', $exception->getMessage());

            return false;
        }

        $this->reportPass('Detector', $driver);

        return true;
    }

    private function checkAgentRules(AgentRules $rules): void
    {
        $configured = (array) config('bot-shield.agents.rules', []);
        $parsed = $rules->all();

        if (count($parsed) < count($configured)) {
            $this->reportWarning('Agent rules',
                sprintf('%d of %d rules were discarded as malformed', count($configured) - count($parsed), count($configured)),
            );
        }

        $broken = [];

        foreach ($parsed as $rule) {
            if (! $rule->regex) {
                continue;
            }

            if (@preg_match($rule->pattern, '') !== false) {
                continue;
            }

            $broken[] = $rule->pattern;
        }

        if ($broken !== []) {
            $this->reportFailure('Agent rules', 'invalid regular expression(s): '.implode(', ', $broken));

            return;
        }

        $this->reportPass('Agent rules', count($parsed).' active');
    }

    private function checkCaptcha(): void
    {
        if (! config('bot-shield.captcha.enabled')) {
            $this->reportDetail('Captcha', 'disabled in config');

            return;
        }

        try {
            $captcha = $this->laravel->make(CaptchaManager::class);
            $driver = $captcha->driverName();
        } catch (Throwable $exception) {
            $this->reportFailure('Captcha', $exception->getMessage());

            return;
        }

        if ($driver === CaptchaManager::NONE) {
            $this->reportDetail('Captcha', 'null driver, nothing verified');

            return;
        }

        if (! $captcha->isActive()) {
            $this->reportWarning('Captcha',
                "[{$driver}] is selected but the keys are missing, so nothing is verified. "
                .'Set BOT_SHIELD_RECAPTCHA_SITE_KEY and BOT_SHIELD_RECAPTCHA_SECRET_KEY.',
            );

            return;
        }

        $this->reportPass('Captcha', $driver.', threshold '.(string) config('bot-shield.captcha.score'));
    }

    private function checkHoneypot(SpamGuard $spam): void
    {
        if (! config('bot-shield.honeypot.enabled')) {
            $this->reportDetail('Honeypot', 'disabled in config');

            return;
        }

        if (! $spam->isAvailable()) {
            $this->reportFailure('Honeypot', 'enabled but spatie/laravel-honeypot is not installed');

            return;
        }

        if (! class_exists(Honeypot::class)) {
            $this->reportFailure('Honeypot', 'spatie/laravel-honeypot is not loadable');

            return;
        }

        $this->reportPass('Honeypot', (string) config('honeypot.name_field_name').', '.(string) config('honeypot.amount_of_seconds').'s minimum');
    }

    private function checkMonitoring(RecordsEvents $recorder): void
    {
        if (! $recorder->enabled()) {
            $this->reportDetail('Monitoring', 'disabled in config');

            return;
        }

        if (! Schema::hasTable('bot_shield_events')) {
            $this->reportFailure(
                'Monitoring',
                'the bot_shield_events table is missing. Run: php artisan vendor:publish '
                .'--tag=bot-shield-migrations && php artisan migrate',
            );

            return;
        }

        $this->reportPass('Monitoring', 'recording, '.(string) config('bot-shield.monitoring.retention_days').' day retention');

        $this->checkPruning();
    }

    /**
     * Pruning can also be scheduled from a closure calling model:prune, which is
     * undetectable from here, so a miss is reported as a reminder rather than a
     * warning. A check that cannot tell configured from unconfigured should not
     * be counted against the site.
     */
    private function checkPruning(): void
    {
        if ($this->prunesScheduled()) {
            $this->reportPass('Pruning', 'model:prune is scheduled');

            return;
        }

        $this->reportDetail('Pruning', 'no scheduled model:prune found, schedule it or the events table grows forever');
    }

    private function prunesScheduled(): bool
    {
        $schedule = $this->laravel->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            $summary = $event->command ?? $event->getSummaryForDisplay();

            if (str_contains($summary, 'model:prune')) {
                return true;
            }
        }

        return false;
    }

    private function checkLivewire(): void
    {
        if (! class_exists(Livewire::class)) {
            $this->reportWarning('Livewire', 'not installed, so the Livewire attributes are unavailable');

            return;
        }

        $this->reportPass('Livewire', 'installed');
    }

    /**
     * An application whose translations do not come from plain files, a loader
     * reading a database for instance, can fail to resolve a namespaced package
     * group entirely. Laravel then returns the key itself, so a visitor who
     * fails the captcha reads "bot-shield::messages.recaptcha.failed" on the
     * form. Nothing throws, nothing is logged, and a test asserting the error
     * field rather than the message still passes, so this is worth a check.
     */
    private function checkTranslations(): void
    {
        $key = 'bot-shield::messages.recaptcha.failed';

        if (trans($key) !== $key) {
            $this->reportPass('Translations', 'resolving for locale '.$this->laravel->getLocale());

            return;
        }

        $this->reportFailure(
            'Translations',
            'bot-shield::messages does not resolve, so visitors would see raw keys. '
            .'A custom translation loader may not read package lang files. Run: php artisan '
            .'vendor:publish --tag=bot-shield-lang',
        );
    }

    private function reportPass(string $label, string $detail): void
    {
        $this->components->twoColumnDetail($label, "<fg=green>{$detail}</>");
    }

    private function reportDetail(string $label, string $detail): void
    {
        $this->components->twoColumnDetail($label, "<fg=gray>{$detail}</>");
    }

    private function reportWarning(string $label, string $detail): void
    {
        $this->warnings++;

        $this->components->twoColumnDetail($label, "<fg=yellow>{$detail}</>");
    }

    private function reportFailure(string $label, string $detail): void
    {
        $this->failures++;

        $this->components->twoColumnDetail($label, "<fg=red>{$detail}</>");
    }
}
