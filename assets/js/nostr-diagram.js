(()=>{
  const demo=document.querySelector('[data-nostr-demo]');
  if(!demo)return;

  const steps=['prepare','distribute','verify','deliver'];
  const durations=[2200,2800,2600,2200];
  const buttons=[...demo.querySelectorAll('[data-nostr-stage]')];
  const reduced=matchMedia('(prefers-reduced-motion: reduce)');
  const canHover=matchMedia('(hover: hover) and (pointer: fine)').matches;
  let current=0,timer=0,resumeTimer=0,visible=true;

  const clearTimers=()=>{clearTimeout(timer);clearTimeout(resumeTimer)};
  const render=index=>{
    current=index;
    delete demo.dataset.stage;
    void demo.offsetWidth;
    demo.dataset.stage=steps[index];
    buttons.forEach((button,buttonIndex)=>{
      const active=buttonIndex===index;
      button.classList.toggle('active',active);
      button.setAttribute('aria-pressed',String(active));
    });
  };
  const play=index=>{
    clearTimers();
    if(reduced.matches){demo.dataset.stage='complete';buttons.forEach(button=>button.setAttribute('aria-pressed','false'));return}
    render(index);
    if(visible)timer=setTimeout(()=>play((index+1)%steps.length),durations[index]);
  };
  const inspect=index=>{
    clearTimers();
    render(index);
    resumeTimer=setTimeout(()=>play((index+1)%steps.length),3600);
  };

  buttons.forEach((button,index)=>{
    button.setAttribute('aria-pressed','false');
    button.addEventListener('click',()=>inspect(index));
    if(canHover)button.addEventListener('pointerenter',()=>inspect(index));
  });

  if('IntersectionObserver'in window){
    new IntersectionObserver(entries=>{
      visible=entries[0]?.isIntersecting??true;
      if(!visible)clearTimers();else play(current);
    },{threshold:.2}).observe(demo);
  }
  reduced.addEventListener?.('change',()=>play(current));
  play(0);
})();
