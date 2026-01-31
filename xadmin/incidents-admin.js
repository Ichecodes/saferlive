(function(){
  'use strict';

  const LIST_URL = '/safer/api/incidents/list.php';
  const DETAIL_URL = '/safer/api/incidents/incident-detail.php';
  const STATUS_URL = '/safer/api/incidents/status-update.php';

  function el(id){ return document.getElementById(id); }

  function escapeHtml(s){ if (s === null || s === undefined) return ''; return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

  async function fetchJson(url){
    try{
      const r = await fetch(url, { cache: 'no-store' });
      if (!r.ok) throw new Error(r.statusText);
      return await r.json();
    } catch (e) { return { success:false, error: String(e) }; }
  }

  function showLoading(){ const tbody = el('incidentsTable').querySelector('tbody'); tbody.innerHTML = '<tr><td colspan="9">Loading...</td></tr>'; }
  function showEmpty(){ const tbody = el('incidentsTable').querySelector('tbody'); tbody.innerHTML = '<tr><td colspan="9">No incidents found</td></tr>'; }
  function showError(msg){ const tbody = el('incidentsTable').querySelector('tbody'); tbody.innerHTML = `<tr><td colspan="9">${escapeHtml(msg)}</td></tr>`; }

  function renderTable(rows){
    const tbody = el('incidentsTable').querySelector('tbody');
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) { showEmpty(); return; }

    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(r.id)}</td>
        <td>${escapeHtml(r.type || '')}</td>
        <td>${escapeHtml(r.category || '')}</td>
        <td>${escapeHtml(r.state || '')}</td>
        <td>${escapeHtml(r.lga || '')}</td>
        <td>${escapeHtml(r.victims ?? 0)}</td>
        <td>${escapeHtml(r.status || '')}</td>
        <td>${escapeHtml(r.start_time || '')}</td>
        <td>
          <button class="btn btn--small view-btn" data-id="${escapeHtml(r.id)}">View</button>
          <button class="btn btn--small approve-btn" data-id="${escapeHtml(r.id)}">Approve</button>
          <button class="btn btn--small close-btn" data-id="${escapeHtml(r.id)}">Close</button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.view-btn').forEach(b=>b.addEventListener('click', ()=> openDetail(b.dataset.id)));
    tbody.querySelectorAll('.approve-btn').forEach(b=>b.addEventListener('click', ()=> updateStatus(b.dataset.id, 'open')));
    tbody.querySelectorAll('.close-btn').forEach(b=>b.addEventListener('click', ()=> updateStatus(b.dataset.id, 'closed')));
  }

  async function loadList(){
    showLoading();
    const json = await fetchJson(LIST_URL);
    if (!json || !json.success) { showError(json && json.error ? json.error : 'Unable to load'); return; }
    renderTable(json.data);
  }

  async function openDetail(id){
    const json = await fetchJson(DETAIL_URL + '?id=' + encodeURIComponent(id));
    if (!json || !json.success) { alert('Unable to load incident'); return; }
    const d = json.data;
    el('incidentBody').innerHTML = `
      <dl class="detail-list">
        <dt>ID</dt><dd>${escapeHtml(d.id)}</dd>
        <dt>Type</dt><dd>${escapeHtml(d.type || '')}</dd>
        <dt>Category</dt><dd>${escapeHtml(d.category || '')}</dd>
        <dt>Status</dt><dd id="detailStatus">${escapeHtml(d.status || '')}</dd>
        <dt>Start</dt><dd>${escapeHtml(d.start_time || '')}</dd>
        <dt>End</dt><dd>${escapeHtml(d.end_time || '—')}</dd>
        <dt>Duration</dt><dd>${escapeHtml(d.duration || '—')}</dd>
        <dt>Victims</dt><dd>${escapeHtml(d.victims ?? 0)}</dd>
        <dt>Casualties</dt><dd>${escapeHtml(d.casualties ?? 0)}</dd>
        <dt>Coordinates</dt><dd>${escapeHtml(d.latitude ?? '')}, ${escapeHtml(d.longitude ?? '')}</dd>
        <dt>Description</dt><dd>${escapeHtml(d.description || '')}</dd>
      </dl>
    `;
    el('approveIncidentBtn').dataset.id = id;
    el('closeIncidentBtn').dataset.id = id;
    el('incidentModal').classList.remove('hidden');
  }

  function closeDetail(){ el('incidentModal').classList.add('hidden'); }

  async function updateStatus(id, status){
    try{
      const resp = await fetch(STATUS_URL, {
        method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id: parseInt(id), status: status })
      });
      const json = await resp.json();
      if (!resp.ok || !json.success) { alert('Update failed: ' + (json.error || resp.statusText)); return; }
      await loadList();
      if (!document.getElementById('incidentModal').classList.contains('hidden')) {
        // refresh detail if open
        const curr = el('approveIncidentBtn').dataset.id;
        if (curr) openDetail(curr);
      }
    } catch (e) { alert('Network error'); }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    loadList();
    el('closeIncident').addEventListener('click', closeDetail);
    el('approveIncidentBtn').addEventListener('click', ()=> updateStatus(el('approveIncidentBtn').dataset.id, 'open'));
    el('closeIncidentBtn').addEventListener('click', ()=> updateStatus(el('closeIncidentBtn').dataset.id, 'closed'));
  });

})();
