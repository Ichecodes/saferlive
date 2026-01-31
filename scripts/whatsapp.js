document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('whatsappForm');
  const statusEl = document.getElementById('formStatus');
  if (!form) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    statusEl.textContent = '';

    const name = form.querySelector('#name').value.trim();
    const email = form.querySelector('#email').value.trim();
    const phone = form.querySelector('#phone').value.trim();
    const submitBtn = form.querySelector('button[type="submit"]');

    if (!name || !email) {
      statusEl.textContent = 'Name and email are required.';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Joining...';

    // basic phone validation (E.164-like): optional +, 7-15 digits total
    const phoneRegex = /^\+?[1-9]\d{6,14}$/;
    if (phone && !phoneRegex.test(phone)) {
      statusEl.style.color = '#ffb4b4';
      statusEl.textContent = 'Invalid phone number format.';
      return;
    }

    try {
      const data = new URLSearchParams();
      data.append('name', name);
      data.append('email', email);
      data.append('phone', phone);

      const resp = await fetch('api/subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString(),
        credentials: 'same-origin'
      });

      const json = await resp.json();
      if (resp.ok && json.success) {
        statusEl.style.color = ''; // inherit
        statusEl.textContent = 'Thanks — you are subscribed. We will send WhatsApp updates soon.';
        form.reset();
      } else {
        statusEl.style.color = '#ffb4b4';
        statusEl.textContent = json.error || 'Subscription failed.';
      }
    } catch (err) {
      statusEl.style.color = '#ffb4b4';
      statusEl.textContent = 'Network error — try again.';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Join Now';
    }
  });
});
