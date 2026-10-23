(()=>{
  const body=document.body;
  const theme=document.querySelector('#theme');
  const saved=localStorage.imgtrTheme;
  if(saved==='dark'||(!saved&&matchMedia('(prefers-color-scheme:dark)').matches))body.classList.add('dark');
  const syncTheme=()=>theme?.setAttribute('aria-pressed',body.classList.contains('dark')?'true':'false');
  syncTheme();
  theme?.addEventListener('click',()=>{
    body.classList.toggle('dark');
    localStorage.imgtrTheme=body.classList.contains('dark')?'dark':'light';
    syncTheme();
  });

  const menus=[...document.querySelectorAll('.language-picker,.account-menu')];
  menus.forEach(menu=>menu.addEventListener('toggle',()=>{
    if(menu.open)menus.forEach(other=>{if(other!==menu)other.open=false});
  }));
  document.addEventListener('click',event=>menus.forEach(menu=>{
    if(menu.open&&!menu.contains(event.target))menu.open=false;
  }));
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape')menus.forEach(menu=>menu.open=false);
  });

  document.addEventListener('click',async event=>{
    const logout=event.target.closest('a[href="/logout"]');
    const csrf=document.querySelector('meta[name="csrf-token"]')?.content;
    if(!logout||!csrf)return;
    event.preventDefault();
    try{
      const response=await fetch('/logout',{method:'POST',headers:{'X-CSRF-Token':csrf}});
      const result=await response.json();
      if(result.success)location.assign('/');
    }catch{ location.assign('/logout'); }
  });

  window.toast=text=>{
    const toast=document.querySelector('#toast');
    if(!toast)return;
    toast.textContent=text;
    toast.classList.add('show');
    setTimeout(()=>toast.classList.remove('show'),1800);
  };
  navigator.serviceWorker?.register('/sw.js').catch(()=>{});
})();
