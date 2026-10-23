{{--
    The script is added through a guard rather than a plain script tag. @once
    covers a single render tree, but a Livewire update renders its component on
    its own, and api.js executing a second time resets widgets already on the
    page. The guard makes a repeat a no-op whichever way the markup arrives.
--}}
@once
<script>
    (function () {
        var src = @js($scriptUrl);

        if (document.querySelector('script[src="' + src + '"]')) {
            return;
        }

        var script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    })();
</script>
@endonce
