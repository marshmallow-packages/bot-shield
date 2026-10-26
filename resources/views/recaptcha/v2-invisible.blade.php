@php
    $containerId = $elementId.'-container';
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
    wire:ignore
    data-sitekey="{{ $siteKey }}"
    data-theme="{{ $theme }}"
    data-size="invisible"
    data-badge="{{ $badge }}"
    data-callback="{{ $callback }}"
></div>

<script>
    (function () {
        // Resolved on every use so a Livewire morph cannot leave this closure
        // holding a detached input.
        function field() {
            return document.getElementById(@js($elementId));
        }

        var container = document.getElementById(@js($containerId));

        if (! field() || ! container) {
            return;
        }

        window[@js($callback)] = function (token) {
            var input = field();

            if (! input) {
                return;
            }

            input.value = token;
            input.dispatchEvent(new Event('input', { bubbles: true }));
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
        var form = field().closest('form') || container.parentElement;

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

@include('bot-shield::recaptcha.script')

@if($showTerms)
    @include('bot-shield::recaptcha.terms')
@endif
