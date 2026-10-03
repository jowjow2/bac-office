{{--
    Live unlock: when the BAC records this bidder's bidding documents fee, the
    notification poller (partials.notification-live) brings a "bidding_fee_paid"
    notification. A page still showing that project as payment-locked reloads
    into the unlocked Submit Bid form, unless the bidder is busy filling in
    another bid, then it offers a button instead. The server checks the
    payment again on submit, so this is only for convenience.
--}}
<script>
    document.addEventListener('bac:notifications-updated', function (event) {
        const notifications = (event.detail && event.detail.notifications) || [];
        const paid = notifications.find(function (item) {
            if (item.type !== 'bidding_fee_paid' || item.is_read || !item.project_id) return false;
            const id = String(item.project_id);
            return document.querySelector('[data-bid-form][data-payment-locked="true"][data-project-id="' + id + '"], [data-fee-locked-project="' + id + '"]');
        });
        if (!paid) return;

        const target = paid.url || window.location.href;
        // Do not throw away a bid being filled in for another project.
        const busy = Array.from(document.querySelectorAll('.bidder-modal-overlay.show [data-bid-form][data-payment-locked="false"]'))
            .some(function (form) {
                return Array.from(form.querySelectorAll('input:not([type=hidden]), textarea')).some(function (field) {
                    return field.type === 'file' ? field.files && field.files.length > 0 : field.value.trim() !== '';
                });
            });

        if (!busy) {
            window.location.href = target;
            return;
        }

        if (document.querySelector('[data-payment-live-banner]')) return;
        const banner = document.createElement('div');
        banner.setAttribute('data-payment-live-banner', '');
        banner.setAttribute('role', 'status');
        banner.style.cssText = 'position:fixed;left:50%;bottom:20px;transform:translateX(-50%);z-index:3000;display:flex;gap:12px;align-items:center;max-width:calc(100vw - 32px);padding:12px 16px;border-radius:12px;background:#1d4f40;color:#fff;box-shadow:0 12px 30px rgba(0,0,0,.25);font-size:14px';
        const text = document.createElement('span');
        text.textContent = (paid.title || 'Payment recorded') + ': you can now submit your bid for this project.';
        const link = document.createElement('a');
        link.href = target;
        link.textContent = 'Open the bid form';
        link.style.cssText = 'padding:6px 12px;border-radius:8px;background:#fff;color:#1d4f40;font-weight:600;text-decoration:none;white-space:nowrap';
        banner.append(text, link);
        document.body.appendChild(banner);
    });
</script>
