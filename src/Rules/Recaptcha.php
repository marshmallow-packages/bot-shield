<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Rules;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Captcha\CaptchaManager;

/**
 * Validation rule for classic form submissions:
 *
 *     'g-recaptcha-response' => ['required', new Recaptcha],
 */
final class Recaptcha implements ValidationRule
{
    /**
     * Run even when the field is absent or blank. A scripted submission simply
     * omits the token, and skipping the rule on empty values would let it
     * through whenever the caller forgot to pair this with "required".
     */
    public bool $implicit = true;

    public function __construct(
        private readonly ?string $form = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $container = Container::getInstance();

        $verdict = $container->make(CaptchaManager::class)->verify(
            is_string($value) ? $value : null,
            $container->make(Request::class),
            $this->form === null ? [] : ['form' => $this->form],
        );

        if ($verdict->passes()) {
            return;
        }

        $fail($verdict->message());
    }
}
