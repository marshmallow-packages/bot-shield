<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Honeypot;

use Illuminate\Contracts\Config\Repository;
use Spatie\Honeypot\Honeypot;

/**
 * Pushes this package's honeypot settings into spatie's config, so a site is
 * configured from one file instead of a published honeypot.php that drifts per
 * project. Spatie binds its Honeypot lazily from config, so applying this at
 * boot is enough regardless of provider order.
 */
final class SpatieHoneypotConfigurator
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function apply(): void
    {
        if (! class_exists(Honeypot::class)) {
            return;
        }

        if (! $this->manages()) {
            return;
        }

        $this->config->set('honeypot.enabled', $this->config->get('bot-shield.honeypot.enabled'));
        $this->config->set('honeypot.name_field_name', $this->config->get('bot-shield.honeypot.name_field'));
        $this->config->set('honeypot.randomize_name_field_name', $this->config->get('bot-shield.honeypot.randomize_name_field'));
        $this->config->set('honeypot.valid_from_field_name', $this->config->get('bot-shield.honeypot.valid_from_field'));
        $this->config->set('honeypot.amount_of_seconds', (int) $this->config->get('bot-shield.honeypot.seconds'));
    }

    public function manages(): bool
    {
        return filter_var(
            $this->config->get('bot-shield.honeypot.manage_spatie_config', false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        ) === true;
    }
}
