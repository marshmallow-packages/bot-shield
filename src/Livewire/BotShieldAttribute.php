<?php

declare(strict_types=1);

namespace Marshmallow\BotShield\Livewire;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;

use function Livewire\trigger;

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

    protected function componentProperty(string $name): mixed
    {
        return data_get($this->component->all(), $name);
    }

    /**
     * Stop the action and surface a validation error on the component.
     *
     * Livewire turns a ValidationException into component errors only when it
     * is thrown inside Livewire\Wrapped::__call, which wraps the action itself
     * and not the attribute hooks running around it. Throwing from here would
     * escape to the exception handler instead, so the hook is fired explicitly.
     */
    protected function failValidation(string $field, string $message, Closure $returnEarly): void
    {
        $returnEarly(trigger(
            'exception',
            $this->component,
            ValidationException::withMessages([$field => $message]),
            static fn (): bool => true,
        ));
    }

    protected function resolve(string $abstract): mixed
    {
        return Container::getInstance()->make($abstract);
    }
}
