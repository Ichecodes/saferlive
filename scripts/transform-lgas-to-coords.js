#!/usr/bin/env node
/**
 * transform-lgas-to-coords.js
 *
 * Reads `config/lgas.csv` and produces `locations_coords.json` in the project root.
 * Expected CSV header (case-insensitive): state,lga,lat,lng (order may vary)
 * Rows with invalid or missing lat/lng are skipped.
 * Usage: node scripts/transform-lgas-to-coords.js
 */

const fs = require('fs');
const path = require('path');

const inputPath = path.join(__dirname, '..', 'config', 'lgas.csv');
const outputPath = path.join(__dirname, '..', 'locations_coords.json');

function parseCSV(text) {
  // Simple CSV parser that handles quoted fields and commas inside quotes
  const rows = [];
  let cur = '';
  let row = [];
  let inQuotes = false;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (ch === '"') {
      if (inQuotes && text[i+1] === '"') { // escaped quote
        cur += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (ch === ',' && !inQuotes) {
      row.push(cur);
      cur = '';
    } else if ((ch === '\n' || ch === '\r') && !inQuotes) {
      if (cur !== '' || row.length > 0) {
        row.push(cur);
        rows.push(row);
      }
      cur = '';
      row = [];
      // handle CRLF by skipping the next char if needed
      if (ch === '\r' && text[i+1] === '\n') i++;
    } else {
      cur += ch;
    }
  }
  if (cur !== '' || row.length > 0) {
    row.push(cur);
    rows.push(row);
  }
  return rows;
}

function normalizeHeader(h) {
  return String(h || '').trim().toLowerCase();
}

function main() {
  if (!fs.existsSync(inputPath)) {
    console.error('Input file not found:', inputPath);
    process.exit(1);
  }

  const text = fs.readFileSync(inputPath, 'utf8');
  const rows = parseCSV(text);
  if (!rows || rows.length === 0) {
    console.error('No rows found in CSV');
    process.exit(1);
  }

  const header = rows[0].map(normalizeHeader);
  const idx = {
    state: header.indexOf('state'),
    lga: header.indexOf('lga'),
    lat: header.indexOf('lat') >= 0 ? header.indexOf('lat') : header.indexOf('latitude'),
    lng: header.indexOf('lng') >= 0 ? header.indexOf('lng') : header.indexOf('longitude')
  };

  if (idx.state < 0 || idx.lga < 0 || idx.lat < 0 || idx.lng < 0) {
    console.error('CSV must include headers: state,lga,lat,lng (or latitude,longitude)');
    console.error('Found headers:', header.join(','));
    process.exit(1);
  }

  const out = {};
  for (let i = 1; i < rows.length; i++) {
    const r = rows[i];
    if (!r) continue;
    const state = (r[idx.state] || '').trim().toLowerCase();
    const lga = (r[idx.lga] || '').trim().toLowerCase();
    const latStr = (r[idx.lat] || '').trim();
    const lngStr = (r[idx.lng] || '').trim();
    if (!state || !lga || !latStr || !lngStr) continue;
    const lat = Number(latStr);
    const lng = Number(lngStr);
    if (!isFinite(lat) || !isFinite(lng)) continue;
    if (!out[state]) out[state] = {};
    out[state][lga] = { lat: Number(lat.toFixed(6)), lng: Number(lng.toFixed(6)) };
  }

  fs.writeFileSync(outputPath, JSON.stringify(out, null, 2), 'utf8');
  console.log('Wrote', outputPath, 'with', Object.keys(out).length, 'states');
}

if (require.main === module) main();
