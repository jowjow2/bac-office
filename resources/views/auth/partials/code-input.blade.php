{{-- 6-digit code: one real input (typing, paste and one-time-code autofill) shown as six boxes. --}}
<div class="auth-otp" data-otp>
    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="auth-otp__input" required autocomplete="one-time-code" aria-label="{{ $label }}">
    <div class="auth-otp__slots" aria-hidden="true">
        @for($i = 0; $i < 6; $i++)
            <span class="auth-otp__slot"></span>
        @endfor
    </div>
</div>
