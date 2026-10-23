(()=>{
  const input=document.querySelector('#faq-search');
  const items=[...document.querySelectorAll('#faq-list details')];
  const empty=document.querySelector('#faq-empty');
  if(!input||!items.length)return;

  const normalize=value=>value.toLocaleLowerCase(document.documentElement.lang||undefined).normalize('NFKD').replace(/[\u0300-\u036f]/g,'');
  const filter=()=>{
    const query=normalize(input.value.trim());
    let visible=0;
    items.forEach(item=>{
      const match=!query||normalize(item.textContent).includes(query);
      item.hidden=!match;
      if(match){visible++;if(query)item.open=true}
    });
    if(empty)empty.hidden=visible!==0;
  };

  input.addEventListener('input',filter);
  document.addEventListener('keydown',event=>{
    if(event.key==='/'&&!/input|textarea|select/i.test(document.activeElement?.tagName||'')){
      event.preventDefault();input.focus();
    }
    if(event.key==='Escape'&&document.activeElement===input){
      input.value='';filter();input.blur();
    }
  });
})();
