<?php

declare(strict_types=1);

return [
    'invalid_component_data' => 'Invalid component data.',
    'forbidden' => 'This request was blocked.',
    'spam_detected' => 'This submission was rejected as spam.',
    'too_many_submissions' => 'Too many submissions. Please wait a moment and try again.',

    'recaptcha' => [
        'passed' => 'Verification succeeded.',
        /*
         * Missing and failed share their wording deliberately. The old
         * "complete the verification" phrasing described a checkbox nobody
         * sees under the default invisible driver, and telling a caller which
         * check it failed is information a scripted submitter can act on.
         * Override either key if your site prefers to be specific.
         */
        'missing' => 'We could not verify that you are not a robot. Please try again.',
        'failed' => 'We could not verify that you are not a robot. Please try again.',
        'unavailable' => 'We could not verify your submission right now. Please try again in a moment.',
        'terms' => 'This site is protected by reCAPTCHA and the Google :privacy and :terms apply.',
        'privacy' => 'Privacy Policy',
        'terms_of_service' => 'Terms of Service',
    ],
];
