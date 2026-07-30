<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Captcha\CaptchaRenderer;

/**
 * <x-bot-shield::recaptcha />
 *
 * Renders nothing while no captcha is configured, so a site without keys is
 * never asked for a token it cannot produce.
 */
final class Recaptcha extends Component
{
    public function __construct(
        private readonly CaptchaManager $captcha,
        private readonly CaptchaRenderer $renderer,
        /*
         * Deliberately private: Laravel merges a component's public properties
         * into the view data, where a null here would override the value the
         * renderer resolved from config.
         */
        private readonly ?string $theme = null,
        private readonly ?string $size = null,
        private readonly ?string $locale = null,
        private readonly ?string $action = null,
        private readonly ?string $property = null,
        private readonly ?string $badge = null,
        /*
         * Accepts both hide-badge="true" and :hide-badge="$flag", which blade
         * compiles to a string and a real boolean respectively.
         */
        private readonly bool|string|null $hideBadge = null,
    ) {}

    public function shouldRender(): bool
    {
        return $this->captcha->isActive();
    }

    public function render(): View
    {
        return $this->renderer->makeView($this->options());
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return array_filter([
            'theme' => $this->theme,
            'size' => $this->size,
            'locale' => $this->locale,
            'action' => $this->action,
            'property' => $this->property,
            'badge' => $this->badge,
            'hideBadge' => $this->hideBadge,
        ], static fn (bool|string|null $value): bool => $value !== null);
    }
}
