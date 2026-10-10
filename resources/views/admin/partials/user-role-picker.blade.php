{{-- Role cards for the create/edit user dialogs. $prefix: create|edit, $selected: current role. --}}
@php
    $roles = [
        'admin' => ['fa-user-shield', 'Admin', 'Full BAC access'],
        'staff' => ['fa-user-tie', 'Staff', 'BAC secretariat work'],
        'bidder' => ['fa-briefcase', 'Bidder', 'Supplier or contractor'],
    ];
@endphp
<fieldset class="um-roles">
    <legend class="um-label">Account type</legend>
    <div class="um-roles__grid">
        @foreach($roles as $value => [$icon, $title, $hint])
            <label class="um-role">
                <input type="radio" name="role" value="{{ $value }}" id="{{ $prefix }}_role_{{ $value }}" @checked($selected === $value) required>
                <span class="um-role__icon" aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
                <span class="um-role__text"><strong>{{ $title }}</strong><small>{{ $hint }}</small></span>
            </label>
        @endforeach
    </div>
</fieldset>
