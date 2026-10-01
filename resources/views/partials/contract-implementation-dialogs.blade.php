{{--
    One Contract Implementation modal per tracked award, opened by partials.contract-implementation-button.
    After a save the page returns with ?contract={award} (or with that award's form errors) and reopens it.
    Suppliers also get a focused "Submit delivery" modal for each contract waiting for their delivery.
--}}
@php
    $ciWorkflow = app(\App\Support\ContractImplementationWorkflow::class);
    $ciAwards = collect($awards)->filter(fn ($award) => $ciWorkflow->eligible($award) && $award->contractImplementation);
    // A failed delivery reopens the delivery modal, not the whole contract screen.
    $ciReopen = (string) ((old('ci_award') && old('ci_form') !== 'delivery') ? old('ci_award') : request()->query('contract', ''));
@endphp
@foreach($ciAwards as $ciAward)
    <dialog class="ci-dialog" id="ci-dialog-{{ $ciAward->id }}" aria-labelledby="ci-{{ $ciAward->id }}-title" data-ci-dialog="{{ $ciAward->id }}" @if($ciReopen === (string) $ciAward->id) data-ci-reopen @endif>
        <button type="button" class="ci-dialog-close" data-ci-close aria-label="Close contract implementation"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        <div class="ci-dialog-scroll">
            @include('partials.contract-implementation', ['award' => $ciAward, 'implementation' => $ciAward->contractImplementation, 'viewerMode' => $viewerMode])
        </div>
    </dialog>
    @if($viewerMode === 'bidder')
        {{-- Rendered only while the contract waits for this supplier's delivery. --}}
        @include('partials.contract-implementation', ['award' => $ciAward, 'implementation' => $ciAward->contractImplementation, 'viewerMode' => $viewerMode, 'part' => 'delivery-dialog'])
    @endif
@endforeach

@once
<style id="contract-implementation-dialog-ui">
    /* The row button that opens a contract's modal. */
    /* Dashboard pages restyle every button with ID-level !important rules; the never-matching ID pair outranks them. */
    :is(.ci-open, #ci-x#ci-x) { display: inline-flex !important; align-items: center !important; justify-content: flex-start !important; gap: 9px !important; width: auto !important; max-width: 100% !important; min-height: 0 !important; height: auto !important; margin: 6px 0 0 !important; padding: 7px 11px !important; border: 1px solid var(--ui-primary-line, #b7d2c4) !important; border-radius: var(--ui-radius, 6px) !important; background: var(--ui-primary-soft, #e8f1ec) !important; color: var(--ui-primary, #1d4f40) !important; font: 600 var(--ui-text-sm, 12.5px)/1.25 var(--ui-font, Inter, system-ui, sans-serif) !important; text-align: left !important; text-transform: none !important; box-shadow: none !important; cursor: pointer !important; }
    .ci-open:hover { border-color: var(--ui-primary, #1d4f40); }
    .ci-open:focus-visible { outline: none; box-shadow: var(--ui-focus, 0 0 0 3px rgba(47, 122, 95, .32)); }
    :is(.ci-open-text, #ci-x#ci-x) { display: grid !important; gap: 3px; min-width: 0; text-align: left !important; }
    :is(.ci-open-status, #ci-x#ci-x) { justify-self: start; display: inline-block !important; padding: 1px 7px !important; border-radius: 999px; font-size: var(--ui-text-xs, 11.5px); font-weight: 700; white-space: nowrap; }
    :is(.ci-open-status.is-info, #ci-x#ci-x) { background: var(--ui-info-soft, #e6eef8) !important; color: var(--ui-info, #1f4f86) !important; }
    :is(.ci-open-status.is-success, #ci-x#ci-x) { background: var(--ui-success-soft, #e2f2ea) !important; color: var(--ui-success, #1d6a4d) !important; }
    :is(.ci-open-status.is-warning, #ci-x#ci-x) { background: var(--ui-warning-soft, #fcf1d8) !important; color: var(--ui-warning, #875705) !important; }

    /* The modals themselves are styled in partials.contract-implementation. */
</style>
<script>
    (function () {
        const setParam = (id) => {
            const url = new URL(window.location.href);
            if (id) url.searchParams.set('contract', id); else url.searchParams.delete('contract');
            history.replaceState(history.state, '', url);
        };
        const open = (id) => {
            const dialog = document.querySelector('[data-ci-dialog="' + id + '"]');
            if (!dialog || dialog.open) return;
            dialog.showModal();
            // Keep the contract in the URL so a save or a failed save comes back to it.
            setParam(id);
        };
        const openDelivery = (id) => {
            const dialog = document.querySelector('[data-ci-delivery-dialog="' + id + '"]');
            if (!dialog || dialog.open) return;
            dialog.showModal();
            dialog.querySelector('input:not([type=hidden])')?.focus();
        };

        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-ci-open]');
            if (opener) {
                open(opener.dataset.ciOpen);
                return;
            }
            const deliveryOpener = event.target.closest('[data-ci-delivery]');
            if (deliveryOpener) {
                openDelivery(deliveryOpener.dataset.ciDelivery);
            }
            // Closing (X or backdrop) is handled in partials.contract-implementation.
        });

        document.querySelectorAll('.ci-dialog[data-ci-dialog]').forEach(function (dialog) {
            dialog.addEventListener('close', function () {
                setParam(null);
                document.querySelector('[data-ci-open="' + dialog.dataset.ciDialog + '"]')?.focus();
            });
        });

        const reopen = document.querySelector('.ci-dialog[data-ci-reopen]');
        if (reopen) {
            open(reopen.dataset.ciDialog);
            // Show the result of the save (or the first error) inside the modal.
            (reopen.querySelector('.ci-note.is-danger, [aria-invalid="true"]') || reopen.querySelector('.ci-steps'))?.scrollIntoView({ block: 'center' });
        }

        const reopenDelivery = document.querySelector('[data-ci-reopen-delivery]');
        if (reopenDelivery) {
            openDelivery(reopenDelivery.dataset.ciDeliveryDialog);
            reopenDelivery.querySelector('.ci-note.is-danger')?.scrollIntoView({ block: 'nearest' });
        }
    })();
</script>
@endonce
