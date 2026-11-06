(()=>{
  const i=window.IMGTR_I18N||{};
  const image=document.querySelector('#viewerImage');
  const loading=document.querySelector('#imageLoading');
  const dialog=document.querySelector('#reportDialog');
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';

  const retryDelays=[1500,3000,6000,10000];
  let retryCount=0;

  const imageLoaded=()=>{
    loading.hidden=true;
    loading.classList.remove('error');
  };

  const showFinalImageError=()=>{
    loading.replaceChildren();
    const heading=document.createElement('b');
    heading.textContent=i.load_error||'Image could not be loaded.';
    const detail=document.createElement('small');
    detail.textContent=i.try_later||'Please try again shortly.';
    loading.append(heading,detail);
    loading.hidden=false;
    loading.classList.add('error');
  };

  const retryImage=()=>{
    if(retryCount>=retryDelays.length){
      showFinalImageError();
      return;
    }
    const delay=retryDelays[retryCount++];
    loading.hidden=false;
    loading.classList.remove('error');
    window.setTimeout(()=>{
      const retryUrl=new URL(image.currentSrc||image.src,window.location.href);
      retryUrl.searchParams.set('_imgtr_retry',String(Date.now())+'-'+retryCount);
      image.src=retryUrl.href;
    },delay);
  };

  image.addEventListener('load',imageLoaded);
  image.addEventListener('error',retryImage);

  // Cached or very fast images can finish before this script attaches listeners.
  if(image.complete){
    if(image.naturalWidth>0)imageLoaded();
    else retryImage();
  }

  document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{
    const input=document.querySelector(button.dataset.copy);
    await navigator.clipboard.writeText(input.value);
    button.textContent=i.copied||'Copied';
    toast(i.copied||'Copied');
    setTimeout(()=>button.textContent=i.copy||'Copy',1500);
  }));

  document.querySelector('#nativeShare').addEventListener('click',async()=>{
    if(navigator.share)await navigator.share({title:'IMGTR',url:location.href});
    else{
      await navigator.clipboard.writeText(location.href);
      toast(i.copied||'Copied');
    }
  });

  document.querySelector('#openReport').addEventListener('click',()=>dialog.showModal());
  dialog.querySelector('[data-close]').addEventListener('click',()=>dialog.close());

  document.querySelector('#reportForm').addEventListener('submit',async event=>{
    event.preventDefault();
    const response=await fetch('/ajax/report.php',{
      method:'POST',
      body:new FormData(event.target),
      headers:{'X-CSRF-Token':csrf}
    });
    const data=await response.json();
    if(!data.success){
      toast(data.message||'Error');
      return;
    }
    dialog.close();
    toast(i.done||'Done');
  });
})();
