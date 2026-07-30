<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Marshmallow\BotShield\Hardening\ExceptionHardening;
use Marshmallow\BotShield\Installation\Installer;

function tempDirectory(): string
{
    $path = sys_get_temp_dir().'/bot-shield-'.bin2hex(random_bytes(6));

    (new Filesystem)->makeDirectory($path, 0777, true);

    return $path;
}

describe('bot-shield:robots', function () {
    it('prints a stanza for every denied agent', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'GPTBot', 'action' => 'deny'],
            ['pattern' => 'CCBot', 'action' => 'deny'],
            ['pattern' => 'Googlebot', 'action' => 'block'],
        ]);

        $this->artisan('bot-shield:robots')
            ->expectsOutputToContain('User-agent: GPTBot')
            ->expectsOutputToContain('User-agent: CCBot')
            ->expectsOutputToContain('Disallow: /')
            ->assertSuccessful();
    });

    it('never disallows a search engine, which would be a disaster', function () {
        $this->artisan('bot-shield:robots')
            ->doesntExpectOutputToContain('User-agent: Googlebot')
            ->assertSuccessful();
    });

    it('warns when no rule denies anything', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'curl/', 'action' => 'block'],
        ]);

        $this->artisan('bot-shield:robots')
            ->expectsOutputToContain('No agent rules use the "deny" action')
            ->assertSuccessful();
    });

    it('skips regular expression rules, which robots.txt cannot express', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => '/^GPTBot/', 'action' => 'deny', 'regex' => true],
        ]);

        $this->artisan('bot-shield:robots')
            ->expectsOutputToContain('robots.txt matches agent names literally')
            ->assertSuccessful();
    });

    it('appends to an existing file', function () {
        $directory = tempDirectory();
        $target = $directory.'/robots.txt';

        (new Filesystem)->put($target, "User-agent: *\nDisallow:\n");

        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'GPTBot', 'action' => 'deny'],
        ]);

        $this->artisan('bot-shield:robots', ['--append' => $target])
            ->expectsOutputToContain('Appended 1 stanza(s)')
            ->assertSuccessful();

        expect((new Filesystem)->get($target))
            ->toContain('User-agent: *')
            ->toContain('User-agent: GPTBot');

        (new Filesystem)->deleteDirectory($directory);
    });

    it('refuses to append to a file that does not exist', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'GPTBot', 'action' => 'deny'],
        ]);

        $this->artisan('bot-shield:robots', ['--append' => '/nope/robots.txt'])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    });
});

describe('bot-shield:doctor', function () {
    it('reports that the exception handler is not wired', function () {
        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('not wired')
            ->assertFailed();
    });

    it('reports a wired handler once handles() has run', function () {
        app(ExceptionHardening::class)->register(app('Illuminate\Contracts\Debug\ExceptionHandler'));

        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('wired')
            ->assertSuccessful();
    });

    it('warns that the captcha has no keys', function () {
        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('the keys are missing')
            ->assertFailed();
    });

    it('passes the captcha check once the keys are set', function () {
        configureCaptcha();

        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('google-v3, threshold 0.6')
            ->assertFailed();
    });

    it('fails on an unknown detector driver', function () {
        config()->set('bot-shield.detector', 'telepathy');

        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('Unknown bot detector')
            ->assertFailed();
    });

    it('fails on an invalid agent regular expression', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => '/unterminated(/', 'action' => 'deny', 'regex' => true],
        ]);

        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('invalid regular expression')
            ->assertFailed();
    });

    it('reports malformed agent rules', function () {
        config()->set('bot-shield.agents.rules', [
            ['pattern' => 'curl/'],
            ['pattern' => 'GPTBot', 'action' => 'deny'],
        ]);

        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('1 of 2 rules were discarded')
            ->assertFailed();
    });

    it('reminds you to schedule pruning', function () {
        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('model:prune')
            ->assertFailed();
    });

    it('says when the master switch is off', function () {
        config()->set('bot-shield.enabled', false);

        $this->artisan('bot-shield:doctor')
            ->expectsOutputToContain('master switch is off')
            ->assertFailed();
    });
});

