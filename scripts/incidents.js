// Global state
let currentPage = 1;
const itemsPerPage = 20;
let currentFilters = {};
let incidentsData = [];
let statsData = {};
let statusFromApiLoaded = false;

// Chart instances
let statusChart = null;
let typeChart = null;
let timelineChart = null;

// Helper: parse JSON safely and log body when parse fails
async function safeParseJson(response, label = 'response') {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    console.error(`${label} returned invalid JSON. Raw response:\n`, text);
    return null;
  }
}
let incidentSourceAdded = false;


// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  // Filter/search behavior moved to scripts/search.js
  initPagination();
  loadDashboard();
  loadIncidents();
  // Register chartjs-plugin-datalabels if loaded
  if (window.Chart && window.ChartDataLabels) {
    try { Chart.register(ChartDataLabels); } catch (e) { /* already registered */ }
  }
});

// Mobile Navigation
function initMobileNav() {
  const navToggle = document.querySelector('.nav__toggle');
  const nav = document.querySelector('.nav');
  
  if (!navToggle || !nav) return;

  navToggle.addEventListener('click', () => {
    const isOpen = nav.classList.contains('nav--open');
    nav.classList.toggle('nav--open');
    navToggle.setAttribute('aria-expanded', !isOpen);
  });

  document.addEventListener('click', (e) => {
    if (!nav.contains(e.target) && nav.classList.contains('nav--open')) {
      nav.classList.remove('nav--open');
      navToggle.setAttribute('aria-expanded', 'false');
    }
  });
}


// Sorting removed — server default ordering is used

// Initialize pagination
function initPagination() {
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  prevBtn.addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      loadIncidents();
    }
  });

  nextBtn.addEventListener('click', () => {
    currentPage++;
    loadIncidents();
  });
}

// Load dashboard data
async function loadDashboard() {
  try {
    const statsParams = new URLSearchParams(currentFilters || {}).toString();
    const q = statsParams ? `?${statsParams}` : '';

    // Load summary stats
    const summaryRes = await fetch(`./api/incidents/stats/summary.php${q}`);
    console.debug('Summary API HTTP status:', summaryRes.status);
    if (!summaryRes.ok) throw new Error('Summary API failed');
    const summary = await safeParseJson(summaryRes, 'Summary API');
    if (summary && summary.success) {
      updateSummaryCard(summary);
    } else if (summary && summary.error) {
      console.error('Summary API error:', summary.error);
    } else if (!summary) {
      console.warn('Summary API did not return valid JSON');
    }

    // Load status stats from DB (preferred)
    try {
      const statusRes = await fetch(`./api/incidents/stats/status-summary.php${q}`);
      console.debug('Status summary HTTP status:', statusRes.status);
      if (statusRes.ok) {
        const statusJson = await safeParseJson(statusRes, 'Status summary API');
        if (statusJson && statusJson.success) {
          updateStatusChartFromStats(statusJson.data);
        } else if (!statusJson) {
          console.warn('Status summary API did not return valid JSON');
        }
      }
    } catch (e) {
      console.warn('Status summary API unavailable, will fallback to incidents list for chart');
    }

    // Load type stats
    const typesRes = await fetch(`./api/incidents/stats/types.php${q}`);
    console.debug('Types API HTTP status:', typesRes.status);
    if (typesRes.ok) {
      const types = await safeParseJson(typesRes, 'Types API');
      if (types && types.success) {
        updateTypeChart(types);
      } else if (!types) {
        console.warn('Types API did not return valid JSON');
      }
    }

    // Load timeline (use absolute path to ensure correct resolution from any page)
    const timelineRes = await fetch(`/api/incidents/stats/timeline.php${q}`);
    console.debug('Timeline API HTTP status:', timelineRes.status);
    if (timelineRes.ok) {
      const timeline = await safeParseJson(timelineRes, 'Timeline API');
      console.debug('Timeline API response:', timeline);
      if (timeline && timeline.success && Array.isArray(timeline.timeline) && timeline.timeline.length > 0) {
        updateTimelineChart(timeline);
      } else if (!timeline) {
        console.warn('Timeline API did not return valid JSON');
      } else {
        console.debug('Timeline API: no data, skipping chart render');
      }
      } else {
        console.warn('Timeline API failed: HTTP', timelineRes.status);
    }

    // Load victim stats
    const victimsRes = await fetch(`./api/incidents/stats/victims.php${q}`);
    console.debug('Victims API HTTP status:', victimsRes.status);
    if (victimsRes.ok) {
      const victims = await safeParseJson(victimsRes, 'Victims API');
      if (victims && victims.success) {
        updateVictimCard(victims);
      } else if (!victims) {
        console.warn('Victims API did not return valid JSON');
      }
    }

    // Load AI summary
    const aiRes = await fetch('./api/ai/summary/incidents.php');
    console.debug('AI summary HTTP status:', aiRes.status);
    if (aiRes.ok) {
      const aiSummary = await safeParseJson(aiRes, 'AI summary API');
      if (aiSummary && aiSummary.success) {
        updateAISummary(aiSummary);
      } else if (!aiSummary) {
        console.warn('AI summary API did not return valid JSON');
      }
    }

    // Update map with current filters (map fetch handled in scripts/map.js)
    if (window.updateMapIncidents) window.updateMapIncidents(currentFilters);

  } catch (error) {
    console.error('Error loading dashboard:', error);
  }
}





