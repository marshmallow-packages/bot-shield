<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Concerns;

use Illuminate\Container\Container;
use Marshmallow\BotShield\Captcha\CaptchaManager;
use Marshmallow\BotShield\Rules\Recaptcha;

/**
 * Adds the captcha field to a form request's rules:
 *
 *     public function rules(): array
 *     {
 *         return array_merge($this->recaptchaRules(), [
 *             'email' => ['required', 'email'],
 *         ]);
 *     }
 *
 * Returns nothing while no captcha is active, so a site without keys is not
 * asked for a token it never rendered.
 */
trait ValidatesRecaptcha
{
    /**
     * @return array<string, list<string|Recaptcha>>
     */
    protected function recaptchaRules(?string $form = null): array
    {
        $captcha = Container::getInstance()->make(CaptchaManager::class);

        if (! $captcha->isActive()) {
            return [];
        }

        return [
            $captcha->fieldName() => ['required', new Recaptcha($form)],
        ];
    }
}
