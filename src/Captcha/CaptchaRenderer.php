<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Captcha;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

/**
 * Chooses and renders the client side markup. Shared by the blade component and
 * the @botShieldRecaptcha directive so both stay in step.
 */
final class CaptchaRenderer
{
    public function __construct(
        private readonly CaptchaManager $captcha,
        private readonly Repository $config,
        private readonly ViewFactory $views,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function render(array $options = []): string
    {
        if (! $this->captcha->isActive()) {
            return '';
        }

        return $this->makeView($options)->render();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function makeView(array $options = []): View
    {
        return $this->views->make($this->view($options), $this->data($options));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function view(array $options = []): string
    {
        if ($this->captcha->usesScores()) {
            return 'bot-shield::recaptcha.v3';
        }

        if ($this->size($options) === 'invisible') {
            return 'bot-shield::recaptcha.v2-invisible';
        }

        return 'bot-shield::recaptcha.v2-checkbox';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function data(array $options = []): array
    {
        $hideBadge = $this->hideBadge($options);

        return [
            'siteKey' => (string) $this->captcha->siteKey(),
            'scriptUrl' => $this->scriptUrl($options),
            'field' => $this->captcha->fieldName(),
            'theme' => $this->option($options, 'theme', 'bot-shield.captcha.theme', 'light'),
            'size' => $this->size($options),
            'locale' => $this->option($options, 'locale', 'bot-shield.captcha.locale', ''),
            'action' => $this->stringOption($options, 'action', 'submit'),
            'property' => $this->stringOption($options, 'property', ''),
            'badge' => $this->option($options, 'badge', 'bot-shield.captcha.badge', 'bottomright'),
            'hideBadge' => $hideBadge,
            'showTerms' => $hideBadge || $this->captcha->showsTerms(),
        ];
    }

    /**
     * Built here rather than in the view: a Blade directive immediately after a
     * word character is not compiled, so assembling this URL in markup silently
     * breaks the template.
     *
     * @param  array<string, mixed>  $options
     */
    private function scriptUrl(array $options): string
    {
        $query = [];

        if ($this->captcha->usesScores()) {
            $query['render'] = (string) $this->captcha->siteKey();
        }

        $locale = $this->option($options, 'locale', 'bot-shield.captcha.locale', '');

        if ($locale !== '') {
            $query['hl'] = $locale;
        }

        $url = 'https://www.google.com/recaptcha/api.js';

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * A tag may hide the badge on a form whose site leaves it visible, so the
     * option wins over the config either way.
     *
     * @param  array<string, mixed>  $options
     */
    private function hideBadge(array $options): bool
    {
        $value = $options['hideBadge'] ?? $options['hide-badge'] ?? null;

        if ($value === null) {
            return $this->captcha->hidesBadge();
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function size(array $options): string
    {
        return $this->option($options, 'size', 'bot-shield.captcha.size', 'normal');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function option(array $options, string $key, string $configKey, string $default): string
    {
        $value = $options[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $configured = $this->config->get($configKey);

        return is_string($configured) && $configured !== '' ? $configured : $default;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function stringOption(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
