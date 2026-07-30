@php
    $elementId = 'bot-shield-recaptcha-'.bin2hex(random_bytes(6));
    $containerId = $elementId.'-container';
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
    id="{{ $containerId }}"
    class="g-recaptcha"
    data-sitekey="{{ $siteKey }}"
    data-theme="{{ $theme }}"
    data-size="invisible"
    data-badge="{{ $badge }}"
    data-callback="{{ $callback }}"
></div>

<script>
    (function () {
        var field = document.getElementById(@js($elementId));
        var container = document.getElementById(@js($containerId));

        if (! field || ! container) {
            return;
        }

        window[@js($callback)] = function (token) {
            field.value = token;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        };

        function challenge() {
            if (typeof grecaptcha === 'undefined' || typeof grecaptcha.execute !== 'function') {
                window.setTimeout(challenge, 300);

                return;
            }

            grecaptcha.execute();
        }

        // An invisible widget only produces a token when asked, and asking at
        // submit time races the submission, so it is asked twice: once now, so
        // a visitor who submits without typing anything still has a token, and
        // again on first interaction, so a slow form submits a fresh one rather
        // than a token that expired while it was being filled in.
        var form = field.closest('form') || container.parentElement;

        if (form) {
            form.addEventListener('input', function once() {
                form.removeEventListener('input', once);

                challenge();
            });
        }

        challenge();
    })();
</script>

@if($hideBadge)
    @include('bot-shield::recaptcha.hide-badge')
@endif

<script src="{{ $scriptUrl }}" async defer></script>

@if($showTerms)
    @include('bot-shield::recaptcha.terms')
@endif
