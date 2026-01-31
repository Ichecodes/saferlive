// Client-side logic for report form
document.addEventListener('DOMContentLoaded', () => {
  const stateEl = document.getElementById('state');
  const lgaEl = document.getElementById('lga');
  const getLocationBtn = document.getElementById('getLocation');
  const latEl = document.getElementById('latitude');
  const lonEl = document.getElementById('longitude');
  const form = document.getElementById('reportForm');
  const resultEl = document.getElementById('result');
  const captchaQuestion = document.getElementById('captchaQuestion');

  const STATES = {
    "Abia": ["Aba North","Aba South","Bende"],
    "Lagos": ["Ikeja","Eti-Osa","Surulere"],
    "Rivers": ["Port Harcourt","Obio-Akpor"],
    "FCT": ["Abuja Municipal","Bwari"]
  };

  // populate states
  Object.keys(STATES).forEach(s => {
    const opt = document.createElement('option'); opt.value = s; opt.textContent = s; stateEl.appendChild(opt);
  });

  stateEl.addEventListener('change', () => {
    const v = stateEl.value; lgaEl.innerHTML = '';
    if (!v) { lgaEl.innerHTML = '<option value="">Select state first</option>'; return; }
    STATES[v].forEach(l => { const o = document.createElement('option'); o.value = l; o.textContent = l; lgaEl.appendChild(o); });
  });

  // set default date/time (date default to today, time default to 12:00)
  const dt = document.getElementById('datetime');
  (function setDefaultDate(){
    const d = new Date(); d.setHours(12,0,0,0);
    const iso = d.toISOString();
    // datetime-local expects YYYY-MM-DDTHH:MM
    dt.value = iso.substring(0,16);
  })();

  // get server captcha
  fetch('report.php?action=captcha').then(r=>r.json()).then(j=>{
    captchaQuestion.textContent = j.question || 'Solve:';
  }).catch(()=>{ captchaQuestion.textContent = 'Enter the answer'; });

  getLocationBtn.addEventListener('click', ()=>{
    if (!navigator.geolocation) return alert('Geolocation not supported');
    getLocationBtn.disabled = true; getLocationBtn.textContent = 'Getting…';
    navigator.geolocation.getCurrentPosition(pos=>{
      latEl.value = pos.coords.latitude.toFixed(6);
      lonEl.value = pos.coords.longitude.toFixed(6);
      getLocationBtn.textContent = 'Use my location'; getLocationBtn.disabled = false;
    }, err=>{ alert('Could not get location'); getLocationBtn.disabled=false; getLocationBtn.textContent='Use my location'; });
  });

  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());

    // require either full_name or phone
    if (!data.full_name && !data.phone) { alert('Please provide full name or phone number'); return; }

    // parse numeric fields
    ['victims','injured','dead','missing'].forEach(k=>{ if(!data[k]) data[k]=0; else data[k]=parseInt(data[k],10)||0 });

    // send
    try{
      const res = await fetch('report.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
      const json = await res.json();
      if(json.success){
        form.classList.add('hidden');
        resultEl.classList.remove('hidden');
        // permalink placeholder
        document.getElementById('permalink').textContent = json.permalink || '(will be available once approved)';

        const shareText = encodeURIComponent(`${data.title} — ${data.description.substring(0,120)}...`);
        document.getElementById('shareWhatsApp').addEventListener('click', ()=>{
          window.open(`https://wa.me/?text=${shareText}`,'_blank');
        });
        document.getElementById('shareX').addEventListener('click', ()=>{
          window.open(`https://twitter.com/intent/tweet?text=${shareText}`,'_blank');
        });
        document.getElementById('shareFacebook').addEventListener('click', ()=>{
          window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(location.href)}&quote=${shareText}`,'_blank');
        });
      } else {
        alert(json.error || 'Submission failed');
      }
    } catch(err){ console.error(err); alert('Submission error'); }
  });
});
