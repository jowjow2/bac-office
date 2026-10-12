// Philippine contact numbers: keep only digits, + ( ) - and spaces, and stop at the longest valid length.
// Mobile 09XX XXX XXXX is 11 digits; +63 / 63 numbers are 12. Landlines are shorter.
// Add data-ph-phone to an input to use it. The server checks the number again.
const PHONE_PATTERN = /^(\+?63|0)(9\d{9}|[2-8]\d{8,9})$/;

function limitPhoneNumber(input) {
    let value = input.value.replace(/[^0-9+\s\-()]/g, '').replace(/(?!^)\+/g, '');
    const digits = value.replace(/\D/g, '');
    const max = value.trim().startsWith('+') || digits.startsWith('63') ? 12 : 11;

    if (digits.length > max) {
        let count = 0;
        let kept = '';
        for (const character of value) {
            if (/\d/.test(character)) {
                if (count >= max) break;
                count += 1;
            }
            kept += character;
        }
        value = kept.trimEnd();
    }

    if (value !== input.value) input.value = value;
}

function checkPhoneNumber(input) {
    const value = input.value.trim();
    const valid = value === '' || PHONE_PATTERN.test(value.replace(/[\s\-()]/g, ''));
    input.setCustomValidity(valid ? '' : 'Enter a valid Philippine number, such as 0917 123 4567 or +63 917 123 4567.');
    return valid;
}

document.addEventListener('input', (event) => {
    const input = event.target.closest?.('[data-ph-phone]');
    if (!input) return;
    limitPhoneNumber(input);
    checkPhoneNumber(input);
});

// Say what is wrong when the person leaves the box, in the form's own error line when it has one.
document.addEventListener('focusout', (event) => {
    const input = event.target.closest?.('[data-ph-phone]');
    if (!input) return;
    const valid = checkPhoneNumber(input);
    const box = input.form?.querySelector(`[data-error-for="${input.name}"]`);
    if (box && !box.dataset.serverMessage) {
        box.textContent = valid ? '' : input.validationMessage;
    }
});
