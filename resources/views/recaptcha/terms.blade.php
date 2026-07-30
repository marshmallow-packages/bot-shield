{{--
    Google requires this notice whenever the reCAPTCHA badge is hidden.
    See https://developers.google.com/recaptcha/docs/faq
--}}
<p class="bot-shield-recaptcha-terms">
    {!! __('bot-shield::messages.recaptcha.terms', [
        'privacy' => '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">'.e(__('bot-shield::messages.recaptcha.privacy')).'</a>',
        'terms' => '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">'.e(__('bot-shield::messages.recaptcha.terms_of_service')).'</a>',
    ]) !!}
</p>
