@php
    $elementId = 'bot-shield-recaptcha-'.bin2hex(random_bytes(6));
    $callback = 'botShieldRecaptcha'.bin2hex(random_bytes(6));
@endphp

<input
    type="hidden"
    id="{{ $elementId }}"
    name="{{ $field }}"
    value=""
    @if($property) wire:model="{{ $property }}" @endif
>

<div
    class="g-recaptcha"
    data-sitekey="{{ $siteKey }}"
    data-theme="{{ $theme }}"
    data-size="{{ $size }}"
    data-callback="{{ $callback }}"
    data-expired-callback="{{ $callback }}Expired"
></div>

<script>
    window[@js($callback)] = function (token) {
        var field = document.getElementById(@js($elementId));

        if (! field) {
            return;
        }

        field.value = token;
        field.dispatchEvent(new Event('input', { bubbles: true }));
    };

    window[@js($callback.'Expired')] = function () {
        window[@js($callback)]('');
    };
</script>

<script src="{{ $scriptUrl }}" async defer></script>

@if($showTerms)
    @include('bot-shield::recaptcha.terms')
@endif
