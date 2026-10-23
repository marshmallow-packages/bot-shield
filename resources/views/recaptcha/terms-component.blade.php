{{-- The component's own view: the attribute bag is only readable from here. --}}
@include('bot-shield::recaptcha.terms', ['termsClass' => $attributes->get('class')])
