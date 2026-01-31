document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('reportForm');
  const submitBtn = document.getElementById('submitBtn');
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  async function fetchIncident(id) {
    try {
      const resp = await fetch(`/safer/api/incidents/incident-detail.php?id=${encodeURIComponent(id)}`);
      if (!resp.ok) throw new Error('Failed to fetch');
      const json = await resp.json();
      if (!json.success) throw new Error(json.error || 'No data');
      return json.data || null;
    } catch (e) {
      console.error('Incident fetch error', e);
      return null;
    }
  }

  function fillForm(data) {
    if (!data) return;
    document.getElementById('title').value = data.title || '';
    document.getElementById('type').value = data.type || '';
    document.getElementById('description').value = data.description || '';
    document.getElementById('datetime').value = data.start_time ? data.start_time.replace(' ', 'T') : '';
    document.getElementById('state').value = data.state || '';
    document.getElementById('lga').value = data.lga || '';
    document.getElementById('location').value = data.location || '';
    try { document.getElementById('victims').value = data.victims ?? 0; } catch(e){}
    try { document.getElementById('injured').value = data.injured ?? 0; } catch(e){}
    try { document.getElementById('dead').value = data.casualties ?? 0; } catch(e){}
    try { document.getElementById('missing').value = data.missing ?? 0; } catch(e){}
  }

  if (id) {
    submitBtn.textContent = 'Update Report';
    (async () => {
      const data = await fetchIncident(id);
      if (data) {
        const mapped = {
          title: data.title,
          type: data.type,
          description: data.description,
          start_time: data.start_time,
          state: data.state,
          lga: data.lga,
          location: data.location || data.address || '',
          victims: data.victims,
          casualties: data.casualties,
          missing: data.missing || 0,
          injured: data.injured || 0
        };
        fillForm(mapped);
        form.dataset.incidentId = id;
        submitBtn.disabled = false;
      }
    })();
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    submitBtn.disabled = true;
    const fd = new FormData(form);
    const payload = {};
    for (const [k, v] of fd.entries()) payload[k] = v;

    if (form.dataset.incidentId) {
      payload.id = form.dataset.incidentId;
      try {
        const resp = await fetch('/safer/xadmin/rprt.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await resp.json();
        if (json.success) {
          alert('Incident updated');
          window.location.reload();
        } else {
          alert('Update failed: ' + (json.error || 'unknown'));
        }
      } catch (err) {
        console.error(err);
        alert('Update failed');
      } finally {
        submitBtn.disabled = false;
      }
    } else {
      try {
        const resp = await fetch('/safer/api/incidents/reports.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await resp.json();
        if (json.success) {
          alert('Report submitted');
          window.location.href = '/safer/incidents.html';
        } else {
          alert('Submission failed: ' + (json.error || 'unknown'));
        }
      } catch (err) {
        console.error(err);
        alert('Submission failed');
      } finally {
        submitBtn.disabled = false;
      }
    }
  });
});
