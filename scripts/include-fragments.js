(function(){
  'use strict';
  // Ensure pages always start at the top on load/navigation
  try{
    if('scrollRestoration' in history) history.scrollRestoration = 'manual';
  }catch(e){/* ignore */}
  window.addEventListener('pageshow', (ev)=>{
    if (ev.persisted) {
      try{ window.scrollTo(0,0); }catch(e){}
    }
  });
  async function loadFragment(el){
    const src = el.getAttribute('data-include');
    if(!src) return;
    try{
      const res = await fetch(src, {cache:'no-store'});
      if(!res.ok) throw new Error('Not found');
      const html = await res.text();
      el.innerHTML = html;

      // If we loaded the nav, mark active link
      if(src.includes('nav.html')){
        const links = el.querySelectorAll('.site-nav__links a');
        const path = location.pathname.split('/').pop() || 'index.html';
        links.forEach(a=>{
          const href = a.getAttribute('href') || '';
          if(href.endsWith(path) || (path==='index.html' && href==='index.html')){
            a.classList.add('active');
          }
        });
      }
    }catch(err){
      console.error('Include failed', src, err);
    }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('[data-include]').forEach(el=>loadFragment(el));
    // Ensure we start scrolled to top (covers normal load and some cached navigations)
    try{ setTimeout(()=>window.scrollTo({top:0,left:0,behavior:'auto'}),0); }catch(e){}
  });
})();
