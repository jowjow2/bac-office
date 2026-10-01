import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../resources/js/bid-management.js', import.meta.url), 'utf8');
const start = source.indexOf("    body.addEventListener('click', event => {", source.indexOf('// Review Bid tabs'));
const end = source.indexOf("    body.addEventListener('keydown'", start);

for (const action of ['pass_preliminary', 'evaluate', 'approve_award', 'technical-opening', 'financial-opening']) {
    test('Proceed opens ' + action + ' without submitting', () => {
        let listener;
        let focused = false;
        let scrolled = false;
        const target = {
            dataset: { brAction: action }, open: false,
            matches: () => !action.endsWith('-opening'),
            closest: () => null,
            scrollIntoView: () => { scrolled = true; },
            querySelector: () => ({ focus: () => { focused = true; } }),
            submit: () => assert.fail('Proceed must not submit a form'),
        };
        const other = { dataset: { brAction: 'unrelated' }, open: false };
        const body = {
            addEventListener: (_, fn) => { listener = fn; },
            querySelectorAll: () => [other, target],
        };
        runInNewContext(source.slice(start, end), { body });
        listener({ target: { closest: () => ({ disabled: false, dataset: { brProceed: action } }) } });
        assert.equal(scrolled, true);
        assert.equal(focused, true);
        assert.equal(target.open, !action.endsWith('-opening'));
        assert.equal(other.open, false);
    });
}

test('disabled Proceed does not open any form', () => {
    let listener;
    const body = {
        addEventListener: (_, fn) => { listener = fn; },
        querySelectorAll: () => assert.fail('Disabled Proceed must not navigate'),
    };
    runInNewContext(source.slice(start, end), { body });
    listener({ target: { closest: () => ({ disabled: true }) } });
});
