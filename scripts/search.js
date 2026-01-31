// Search and filter behavior (migrated from incidents.js)
// Assumes `currentFilters`, `loadIncidents`, `loadDashboard` and `window.updateMapIncidents` are globals

// Initialize filter sidebar (mobile)
function initFilterSidebar() {
  const filterToggle = document.getElementById('filterToggle') || document.getElementById('filterToggleMobile');
  const filtersSidebar = document.getElementById('filtersSidebar');
  const filterOverlay = document.getElementById('filterOverlay');
  const closeFilters = document.getElementById('closeFilters');

  if (!filterToggle || !filtersSidebar) return;

  function openFilters() {
    filtersSidebar.classList.add('active');
    if (filterOverlay) filterOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeFiltersSidebar() {
    filtersSidebar.classList.remove('active');
    if (filterOverlay) filterOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  filterToggle.addEventListener('click', openFilters);
  if (closeFilters) closeFilters.addEventListener('click', closeFiltersSidebar);
  if (filterOverlay) filterOverlay.addEventListener('click', closeFiltersSidebar);
}

// Initialize filters
function initFilters() {
  const filterForm = document.getElementById('filterForm');
  const resetBtn = document.getElementById('resetFilters');

  if (!filterForm) return;

  // Populate State select from locations.json so users can filter by state
  (async function populateStates(){
    try {
      const resp = await fetch('/safer/locations.json');
      if (!resp.ok) return;
      const data = await resp.json();
      const stateSet = new Set();
      if (Array.isArray(data)) {
        data.forEach(item => {
          const s = item.state || item.name || '';
          if (s) stateSet.add(s);
        });
      } else if (typeof data === 'object' && data !== null) {
        Object.keys(data).forEach(k => { stateSet.add(k); });
      }
      const stateSelect = document.getElementById('filterState');
      if (!stateSelect) return;
      const opts = ['<option value="">All States</option>'];
      Array.from(stateSet).sort().forEach(n => {
        const display = n.replace(/-/g,' ').split(' ').map(w=>w.charAt(0).toUpperCase()+w.slice(1)).join(' ');
        opts.push(`<option value="${n}">${display}</option>`);
      });
      stateSelect.innerHTML = opts.join('');
    } catch (e) {
      // fail silently
    }
  })();

  filterForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(filterForm);
    currentFilters = Object.fromEntries(formData.entries());
    // remove empty values
    Object.keys(currentFilters).forEach(k => {
      if (currentFilters[k] === '') delete currentFilters[k];
    });
    // Reset pagination
    if (typeof currentPage !== 'undefined') currentPage = 1;
    if (typeof loadIncidents === 'function') loadIncidents();
    if (typeof loadDashboard === 'function') loadDashboard();
    if (window.updateMapIncidents) window.updateMapIncidents(currentFilters);

    // Close mobile sidebar after applying filters
    const filtersSidebar = document.getElementById('filtersSidebar');
    const filterOverlay = document.getElementById('filterOverlay');
    if (filtersSidebar) filtersSidebar.classList.remove('active');
    if (filterOverlay) filterOverlay.classList.remove('active');
    document.body.style.overflow = '';
  });

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      filterForm.reset();
      const mobile = document.getElementById('mobileSearch');
      const filterSearch = document.getElementById('filterSearch');
      const filterState = document.getElementById('filterState');
      if (mobile) mobile.value = '';
      if (filterSearch) filterSearch.value = '';
      if (filterState) filterState.value = '';
      currentFilters = {};
      if (typeof currentPage !== 'undefined') currentPage = 1;
      if (typeof loadIncidents === 'function') loadIncidents();
      if (typeof loadDashboard === 'function') loadDashboard();
      if (window.updateMapIncidents) window.updateMapIncidents(currentFilters);
    });
  }
}

// Initialize search
function initSearch() {
  const filterSearch = document.getElementById('filterSearch');
  const mobileSearch = document.getElementById('mobileSearch');
  if (!filterSearch && !mobileSearch) return;

  let searchTimeout;
  function handleInput(value) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      if (value) currentFilters.search = value.trim();
      else delete currentFilters.search;
      if (typeof currentPage !== 'undefined') currentPage = 1;
      if (typeof loadIncidents === 'function') loadIncidents();
      if (typeof loadDashboard === 'function') loadDashboard();
      if (window.updateMapIncidents) window.updateMapIncidents(currentFilters);
    }, 500);
  }

  if (filterSearch) {
    filterSearch.addEventListener('input', (e) => {
      // mirror to mobile input
      const mobile = document.getElementById('mobileSearch');
      if (mobile && mobile.value !== e.target.value) mobile.value = e.target.value;
      handleInput(e.target.value);
    });
  }

  if (mobileSearch) {
    mobileSearch.addEventListener('input', (e) => {
      // mirror to filter input
      const filter = document.getElementById('filterSearch');
      if (filter && filter.value !== e.target.value) filter.value = e.target.value;
      handleInput(e.target.value);
    });
  }
}

// Wire up on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initFilterSidebar();
    initFilters();
    initSearch();
  });
} else {
  initFilterSidebar();
  initFilters();
  initSearch();
}
