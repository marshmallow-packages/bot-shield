<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Marshmallow\BotShield\Concerns\ValidatesRecaptcha;

class ContactFormRequest extends FormRequest
{
    use ValidatesRecaptcha;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->recaptchaRules('contact'), [
            'email' => ['required', 'email'],
        ]);
    }
}
