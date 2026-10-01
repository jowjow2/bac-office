import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../resources/js/auth.js', import.meta.url), 'utf8');

function setup(fetch) {
    const messages = [];
    const statusLabel = { textContent: '' };
    const classes = { contains: () => false, toggle() {} };
    const button = { classList: classes, disabled: false };
    const form = { dataset: { resendUrl: '/forgot-password' }, querySelector: () => null };
    const elements = {
        forgotVerifyForm: form,
        forgotVerifyEmail: { value: 'reset@example.com' },
        forgotCodeStatus: { querySelector: () => statusLabel, classList: classes },
        forgotCodeTimer: {},
        resendPasswordCodeButton: button,
    };
    const context = vm.createContext({
        window: { addEventListener() {}, clearInterval() {} },
        document: { addEventListener() {}, querySelector: () => null, getElementById: id => elements[id] },
        FormData, fetch,
    });
    vm.runInContext(source, context);
    context.clearAuthMessage = () => {};
    context.clearFieldErrors = () => {};
    context.showFieldErrors = () => {};
    context.setButtonLoading = () => {};
    context.showAuthMessage = (type, message) => messages.push({ type, message });
    return { context, messages, button, statusLabel };
}

for (const errors of [[], { email: ['Account unavailable.'] }]) {
    test('resend displays server errors even without a visible matching field: ' + JSON.stringify(errors), async () => {
        const state = setup(async () => ({ json: async () => ({ ok: false, errors, message: 'Unable to deliver code.' }) }));
        await state.context.resendPasswordResetCode(state.button);
        assert.deepEqual(state.messages, [{ type: 'error', message: 'Unable to deliver code.' }]);
        assert.equal(state.button.disabled, false);
        assert.equal(state.statusLabel.textContent, 'Code expired. Request a new code.');
    });
}

test('network errors remain visible after the resend countdown updates', async () => {
    const state = setup(async () => { throw new Error('Network failed'); });
    await state.context.resendPasswordResetCode(state.button);
    assert.deepEqual(state.messages, [{ type: 'error', message: 'Unable to send a new code. Please try again.' }]);
    assert.equal(state.button.disabled, false);
});
