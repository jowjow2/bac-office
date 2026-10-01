{{-- Show/hide button for a password input; togglePassword() swaps the two icons. --}}
<button type="button" class="auth-password-toggle" onclick="togglePassword('{{ $target }}', this)" aria-label="Show password" aria-controls="{{ $target }}" aria-pressed="false">
    <svg class="password-icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="password-icon-hide hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 3l18 18"/><path d="M6.7 6.8C4.5 8.3 3 12 3 12s3.6 6 9 6c2.1 0 3.9-.6 5.3-1.5"/><path d="M14.9 5.3A10.7 10.7 0 0 1 21 12s-1.1 1.9-3.1 3.7"/>
    </svg>
</button>
