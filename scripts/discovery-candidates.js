(function () {
  'use strict';

  const API_BASE = '/safer/api/discovery';

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    filters: {
      q: '',
      source_platform: '',
      is_candidate: '',
      review_status: '',
      min_score: '',
      date_from: '',
      date_to: ''
    }
  };

  function el(id) { return document.getElementById(id); }

  function esc(text) {
    if (text === null || text === undefined) return '';
    return String(text).replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
  }

  async function fetchJson(url, options) {
    const resp = await fetch(url, options);
    const json = await resp.json();
    if (!resp.ok || !json.success) {
      throw new Error((json && json.error) ? json.error : 'Request failed');
    }
    return json;
  }

  function buildQuery() {
    const params = new URLSearchParams();
    params.set('page', String(state.page));
    params.set('per_page', String(state.perPage));

    Object.keys(state.filters).forEach((k) => {
      const value = state.filters[k];
      if (value !== '' && value !== null && value !== undefined) {
        params.set(k, String(value));
      }
    });

    return params.toString();
  }

  function renderSummary(summary) {
    el('sumReviewed').textContent = String(summary.total_reviewed_items || 0);
    el('sumCandidates').textContent = String(summary.total_candidates || 0);
    el('sumNonCandidates').textContent = String(summary.total_non_candidates || 0);
    el('sumPending').textContent = String(summary.pending_review || 0);
    el('sumApproved').textContent = String(summary.approved || 0);
    el('sumRejected').textContent = String(summary.rejected || 0);
  }

  function renderTable(items) {
    const tbody = el('candidatesTable').querySelector('tbody');
    tbody.innerHTML = '';

    if (!Array.isArray(items) || items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="empty">No candidate decisions found</td></tr>';
      return;
    }

    items.forEach((item) => {
      const candidateClass = Number(item.is_candidate) === 1 ? 'yes' : 'no';
      const candidateText = Number(item.is_candidate) === 1 ? 'Yes' : 'No';
      const title = item.title ? item.title : '(No title)';
      const preview = item.body_preview ? item.body_preview : '';
      const score = item.candidate_score !== null ? Number(item.candidate_score).toFixed(2) : '0.00';
      const posted = item.posted_at || item.fetched_at || '-';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(item.raw_item_id)}</td>
        <td>${esc(item.source_platform)}</td>
        <td><strong>${esc(title)}</strong><br><small>${esc(preview)}</small></td>
        <td><span class="pill ${candidateClass}">${candidateText}</span></td>
        <td>${esc(score)}</td>
        <td>${esc(item.candidate_review_status || 'pending')}</td>
        <td>${esc(posted)}</td>
        <td>
          <button class="btn btn--small view-btn" data-action="view" data-id="${esc(item.raw_item_id)}">View</button>
          <button class="btn btn--small" data-action="checked" data-id="${esc(item.raw_item_id)}">Checked</button>
          <button class="btn btn--small" data-action="approve" data-id="${esc(item.raw_item_id)}">Approve</button>
          <button class="btn btn--small" data-action="reject" data-id="${esc(item.raw_item_id)}">Reject</button>
          <button class="btn btn--small view-btn" data-action="rerun" data-id="${esc(item.raw_item_id)}">Re-run</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderPagination(page, perPage, total) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    el('pageInfo').textContent = `Page ${page} of ${totalPages}`;
    el('prevPageBtn').disabled = page <= 1;
    el('nextPageBtn').disabled = page >= totalPages;
  }

  async function loadCandidates() {
    const tbody = el('candidatesTable').querySelector('tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="loading">Loading...</td></tr>';

    try {
      const query = buildQuery();
      const json = await fetchJson(`${API_BASE}/candidates.php?${query}`);
      const data = json.data || {};
      const items = data.items || [];
      const pagination = data.pagination || {};

      state.total = Number(pagination.total || 0);
      renderSummary(data.summary || {});
      renderTable(items);
      renderPagination(Number(pagination.page || 1), Number(pagination.per_page || state.perPage), state.total);
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="8" class="error">${esc(err.message || 'Failed to load')}</td></tr>`;
    }
  }

  async function updateReviewStatus(rawItemId, reviewStatus) {
    const note = prompt(`Optional note for ${reviewStatus} (leave blank to skip):`, '') || '';
    try {
      await fetchJson(`${API_BASE}/candidate-review.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          raw_item_id: Number(rawItemId),
          review_status: reviewStatus,
          note: note
        })
      });
      await loadCandidates();
    } catch (err) {
      alert(err.message || 'Failed to update review status');
    }
  }

  async function rerunDetection(rawItemId) {
    try {
      await fetchJson(`${API_BASE}/candidate-run.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ raw_item_id: Number(rawItemId) })
      });
      await loadCandidates();
    } catch (err) {
      alert(err.message || 'Failed to re-run detection');
    }
  }

  async function openDetail(rawItemId) {
    const modal = el('detailModal');
    const body = el('detailBody');
    modal.classList.remove('hidden');
    body.innerHTML = '<p class="loading">Loading detail...</p>';

    try {
      const json = await fetchJson(`${API_BASE}/candidate-detail.php?id=${encodeURIComponent(rawItemId)}`);
      const d = json.data || {};

      const keywords = Array.isArray(d.matched_keywords) ? d.matched_keywords.join(', ') : '';
      const places = Array.isArray(d.matched_places) ? d.matched_places.join(', ') : '';
      const signals = d.signals ? JSON.stringify(d.signals, null, 2) : '{}';
      const media = Array.isArray(d.media) ? d.media.map((m) => `<li><a href="${esc(m.url)}" target="_blank" rel="noopener">${esc(m.url)}</a> (${esc(m.type)})</li>`).join('') : '';
      const rawPayload = d.raw_payload ? JSON.stringify(d.raw_payload, null, 2) : 'Raw payload not available (schema column missing).';

      body.innerHTML = `
        <div class="detail-grid">
          <div>
            <h3>${esc(d.title || '(No title)')}</h3>
            <p><strong>URL:</strong> <a href="${esc(d.source_url || '#')}" target="_blank" rel="noopener">${esc(d.source_url || '-')}</a></p>
            <p><strong>Author:</strong> ${esc(d.author_name || '-')} ${d.author_handle ? `(@${esc(d.author_handle)})` : ''}</p>
            <p><strong>Posted:</strong> ${esc(d.posted_at || d.fetched_at || '-')}</p>
            <p><strong>Candidate:</strong> ${Number(d.is_candidate) === 1 ? 'Yes' : 'No'}</p>
            <p><strong>Score:</strong> ${esc(d.candidate_score)} / ${esc(d.threshold)}</p>
            <p><strong>Review:</strong> ${esc(d.candidate_review_status || 'pending')}</p>
            <p><strong>Reason:</strong> ${esc(d.reason_summary || '')}</p>
            <p><strong>Matched keywords:</strong> ${esc(keywords || '-')}</p>
            <p><strong>Matched places:</strong> ${esc(places || '-')}</p>
            <h4>Body</h4>
            <pre>${esc(d.body || '')}</pre>
          </div>
          <div>
            <h4>Signals</h4>
            <pre>${esc(signals)}</pre>
            <h4>Media</h4>
            <ul>${media || '<li>-</li>'}</ul>
            <h4>Raw Payload</h4>
            <pre>${esc(rawPayload)}</pre>
          </div>
        </div>
      `;
    } catch (err) {
      body.innerHTML = `<p class="error">${esc(err.message || 'Failed to load detail')}</p>`;
    }
  }

  async function runBatchDetection() {
    const limit = Number(el('runLimit').value || 100);
    const statusEl = el('runStatus');
    statusEl.textContent = 'Running detection...';
    try {
      const json = await fetchJson(`${API_BASE}/candidate-run.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ limit: limit })
      });
      const d = json.data || {};
      statusEl.textContent = `Done: processed ${d.processed || 0}, flagged ${d.flagged || 0}, skipped ${d.skipped || 0}`;
      await loadCandidates();
    } catch (err) {
      statusEl.textContent = 'Run failed';
      alert(err.message || 'Failed to run detection');
    }
  }

  function applyFiltersFromUI() {
    state.page = 1;
    state.filters.q = el('q').value.trim();
    state.filters.source_platform = el('platformFilter').value;
    state.filters.is_candidate = el('candidateFilter').value;
    state.filters.review_status = el('reviewFilter').value;
    state.filters.min_score = el('minScore').value.trim();
    state.filters.date_from = el('dateFrom').value;
    state.filters.date_to = el('dateTo').value;
  }

  function resetFilters() {
    el('q').value = '';
    el('platformFilter').value = '';
    el('candidateFilter').value = '';
    el('reviewFilter').value = '';
    el('minScore').value = '';
    el('dateFrom').value = '';
    el('dateTo').value = '';
    applyFiltersFromUI();
  }

  function bindEvents() {
    el('applyFiltersBtn').addEventListener('click', async () => {
      applyFiltersFromUI();
      await loadCandidates();
    });

    el('resetFiltersBtn').addEventListener('click', async () => {
      resetFilters();
      await loadCandidates();
    });

    el('prevPageBtn').addEventListener('click', async () => {
      if (state.page <= 1) return;
      state.page -= 1;
      await loadCandidates();
    });

    el('nextPageBtn').addEventListener('click', async () => {
      const totalPages = Math.max(1, Math.ceil(state.total / state.perPage));
      if (state.page >= totalPages) return;
      state.page += 1;
      await loadCandidates();
    });

    el('runBatchBtn').addEventListener('click', runBatchDetection);

    el('candidatesTable').addEventListener('click', async (e) => {
      const target = e.target;
      if (!target || !target.dataset) return;
      const action = target.dataset.action;
      const id = target.dataset.id;
      if (!action || !id) return;

      if (action === 'view') {
        await openDetail(id);
      } else if (action === 'checked') {
        await updateReviewStatus(id, 'checked');
      } else if (action === 'approve') {
        await updateReviewStatus(id, 'approved');
      } else if (action === 'reject') {
        await updateReviewStatus(id, 'rejected');
      } else if (action === 'rerun') {
        await rerunDetection(id);
      }
    });

    el('closeDetailBtn').addEventListener('click', () => {
      el('detailModal').classList.add('hidden');
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    bindEvents();
    applyFiltersFromUI();
    await loadCandidates();
  });
})();

