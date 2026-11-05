import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('public/assets/logistics/js/center-application-apply-2.js', 'utf8');
function wizard(fetch) {
    const context = vm.createContext({
        document: { currentScript: { dataset: { config: '["/send","/verify"]' } }, querySelector: () => null },
        fetch, setInterval: () => 1, clearInterval: () => {},
    });
    vm.runInContext(source, context);
    const state = context.centerWizard({});
    state.$refs = { email: { value: 'one@example.test' }, region: { value: '130000000' }, postal_code: { value: '1100' } };
    state.$nextTick = () => {};
    return state;
}
const response = (data, ok = true) => ({ ok, json: async () => data });

test('an old send response cannot restore verification for a changed email', async () => {
    let complete;
    const state = wizard(() => new Promise(resolve => { complete = resolve; }));
    const pending = state.sendVerifyCode();
    state.$refs.email.value = 'two@example.test';
    state.resetEmailVerification();
    complete(response({ message: 'Sent' }));
    await pending;
    assert.equal(state.codeSentTo, '');
    assert.equal(state.verifyBusy, false);
    assert.equal(state.resendWait, 0);
});

test('an old verify response cannot verify a changed email', async () => {
    let complete;
    const state = wizard(() => new Promise(resolve => { complete = resolve; }));
    state.codeSentTo = 'one@example.test'; state.verifyCode = '123456';
    const pending = state.confirmVerifyCode();
    state.$refs.email.value = 'two@example.test'; state.resetEmailVerification();
    complete(response({ email: 'one@example.test', message: 'Verified' }));
    await pending;
    assert.equal(state.verifiedEmail, '');
    assert.equal(state.emailVerifiedNow(), false);
});

test('duplicate send and verification requests are suppressed while busy', async () => {
    let calls = 0, complete;
    const state = wizard(() => { calls++; return new Promise(resolve => { complete = resolve; }); });
    const pending = state.sendVerifyCode();
    await state.sendVerifyCode(); await state.confirmVerifyCode();
    assert.equal(calls, 1);
    complete(response({ message: 'Sent' })); await pending;
    await state.sendVerifyCode(); assert.equal(calls, 1); // Resend cooldown.
});

test('HTML redirects and malformed responses cannot fake successful verification', async () => {
    const state = wizard(async () => response({}));
    await state.sendVerifyCode(); assert.equal(state.codeSentTo, '');
    state.codeSentTo = 'one@example.test'; state.verifyCode = '123456';
    await state.confirmVerifyCode(); assert.equal(state.verifiedEmail, '');
    assert.equal(state.verifyBusy, false);
});

test('network failures unlock verification so the applicant can retry', async () => {
    const state = wizard(async () => { throw Error('offline'); });
    await state.sendVerifyCode(); assert.equal(state.verifyBusy, false); assert.ok(state.verifyMsg);
    state.codeSentTo = 'one@example.test'; state.verifyCode = '123456';
    await state.confirmVerifyCode(); assert.equal(state.verifyBusy, false); assert.ok(state.verifyMsg);
});

test('final submission rechecks earlier fields after review edits', () => {
    const state = wizard();
    state.step = 3; state.termsAccepted = true;
    state.validateStep1 = () => true;
    state.$refs.postal_code.value = '123';
    let prevented = false;
    state.submitApplication({ preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true); assert.equal(state.step, 1); assert.equal(state.submitting, false);
});

test('invalid optional uploads prevent review until removed or replaced', () => {
    const state = wizard();
    state.files = { valid_id: { name: 'id.pdf' }, business_registration: { name: 'business.pdf' } };
    const input = { value: 'bad.exe', files: [{ name: 'bad.exe', size: 10 }] };
    state.onFileChange('other', input);
    assert.equal(input.value, ''); assert.equal(state.validateDocuments(), false);
    input.files = []; state.onFileChange('other', input);
    assert.equal(state.validateDocuments(), true);
});
