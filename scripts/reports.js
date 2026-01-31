

(function () {
  'use strict';

  function el(id) { return document.getElementById(id); }

  async function postJson(url, data) {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    return resp;
  }

  let locations = [];
  const filesToUpload = [];
  const MAX_PHOTOS = 8;
  const MAX_BYTES = 5 * 1024 * 1024;
  // No frontend coordinates collected; backend will derive coordinates based on state/lga or use central fallback.

  function showInlineError(id, msg) {
    const eln = el(id);
    if (!eln) return;
    eln.textContent = msg;
    eln.hidden = false;
  }

  function hideInlineError(id) {
    const eln = el(id);
    if (!eln) return;
    eln.hidden = true;
  }

  function populateStates() {
    const stateSel = el('state');
    if (!stateSel) return;
    stateSel.innerHTML = '<option value="">Select state</option>' + locations.map(s => `<option value="${s.state}">${capitalize(s.state)}</option>`).join('');
  }

  function capitalize(str) {
    if (!str) return str;
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function onStateChange() {
    const state = el('state').value;
    const lgaSel = el('lga');
    const placeSel = el('place');
    placeSel.innerHTML = '<option value="">Select state and LGA</option>';
    placeSel.disabled = true;
    if (!state) {
      lgaSel.innerHTML = '<option value="">Select state first</option>';
      return;
    }
    const s = locations.find(x => x.state === state);
    if (!s) return;
    lgaSel.innerHTML = '<option value="">Select LGA</option>' + s.lgas.map(l => `<option value="${l.lga}">${capitalize(l.lga)}</option>`).join('');
  }

  function onLgaChange() {
    const state = el('state').value;
    const lga = el('lga').value;
    const placeSel = el('place');
    placeSel.disabled = true;
    if (!state || !lga) {
      placeSel.innerHTML = '<option value="">Select state and LGA</option>';
      return;
    }
    const s = locations.find(x => x.state === state);
    if (!s) return;
    const l = s.lgas.find(x => x.lga === lga);
    if (!l) return;
    placeSel.innerHTML = '<option value="">Select place</option>' + l.wards.map(w => `<option value="${w}">${w.replace(/-/g,' ')}</option>`).join('');
    placeSel.disabled = false;
  }

  // Coordinates will be resolved on the server side; frontend does not write lat/lng.

  function toggleSocialHandle() {
    const platform = el('social-platform').value;
    const input = el('social-handle');
    if (!input) return;
    if (platform) {
      input.disabled = false;
      input.placeholder = 'Enter handle or profile link';
    } else {
      input.disabled = true;
      input.value = '';
    }
  }

  function addPhotoPreview(file, index) {
    const previews = el('photo-previews');
    if (!previews) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'preview-item';
    wrapper.dataset.index = index;

    const img = document.createElement('img');
    img.alt = file.name;
    img.className = 'preview-thumb';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn--ghost btn--small remove-photo';
    btn.textContent = 'Remove';
    btn.addEventListener('click', () => removePhoto(index));

    wrapper.appendChild(img);
    wrapper.appendChild(btn);
    previews.appendChild(wrapper);

    const reader = new FileReader();
    reader.onload = function (ev) { img.src = ev.target.result; };
    reader.readAsDataURL(file);
  }

  function refreshPreviews() {
    const previews = el('photo-previews');
    if (!previews) return;
    previews.innerHTML = '';
    filesToUpload.forEach((f, i) => addPhotoPreview(f, i));
  }

  function removePhoto(index) {
    if (index < 0 || index >= filesToUpload.length) return;
    filesToUpload.splice(index, 1);
    refreshPreviews();
  }

  function handlePhotoInput(e) {
    const inputFiles = Array.from(e.target.files || []);
    for (const f of inputFiles) {
      if (filesToUpload.length >= MAX_PHOTOS) break;
      if (f.size > MAX_BYTES) continue;
      const info = f.type || '';
      if (!info.startsWith('image/')) continue;
      filesToUpload.push(f);
    }
    refreshPreviews();
  }

  function enableSubmitIfConsented() {
    const chk = el('verify');
    const btn = el('submitBtn');
    if (!btn) return;
    btn.disabled = !chk.checked;
  }

  async function loadLocations() {
    try {
      const resp = await fetch('/locations.json');
      if (!resp.ok) return;
      locations = await resp.json();
      populateStates();
    } catch (e) {
      // ignore
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    hideInlineError('contact-error');
    const form = el('reportForm');
    const errorBox = document.getElementById('submissionError');
    const errorPre = document.getElementById('submissionErrorPre');
    if (errorBox) { errorBox.style.display = 'none'; errorPre.textContent = ''; }
    if (!form) return;
    const submitBtn = el('submitBtn');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting…'; }

    const email = el('email')?.value?.trim() || '';
    const phone = el('phone')?.value?.trim() || '';
    if (!email && !phone) {
      showInlineError('contact-error', 'Please provide at least an email or phone number.');
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Report'; }
      return;
    }

    // Coordinates are derived server-side; frontend will not submit GPS coordinates.

    const locationRaw = el('location')?.value ?? '';
    const locationTrimmed = locationRaw.trim();

    const payload = {
      title: el('title')?.value || '',
      type: el('type')?.value || '',
      description: el('description')?.value || '',
      state: el('state')?.value || '',
      lga: el('lga')?.value || '',
      // place is frontend-only; do not include
      datetime: el('datetime')?.value || '',
      location: locationTrimmed === '' ? null : locationTrimmed,
      address: el('address')?.value || '',
      victims: el('victims')?.value || 0,
      injured: el('injured')?.value || 0,
      dead: el('dead')?.value || 0,
      missing: el('missing')?.value || 0,
      full_name: el('full_name')?.value || '',
      nickname: el('nickname')?.value || '',
      email: email,
      phone: phone,
      social_platform: el('social-platform')?.value || null,
      social_handle: el('social-handle')?.value || null
    };

    try {
      const debug = (location.hostname === 'localhost' || location.hostname === '127.0.0.1');
      const url = '/api/incidents/reports.php' + (debug ? '?debug=1' : '');
      const resp = await postJson(url, payload);
      const text = await resp.text();
      let json = null;
      try { json = JSON.parse(text); } catch (e) { /* not JSON */ }

      if (!resp.ok) {
        const msg = (json && (json.error || json.message)) ? (json.error || json.message) : text || `HTTP ${resp.status}`;
        if (errorBox) { errorBox.style.display = 'block'; errorPre.textContent = msg + (json && json.debug ? '\n\nDEBUG:\n' + json.debug : ''); }
        else alert(msg);
        throw new Error('Server returned error');
      }

      if (!json || !json.success || !json.incident_id) {
        const msg = (json && (json.error || json.message)) ? (json.error || json.message) : 'Invalid response from server';
        if (errorBox) { errorBox.style.display = 'block'; errorPre.textContent = msg + (json && json.debug ? '\n\nDEBUG:\n' + json.debug : ''); }
        else alert(msg);
        throw new Error('Invalid response');
      }

      const incidentId = json.incident_id;

      let uploadResult = { uploaded: [], failed: [] };
      if (filesToUpload.length > 0 && window.Photos && typeof window.Photos.uploadPhotos === 'function') {
        uploadResult = await window.Photos.uploadPhotos(incidentId, filesToUpload);
      }

      const result = el('result');
      if (result) {
        el('permalink').textContent = `(not available)`;
        result.classList.remove('hidden');
        form.classList.add('hidden');
      }

    } catch (err) {
      console.error('Submission error', err);
      if (errorBox && !errorPre.textContent) {
        errorBox.style.display = 'block';
        errorPre.textContent = err && err.stack ? err.stack : String(err);
      }
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Report'; }
    }
  }

  function setup() {
    document.addEventListener('DOMContentLoaded', () => {
      loadLocations();
      
      // Map UI removed: no openMapBtn or modal handlers
      document.getElementById('confirmMapBtn')?.addEventListener('click', () => {});
      document.getElementById('cancelMapBtn')?.addEventListener('click', () => {});
      el('state')?.addEventListener('change', onStateChange);
      el('lga')?.addEventListener('change', onLgaChange);
      el('social-platform')?.addEventListener('change', toggleSocialHandle);
      el('photos')?.addEventListener('change', handlePhotoInput);
      el('verify')?.addEventListener('change', enableSubmitIfConsented);
      // map picker removed
      el('reportForm')?.addEventListener('submit', handleSubmit);
    });
  }

  setup();

})();

// Map UI removed from frontend; no modal/map initialization functions remain.

