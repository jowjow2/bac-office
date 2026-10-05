{{--
    Fields of the create/edit user dialogs (admin/users), by account type:
      - staff and end-user offices: office, position, office contact number
      - admin: position, contact number
      - bidder: company, registration no., contact number, business address
    then the sign-in details every account has.

    $prefix: create|edit. Create fills from old(); edit is filled by
    openEditUserModal() in admin/users.blade.php.
--}}
@php
    $isCreate = $prefix === 'create';
    $value = fn (string $field, string $default = '') => $isCreate ? (string) old($field, $default) : '';
    $role = $isCreate ? old('role', 'bidder') : 'bidder';
@endphp

@include('admin.partials.user-role-picker', ['prefix' => $prefix, 'selected' => $role])

@unless($contactColumnsReady ?? true)
    <p class="um-note"><i class="fas fa-database" aria-hidden="true"></i> Position and contact number are saved once the database update for them is applied.</p>
@endunless

{{-- Staff, end-user offices and admins --}}
<div id="{{ $prefix }}OfficeSection" class="um-group" data-role-group="admin staff end_user">
    <h3 class="um-section" id="{{ $prefix }}OfficeHeading">Office assignment</h3>

    <div id="{{ $prefix }}OfficeField" class="um-field" data-role-group="staff end_user">
        <label for="{{ $prefix }}_office" id="{{ $prefix }}OfficeLabel" class="um-label">Office <span class="um-req">*</span></label>
        <select name="office" id="{{ $prefix }}_office" class="form-select">
            <option value="">Select office</option>
            @foreach($staffOffices as $office)
                <option value="{{ $office }}" data-office-role="staff" @selected($isCreate && old('office') === $office)>{{ $office }}</option>
            @endforeach
            @foreach($endUserOffices as $office)
                <option value="{{ $office }}" data-office-role="end_user" @selected($isCreate && old('office') === $office)>{{ $office }}</option>
            @endforeach
        </select>
        <span class="um-hint" data-office-hint></span>
    </div>

    <div class="um-grid">
        <div class="um-field">
            <label for="{{ $prefix }}_position" class="um-label">Position / designation <span class="um-opt">(optional)</span></label>
            <input type="text" name="position" id="{{ $prefix }}_position" value="{{ $value('position') }}" maxlength="120" class="form-input" autocomplete="off" data-position-placeholder>
        </div>
        <div class="um-field">
            <label for="{{ $prefix }}_contact_number" class="um-label" data-contact-label>Office contact no. <span class="um-opt">(optional)</span></label>
            <input type="tel" name="contact_number" id="{{ $prefix }}_contact_number" value="{{ $value('contact_number') }}" maxlength="50" class="form-input" placeholder="e.g. (043) 457-1234" autocomplete="off" data-role-input="admin staff end_user">
        </div>
    </div>
</div>

{{-- Bidders --}}
<div id="{{ $prefix }}BidderFields" class="um-group" data-role-group="bidder">
    <h3 class="um-section">Company</h3>
    <div class="um-grid">
        <div class="um-field">
            <label for="{{ $prefix }}_company" class="um-label">Company name <span class="um-req">*</span></label>
            <input type="text" name="company" id="{{ $prefix }}_company" value="{{ $value('company') }}" maxlength="255" class="form-input" placeholder="e.g. Juan Dela Cruz Construction" autocomplete="off">
        </div>
        <div class="um-field">
            <label for="{{ $prefix }}_registration_no" class="um-label">Business registration no. <span class="um-opt">(optional)</span></label>
            <input type="text" name="registration_no" id="{{ $prefix }}_registration_no" value="{{ $value('registration_no') }}" maxlength="255" class="form-input" placeholder="DTI / SEC / CDA no." autocomplete="off">
        </div>
        <div class="um-field">
            <label for="{{ $prefix }}_bidder_contact_number" class="um-label">Contact no. <span class="um-opt">(optional)</span></label>
            <input type="tel" name="contact_number" id="{{ $prefix }}_bidder_contact_number" value="{{ $value('contact_number') }}" maxlength="50" class="form-input" placeholder="09XX XXX XXXX" autocomplete="off" data-role-input="bidder">
        </div>
        <div class="um-field">
            <label for="{{ $prefix }}_business_address" class="um-label">Business address <span class="um-opt">(optional)</span></label>
            <input type="text" name="business_address" id="{{ $prefix }}_business_address" value="{{ $value('business_address') }}" maxlength="500" class="form-input" placeholder="Street, barangay, municipality, province" autocomplete="off">
        </div>
    </div>
</div>

<h3 class="um-section">Sign-in details</h3>
<div class="um-grid">
    <div class="um-field">
        <label for="{{ $prefix }}_name" class="um-label" data-name-label>Full name <span class="um-req">*</span></label>
        <input type="text" name="name" id="{{ $prefix }}_name" value="{{ $value('name') }}" required maxlength="255" class="form-input" autocomplete="off" data-name-placeholder>
    </div>
    <div class="um-field">
        <label for="{{ $prefix }}_email" class="um-label">Email <span class="um-req">*</span></label>
        <input type="email" name="email" id="{{ $prefix }}_email" value="{{ $value('email') }}" required maxlength="255" class="form-input" placeholder="name@example.com" autocomplete="off">
    </div>
</div>
<div class="um-grid">
    <div class="um-field">
        <label for="{{ $prefix }}_username" class="um-label">Username <span class="um-opt">(optional)</span></label>
        <input type="text" name="username" id="{{ $prefix }}_username" value="{{ $value('username') }}" class="form-input" placeholder="Email also works to sign in" autocomplete="off">
    </div>
    <div class="um-field">
        <label for="{{ $prefix }}_status" class="um-label">Account status</label>
        <select name="status" id="{{ $prefix }}_status" class="form-select" required>
            <option value="active" @selected($value('status', 'active') === 'active')>Active</option>
            <option value="pending" @selected($value('status') === 'pending')>Pending</option>
            <option value="rejected" @selected($value('status') === 'rejected')>Rejected</option>
        </select>
    </div>
</div>
<div class="um-field">
    @if($isCreate)
        <label for="create_password" class="um-label">Password <span class="um-req">*</span></label>
    @else
        <label for="edit_password" class="um-label">New password <span class="um-opt">(optional)</span></label>
    @endif
    <div class="um-password">
        <input type="password" name="password" id="{{ $prefix }}_password" @if($isCreate) required @endif minlength="6" class="form-input" @unless($isCreate) placeholder="Leave blank to keep it" @endunless autocomplete="new-password">
        <button type="button" class="um-password__toggle" data-password-toggle aria-label="Show password"><i class="fas fa-eye" aria-hidden="true"></i></button>
    </div>
    <span class="um-hint">At least 6 characters. Share it with the user through a secure channel.</span>
</div>
