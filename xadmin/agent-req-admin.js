/*
  Admin dashboard Javascript for adminx.html

  Responsibilities:
  - Fetch job requests from GET /safer/api/jobs/jobs.php
  - Render table rows using the exact `job_requests` schema
    (id, full_name, phone, email, agent_type, job_type, state, lga, address, status, created_at)
  - Provide a per-row status <select> with allowed values: pending, reviewed, assigned, cancelled
  - On status change, POST update to /safer/api/jobs.php with payload { job_id, id, status, action: 'update_status' }
  - Provide a modal to View full request details and update status there as well
  - Handle loading state, empty state, and basic error handling

  Notes:
  - This file uses only vanilla JavaScript and DOM APIs.
  - It intentionally avoids referencing removed fields: job_location, latitude, longitude.
  - Keep this file in sync with the markup in xadmin/adminx.html (table id: requestsTable)
*/

(function(){
  'use strict';

  // -----------------------------
  // Configuration / Endpoints
  // -----------------------------
  // Endpoint that returns a JSON list or single item when ?id= is provided
  const LIST_URL = '/safer/api/jobs/jobs.php';
  // Endpoint that accepts POST updates for status
  const UPDATE_URL = '/safer/api/jobs.php';

  // Allowed status values for validation and dropdowns
  const STATUSES = ['pending','reviewed','assigned','cancelled'];

  // -----------------------------
  // Small DOM helpers
  // -----------------------------
  function el(id){ return document.getElementById(id); }

  // Escape user provided content before inserting into HTML to prevent XSS
  function escapeHtml(input){
    if (input === null || input === undefined) return '';
    return String(input).replace(/[&<>\"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c];
    });
  }

  // -----------------------------
  // Network helpers
  // -----------------------------
  // Fetch JSON with basic error handling. Returns object { success, data, error }
  async function fetchJson(url){
    try{
      const resp = await fetch(url, { cache: 'no-store' });
      if (!resp.ok) return { success: false, error: resp.statusText };
      const json = await resp.json();
      return json;
    } catch (err){
      console.error('fetchJson error', err);
      return { success: false, error: String(err) };
    }
  }

  // POST JSON helper
  async function postJson(url, body){
    try{
      const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const json = await resp.json();
      return { ok: resp.ok, json };
    } catch (err){
      console.error('postJson error', err);
      return { ok: false, json: { success: false, error: String(err) } };
    }
  }

  // -----------------------------
  // UI states: loading / empty / error rows
  // -----------------------------
  function showLoading(){
    const tbody = el('requestsTable').querySelector('tbody');
    tbody.innerHTML = '<tr><td colspan="11" class="loading">Loading...</td></tr>';
  }

  function showEmpty(){
    const tbody = el('requestsTable').querySelector('tbody');
    tbody.innerHTML = '<tr><td colspan="11" class="empty">No requests found</td></tr>';
  }

  function showError(msg){
    const tbody = el('requestsTable').querySelector('tbody');
    tbody.innerHTML = `<tr><td colspan="11" class="error">${escapeHtml(msg)}</td></tr>`;
  }

  // -----------------------------
  // Rendering logic
  // -----------------------------
  // Render the table rows; rows is expected to be an array of objects matching the DB schema
  function renderTable(rows){
    const tbody = el('requestsTable').querySelector('tbody');
    tbody.innerHTML = '';

    if (!rows || rows.length === 0){
      showEmpty();
      return;
    }

    rows.forEach(r => {
      // Build status <select> with current value selected
      const statusOptions = STATUSES.map(s => `<option value="${s}" ${s === (r.status||'pending') ? 'selected' : ''}>${s}</option>`).join('');

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(r.id)}</td>
        <td>${escapeHtml(r.full_name)}</td>
        <td>${escapeHtml(r.phone)}</td>
        <td>${escapeHtml(r.agent_type)}</td>
        <td>${escapeHtml(r.job_type)}</td>
        <td>${escapeHtml(r.state || '')}</td>
        <td>${escapeHtml(r.lga || '')}</td>
        <td>${escapeHtml(r.address || '')}</td>
        <td><select class="status-select">${statusOptions}</select></td>
        <td>${escapeHtml(r.created_at)}</td>
        <td><button class="view-btn" data-id="${escapeHtml(r.id)}">View</button></td>
      `;

      // Attach job id to select for later reference
      const select = tr.querySelector('.status-select');
      select.dataset.jobId = r.id;
      // Add class for visual styling based on status
      select.classList.add((r.status || 'pending'));

      // Attach change handler
      select.addEventListener('change', async (e) => {
        const newStatus = e.target.value;
        const jobId = e.target.dataset.jobId;
        // Optimistically disable the control while updating
        e.target.disabled = true;
        try{
          const { ok, json } = await postJson(UPDATE_URL, { job_id: jobId, id: jobId, status: newStatus, action: 'update_status' });
          if (!ok || !json.success){
            alert('Update failed: ' + (json.error || 'Unknown error'));
            // Reload list to restore previous state
            await loadRequests();
          } else {
            // update succeeded; refresh to reflect any ordering/status changes
            await loadRequests();
          }
        } catch (err){
          console.error(err);
          alert('Network error while updating status');
          await loadRequests();
        } finally{
          e.target.disabled = false;
        }
      });

      // Attach view button handler
      tr.querySelector('.view-btn').addEventListener('click', () => openDetail(r.id));

      tbody.appendChild(tr);
    });
  }

  // -----------------------------
  // Detail modal
  // -----------------------------
  // Open detail view for a single request and populate modal controls
  async function openDetail(id){
    const json = await fetchJson(LIST_URL + '?id=' + encodeURIComponent(id));
    if (!json || !json.success){
      alert('Unable to load request: ' + (json && json.error ? json.error : 'Unknown'));
      return;
    }
    const d = json.data;

    // Render only fields from the approved schema — do NOT reference removed columns
    el('detailBody').innerHTML = `
      <dl class="detail-list">
        <dt>ID</dt><dd>${escapeHtml(d.id)}</dd>
        <dt>Full Name</dt><dd>${escapeHtml(d.full_name)}</dd>
        <dt>Phone</dt><dd>${escapeHtml(d.phone)}</dd>
        <dt>Email</dt><dd>${escapeHtml(d.email || '—')}</dd>
        <dt>Agent Type</dt><dd>${escapeHtml(d.agent_type)}</dd>
        <dt>Job Type</dt><dd>${escapeHtml(d.job_type)}</dd>
        <dt>State</dt><dd>${escapeHtml(d.state || '—')}</dd>
        <dt>LGA</dt><dd>${escapeHtml(d.lga || '—')}</dd>
        <dt>Address</dt><dd>${escapeHtml(d.address || '—')}</dd>
        <dt>Status</dt><dd id="currentStatus">${escapeHtml(d.status)}</dd>
        <dt>Created</dt><dd>${escapeHtml(d.created_at)}</dd>
      </dl>
    `;

    // Set modal status select to current value and attach dataset for update
    el('statusSelect').value = d.status || 'pending';
    el('updateStatusBtn').dataset.jobId = d.id;
    el('detailModal').classList.remove('hidden');
  }

  function closeDetail(){ el('detailModal').classList.add('hidden'); }

  // Handler invoked from the modal to update status for the currently loaded job
  async function modalUpdate(){
    const jobId = el('updateStatusBtn').dataset.jobId;
    const status = el('statusSelect').value;
    el('updateStatusBtn').disabled = true;
    try{
      const { ok, json } = await postJson(UPDATE_URL, { job_id: jobId, id: jobId, status: status, action: 'update_status' });
      if (!ok || !json.success){
        alert('Update failed: ' + (json.error || 'Unknown'));
      }
      // Refresh list and close modal regardless (keeps UI in sync)
      await loadRequests();
      closeDetail();
    } catch (err){
      console.error(err);
      alert('Network error while updating status');
    } finally{
      el('updateStatusBtn').disabled = false;
    }
  }

  // -----------------------------
  // Load requests and attach top-level handlers
  // -----------------------------
  async function loadRequests(){
    showLoading();
    const json = await fetchJson(LIST_URL);
    if (!json || !json.success){
      showError(json && json.error ? json.error : 'Unable to load requests');
      return;
    }
    renderTable(json.data);
  }

  // Initialize on DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    // Load the initial list
    loadRequests();

    // Modal controls
    const closeBtn = el('closeDetail');
    if (closeBtn) closeBtn.addEventListener('click', closeDetail);
    const updateBtn = el('updateStatusBtn');
    if (updateBtn) updateBtn.addEventListener('click', modalUpdate);
  });

})();
(function(){
  'use strict';

  // Helper to get element by id
  function el(id){ return document.getElementById(id); }

  // Safely escape user-provided strings for insertion into the DOM
  function escapeHtml(s){ if (!s && s !== 0) return ''; return String(s).replace(/[&<>"]|'/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

  // API endpoints per requirements
  const LIST_URL = '/safer/api/jobs/jobs.php';
  const UPDATE_URL = '/safer/api/jobs.php';

  // Render table rows based on exact `job_requests` schema fields
  function renderTable(rows){
    const tbody = el('requestsTable').querySelector('tbody');
    tbody.innerHTML = '';

    if (!rows || rows.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="11" class="empty">No requests found</td>`;
      tbody.appendChild(tr);
      return;
    }

    rows.forEach(r => {
      const tr = document.createElement('tr');

      // Build status dropdown with allowed values; current status selected
      const statuses = ['pending','reviewed','assigned','cancelled'];
      const statusOptions = statuses.map(s => `<option value="${s}" ${s === (r.status||'pending') ? 'selected' : ''}>${s}</option>`).join('');

      tr.innerHTML = `
        <td>${escapeHtml(r.id)}</td>
        <td>${escapeHtml(r.full_name)}</td>
        <td>${escapeHtml(r.phone)}</td>
        <td>${escapeHtml(r.agent_type)}</td>
        <td>${escapeHtml(r.job_type)}</td>
        <td>${escapeHtml(r.state || '')}</td>
        <td>${escapeHtml(r.lga || '')}</td>
        <td>${escapeHtml(r.address || '')}</td>
        <td><select class="status-select">${statusOptions}</select></td>
        <td>${escapeHtml(r.created_at)}</td>
        <td>
          <button class="btn btn--small view-btn" data-id="${escapeHtml(r.id)}">View</button>
        </td>
      `;

      // Attach dataset for later use
      tr.querySelector('.status-select').dataset.id = r.id;
      tbody.appendChild(tr);
    });

    // Attach change handlers for status selects
    tbody.querySelectorAll('.status-select').forEach(sel => {
      sel.addEventListener('change', async (e) => {
        const jobId = sel.dataset.id;
        const newStatus = sel.value;
        await sendStatusUpdate(jobId, newStatus, sel);
      });
    });

    // Attach view handlers
    tbody.querySelectorAll('.view-btn').forEach(btn => btn.addEventListener('click', () => openDetail(btn.dataset.id)));
  }

  // Show a loading row while fetching
  function showLoading(){
    const tbody = el('requestsTable').querySelector('tbody');
    tbody.innerHTML = '<tr><td colspan="11" class="loading">Loading...</td></tr>';
  }

  // Fetch helper with error handling
  async function fetchJson(url){
    try{
      const r = await fetch(url, {cache: 'no-store'});
      if (!r.ok) throw new Error('Network error');
      return await r.json();
    } catch (err) {
      console.error('Fetch error', err);
      return { success: false, error: err.message };
    }
  }

  // Load list of requests from the jobs/jobs.php endpoint
  async function loadRequests(){
    showLoading();
    const json = await fetchJson(LIST_URL);
    if (!json || !json.success) {
      const tbody = el('requestsTable').querySelector('tbody');
      tbody.innerHTML = `<tr><td colspan="11" class="error">Error loading requests: ${escapeHtml((json && json.error) || 'Unknown')}</td></tr>`;
      return;
    }
    renderTable(json.data);
  }

  // Open detail modal and fetch single request
  async function openDetail(id){
    const json = await fetchJson(LIST_URL + '?id=' + encodeURIComponent(id));
    if (!json || !json.success) {
      alert('Unable to load request');
      return;
    }
    const d = json.data;
    // Render only schema fields — do not reference removed columns
    el('detailBody').innerHTML = `
      <dl class="detail-list">
        <dt>ID</dt><dd>${escapeHtml(d.id)}</dd>
        <dt>Full Name</dt><dd>${escapeHtml(d.full_name)}</dd>
        <dt>Phone</dt><dd>${escapeHtml(d.phone)}</dd>
        <dt>Email</dt><dd>${escapeHtml(d.email || '—')}</dd>
        <dt>Agent Type</dt><dd>${escapeHtml(d.agent_type)}</dd>
        <dt>Job Type</dt><dd>${escapeHtml(d.job_type)}</dd>
        <dt>State</dt><dd>${escapeHtml(d.state || '—')}</dd>
        <dt>LGA</dt><dd>${escapeHtml(d.lga || '—')}</dd>
        <dt>Address</dt><dd>${escapeHtml(d.address || '—')}</dd>
        <dt>Status</dt><dd id="currentStatus">${escapeHtml(d.status)}</dd>
        <dt>Created</dt><dd>${escapeHtml(d.created_at)}</dd>
      </dl>
    `;

    el('statusSelect').value = d.status || 'pending';
    el('updateStatusBtn').dataset.id = d.id;
    el('detailModal').classList.remove('hidden');
  }

  function closeDetail(){ el('detailModal').classList.add('hidden'); }

  // Send status update to the server
  async function sendStatusUpdate(jobId, status, control){
    // Show a small busy state on the control
    const original = control.disabled;
    control.disabled = true;
    try{
      const resp = await fetch(UPDATE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // Include both `job_id` (requested) and `action`/`id` for compatibility
        body: JSON.stringify({ job_id: jobId, id: jobId, status: status, action: 'update_status' })
      });
      const json = await resp.json();
      if (!resp.ok || !json.success) {
        alert('Update failed: ' + (json.error || resp.statusText));
        // Revert select to previous value by reloading the list
        await loadRequests();
      } else {
        // refresh list to reflect latest status
        await loadRequests();
      }
    } catch (err) {
      console.error(err);
      alert('Network error while updating status');
      await loadRequests();
    } finally {
      control.disabled = original;
    }
  }

  // Update status from modal
  async function modalUpdate(){
    const id = el('updateStatusBtn').dataset.id;
    const status = el('statusSelect').value;
    await sendStatusUpdate(id, status, el('updateStatusBtn'));
    closeDetail();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    loadRequests();
    el('closeDetail').addEventListener('click', closeDetail);
    el('updateStatusBtn').addEventListener('click', modalUpdate);
  });

})();