// Update summary card
function updateSummaryCard(data) {
  document.getElementById('totalIncidents').textContent = data.total_incidents || 0;
  document.getElementById('totalLGAs').textContent = data.total_lgas || 0;
  document.getElementById('totalCommunities').textContent = data.total_communities || 0;
}

// Update status pie chart
function updateStatusChart(incidents) {
  // If status summary was loaded from the API, we don't override it using incidents list
  if (statusFromApiLoaded) return;
  const ctx = document.getElementById('statusChart');
  if (!ctx) return;

  const statusCounts = {
    open: 0,
    closed: 0,
  };

  incidents.forEach(incident => {
    const status = (incident.status || 'pending').toLowerCase();
    if (statusCounts.hasOwnProperty(status)) {
      statusCounts[status]++;
    }
  });

  if (statusChart) {
    statusChart.destroy();
  }

  statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Open', 'Closed'],
      datasets: [{
        data: [statusCounts.open, statusCounts.closed],
        backgroundColor: [
          'rgba(211, 33, 44, 0.8)',
          'rgba(155, 181, 167, 0.8)'
        ],
        borderColor: [
          'rgba(211, 33, 44, 1)',
          'rgba(155, 181, 167, 1)'
        ],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: '#ffffff',
            padding: 15
          }
        },
        datalabels: {
          color: '#064d2c',
          formatter: (value, ctx) => {
            return value;
          },
          font: { weight: 'bold', size: 12 },
          anchor: 'center',
          align: 'center'
        }
      }
    }
  });
}

// Update status chart using pre-aggregated counts from API: { open: X, closed: Y, pending: Z }
function updateStatusChartFromStats(counts) {
  const ctx = document.getElementById('statusChart');
  if (!ctx) return;

  if (!counts) counts = { open: 0, closed: 0 };
  const openCount = counts.open || 0;
  const closedCount = counts.closed || 0;

  if (statusChart) {
    statusChart.destroy();
  }

  statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Open', 'Closed'],
      datasets: [{
        data: [openCount, closedCount],
        backgroundColor: [
          'rgba(211, 33, 44, 0.8)',
          'rgba(155, 181, 167, 0.8)'
        ],
        borderColor: [
          'rgba(211, 33, 44, 1)',
          'rgba(155, 181, 167, 1)'
        ],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#ffffff', padding: 15 }
        },
        datalabels: {
          color: '#064d2c',
          formatter: (value, ctx) => {
            return value;
          },
          font: { weight: 'bold', size: 12 },
          anchor: 'center',
          align: 'center'
        }
      }
    }
  });

  statusFromApiLoaded = true;
}

// Update type breakdown chart
function updateTypeChart(data) {
  const ctx = document.getElementById('typeChart');
  if (!ctx) return;

  const types = data.types || [];
  const labels = types.map(t => t.type || 'Unknown');
  const counts = types.map(t => t.count || 0);

  if (typeChart) {
    typeChart.destroy();
  }

  typeChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Incidents',
        data: counts,
        backgroundColor: 'rgba(6, 156, 86, 0.8)',
        borderColor: 'rgba(6, 156, 86, 1)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            color: '#9bb5a7'
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.05)'
          }
        },
        x: {
          ticks: {
            color: '#9bb5a7'
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.05)'
          }
        }
      }
    }
  });
}

// Update timeline chart
function updateTimelineChart(data) {
  const ctx = document.getElementById('timelineChart');
  if (!ctx) return;
  const timeline = (data && Array.isArray(data.timeline)) ? data.timeline : [];
  if (timeline.length === 0) {
    console.debug('updateTimelineChart: no timeline entries found');
    // If a chart exists, clear/destroy it so UI does not show stale/empty chart
    if (timelineChart) {
      timelineChart.destroy();
      timelineChart = null;
    }
    return;
  }
  const labels = timeline.map(t => t.date || '');
  const counts = timeline.map(t => Number(t.count || 0));
  console.debug('updateTimelineChart: labels=', labels, 'counts=', counts);

  if (timelineChart) {
    timelineChart.destroy();
  }

  timelineChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Incidents',
        data: counts,
        borderColor: 'rgba(255, 104, 30, 1)',
        backgroundColor: 'rgba(255, 104, 30, 0.1)',
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            color: '#9bb5a7'
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.05)'
          }
        },
        x: {
          ticks: {
            color: '#9bb5a7'
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.05)'
          }
        }
      }
    }
  });
}

