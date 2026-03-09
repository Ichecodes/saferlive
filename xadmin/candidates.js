(() => {
  const tbody = document.querySelector('#candidatesTable tbody');
  const flashMessage = document.getElementById('flashMessage');

  const counters = {
    new: document.getElementById('count-new'),
    approved: document.getElementById('count-approved'),
    rejected: document.getElementById('count-rejected'),
    duplicate: document.getElementById('count-duplicate')
  };

  const approveModal = document.getElementById('approveModal');
  const approveModalForm = document.getElementById('approveModalForm');
  const approveCandidateId = document.getElementById('approveCandidateId');
  const approveState = document.getElementById('approveState');
  const approveLga = document.getElementById('approveLga');
  const approveLatitude = document.getElementById('approveLatitude');
  const approveLongitude = document.getElementById('approveLongitude');
  const approveCoordPreview = document.getElementById('approveCoordPreview');
  const approveCancel = document.getElementById('approveCancel');

  let locations = [];
  let locationCoords = {};
  let pendingApproveForm = null;

  document.addEventListener('DOMContentLoaded', async () => {
    showFlashFromQuery();
    await loadLocationSources();
    loadCandidates();

    approveState?.addEventListener('change', onApproveStateChange);
    approveLga?.addEventListener('change', updateCoordinatesPreview);
    approveCancel?.addEventListener('click', closeApproveModal);
  });

  document.addEventListener('click', (event) => {
    const button = event.target;
    if (!(button instanceof HTMLElement)) {
      return;
    }

    if (button.classList.contains('open-approve-btn')) {
      const form = button.closest('form.review-form');
      if (form) {
        openApproveModal(form);
      }
    }
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form === approveModalForm) {
      const state = (approveState?.value || '').trim();
      const lga = (approveLga?.value || '').trim();
      if (!state || !lga) {
        event.preventDefault();
        window.alert('Please select both State and LGA before promotion.');
        return;
      }
      return;
    }

    if (!(form instanceof HTMLFormElement) || !form.classList.contains('review-form')) {
      return;
    }

    const action = form.dataset.action || '';

    if (action === 'approve') {
      event.preventDefault();
      openApproveModal(form);
      return;
    }

    const noteInput = form.querySelector('input[name="review_note"]');
    const note = window.prompt('Optional review note:', '');

    if (note === null) {
      event.preventDefault();
      return;
    }

    if (noteInput) {
      noteInput.value = note.trim();
    }

    if (action === 'duplicate') {
      const targetInput = form.querySelector('input[name="target_incident_id"]');
      const target = window.prompt('Optional existing incident ID:', '');
      if (targetInput) {
        targetInput.value = target ? target.trim() : '';
      }
    }
  });

  async function loadLocationSources() {
    try {
      const [locationsResp, coordsResp] = await Promise.all([
        fetch('../locations.json'),
        fetch('../locations_coords.json')
      ]);

      if (locationsResp.ok) {
        const data = await locationsResp.json();
        if (Array.isArray(data)) {
          locations = data;
        }
      }

      if (coordsResp.ok) {
        const data = await coordsResp.json();
        if (data && typeof data === 'object') {
          locationCoords = data;
        }
      }
    } catch (error) {
      locations = [];
      locationCoords = {};
    }
  }

  async function loadCandidates() {
    tbody.innerHTML = '<tr><td colspan="11" class="loading">Loading candidates...</td></tr>';

    try {
      const response = await fetch('candidates.php?_=' + Date.now(), { headers: { 'Accept': 'application/json' } });
      const payload = await response.json();

      if (!payload || payload.success !== true) {
        throw new Error((payload && payload.error) ? payload.error : 'Unable to load candidates');
      }

      renderSummary(payload.summary || {});
      renderRows(Array.isArray(payload.data) ? payload.data : []);
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="11" class="error">Failed to load candidates.</td></tr>';
    }
  }

  function renderSummary(summary) {
    counters.new.textContent = String(summary.new || 0);
    counters.approved.textContent = String(summary.approved || 0);
    counters.rejected.textContent = String(summary.rejected || 0);
    counters.duplicate.textContent = String(summary.duplicate || 0);
  }

  function renderRows(items) {
    tbody.innerHTML = '';

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="11" class="empty">No candidates found.</td></tr>';
      return;
    }

    items.forEach((item, index) => {
      const tr = document.createElement('tr');

      tr.appendChild(makeCell(String(index + 1)));
      tr.appendChild(makeCell(item.candidate_id || ''));
      tr.appendChild(makeCell(String(item.score || 0)));
      tr.appendChild(makeCell(item.incident_type || ''));
      tr.appendChild(makeCell(item.title || ''));
      tr.appendChild(makeCell(item.source_name || ''));
      tr.appendChild(makeCell(item.posted_at || '-'));
      tr.appendChild(makeCell((item.matched_places || []).join(', ')));

      const urlCell = document.createElement('td');
      if (item.source_url) {
        const anchor = document.createElement('a');
        anchor.href = item.source_url;
        anchor.textContent = 'Open';
        anchor.target = '_blank';
        anchor.rel = 'noopener noreferrer';
        urlCell.appendChild(anchor);
      }
      tr.appendChild(urlCell);

      const statusCell = document.createElement('td');
      const statusBadge = document.createElement('span');
      statusBadge.className = 'status-chip status-' + (item.current_status || 'new');
      statusBadge.textContent = item.current_status || 'new';
      statusCell.appendChild(statusBadge);
      tr.appendChild(statusCell);

      const actionCell = document.createElement('td');
      actionCell.className = 'action-cell';

      actionCell.appendChild(buildActionForm('promote.php', 'approve', 'Approve', item));
      actionCell.appendChild(buildActionForm('reject.php', 'rejected', 'Reject', item));
      actionCell.appendChild(buildActionForm('duplicate.php', 'duplicate', 'Duplicate', item));

      tr.appendChild(actionCell);
      tbody.appendChild(tr);
    });
  }

  function buildActionForm(actionUrl, actionKey, label, item) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = actionUrl;
    form.className = 'review-form';
    form.dataset.action = actionKey;

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'candidate_id';
    idInput.value = item.candidate_id || '';
    form.appendChild(idInput);

    const noteInput = document.createElement('input');
    noteInput.type = 'hidden';
    noteInput.name = 'review_note';
    noteInput.value = '';
    form.appendChild(noteInput);

    if (actionKey === 'approve') {
      form.dataset.defaultState = item.state || '';
      form.dataset.defaultLga = item.lga || '';

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn--small btn--success open-approve-btn';
      button.textContent = label;
      form.appendChild(button);

      return form;
    }

    if (actionKey === 'duplicate') {
      const targetInput = document.createElement('input');
      targetInput.type = 'hidden';
      targetInput.name = 'target_incident_id';
      targetInput.value = '';
      form.appendChild(targetInput);
    }

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'btn btn--small ' + (actionKey === 'duplicate' ? 'btn--warning' : 'btn--ghost');
    button.textContent = label;
    form.appendChild(button);

    return form;
  }

  function openApproveModal(form) {
    if (!approveModal || !approveModalForm || !approveState || !approveLga) {
      return;
    }

    pendingApproveForm = form;

    const candidateIdInput = form.querySelector('input[name="candidate_id"]');
    const candidateId = candidateIdInput ? candidateIdInput.value : '';
    if (approveCandidateId) {
      approveCandidateId.value = candidateId;
    }

    const defaultState = (form.dataset.defaultState || '').trim();
    const defaultLga = (form.dataset.defaultLga || '').trim();

    populateApproveStates(defaultState);
    onApproveStateChange(defaultLga);

    approveModal.classList.remove('hidden');
    approveModal.setAttribute('aria-hidden', 'false');
  }

  function closeApproveModal() {
    if (!approveModal || !approveModalForm) {
      return;
    }

    approveModal.classList.add('hidden');
    approveModal.setAttribute('aria-hidden', 'true');
    approveModalForm.reset();
    pendingApproveForm = null;

    if (approveLatitude) {
      approveLatitude.value = '9.082';
    }
    if (approveLongitude) {
      approveLongitude.value = '8.6753';
    }
    if (approveCoordPreview) {
      approveCoordPreview.textContent = 'Default coordinates: 9.082, 8.6753 (fallback)';
    }
  }

  function populateApproveStates(defaultState) {
    if (!approveState) {
      return;
    }

    approveState.innerHTML = '<option value="">Select state</option>';

    locations.forEach((stateObj) => {
      const option = document.createElement('option');
      option.value = stateObj.state;
      option.textContent = toTitle(stateObj.state);
      approveState.appendChild(option);
    });

    const matchedState = findMatchingState(defaultState);
    if (matchedState) {
      approveState.value = matchedState;
    }
  }

  function onApproveStateChange(defaultLga) {
    if (!approveState || !approveLga) {
      return;
    }

    const selectedState = approveState.value;
    const stateObj = locations.find((s) => s.state === selectedState);

    if (!stateObj) {
      approveLga.innerHTML = '<option value="">Select state first</option>';
      updateCoordinatesPreview();
      return;
    }

    approveLga.innerHTML = '<option value="">Select LGA</option>';
    (stateObj.lgas || []).forEach((lgaObj) => {
      const option = document.createElement('option');
      option.value = lgaObj.lga;
      option.textContent = toTitle(lgaObj.lga);
      approveLga.appendChild(option);
    });

    const matchedLga = findMatchingLga(stateObj, typeof defaultLga === 'string' ? defaultLga : '');
    if (matchedLga) {
      approveLga.value = matchedLga;
    }

    updateCoordinatesPreview();
  }

  function updateCoordinatesPreview() {
    const stateValue = (approveState?.value || '').trim();
    const lgaValue = (approveLga?.value || '').trim();

    const coords = findCoordinates(stateValue, lgaValue);

    if (coords) {
      if (approveLatitude) {
        approveLatitude.value = String(coords.lat);
      }
      if (approveLongitude) {
        approveLongitude.value = String(coords.lng);
      }
      if (approveCoordPreview) {
        approveCoordPreview.textContent = `Default coordinates: ${coords.lat}, ${coords.lng}`;
      }
      return;
    }

    if (approveLatitude) {
      approveLatitude.value = '9.082';
    }
    if (approveLongitude) {
      approveLongitude.value = '8.6753';
    }
    if (approveCoordPreview) {
      approveCoordPreview.textContent = 'Default coordinates: 9.082, 8.6753 (fallback)';
    }
  }

  function findCoordinates(stateRaw, lgaRaw) {
    if (!stateRaw || !lgaRaw || !locationCoords || typeof locationCoords !== 'object') {
      return null;
    }

    const stateKeyNorm = normalizeName(stateRaw);
    const lgaKeyNorm = normalizeName(lgaRaw);

    let stateCoords = null;
    Object.keys(locationCoords).forEach((key) => {
      if (!stateCoords && normalizeName(key) === stateKeyNorm) {
        stateCoords = locationCoords[key];
      }
    });

    if (!stateCoords || typeof stateCoords !== 'object') {
      return null;
    }

    let match = null;
    Object.keys(stateCoords).forEach((key) => {
      if (!match && normalizeName(key) === lgaKeyNorm) {
        const coord = stateCoords[key];
        if (coord && typeof coord === 'object' && isFiniteNumber(coord.lat) && isFiniteNumber(coord.lng)) {
          match = { lat: Number(coord.lat), lng: Number(coord.lng) };
        }
      }
    });

    return match;
  }

  function findMatchingState(value) {
    const target = normalizeName(value || '');
    if (!target) {
      return '';
    }

    const found = locations.find((s) => normalizeName(s.state) === target);
    return found ? found.state : '';
  }

  function findMatchingLga(stateObj, value) {
    const target = normalizeName(value || '');
    if (!target || !stateObj || !Array.isArray(stateObj.lgas)) {
      return '';
    }

    const found = stateObj.lgas.find((l) => normalizeName(l.lga) === target);
    return found ? found.lga : '';
  }

  function normalizeName(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/[\-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function toTitle(value) {
    return String(value || '')
      .replace(/[-_]+/g, ' ')
      .replace(/\b\w/g, (m) => m.toUpperCase());
  }

  function isFiniteNumber(value) {
    const num = Number(value);
    return Number.isFinite(num);
  }

  function makeCell(value) {
    const td = document.createElement('td');
    td.textContent = value || '';
    return td;
  }

  function showFlashFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const msg = params.get('msg');
    const type = params.get('type') || 'info';

    if (!msg) {
      return;
    }

    flashMessage.classList.remove('hidden');
    flashMessage.classList.add('flash-' + type);
    flashMessage.textContent = msg;
  }
})();
