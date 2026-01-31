#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const inPath = path.join(__dirname, '..', 'config', 'lgas.csv');
const outPath = path.join(__dirname, '..', 'locations_coords.json');

if (!fs.existsSync(inPath)) {
  console.error('Input not found:', inPath);
  process.exit(1);
}

const text = fs.readFileSync(inPath, 'utf8');
const lines = text.split(/\r?\n/).filter(Boolean);
const header = lines.shift().split(',').map(h => h.trim());
const idx = {
  name: header.indexOf('name'),
  state_name: header.indexOf('state_name'),
  latitude: header.indexOf('latitude'),
  longitude: header.indexOf('longitude')
};

if (idx.name < 0 || idx.state_name < 0 || idx.latitude < 0 || idx.longitude < 0) {
  console.error('Unexpected CSV header. Expected columns: name,state_name,latitude,longitude');
  process.exit(1);
}

const out = {};
for (const line of lines) {
  const parts = line.split(',');
  const name = (parts[idx.name] || '').trim();
  const state = (parts[idx.state_name] || '').trim();
  const lat = parseFloat((parts[idx.latitude] || '').trim());
  const lng = parseFloat((parts[idx.longitude] || '').trim());
  if (!name || !state || !isFinite(lat) || !isFinite(lng)) continue;
  const sk = state.toLowerCase();
  const lk = name.toLowerCase();
  out[sk] = out[sk] || {};
  out[sk][lk] = { lat: Number(lat.toFixed(6)), lng: Number(lng.toFixed(6)) };
}

fs.writeFileSync(outPath, JSON.stringify(out, null, 2), 'utf8');
console.log('Wrote', outPath);