describe('the installer', function () {
    it('wires handles() into a bootstrap file', function () {
        $directory = tempDirectory();
        $path = $directory.'/app.php';

        (new Filesystem)->put($path, <<<'PHP'
            <?php

            use Illuminate\Foundation\Application;
            use Illuminate\Foundation\Configuration\Exceptions;

            return Application::configure(basePath: dirname(__DIR__))
                ->withExceptions(function (Exceptions $exceptions) {
                    //
                })->create();
            PHP);

        expect(app(Installer::class)->wireExceptionHandler($path))->toBe(Installer::WIRED);

        $contents = (new Filesystem)->get($path);

        expect($contents)->toContain('BotShield::handles($exceptions);')
            ->and($contents)->toContain('use Marshmallow\BotShield\Facades\BotShield;');

        (new Filesystem)->deleteDirectory($directory);
    });

    it('leaves an already wired file alone', function () {
        $directory = tempDirectory();
        $path = $directory.'/app.php';

        (new Filesystem)->put($path, '<?php BotShield::handles($exceptions);');

        expect(app(Installer::class)->wireExceptionHandler($path))->toBe(Installer::ALREADY_WIRED);

        (new Filesystem)->deleteDirectory($directory);
    });

    it('reports a missing bootstrap file rather than creating one', function () {
        expect(app(Installer::class)->wireExceptionHandler('/nope/app.php'))->toBe(Installer::MISSING_FILE);
    });

    it('reports a bootstrap file it does not recognise', function () {
        $directory = tempDirectory();
        $path = $directory.'/app.php';

        (new Filesystem)->put($path, '<?php return "something else entirely";');

        expect(app(Installer::class)->wireExceptionHandler($path))->toBe(Installer::NO_ANCHOR);

        expect((new Filesystem)->get($path))->toBe('<?php return "something else entirely";');

        (new Filesystem)->deleteDirectory($directory);
    });

    it('adds the missing env keys and leaves the present ones', function () {
        $directory = tempDirectory();
        $path = $directory.'/.env.example';

        (new Filesystem)->put($path, "APP_NAME=Laravel\nBOT_SHIELD_ENABLED=true\n");

        $added = app(Installer::class)->addEnvKeys($path);

        expect($added)->not->toContain('BOT_SHIELD_ENABLED')
            ->and($added)->toContain('BOT_SHIELD_RECAPTCHA_SITE_KEY');

        $contents = (new Filesystem)->get($path);

        expect(substr_count($contents, 'BOT_SHIELD_ENABLED='))->toBe(1)
            ->and($contents)->toContain('BOT_SHIELD_RECAPTCHA_SECRET_KEY=');

        (new Filesystem)->deleteDirectory($directory);
    });

    it('adds nothing twice', function () {
        $directory = tempDirectory();
        $path = $directory.'/.env.example';

        (new Filesystem)->put($path, "APP_NAME=Laravel\n");

        app(Installer::class)->addEnvKeys($path);

        expect(app(Installer::class)->addEnvKeys($path))->toBe([]);

        (new Filesystem)->deleteDirectory($directory);
    });

    it('skips a missing env example file', function () {
        expect(app(Installer::class)->addEnvKeys('/nope/.env.example'))->toBe([]);
    });
});

describe('translations', function () {
    it('ships dutch alongside english', function () {
        app()->setLocale('nl');

        expect(trans('bot-shield::messages.forbidden'))->toBe('Dit verzoek is geblokkeerd.')
            ->and(trans('bot-shield::messages.recaptcha.failed'))->toBe('Verificatie mislukt. Probeer het opnieuw.');
    });

    it('covers every english key in dutch', function () {
        $english = require __DIR__.'/../../lang/en/messages.php';
        $dutch = require __DIR__.'/../../lang/nl/messages.php';

        expect(array_keys($dutch))->toBe(array_keys($english))
            ->and(array_keys($dutch['recaptcha']))->toBe(array_keys($english['recaptcha']));
    });
});
