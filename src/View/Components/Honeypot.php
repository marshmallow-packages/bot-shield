<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\View\Components;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Marshmallow\BotShield\Guards\SpamGuard;

/**
 * <x-bot-shield::honeypot />
 *
 * For a Livewire component, name the HoneypotData property so the fields sync:
 *
 *     <x-bot-shield::honeypot livewire-model="honeypotData" />
 *
 * Renders spatie/laravel-honeypot's fields. The indirection is deliberate: it
 * keeps a package owned tag in customer views so the internals can change
 * without touching every form.
 */
final class Honeypot extends Component
{
    public function __construct(
        private readonly SpamGuard $guard,
        private readonly ViewFactory $views,
        /*
         * Private on purpose: Laravel merges public component properties into
         * the view data, where a null would override what render() passes.
         */
        private readonly ?string $livewireModel = null,
    ) {}

    public function shouldRender(): bool
    {
        if (! $this->guard->enabled()) {
            return false;
        }

        $this->guard->ensureAvailable();

        return true;
    }

    public function render(): View
    {
        return $this->views->make('honeypot::honeypotFormFields', array_merge(
            $this->guard->honeypot()->toArray(),
            ['livewireModel' => $this->livewireModel],
        ));
    }
}
