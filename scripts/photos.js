/* photos.js — media rendering, fetch, and upload helpers */
(function () {
  'use strict';

  function el(id) { return document.getElementById(id); }
  function show(id) { const n = el(id); if (n) n.hidden = false; }
  function hide(id) { const n = el(id); if (n) n.hidden = true; }

  function renderPhotos(media) {
    const gallery = el('photo-gallery');
    const placeholder = el('photo-placeholder');
    if (!gallery) return;
    gallery.innerHTML = '';

    if (!Array.isArray(media) || media.length === 0) {
      // Show app logo as a friendly placeholder inside the gallery
      const img = document.createElement('img');
      img.src = '../assets/logo.svg';
      img.alt = 'Safer.ng';
      img.className = 'incident-photo incident-placeholder';
      img.loading = 'lazy';
      gallery.appendChild(img);
      if (placeholder) placeholder.hidden = true;
      show('photo-gallery');
      return;
    }

    if (placeholder) placeholder.hidden = true;
    show('photo-gallery');

    media.forEach(item => {
      if (!item || !item.secure_url) return;
      try {
        const src = item.secure_url;
        const img = document.createElement('img');
        img.src = src;
        img.loading = 'lazy';
        img.alt = 'Incident photo';
        img.className = 'incident-photo';
        img.style.cursor = 'pointer';
        img.addEventListener('click', () => window.open(src, '_blank'));
        gallery.appendChild(img);
      } catch (e) {
        // skip
      }
    });
  }

  async function fetchMedia(id) {
    try {
      const resp = await fetch(`api/incidents/photos.php?id=${encodeURIComponent(id)}`);
      if (!resp.ok) return [];
      const json = await resp.json();
      if (!json || !json.success || !json.data) return [];
      return Array.isArray(json.data) ? json.data : [];
    } catch (e) {
      return [];
    }
  }

  async function uploadPhotos(incidentId, fileList) {
    if (!incidentId) throw new Error('Invalid incidentId');
    if (!fileList || fileList.length === 0) return { uploaded: [], failed: [] };
    const fd = new FormData();
    fd.append('incident_id', String(incidentId));
    for (let i = 0; i < fileList.length; i++) {
      fd.append('photos[]', fileList[i], fileList[i].name);
    }

    try {
      const resp = await fetch('api/incidents/photos.php', { method: 'POST', body: fd });
      console.debug('photos POST status', resp.status);
      const text = await resp.text();
      let json = null;
      try { json = JSON.parse(text); } catch (e) { console.debug('photos response text', text); }
      console.debug('photos response', json);
      if (!resp.ok) return { uploaded: [], failed: [{ error: 'Upload request failed', status: resp.status, body: json || text }] };
      if (!json || !json.success) return { uploaded: json?.uploaded || [], failed: json?.failed || [{ error: 'Upload error', body: json || text }] };
      return { uploaded: json.uploaded || [], failed: json.failed || [] };
    } catch (e) {
      console.error('uploadPhotos exception', e);
      return { uploaded: [], failed: [{ error: 'Upload exception', message: String(e) }] };
    }
  }

  window.Photos = window.Photos || {};
  window.Photos.renderPhotos = renderPhotos;
  window.Photos.fetchMedia = fetchMedia;
  window.Photos.uploadPhotos = uploadPhotos;

})();
