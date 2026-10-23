document.addEventListener('click',async event=>{
  const copy=event.target.closest('[data-copy-text]');
  if(!copy)return;
  try{await navigator.clipboard.writeText(copy.dataset.copyText||'');const original=copy.textContent;copy.textContent='Kopyalandı';setTimeout(()=>copy.textContent=original,1200)}catch{}
});
document.addEventListener('submit',event=>{
  const message=event.target.dataset.confirm;
  if(message&&!window.confirm(message))event.preventDefault();
});
document.querySelectorAll('[data-ban-duration]').forEach(select=>{
  const form=select.closest('form'),custom=form?.querySelector('[data-ban-until]');
  const sync=()=>{if(custom){custom.hidden=select.value!=='custom';custom.required=select.value==='custom'}};
  select.addEventListener('change',sync);sync();
});
