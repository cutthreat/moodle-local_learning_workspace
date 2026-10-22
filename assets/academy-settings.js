/* Account, onboarding and support forms: native validation, server-owned saves. */
(function () {
 'use strict';
 function init() {
  const pending=[];
  const signature=form=>JSON.stringify(Array.from(form.elements)
   .filter(field=>field.name&&!field.disabled&&!field.readOnly&&!['hidden','submit','button','reset'].includes(field.type))
   .map(field=>[field.name,field.type==='checkbox'||field.type==='radio'?field.checked:field.value]));
  document.querySelectorAll('form[data-aw-form]').forEach(form=>{
   const status=form.querySelector('[data-aw-form-status]');
   const initial=signature(form);
   const initiallyUnsaved=form.dataset.awUnsaved==='true';
   let dirty=initiallyUnsaved,submitting=false,locked=[],pendingControls=[],pendingSubmitEvent=null;
   function change() {
    dirty=initiallyUnsaved||signature(form)!==initial;
    if(!status||submitting)return;
    status.textContent=dirty?(form.dataset.awForm==='help'?'Обращение ещё не отправлено':'Есть несохранённые изменения'):'';
    status.hidden=!dirty;
   }
   function unlock() {
    submitting=false;form.removeAttribute('aria-busy');
    locked.forEach(button=>{button.disabled=false;});locked=[];
    pendingControls.forEach(control=>{control.disabled=false;});pendingControls=[];pendingSubmitEvent=null;change();
   }
   // The entry list already exists when formdata fires. Disabling before this
   // point would remove named controls (including finish/draft) from the POST.
   form.addEventListener('formdata',()=>{
    // A validator may construct FormData while submit is still dispatching.
    // Only the native post-dispatch entry-list phase may lock the controls.
    if(!submitting||!pendingSubmitEvent||pendingSubmitEvent.eventPhase!==0||pendingSubmitEvent.defaultPrevented||pendingControls.length)return;
    pendingControls=Array.from(form.elements).filter(control=>'disabled' in control&&!control.disabled&&control.type!=='hidden');
    pendingControls.forEach(control=>{control.disabled=true;});
   });
   form.addEventListener('input',change);
   form.addEventListener('change',change);
   form.addEventListener('reset',()=>setTimeout(change,0));
   form.addEventListener('submit',event=>{
    if(submitting){event.preventDefault();return;}
    if(event.defaultPrevented)return;
    // Let the browser and server keep the real validation and navigation contract.
    const skipValidation=form.noValidate||Boolean(event.submitter&&event.submitter.formNoValidate);
    if(!skipValidation&&!form.checkValidity())return;
    pendingSubmitEvent=event;submitting=true;form.setAttribute('aria-busy','true');
    if(status){status.hidden=false;status.textContent=form.dataset.awForm==='help'?'Отправляем обращение…':'Сохраняем изменения…';}
    // Named submitters are part of native form data; never disable them here.
    locked=Array.from(form.querySelectorAll('button[type="submit"],button:not([type])'))
     .filter(button=>!button.disabled&&!button.name);
    locked.forEach(button=>{button.disabled=true;});
    // A later page-specific validator can still cancel this native submit.
    // Wait for the next task: browsers can run microtasks between listeners.
    setTimeout(()=>{if(event.defaultPrevented)unlock();},0);
   });
   window.addEventListener('pageshow',unlock);
   change();
   pending.push(()=>dirty&&!submitting);
  });
  if(pending.length){
   window.addEventListener('beforeunload',event=>{
    if(pending.some(check=>check())){event.preventDefault();event.returnValue='';}
   });
  }
  const error=document.querySelector('.lw-error[role="alert"]');
  if(error){error.tabIndex=-1;error.focus();}
  document.querySelectorAll('[data-aw-error-summary] a[href^="#"]').forEach(link=>{
   link.addEventListener('click',event=>{
    const target=document.getElementById(link.hash.slice(1));
    if(!target)return;
    const details=target.closest('details');
    if(details)details.open=true;
    event.preventDefault();
    target.focus();
   });
  });
  const nav=document.querySelector('.al-help .al-section-nav');
  if(nav){
   const links=Array.from(nav.querySelectorAll('a[href^="#"]'));
   const targets=links.map(link=>document.getElementById(link.hash.slice(1))).filter(Boolean);
   function indicate(){
    const offset=matchMedia('(max-width:760px)').matches?nav.getBoundingClientRect().height+32:64;
    const current=targets.filter(target=>target.getBoundingClientRect().top<=offset).pop()||targets[0];
    links.forEach(link=>{
     if(current&&link.hash==='#'+current.id){link.setAttribute('aria-current','location');}
     else{link.removeAttribute('aria-current');}
    });
   }
   let queued=false;
   window.addEventListener('scroll',()=>{if(!queued){queued=true;requestAnimationFrame(()=>{queued=false;indicate();});}},{passive:true});
   window.addEventListener('resize',indicate);
   indicate();
  }
 }
 if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init,{once:true});}else{init();}
})();
