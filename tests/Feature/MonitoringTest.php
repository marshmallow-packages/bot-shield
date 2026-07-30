<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Enums\EventType;
use Marshmallow\BotShield\Guards\FormGuard;
use Marshmallow\BotShield\Hardening\ExceptionHardening;
use Marshmallow\BotShield\Models\BotShieldEvent;
use Marshmallow\BotShield\Monitoring\EventRecorder;
use Marshmallow\BotShield\Tests\Fixtures\SpamProtectedComponent;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Symfony\Component\HttpKernel\Exception\HttpException;

function monitoredRequest(string $userAgent = 'curl/8.7.1', string $path = '/contact'): Request
{
    $request = Request::create($path, 'POST', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);

    app()->instance('request', $request);

    return $request;
}

it('records a captcha verification with its score', function () {
    configureCaptcha('google-v3');
    fakeSiteverify(['success' => true, 'score' => 0.8]);

    app(CaptchaManager::class)->verify('token', monitoredRequest(), ['form' => 'contact']);

    $event = BotShieldEvent::query()->sole();

    expect($event->type)->toBe(EventType::Captcha)
        ->and($event->outcome)->toBe('passed')
        ->and($event->score)->toBe(0.8)
        ->and($event->form)->toBe('contact')
        ->and($event->ip)->not->toBeNull()
        ->and($event->user_agent)->toBe('curl/8.7.1');
});

it('records the score of a failed verification too, which is the point', function () {
    configureCaptcha('google-v3');
    fakeSiteverify(['success' => true, 'score' => 0.2]);

    app(CaptchaManager::class)->verify('token', monitoredRequest());

    expect(BotShieldEvent::query()->sole())
        ->outcome->toBe('low-score')
        ->score->toBe(0.2);
});

it('records a blocked form submission', function () {
    expect(fn () => app(FormGuard::class)->guard(monitoredRequest(), 'newsletter'))->toThrow(HttpException::class);

    $event = BotShieldEvent::query()->sole();

    expect($event->type)->toBe(EventType::DetectorBlock)
        ->and($event->outcome)->toBe('blocked')
        ->and($event->form)->toBe('newsletter');
});

it('records a honeypot trip', function () {
    monitoredRequest();

    $component = new SpamProtectedComponent;
    $component->honeypotData = new HoneypotData;
    $component->honeypotData->contact_reference = 'a bot wrote here';

    $this->travel(5)->seconds();

    expect(fn () => $component->submit())->toThrow(HttpException::class);

    expect(BotShieldEvent::query()->sole())
        ->type->toBe(EventType::Honeypot)
        ->outcome->toBe('tripped');
});

it('records a suppressed exception', function () {
    monitoredRequest('curl/8.7.1', '/livewire/update');

    $suppressed = app(ExceptionHardening::class)->shouldNotReport(
        new TypeError('Cannot assign array to property Foo::$bar of type string'),
    );

    expect($suppressed)->toBeTrue();

    expect(BotShieldEvent::query()->sole())
        ->type->toBe(EventType::SuppressedException)
        ->outcome->toBe(TypeError::class);
});

it('records nothing for an exception it does not suppress', function () {
    monitoredRequest('Mozilla/5.0 (Macintosh) Safari/537.36', '/livewire/update');

    expect(app(ExceptionHardening::class)->shouldNotReport(new TypeError('must be of type string')))->toBeFalse()
        ->and(BotShieldEvent::query()->count())->toBe(0);
});

it('records nothing while monitoring is disabled', function () {
    config()->set('bot-shield.monitoring.enabled', false);

    configureCaptcha('google-v3');
    fakeSiteverify(['success' => true, 'score' => 0.8]);

    app(CaptchaManager::class)->verify('token', monitoredRequest());

    expect(BotShieldEvent::query()->count())->toBe(0);
});

it('records nothing while the package is disabled', function () {
    config()->set('bot-shield.enabled', false);

    app(EventRecorder::class)->record(EventType::ProbePath, 'hit');

    expect(BotShieldEvent::query()->count())->toBe(0);
});

it('stores a hashed address when asked to', function () {
    config()->set('bot-shield.monitoring.hash_ips', true);

    monitoredRequest();

    app(EventRecorder::class)->record(EventType::ProbePath, 'hit');

    $event = BotShieldEvent::query()->sole();

    expect($event->ip)->toHaveLength(64)
        ->and($event->ip)->not->toContain('.');
});

it('stores a readable address by default', function () {
    monitoredRequest();

    app(EventRecorder::class)->record(EventType::ProbePath, 'hit');

    expect(BotShieldEvent::query()->sole()->ip)->toBe('127.0.0.1');
});

it('never lets a recording failure break the request', function () {
    Schema::drop('bot_shield_events');

    monitoredRequest();

    app(EventRecorder::class)->record(EventType::ProbePath, 'hit');
})->throwsNoExceptions();

it('prunes events past the retention window', function () {
    config()->set('bot-shield.monitoring.retention_days', 30);

    BotShieldEvent::query()->create(['type' => EventType::ProbePath, 'created_at' => now()->subDays(31)]);
    BotShieldEvent::query()->create(['type' => EventType::ProbePath, 'created_at' => now()->subDays(29)]);

    $pruned = (new BotShieldEvent)->pruneAll();

    expect($pruned)->toBe(1)
        ->and(BotShieldEvent::query()->count())->toBe(1);
});

it('honours a configured retention window', function () {
    config()->set('bot-shield.monitoring.retention_days', 7);

    BotShieldEvent::query()->create(['type' => EventType::ProbePath, 'created_at' => now()->subDays(8)]);
    BotShieldEvent::query()->create(['type' => EventType::ProbePath, 'created_at' => now()->subDays(6)]);

    expect((new BotShieldEvent)->pruneAll())->toBe(1);
});
