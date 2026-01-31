// Mapbox incidents map: initialize mapbox and fetch incidents data
const MAPBOX_TOKEN = window.MAPBOX_TOKEN || 'pk.eyJ1IjoiYXFxdXRlIiwiYSI6ImNtajVlbXc5NDFvbWszZnF3ZjJrcTNiYXIifQ.4GZMU25Dj9NJV6D_U6XHCg';
mapboxgl.accessToken = MAPBOX_TOKEN;

let incidentMap = null;

function safeParseJsonText(text, label = 'response') {
  try {
    return JSON.parse(text);
  } catch (e) {
    console.error(`${label} returned invalid JSON. Raw response:\n`, text);
    return null;
  }
}

function addOrUpdateIncidentSource(incidents) {
  if (!incidentMap) return;

  const features = incidents
    .filter(i => i.latitude && i.longitude)
    .map(i => ({
      type: 'Feature',
      geometry: { type: 'Point', coordinates: [parseFloat(i.longitude), parseFloat(i.latitude)] },
      properties: {
        id: i.id,
        type: i.type || 'Incident',
        status: (i.status || 'open').toLowerCase(),
        victims: Number(i.victims || 0),
        state: i.state || '',
        lga: i.lga || ''
      }
    }));

  const geojson = { type: 'FeatureCollection', features };

  if (incidentMap.getSource('incidents')) {
    incidentMap.getSource('incidents').setData(geojson);
    return;
  }

  incidentMap.addSource('incidents', { type: 'geojson', data: geojson });

  incidentMap.addLayer({
    id: 'incident-points',
    type: 'circle',
    source: 'incidents',
    paint: {
      'circle-color': [
        'case', ['==', ['get', 'status'], 'open'], '#d3212c', '#9bb5a7'
      ],
      'circle-radius': [
        'interpolate', ['linear'], ['get', 'victims'], 0, 6, 5, 10, 10, 16, 20, 22
      ],
      'circle-opacity': 0.75,
      'circle-stroke-width': 1,
      'circle-stroke-color': '#ffffff'
    }
  });

  incidentMap.on('click', 'incident-points', (e) => {
    const p = e.features[0].properties;
    new mapboxgl.Popup()
      .setLngLat(e.lngLat)
      .setHTML(`<strong>${p.type}</strong><br>${p.state}, ${p.lga}<br>Status: ${p.status}<br>Victims: ${p.victims}`)
      .addTo(incidentMap);
  });

  incidentMap.on('mouseenter', 'incident-points', () => incidentMap.getCanvas().style.cursor = 'pointer');
  incidentMap.on('mouseleave', 'incident-points', () => incidentMap.getCanvas().style.cursor = '');
}

function initIncidentMap() {
  const mapContainer = document.getElementById('incidentMap');
  if (!mapContainer) return;

  incidentMap = new mapboxgl.Map({
    container: 'incidentMap',
    style: 'mapbox://styles/mapbox/light-v11',
    center: [8.6753, 9.0820],
    zoom: 5,
    attributionControl: false
  });

  incidentMap.addControl(new mapboxgl.NavigationControl());

  incidentMap.on('load', async () => {
    console.debug('Incident Map loaded');

    try {
      const res = await fetch('./api/incidents/list.php?limit=1000');
      console.debug('Incidents API HTTP status:', res.status);
      const text = await res.text();
      const json = safeParseJsonText(text, 'Incidents API');
      if (json && json.success && json.data) {
        addOrUpdateIncidentSource(json.data);
      } else {
        console.warn('Incidents API returned no data or success=false');
      }
    } catch (err) {
      console.error('Error fetching incidents for map:', err);
    }
  });

  // Expose a helper to refresh incidents on the map using current filters
  window.updateMapIncidents = async function(filters = {}) {
    try {
      const params = new URLSearchParams({ ...filters, limit: 1000 });
      const res = await fetch(`./api/incidents/list.php?${params}`);
      console.debug('Map incidents HTTP status:', res.status);
      const text = await res.text();
      const json = safeParseJsonText(text, 'Incidents API (map)');
      if (json && json.success && json.data) {
        addOrUpdateIncidentSource(json.data);
      } else {
        console.warn('Map incidents API returned no data or success=false');
        addOrUpdateIncidentSource([]);
      }
    } catch (err) {
      console.error('Error updating incidents for map:', err);
    }
  };

}

// Initialize map when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initIncidentMap);
} else {
  initIncidentMap();
}