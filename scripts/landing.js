let map;
let markers = [];
let markerInterval;
let markerTimeout;

// Nigeria coordinates
const NIGERIA_CENTER = [9.0820, 8.6753];

function initMap() {
  const mapContainer = document.getElementById("nigeriaMap");
  if (!mapContainer) return;

  // Initialize Leaflet map
  map = L.map("nigeriaMap", {
    center: NIGERIA_CENTER,
    zoom: 5, 
    minZoom: 5,
    maxZoom: 10,
    zoomControl: false,
    attributionControl: false,
    scrollWheelZoom: false,
    doubleClickZoom: false,
    dragging: false,
    touchZoom: false,
    boxZoom: false,
    keyboard: false
  });

  // Dark matter tiles
  L.tileLayer(
    "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",
    {
      maxZoom: 8,
      detectRetina: true,
      crossOrigin: true
    }
  ).addTo(map);

  // Animate slow zoom-in
  map.setZoom(5);
  setTimeout(() => {
    map.flyTo(NIGERIA_CENTER, 6.5, {
      duration: 6,
      easeLinearity: 0.08
    });
  }, 300);

  // After zoom animation completes → shift Nigeria to the right
  setTimeout(() => {
    const offsetX = 180; // shift right (px). Adjust to taste.
    const targetPoint = map.project(NIGERIA_CENTER, map.getZoom()).subtract([offsetX, 0]);
    const newCenter = map.unproject(targetPoint, map.getZoom());
    map.setView(newCenter, map.getZoom(), { animate: false });
  }, 7000); // run after zoom finishes (6s animation)

  // Start marker animation
  startMarkerAnimation();

  // Start parallax effect
  initMapParallax();
}

/* ---------------------------------------------
   PARALLAX SCROLL EFFECT
---------------------------------------------- */
function initMapParallax() {
  const hero = document.querySelector(".hero");
  const mapEl = document.getElementById("nigeriaMap");

  if (!hero || !mapEl) return;

  window.addEventListener("scroll", () => {
    const rect = hero.getBoundingClientRect();
    const scrollProgress = Math.min(Math.max(-rect.top / rect.height, 0), 1);

    const offset = scrollProgress * 50; // px drift
    mapEl.style.transform = `translateY(${offset}px) scale(1.05)`;
  });
}

/* ---------------------------------------------
   MARKER ANIMATION
---------------------------------------------- */

function createOrangeMarker(lat, lng) {
  const orangeIcon = L.divIcon({
    className: "orange-incident-marker",
    html: `<div style="
      width: 12px;
      height: 12px;
      background: #FF681E;
      border-radius: 50%;
      box-shadow: 0 0 12px rgba(255, 104, 30, 0.8), 
                  0 0 24px rgba(255, 104, 30, 0.4);
      animation: pulse 1s ease-in-out infinite;
    "></div>`,
    iconSize: [12, 12],
    iconAnchor: [6, 6]
  });

  const marker = L.marker([lat, lng], { icon: orangeIcon }).addTo(map);
  markers.push({ marker, createdAt: Date.now() });
  return marker;
}

function removeOldMarkers() {
  const now = Date.now();
  markers = markers.filter(item => {
    if (now - item.createdAt >= 3000) {
      map.removeLayer(item.marker);
      return false;
    }
    return true;
  });
}

function addRandomMarker() {
  // Random coordinates roughly within Nigeria
  const lat = 4.3 + Math.random() * (13.9 - 4.3);
  const lng = 2.6 + Math.random() * (14.7 - 2.6);
  createOrangeMarker(lat, lng);
  removeOldMarkers();
}

function startMarkerAnimation() {
  addRandomMarker();
  markerInterval = setInterval(addRandomMarker, 2000);
  markerTimeout = setInterval(removeOldMarkers, 500);
}

/* ---------------------------------------------
   STATS COUNTER
---------------------------------------------- */
const statsObserver = new IntersectionObserver(
  entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounters();
        statsObserver.disconnect();
      }
    });
  },
  { threshold: 0.3 }
);

const statValues = document.querySelectorAll(".stat-value");

function animateCounters() {
  statValues.forEach(el => {
    const target = parseInt(el.dataset.target, 10);
    const duration = 1600;
    const start = performance.now();

    const tick = now => {
      const progress = Math.min((now - start) / duration, 1);
      const value = Math.floor(progress * target);
      el.textContent = value.toLocaleString();
      if (progress < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
  });
}

/* ---------------------------------------------
   FORM HANDLING
---------------------------------------------- */
function handleForm() {
  const form = document.getElementById("whatsappForm");
  const statusEl = document.getElementById("formStatus");
  if (!form) return;

  form.addEventListener("submit", async event => {
    event.preventDefault();
    statusEl.textContent = "Sending...";

    const payload = Object.fromEntries(new FormData(form).entries());

    try {
      const res = await fetch("/api/whatsapp/collect", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      if (!res.ok) throw new Error("Failed to submit. Please try again.");

      statusEl.textContent = "Success! Redirecting to WhatsApp...";
      setTimeout(() => {
        window.location.href = "https://chat.whatsapp.com/placeholderlink";
      }, 400);
    } catch (err) {
      statusEl.textContent = err.message || "Something went wrong. Try again.";
    }
  });
}

/* ---------------------------------------------
   MOBILE NAVIGATION
---------------------------------------------- */
function handleMobileNav() {
  const navToggle = document.querySelector(".nav__toggle");
  const nav = document.querySelector(".nav");

  if (!navToggle || !nav) return;

  navToggle.addEventListener("click", () => {
    const isOpen = nav.classList.contains("nav--open");
    nav.classList.toggle("nav--open");
    navToggle.setAttribute("aria-expanded", !isOpen);
  });

  document.addEventListener("click", (e) => {
    if (!nav.contains(e.target) && nav.classList.contains("nav--open")) {
      nav.classList.remove("nav--open");
      navToggle.setAttribute("aria-expanded", "false");
    }
  });

  const navLinks = document.querySelectorAll(".nav__links a");
  navLinks.forEach(link => {
    link.addEventListener("click", () => {
      nav.classList.remove("nav--open");
      navToggle.setAttribute("aria-expanded", "false");
    });
  });
}

/* ---------------------------------------------
   INITIALIZE EVERYTHING
---------------------------------------------- */
function init() {
  initMap();
  statsObserver.observe(document.querySelector(".stats"));
  handleForm();
  handleMobileNav();
}

window.addEventListener("load", init);
