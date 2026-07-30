<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Livewire;

use Attribute;
use Closure;
use Illuminate\Http\Request;
use Marshmallow\BotShield\Captcha\CaptchaManager;

/**
 * Verifies a captcha token before a Livewire action runs:
 *
 *     #[ValidatesRecaptcha]
 *     public function submit(): void
 *
 * The token is read from the component property the client script fills, and
 * falls back to the request payload. Public Livewire methods are callable
 * without ever running the client challenge, so a blank token is a failure
 * rather than a reason to skip.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ValidatesRecaptcha extends BotShieldAttribute
{
    public function __construct(
        private readonly string $property = 'gRecaptchaResponse',
        private readonly ?string $form = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $params
     */
    public function call(array $params, Closure $returnEarly): void
    {
        $captcha = $this->resolve(CaptchaManager::class);

        if (! $captcha instanceof CaptchaManager) {
            return;
        }

        $request = $this->currentRequest();

        $verdict = $captcha->verify($this->token($captcha, $request), $request, [
            'component' => $this->componentName(),
            'action' => $this->actionName(),
            'form' => $this->form,
        ]);

        if ($verdict->passes()) {
            return;
        }

        $this->failValidation($captcha->fieldName(), $verdict->message(), $returnEarly);
    }

    private function token(CaptchaManager $captcha, Request $request): ?string
    {
        $token = $this->componentProperty($this->property);

        if (is_string($token)) {
            return $token;
        }

        return $captcha->tokenFrom($request);
    }
}
