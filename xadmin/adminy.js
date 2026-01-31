document.addEventListener('DOMContentLoaded', () => {
  const createdAtHeader = document.getElementById('createdAtHeader');
  const sortArrow = document.getElementById('sortArrow');
  let sortOrder = 'desc';

  createdAtHeader.addEventListener('click', () => {
    sortOrder = sortOrder === 'desc' ? 'asc' : 'desc';
    sortArrow.textContent = sortOrder === 'desc' ? '↓' : '↑';
    loadIncidents(sortOrder);
  });

  loadIncidents(sortOrder);

  async function loadIncidents(order = 'desc') {
    const statuses = ['open', 'closed', 'pending'];
    const limit = 1000;
    const fetches = statuses.map(s =>
      fetch(`../api/incidents/list.php?status=${encodeURIComponent(s)}&sort_by=start_time&sort_order=${encodeURIComponent(order)}&limit=${limit}`)
        .then(r => r.json())
        .catch(() => ({ success: false }))
    );

    const results = await Promise.all(fetches);
    const incidentsMap = new Map();

    for (const res of results) {
      if (!res || !res.success || !Array.isArray(res.data)) continue;
      for (const item of res.data) {
        incidentsMap.set(String(item.id), item);
      }
    }

    const incidents = Array.from(incidentsMap.values());

    incidents.sort((a, b) => {
      const ta = new Date(a.start_time).getTime() || 0;
      const tb = new Date(b.start_time).getTime() || 0;
      return order === 'asc' ? ta - tb : tb - ta;
    });

    renderTable(incidents);
  }

  function renderTable(data) {
    const tbody = document.querySelector('#incidentsTable tbody');
    tbody.innerHTML = '';

    data.forEach(incident => {
      const tr = document.createElement('tr');

      const tdId = document.createElement('td');
      tdId.textContent = incident.id || '';

      const tdType = document.createElement('td');
      tdType.textContent = incident.type || '';

      const tdState = document.createElement('td');
      tdState.textContent = incident.state || '';

      const tdLga = document.createElement('td');
      tdLga.textContent = incident.lga || '';

      const tdVictims = document.createElement('td');
      tdVictims.textContent = incident.victims != null ? String(incident.victims) : '';

      const tdCreated = document.createElement('td');
      tdCreated.textContent = incident.start_time || '';

      const tdActions = document.createElement('td');
      tdActions.style.display = 'flex';
      tdActions.style.gap = '8px';
      tdActions.style.alignItems = 'center';

      const editBtn = document.createElement('button');
      editBtn.className = 'btn btn--ghost';
      editBtn.textContent = 'Edit';

      const statusSelect = document.createElement('select');
      ['pending', 'open', 'closed'].forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        statusSelect.appendChild(opt);
      });
      statusSelect.value = incident.status || 'pending';

      const statusMsg = document.createElement('span');
      statusMsg.style.marginLeft = '6px';
      statusMsg.style.fontSize = '0.9em';

      statusSelect.addEventListener('change', async (e) => {
        const newStatus = e.target.value;
        const prev = incident.status;
        statusSelect.disabled = true;
        statusMsg.textContent = 'Saving...';

        try {
          const res = await fetch('../api/incidents/status-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: incident.id, status: newStatus })
          });
          const json = await res.json();
          if (json && json.success) {
            incident.status = newStatus;
            statusMsg.textContent = 'Saved';
            setTimeout(() => statusMsg.textContent = '', 1500);
          } else {
            statusSelect.value = prev;
            statusMsg.textContent = '';
            alert((json && json.error) ? json.error : 'Failed to update status');
          }
        } catch (err) {
          statusSelect.value = prev;
          statusMsg.textContent = '';
          alert('Network error while updating status');
        } finally {
          statusSelect.disabled = false;
        }
      });

      tdActions.appendChild(editBtn);
      tdActions.appendChild(statusSelect);
      tdActions.appendChild(statusMsg);

      tr.appendChild(tdId);
      tr.appendChild(tdType);
      tr.appendChild(tdState);
      tr.appendChild(tdLga);
      tr.appendChild(tdVictims);
      tr.appendChild(tdCreated);
      tr.appendChild(tdActions);

      tbody.appendChild(tr);
    });
  }
});