// Update victim card
function updateVictimCard(data) {
  document.getElementById('totalVictims').textContent = data.total_victims || 0;
  document.getElementById('totalCasualties').textContent = data.total_casualties || 0;
  // Support new stats: missing and injured
  const missingEl = document.getElementById('totalMissing');
  const injuredEl = document.getElementById('totalInjured');
  if (missingEl) missingEl.textContent = data.total_missing || 0;
  if (injuredEl) injuredEl.textContent = data.total_injured || 0;
}

// Update AI summary
function updateAISummary(data) {
  const summaryElRight = document.getElementById('aiSummaryRight');
  const summaryElMobile = document.getElementById('aiSummaryMobile');
  const html = `<p>${data.summary || 'No summary available.'}</p>`;
  if (summaryElRight) summaryElRight.innerHTML = html;
  if (summaryElMobile) summaryElMobile.innerHTML = html;
}


// Add or update incident source on the map
// Load incidents table
async function loadIncidents() {
  const tbody = document.getElementById('incidentsTableBody');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="10" class="loading-row">Loading incidents...</td></tr>';

  try {
    const params = new URLSearchParams({
      page: currentPage,
      limit: itemsPerPage,
      ...currentFilters
    });

    const response = await fetch(`./api/incidents/list.php?${params}`);
    console.debug('Incidents table HTTP status:', response.status);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const data = await safeParseJson(response, 'Incidents list (table)');
    
    if (!data.success) {
      throw new Error(data.error || 'Failed to load incidents');
    }

    incidentsData = data.data || [];
      try {
        // store the last loaded ordered ids for detail navigation
        const ids = (incidentsData || []).map(i => i.id);
        localStorage.setItem('incidents_order', JSON.stringify({ ids, page: currentPage, ts: Date.now() }));
      } catch (e) { /* ignore storage errors */ }
    renderTable(incidentsData);
    updatePagination(data.pagination || {});
    if (window.updateMapIncidents) window.updateMapIncidents(currentFilters);
  } catch (error) {
    console.error('Error loading incidents:', error);
    tbody.innerHTML = '<tr><td colspan="10" class="loading-row">Error loading incidents. Please try again.</td></tr>';
  }
}

// Render table
function renderTable(incidents) {
  const tbody = document.getElementById('incidentsTableBody');
  if (!tbody) return;

  if (incidents.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" class="loading-row">No incidents found.</td></tr>';
    return;
  }
  tbody.innerHTML = incidents.map(incident => {
    const isClosed = incident.status?.toLowerCase() === 'closed';
    const statusClass = incident.status?.toLowerCase() || 'pending';
    const duration = calculateDuration(incident.start_time, incident.end_time);
    const locationText = incident.location || incident.address || '—';

    return `
      <tr class="${isClosed ? 'closed' : ''}">
        <td>
          <span class="status-badge ${statusClass}">${incident.status || 'Pending'}</span>
        </td>
        <td>${incident.type || '—'}</td>
        <td>${incident.state || '—'}</td>
        <td>${incident.lga || '—'}</td>
        <td>${formatDate(incident.start_time)}</td>
        <td>${duration}</td>
        <td>${incident.victims || 0}</td>
        <td>${incident.casualties || 0}</td>
        <td>${locationText}</td>
        <td>
          <button class="btn btn--small btn--outline" onclick="viewIncident(${incident.id})">View</button>
        </td>
      </tr>
    `;
  }).join('');
}

// Calculate duration
function calculateDuration(startTime, endTime) {
  if (!startTime) return '—';
  const start = new Date(startTime);
  const end = endTime ? new Date(endTime) : new Date();
  const diff = Math.abs(end - start);
  const hours = Math.floor(diff / (1000 * 60 * 60));
  const days = Math.floor(hours / 24);
  
  if (days > 0) return `${days}d ${hours % 24}h`;
  if (hours > 0) return `${hours}h`;
  return `${Math.floor(diff / (1000 * 60))}m`;
}

// Format date
function formatDate(dateString) {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

// Update pagination
function updatePagination(pagination) {
  const currentPageEl = document.getElementById('currentPage');
  const totalPagesEl = document.getElementById('totalPages');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  const totalPages = pagination.total_pages || 1;
  currentPage = pagination.current_page || 1;

  if (currentPageEl) currentPageEl.textContent = currentPage;
  if (totalPagesEl) totalPagesEl.textContent = totalPages;
  if (prevBtn) prevBtn.disabled = currentPage <= 1;
  if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
}

// View incident (placeholder)
function viewIncident(id) {
  window.location.href = `/incident-detail.html?id=${id}`;
}

