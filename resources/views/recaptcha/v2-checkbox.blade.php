<input
    type="hidden"
    id="{{ $elementId }}"
    name="{{ $field }}"
    value=""
    @if($property) wire:model="{{ $property }}" @endif
>

<div
    class="g-recaptcha"
    wire:ignore
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

@include('bot-shield::recaptcha.script')

@if($showTerms)
    @include('bot-shield::recaptcha.terms')
@endif
