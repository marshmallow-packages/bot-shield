{{--
    Google allows the badge to be hidden only while the reCAPTCHA notice is
    visible, so the notice is rendered alongside this whether or not
    "show_terms" is on.

    Visibility rather than display: taking the badge out of the layout stops the
    widget from working. The !important is required, not defensive, because
    Google sets visibility on the badge in an inline style attribute and nothing
    short of !important overrides that.
--}}
<style>
    .grecaptcha-badge {
        visibility: hidden !important;
    }
</style>
