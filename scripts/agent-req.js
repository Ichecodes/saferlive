(function(){'use strict';

function el(id){return document.getElementById(id)}

function showMessage(msg, success=true){
  const r=el('response');
  if(!r) return;
  r.hidden=false; r.textContent=msg;
  r.style.borderColor = success? 'rgba(6,156,86,0.18)':'rgba(211,33,44,0.2)';
}

// Basic validators
function validateEmail(email){
  if(!email) return true; // optional
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePhone(phone){
  if(!phone) return false;
  const digits = phone.replace(/\D/g,'');
  // Accept 11-12 digits (covers local and international formats)
  return digits.length >= 11 && digits.length <= 12;
}

async function postJson(url, data){
  const resp = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)});
  return resp;
}

async function handleSubmit(e){
  e.preventDefault();
  const btn = e.target.querySelector('button[type=submit]'); if(btn) btn.disabled=true;

  const data = {
    full_name: el('full_name').value.trim(),
    phone: el('phone').value.trim(),
    email: el('email').value.trim(),
    agent_type: el('agent_type').value,
    job_type: el('job_type').value,
    state: el('state').value,
    lga: el('lga').value,
    address: el('address').value.trim(),
    // new fields
    job_details: (el('job_details')?.value || '').trim(),
    number_of_agents: parseInt(el('number_of_agents')?.value || '1', 10) || 1,
    number_of_days: parseInt(el('number_of_days')?.value || '1', 10) || 1,
    inter_city: !!el('inter_city')?.checked,
    foreign_national: !!el('foreign_national')?.checked
  };

  // Client-side validation
  if(!data.full_name){ showMessage('Full name is required', false); if(btn)btn.disabled=false; return; }
  if(!data.phone){ showMessage('Phone is required', false); if(btn)btn.disabled=false; return; }
  if(!validatePhone(data.phone)){ showMessage('Phone number looks invalid', false); if(btn)btn.disabled=false; return; }
  if(!data.agent_type){ showMessage('Agent type is required', false); if(btn)btn.disabled=false; return; }
  if(!data.job_type){ showMessage('Job type is required', false); if(btn)btn.disabled=false; return; }
  if(!el('state').value){ showMessage('State is required', false); if(btn)btn.disabled=false; return; }
  if(!el('lga').value){ showMessage('LGA is required', false); if(btn)btn.disabled=false; return; }
  if(!el('address').value || !el('address').value.trim()){ showMessage('Address is required', false); if(btn)btn.disabled=false; return; }
  // Validate numeric fields
  if (!Number.isInteger(data.number_of_agents) || data.number_of_agents < 1) { showMessage('Number of agents must be at least 1', false); if(btn)btn.disabled=false; return; }
  if (!Number.isInteger(data.number_of_days) || data.number_of_days < 1) { showMessage('Number of days must be at least 1', false); if(btn)btn.disabled=false; return; }
  // Email is optional but validate if provided
  if(el('email').value && !validateEmail(el('email').value)) { showMessage('Email address is invalid', false); if(btn)btn.disabled=false; return; }
  // Terms checkbox must be checked (HTML `required` helps, but double-check)
  if(!el('agree') || !el('agree').checked){ showMessage('You must agree to the terms and conditions', false); if(btn)btn.disabled=false; return; }

  try{
    // Post directly to the jobs create endpoint; it will return calculated_price in the response
    const resp = await postJson('/safer/api/jobs/create-job.php', data);
    const json = await resp.json();
    if(!resp.ok || !json.success){ showMessage(json.error || 'Unable to create request', false); }
    else{
      const currency = json.currency || 'NGN';
      const price = json.calculated_price !== undefined ? `${currency} ${json.calculated_price.toLocaleString()}` : null;
      showMessage('Request submitted — ID: ' + json.request_id + (price ? ' — Total: ' + price : ''), true);
      e.target.reset();
       // ✅ Redirect to payment/invoice page WITH job_id
  window.location.href = `/safer/pay.html?job_id=${json.request_id}`;
    }
  }catch(err){ showMessage('Network error', false); }
  finally{ if(btn) btn.disabled=false; }
}
document.addEventListener('DOMContentLoaded', ()=>{
  const form = el('agentForm'); if(form) form.addEventListener('submit', handleSubmit);
  // Load locations for state/lga dropdowns (reuses locations.json)
  async function loadLocations(){
    try{
      const resp = await fetch('/safer/locations.json');
      if(!resp.ok) return;
      const locations = await resp.json();
      const stateSel = el('state');
      const lgaSel = el('lga');
      if(!stateSel || !lgaSel) return;
      stateSel.innerHTML = '<option value="">Select state</option>' + locations.map(s => `<option value="${s.state}">${s.state.replace(/-/g,' ')}</option>`).join('');
      stateSel.addEventListener('change', ()=>{
        const state = stateSel.value;
        if(!state){ lgaSel.innerHTML = '<option value="">Select state first</option>'; return; }
        const s = locations.find(x => x.state === state);
        if(!s){ lgaSel.innerHTML = '<option value="">No LGAs</option>'; return; }
        lgaSel.innerHTML = '<option value="">Select LGA</option>' + s.lgas.map(l => `<option value="${l.lga}">${l.lga.replace(/-/g,' ')}</option>`).join('');
      });
    }catch(e){ /* ignore */ }
  }
  loadLocations();
  // Enable/disable submit based on agree checkbox
  const agree = el('agree'); const submitBtn = el('submitBtn'); if(agree && submitBtn){ agree.addEventListener('change', ()=>{ submitBtn.disabled = !agree.checked; }); submitBtn.disabled = !agree.checked; }
});

})();