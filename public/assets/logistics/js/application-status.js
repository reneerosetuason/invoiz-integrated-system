document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-status-verification]');
    if (!form) return;
    const email = form.querySelector('[name="email"]');
    const code = form.querySelector('[name="code"]');
    const feedback = form.querySelector('[data-verification-feedback]');
    const csrf = form.querySelector('[name="_token"]').value;
    let verifying = false;
    let verifiedEmail = '';
    const request = async (url, body) => {
        const response = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify(body)});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Verification could not be completed. Please try again.');
        return data;
    };
    form.querySelector('[data-send-code]').addEventListener('click', async (event) => {
        if (!email.validity.valid || !email.value.trim()) { email.reportValidity(); return; }
        const button = event.currentTarget;
        button.disabled = true;
        feedback.textContent = 'Sending code…';
        try { await request(form.dataset.sendUrl, {email: email.value.trim()}); feedback.textContent = 'Code sent. Check your inbox, then enter the code below.'; }
        catch (error) { feedback.textContent = error.message; }
        finally { button.disabled = false; }
    });
    form.addEventListener('submit', async (event) => {
        const currentEmail = email.value.trim().toLowerCase();
        if (verifiedEmail === currentEmail) return;
        event.preventDefault();
        if (verifying) return;
        if (!email.validity.valid) { email.reportValidity(); return; }
        if (!/^\d{6}$/.test(code.value.trim())) { feedback.textContent = 'Send a code and enter the six digits from your email first.'; return; }
        verifying = true;
        feedback.textContent = 'Verifying…';
        try {
            await request(form.dataset.verifyUrl, {email: currentEmail, code: code.value.trim()});
            verifiedEmail = currentEmail;
            // Submit only after the server stored an email-bound session proof.
            HTMLFormElement.prototype.submit.call(form);
        } catch (error) { feedback.textContent = error.message; }
        finally { verifying = false; }
    });
});
