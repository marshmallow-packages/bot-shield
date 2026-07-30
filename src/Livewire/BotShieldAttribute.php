<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Livewire;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;

/**
 * Base class for the package's Livewire method attributes.
 *
 * Livewire's attribute mechanism is undocumented internal API. It is identical
 * across Livewire 3 and 4, but keeping every touch point in this one class
 * means a future Livewire release can be absorbed here instead of in each
 * attribute. Subclasses must not reach for $this->component or $this->subName
 * directly.
 */
abstract class BotShieldAttribute extends LivewireAttribute
{
    protected function actionName(): string
    {
        return is_string($this->subName) ? $this->subName : '';
    }

    protected function componentName(): string
    {
        return $this->component->getName();
    }

    protected function currentRequest(): Request
    {
        return Container::getInstance()->make(Request::class);
    }

    protected function resolve(string $abstract): mixed
    {
        return Container::getInstance()->make($abstract);
    }
}
