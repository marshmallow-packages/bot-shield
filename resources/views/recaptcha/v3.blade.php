@php
    $elementId = 'bot-shield-recaptcha-'.bin2hex(random_bytes(6));
@endphp

<input
    type="hidden"
    id="{{ $elementId }}"
    name="{{ $field }}"
    value=""
    @if($property) wire:model="{{ $property }}" @endif
>

@if($hideBadge)
    @include('bot-shield::recaptcha.hide-badge')
@endif

<script src="{{ $scriptUrl }}" async defer></script>

<script>
    (function () {
        var field = document.getElementById(@js($elementId));

        if (! field) {
            return;
        }

        function apply(token) {
            field.value = token;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function refresh() {
            if (typeof grecaptcha === 'undefined' || typeof grecaptcha.ready !== 'function') {
                window.setTimeout(refresh, 300);

                return;
            }

            grecaptcha.ready(function () {
                grecaptcha.execute(@js($siteKey), { action: @js($action) }).then(apply);
            });
        }

        refresh();

        // Tokens expire after two minutes, so keep a fresh one on long forms.
        window.setInterval(refresh, 100000);

        // A token is single use: a retry after a validation error would resend
        // the spent one and fail as a robot. Fetch a new one after every round
        // trip of the component that holds this field.
        function watchLivewire() {
            window.Livewire.hook('commit', function (hook) {
                if (! hook.component.el.contains(field)) {
                    return;
                }

                hook.succeed(function () {
                    queueMicrotask(function () {
                        if (document.contains(field)) {
                            refresh();
                        }
                    });
                });
            });
        }

        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            watchLivewire();
        } else {
            document.addEventListener('livewire:init', watchLivewire, { once: true });
        }
    })();
</script>

@if($showTerms)
    @include('bot-shield::recaptcha.terms')
@endif
