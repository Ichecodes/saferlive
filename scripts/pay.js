// Original pay.js — minimal, as provided earlier
(function(){
  'use strict';

  

  function qs(sel){return document.querySelector(sel)}
  function q(id){return document.getElementById(id)}

  // Read job_id from query string
  function getQuery(name){
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  async function fetchJson(url){
    const r = await fetch(url, {cache:'no-store'});
    if(!r.ok) throw new Error('Network');
    return await r.json();
  }

  function formatCurrency(curr, amount){
    return curr + ' ' + amount.toLocaleString();
  }

  async function render(){
    const jobId = getQuery('job_id');
    if(!jobId){ qs('#invoiceRoot').innerHTML = '<p>No job_id provided in query string.</p>'; return; }

    try{
      const resp = await fetchJson(`api/pay.php?job_id=${encodeURIComponent(jobId)}`);
      if(!resp.success) throw new Error(resp.error || 'Unable to load');
      const job = resp.data;
      const currency = job.currency || 'NGN';

      q('invoiceDate').textContent = new Date().toLocaleString();
      q('billName').textContent = job.full_name || '';
      q('billEmail').textContent = job.email || '';
      q('billPhone').textContent = job.phone || '';

      q('bankName').textContent = '';
      q('bankAccount').textContent = '';
      q('bankAccountName').textContent = '';

      // load invoice settings
      let invoiceCfg = { bank: {}, contact: {}, paystack_public_key: '', whatsapp_number: '' };
      try{ invoiceCfg = await fetchJson(`scripts/invoice.json`); }catch(e){}
      q('bankName').textContent = invoiceCfg.bank?.name || '';
      q('bankAccount').textContent = invoiceCfg.bank?.account_number || '';
      q('bankAccountName').textContent = invoiceCfg.bank?.account_name || '';

      const tbody = q('itemsBody'); tbody.innerHTML = '';
      const unit = job.unit_price || Math.round((job.calculated_price || 0) / Math.max(1, (job.number_of_agents||1)*(job.number_of_days||1)));
      const agents = job.number_of_agents || 1;
      const days = job.number_of_days || 1;
      const total = job.calculated_price || 0;

      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${job.agent_type || 'Personnel'}</td><td>${formatCurrency(currency, unit)}</td><td>${agents}</td><td>${days}</td><td>${formatCurrency(currency, total)}</td>`;
      tbody.appendChild(tr);

      q('grandTotal').textContent = formatCurrency(currency, total);

      // footer
     qs('#footerInfo').innerHTML = `
  <ul class="footer-list">

    ${invoiceCfg.contact?.phone ? `
      <li class="footer-item">
        <span class="footer-icon">
          <!-- Phone icon -->
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6.6 10.8c1.5 3 3.6 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3
                     1.2.4 2.5.6 3.9.6.6 0 1 .4 1 1V20
                     c0 .6-.4 1-1 1C10.1 21 3 13.9 3 5
                     c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1
                     0 1.3.2 2.6.6 3.9.1.4 0 .8-.3 1.1l-2.2 2.2z"/>
          </svg>
        </span>
        <span>${invoiceCfg.contact.phone}</span>
      </li>` : ''}

    ${invoiceCfg.contact?.email ? `
      <li class="footer-item">
        <span class="footer-icon">
          <!-- Email icon -->
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20 4H4c-1.1 0-2 .9-2 2v12
                     c0 1.1.9 2 2 2h16
                     c1.1 0 2-.9 2-2V6
                     c0-1.1-.9-2-2-2zm0 4
                     l-8 5-8-5V6l8 5 8-5v2z"/>
          </svg>
        </span>
        <span>${invoiceCfg.contact.email}</span>
      </li>` : ''}

    ${invoiceCfg.contact?.website ? `
      <li class="footer-item">
        <span class="footer-icon">
          <!-- Globe icon -->
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm7.9 9h-3.2
                     a15.3 15.3 0 00-1.1-5.1A8.03 8.03 0 0119.9 11zM12 4
                     c.9 1.3 1.6 3.3 1.9 5H10.1
                     c.3-1.7 1-3.7 1.9-5zM4.1 11
                     a8.03 8.03 0 014.3-5.1
                     A15.3 15.3 0 007.3 11H4.1zm0 2h3.2
                     a15.3 15.3 0 001.1 5.1
                     A8.03 8.03 0 014.1 13zM12 20
                     c-.9-1.3-1.6-3.3-1.9-5h3.8
                     c-.3 1.7-1 3.7-1.9 5zm3.6-1.9
                     A15.3 15.3 0 0016.7 13h3.2
                     a8.03 8.03 0 01-4.3 5.1z"/>
          </svg>
        </span>
        <span>${invoiceCfg.contact.website}</span>
      </li>` : ''}

    ${invoiceCfg.contact?.address ? `
      <li class="footer-item">
        <span class="footer-icon">
          <!-- Location icon -->
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2a7 7 0 00-7 7
                     c0 5.2 7 13 7 13s7-7.8 7-13
                     a7 7 0 00-7-7zm0 9.5
                     a2.5 2.5 0 110-5
                     2.5 2.5 0 010 5z"/>
          </svg>
        </span>
        <span>${invoiceCfg.contact.address}</span>
      </li>` : ''}

  </ul>
`;


      // Buttons
      const paystackBtn = q('paystackBtn');
      const whatsappBtn = q('whatsappBtn');
      const printBtn = q('printBtn');

      if(invoiceCfg.paystack_public_key){
        paystackBtn.addEventListener('click', async ()=>{
          try{
            // Initialize payment on server which returns amount in kobo and public key
            const init = await fetchJson(`api/pay-init.php?job_id=${encodeURIComponent(job.id)}`);
            if(!init.success) throw new Error(init.error || 'Unable to initialize payment');
            const pk = init.paystack_public_key || invoiceCfg.paystack_public_key;
            const amount = init.amount_kobo; // in kobo
            const email = init.customer?.email || job.email || '';

            if (!pk) throw new Error('Paystack public key not configured');
            if (!window.PaystackPop) throw new Error('Paystack library not loaded');

            const handler = PaystackPop.setup({
              key: pk,
              email: email,
              amount: amount,
              currency: init.currency || 'NGN',
              metadata: {
                custom_fields: [{ display_name: "Job ID", variable_name: "job_id", value: job.id }]
              },
              callback: function(response){
                // On success, redirect to server verify endpoint
                window.location = `api/pay-verify.php?job_id=${encodeURIComponent(job.id)}&reference=${encodeURIComponent(response.reference)}`;
              },
              onClose: function(){
                alert('Payment window closed');
              }
            });
            handler.openIframe();
          } catch(err){
            alert('Payment error: ' + err.message);
            console.error(err);
          }
        });
      } else {
        paystackBtn.disabled = true; paystackBtn.title = 'Paystack public key not configured';
      }

      const whatsappNumber = invoiceCfg.whatsapp_number || '';
      if(whatsappNumber){
        whatsappBtn.addEventListener('click', ()=>{
          const msg = encodeURIComponent(`Hi, I want to pay for job ${job.id}. Amount: ${currency} ${total}`);
          const url = `https://wa.me/${whatsappNumber.replace(/[^0-9]/g,'')}?text=${msg}`;
          window.open(url,'_blank');
        });
      } else {
        whatsappBtn.disabled = true; whatsappBtn.title = 'WhatsApp number not configured';
      }

      printBtn.addEventListener('click', ()=>window.print());

    } catch(err){
      qs('#invoiceRoot').innerHTML = '<p>Error loading invoice.</p>';
      console.error(err);
    }
  }

  document.addEventListener('DOMContentLoaded', render);

})();
