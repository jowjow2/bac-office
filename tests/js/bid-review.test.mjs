import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../resources/js/bid-management.js', import.meta.url), 'utf8');
const start = source.indexOf("    body.addEventListener('click', event => {", source.indexOf('// Review Bid tabs'));
const end = source.indexOf("    body.addEventListener('keydown'", start);

function setup(action, target) {
    let listener;
    const proceed = { disabled: false, dataset: { brProceed: action } };
    const body = {
        addEventListener: (_, fn) => { listener = fn; },
        querySelectorAll: () => [target],
    };
    runInNewContext(source.slice(start, end), { body });
    const click = () => listener({ target: { closest: selector => selector === '[data-br-proceed]' ? proceed : null } });
    return { click, proceed };
}

for (const action of ['pass_preliminary', 'evaluate', 'approve_award']) {
    test('Proceed submits the selected valid ' + action + ' decision once', () => {
        let submissions = 0;
        const form = {
            dataset: {},
            reportValidity: () => true,
            requestSubmit: () => { submissions++; },
        };
        const target = {
            dataset: { brAction: action }, open: false,
            matches: selector => selector === 'details',
            querySelector: selector => selector.startsWith('form.br-form') ? form : null,
        };
        const { click, proceed } = setup(action, target);
        click();
        click();
        assert.equal(submissions, 1);
        assert.equal(target.open, true);
        assert.equal(form.dataset.brFooterSubmit, '1');
        assert.equal(proceed.disabled, true);
    });
}

test('Proceed navigates to the technical opening section', () => {
    let scrolled = false;
    let focused = false;
    const target = {
        dataset: { brAction: 'technical-opening' },
        matches: () => false,
        closest: () => null,
        scrollIntoView: () => { scrolled = true; },
        querySelector: () => ({ focus: () => { focused = true; } }),
    };
    setup('technical-opening', target).click();
    assert.equal(scrolled, true);
    assert.equal(focused, true);
});

test('Proceed submits the financial opening password only when valid', () => {
    let valid = false;
    let submissions = 0;
    const form = {
        reportValidity: () => valid,
        requestSubmit: () => { submissions++; },
    };
    const target = {
        dataset: { brAction: 'financial-opening' },
        querySelector: () => form,
    };
    const { click, proceed } = setup('financial-opening', target);
    click();
    assert.equal(submissions, 0);
    assert.equal(proceed.disabled, false);
    valid = true;
    click();
    click();
    assert.equal(submissions, 1);
    assert.equal(proceed.disabled, true);
});

test('disabled Proceed does not open or submit any action', () => {
    const target = { dataset: { brAction: 'evaluate' } };
    const { click, proceed } = setup('evaluate', target);
    proceed.disabled = true;
    click();
});