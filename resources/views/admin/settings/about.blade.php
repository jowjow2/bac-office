{{--
    Admin: what the public About page says about the BAC office and who sits
    on the BAC, the Secretariat and the Technical Working Group (BacProfile).
--}}
@extends('layouts.portal')

@section('title', 'About page')
@section('subtitle', 'The office details and the BAC composition shown on the public About page.')

@section('actions')
    <a href="{{ url('/about') }}" target="_blank" rel="noopener" class="ui-btn ui-btn--secondary"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> View About page</a>
@endsection

@section('content')
@php
    $old = old('members');
    $rows = is_array($old) ? array_values($old) : $profile['members'];
    $placeholders = [
        'bac' => ['Chairperson', 'Vice-Chairperson', 'Member'],
        'secretariat' => ['Head, BAC Secretariat', 'Member'],
        'twg' => ['Chairperson', 'Member'],
    ];
    $index = 0;
@endphp

<div class="ui-page as-page">
    @unless($available)
        <div class="ui-alert ui-alert--warning" role="alert">
            <i class="fas fa-database" aria-hidden="true"></i>
            <span>The database update for these settings is not applied yet, so they cannot be saved. Run the migrations (the <code>site_settings</code> table) first.</span>
        </div>
    @endunless

    @if($errors->any())
        <div class="ui-alert ui-alert--danger" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.about.update') }}" class="ui-stack" data-as-form>
        @csrf
        @method('PUT')

        <section class="ui-card" aria-labelledby="as-office-title">
            <header class="ui-card__head">
                <div>
                    <h2 class="ui-card__title" id="as-office-title">Office details</h2>
                    <p class="ui-card__desc">Shown in the About page header and contact section. Blank fields use the defaults set for Invitations to Bid.</p>
                </div>
            </header>
            <div class="ui-card__body">
                <div class="ui-fields">
                    @foreach([
                        'address' => ['Office address', 'e.g. BAC Secretariat, 2F Municipal Hall, San Jose', 'text'],
                        'hours' => ['Office hours', 'e.g. Monday to Friday, 8:00 AM – 5:00 PM', 'text'],
                        'email' => ['Email', 'e.g. bac@sanjose.gov.ph', 'email'],
                        'phone' => ['Phone', 'e.g. (043) 491-0000', 'text'],
                        'person' => ['Contact person', 'Full name', 'text'],
                        'person_position' => ['Contact person\'s position', 'e.g. Head, BAC Secretariat', 'text'],
                    ] as $field => [$label, $placeholder, $type])
                        <div class="ui-field">
                            <label class="ui-label" for="office-{{ $field }}">{{ $label }}</label>
                            <input id="office-{{ $field }}" type="{{ $type }}" name="office[{{ $field }}]" class="ui-input" maxlength="255" value="{{ old('office.'.$field, $profile['office'][$field]) }}" placeholder="{{ $placeholder }}">
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @foreach(\App\Support\BacProfile::GROUPS as $group => $groupLabel)
            @php $groupRows = array_values(array_filter($rows, fn ($row) => ($row['group'] ?? '') === $group)); @endphp
            <section class="ui-card" aria-labelledby="as-{{ $group }}-title">
                <header class="ui-card__head">
                    <div>
                        <h2 class="ui-card__title" id="as-{{ $group }}-title">{{ $groupLabel }}</h2>
                        <p class="ui-card__desc">{{ match ($group) { 'bac' => 'Chairperson, Vice-Chairperson and members, in the order to show.', 'secretariat' => 'The head and staff of the BAC Secretariat.', default => 'Members of the Technical Working Group, if the BAC has one.' } }}</p>
                    </div>
                    <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm" data-as-add="{{ $group }}"><i class="fas fa-plus" aria-hidden="true"></i> Add person</button>
                </header>
                <div class="ui-card__body">
                    <ol class="as-rows" data-as-rows="{{ $group }}">
                        @foreach($groupRows as $row)
                            <li class="as-row">
                                <input type="hidden" name="members[{{ $index }}][group]" value="{{ $group }}">
                                <input type="text" name="members[{{ $index }}][position]" class="ui-input" maxlength="120" value="{{ $row['position'] ?? '' }}" placeholder="Position" aria-label="Position">
                                <input type="text" name="members[{{ $index }}][name]" class="ui-input" maxlength="120" value="{{ $row['name'] ?? '' }}" placeholder="Full name" aria-label="Full name">
                                <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-as-remove aria-label="Remove this person"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                            </li>
                            @php $index++; @endphp
                        @endforeach
                    </ol>
                    <p class="as-empty" data-as-empty="{{ $group }}" @if($groupRows) hidden @endif>Nobody listed. This group is left off the About page.</p>
                    <template data-as-template="{{ $group }}">
                        <li class="as-row">
                            <input type="hidden" name="members[__I__][group]" value="{{ $group }}">
                            <input type="text" name="members[__I__][position]" class="ui-input" maxlength="120" placeholder="{{ $placeholders[$group][0] }}" aria-label="Position">
                            <input type="text" name="members[__I__][name]" class="ui-input" maxlength="120" placeholder="Full name" aria-label="Full name">
                            <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" data-as-remove aria-label="Remove this person"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                        </li>
                    </template>
                </div>
            </section>
        @endforeach

        <div class="as-foot">
            <p>Rows without a name are not saved. Changes show on the About page right away.</p>
            <button type="submit" class="ui-btn ui-btn--primary" @disabled(! $available)><i class="fas fa-floppy-disk" aria-hidden="true"></i> Save About page</button>
        </div>
    </form>
</div>

<style>
    .as-page { display: grid; gap: 16px; }
    .as-rows { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; counter-reset: as-row; }
    .as-row { display: grid; grid-template-columns: 26px minmax(0, 1fr) minmax(0, 1.4fr) auto; align-items: center; gap: 8px; counter-increment: as-row; }
    .as-row::before { content: counter(as-row); color: var(--ui-subtle); font-size: 12px; font-variant-numeric: tabular-nums; text-align: right; }
    .as-empty { margin: 0; color: var(--ui-muted); font-size: 13px; }
    .as-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); background: var(--ui-surface); }
    .as-foot p { margin: 0; color: var(--ui-muted); font-size: 13px; }
    @media (max-width: 640px) {
        .as-row { grid-template-columns: minmax(0, 1fr) auto; }
        .as-row::before { display: none; }
        .as-row input[name$="[position]"] { grid-column: 1 / -1; }
    }
</style>

<script>
    (function () {
        const form = document.querySelector('[data-as-form]');
        if (!form) return;
        let next = {{ $index }};
        const sync = function (group) {
            const list = form.querySelector('[data-as-rows="' + group + '"]');
            form.querySelector('[data-as-empty="' + group + '"]').hidden = list.children.length > 0;
        };
        form.addEventListener('click', function (event) {
            const add = event.target.closest('[data-as-add]');
            if (add) {
                const group = add.dataset.asAdd;
                const template = form.querySelector('[data-as-template="' + group + '"]');
                const list = form.querySelector('[data-as-rows="' + group + '"]');
                const holder = document.createElement('div');
                holder.innerHTML = template.innerHTML.replace(/__I__/g, String(next++));
                const row = holder.firstElementChild;
                list.appendChild(row);
                row.querySelector('input[name$="[position]"]').focus();
                sync(group);
                return;
            }
            const remove = event.target.closest('[data-as-remove]');
            if (remove) {
                const row = remove.closest('.as-row');
                const group = row.querySelector('input[name$="[group]"]').value;
                row.remove();
                sync(group);
            }
        });
    })();
</script>
@endsection
