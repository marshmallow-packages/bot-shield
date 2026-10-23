<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\View\Components;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Marshmallow\BotShield\Captcha\CaptchaManager;

/**
 * <x-bot-shield::recaptcha-terms class="text-xs" />
 *
 * The notice Google requires when the badge is hidden, on its own, so a page
 * can keep the widget at the top of the form and put the notice next to the
 * submit button. Pair it with terms="false" on the widget. Placing it is the
 * intent, so it renders whatever "show_terms" says, as long as a captcha is
 * active at all.
 */
final class RecaptchaTerms extends Component
{
    public function __construct(
        private readonly CaptchaManager $captcha,
        private readonly ViewFactory $views,
    ) {}

    public function shouldRender(): bool
    {
        return $this->captcha->isActive();
    }

    public function render(): View
    {
        return $this->views->make('bot-shield::recaptcha.terms-component');
    }
}
