/*
 * Peso amount fields: thousands separators added while typing, with the
 * caret kept after the same digit. Shared by the portal forms and the
 * project wizard.
 */

export function groupDigits(field) {
    const raw = field.value;
    const caret = field.selectionStart ?? raw.length;
    const digitsBeforeCaret = raw.slice(0, caret).replace(/[^\d.]/g, '').length;

    let [whole, ...rest] = raw.replace(/[^\d.]/g, '').split('.');
    whole = whole.replace(/^0+(?=\d)/, '');
    const decimals = rest.length ? '.' + rest.join('').slice(0, 2) : '';
    const next = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + decimals;
    if (next === raw) return;

    field.value = next;
    let position = 0;
    for (let seen = 0; position < next.length && seen < digitsBeforeCaret; position++) {
        if (next[position] !== ',') seen++;
    }
    field.setSelectionRange(position, position);
}
