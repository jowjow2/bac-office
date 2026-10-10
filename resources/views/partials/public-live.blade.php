{{--
    Keeps a public page current without a manual refresh. A light request every few seconds asks whether what
    this page shows has changed (App\Support\LiveVersion, no sign-in needed); when it has, the page reloads itself,
    unless the visitor is typing or has filled in a filter, in which case it waits.

    @include('partials.public-live', ['scope' => 'public-procurement'])
--}}
<script>
    (function () {
        const url = @json(route('public.live', ['scope' => $scope]));
        let version = null;
        let pending = false;

        const busy = function () {
            if (document.hidden) return true;
            const active = document.activeElement;
            if (active && active.matches('input:not([type=button]):not([type=submit]), textarea, select, [contenteditable="true"]')) return true;
            // A search or filter someone has filled in should not be thrown away.
            return Array.from(document.querySelectorAll('input[type="search"], input[type="text"], select')).some(function (field) {
                if (field.disabled || field.getClientRects().length === 0) return false;
                if (field.tagName === 'SELECT') return Array.from(field.options).some(function (option) { return option.selected !== option.defaultSelected; });
                return field.value !== field.defaultValue;
            });
        };

        const reloadWhenIdle = function () {
            if (pending && !busy()) {
                pending = false;
                window.location.reload();
            }
        };

        const check = async function () {
            if (document.hidden) return;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                if (!data.version) return;
                if (version !== null && data.version !== version) pending = true;
                version = data.version;
                reloadWhenIdle();
            } catch (error) {
                // Offline for a moment: the next check tries again.
            }
        };

        check();
        window.setInterval(check, 4000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
        document.addEventListener('focusout', function () { window.setTimeout(reloadWhenIdle, 400); });
    })();
</script>
