(function () {
  'use strict';

  function qs(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function el(id) {
    return document.getElementById(id);
  }

  function text(id, value) {
    const node = el(id);
    if (node) node.textContent = value ?? '—';
  }

  function show(id) {
    const node = el(id);
    if (node) node.hidden = false;
  }

  function hide(id) {
    const node = el(id);
    if (node) node.hidden = true;
  }

  function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d)) return '—';
    return d.toLocaleString();
  }

  function showLoading() {
    show('loading');
    hide('incident-card');
    hide('error');
  }

  function showError(message) {
    hide('loading');
    hide('incident-card');
    show('error');
    text('error', message || 'Incident not found');
  }

  function showContent() {
    hide('loading');
    hide('error');
    show('incident-card');
  }

  function renderStatus(status) {
    const badge = el('status-badge');
    if (!badge) return;

    badge.className = 'status-badge';
    badge.textContent = status ? status.toUpperCase() : '—';

    if (!status) return;
    const s = status.toLowerCase();
    if (s === 'open') badge.classList.add('open');
    else if (s === 'closed') badge.classList.add('closed');
    else badge.classList.add('pending');
  }

  function renderMap(lat, lon) {
    const container = el('map-container');
    if (!container) return;

    container.innerHTML = '';

    if (!lat || !lon) {
      container.textContent = 'Location not available on map';
      return;
    }

    const iframe = document.createElement('iframe');
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'no-referrer-when-downgrade';
    iframe.src =
      `https://www.openstreetmap.org/export/embed.html?` +
      `bbox=${lon - 0.01},${lat - 0.01},${lon + 0.01},${lat + 0.01}` +
      `&layer=mapnik&marker=${lat},${lon}`;

    container.appendChild(iframe);
  }

  function setupShare(incident) {
    const btn = el('share-btn');
    if (!btn) return;

    btn.addEventListener('click', () => {
      const textVal =
        `Incident reported: ${incident.type || 'Incident'} ` +
        `${incident.lga ? 'in ' + incident.lga : ''}. ` +
        `View details: ${location.href}`;

      if (navigator.share) {
        navigator.share({ text: textVal, url: location.href }).catch(() => {});
      } else {
        navigator.clipboard.writeText(textVal);
        btn.textContent = 'Copied';
        setTimeout(() => (btn.textContent = 'Share'), 1200);
      }
    });
  }

  function renderIncident(incident) {
    showContent();

    text('incident-title', incident.type ? `${incident.type} Incident` : 'Incident');
    text('incident-subtitle', `${incident.state || '—'}${incident.lga ? ', ' + incident.lga : ''}`);

    renderStatus(incident.status);

    text('detail-type', incident.type);
    text('detail-category', incident.category);
    text('detail-status', incident.status);
    text('detail-start', formatDate(incident.start_time));
    text('detail-end', formatDate(incident.end_time));
    text('detail-duration', incident.duration);
    text('detail-state', incident.state);
    text('detail-lga', incident.lga);
    text('detail-location',
      incident.latitude && incident.longitude
        ? `${incident.latitude}, ${incident.longitude}`
        : '—'
    );
    text('detail-victims', incident.victims);
    text('detail-casualties', incident.casualties);
    text('detail-description', incident.description);

    renderMap(incident.latitude, incident.longitude);
    setupShare(incident);

    if (incident.status && incident.status.toLowerCase() === 'closed') {
      el('incident-card')?.classList.add('closed');
    }
  }

  async function setupNavigation(currentId) {
    const prevBtn = el('prev-incident');
    const nextBtn = el('next-incident');
    if (!prevBtn || !nextBtn) return;

    prevBtn.disabled = true;
    nextBtn.disabled = true;
    prevBtn.onclick = null;
    nextBtn.onclick = null;

    try {
      const resp = await fetch(`/safer/api/incidents/neighbor.php?id=${encodeURIComponent(currentId)}`);
      if (!resp.ok) throw new Error('Request failed');
      const json = await resp.json();
      if (!json.success || !json.data) throw new Error('Invalid response');

      const prev = json.data.prev_id;
      const next = json.data.next_id;

      if (prev) {
        prevBtn.disabled = false;
        prevBtn.onclick = () => (window.location.href = `incident-detail.html?id=${prev}`);
      } else {
        prevBtn.disabled = true;
        prevBtn.onclick = null;
      }

      if (next) {
        nextBtn.disabled = false;
        nextBtn.onclick = () => (window.location.href = `incident-detail.html?id=${next}`);
      } else {
        nextBtn.disabled = true;
        nextBtn.onclick = null;
      }

    } catch (err) {
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      prevBtn.onclick = null;
      nextBtn.onclick = null;
    }
  }

  async function loadScript(src) {
    if (window.Photos && typeof window.Photos.fetchMedia === 'function') return;
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = src;
      s.defer = true;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Failed to load ' + src));
      document.head.appendChild(s);
    });
  }

  async function loadIncident() {
    const id = qs('id');
    if (!id) {
      showError('Incident not found');
      return;
    }

    showLoading();

    try {
      const resp = await fetch(`/api/incidents/incident-detail.php?id=${encodeURIComponent(id)}`);
      if (!resp.ok) throw new Error('Request failed');

      const json = await resp.json();
      if (!json.success || !json.data) throw new Error('Invalid response');

      renderIncident(json.data);
      setupNavigation(id);

      // Ensure photos module loaded then fetch media
      try {
        await loadScript('./scripts/photos.js');
      } catch (e) {
        // continue; we will attempt to call window.Photos but it's optional
      }

      const media = (window.Photos && typeof window.Photos.fetchMedia === 'function')
        ? await window.Photos.fetchMedia(id)
        : [];

      if (window.Photos && typeof window.Photos.renderPhotos === 'function') {
        window.Photos.renderPhotos(media);
      }

    } catch (err) {
      showError('Unable to load incident');
    }
  }

  document.addEventListener('DOMContentLoaded', loadIncident);

})();
