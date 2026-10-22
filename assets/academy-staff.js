(function(){
 'use strict';
 function init(){
  // Keep old deep links useful after separating manager tasks into distinct views.
  if(document.querySelector('[data-as-manage]')&&!new URL(location.href).searchParams.has('panel')){
   const panel={'#test-policy':'policy','#teaching':'overview','#support':'support','#announcements':'announcements','#quickstart':'quickstart'}[location.hash];
   if(panel&&panel!=='overview'){const url=new URL(location.href);url.searchParams.set('panel',panel);location.replace(url.href);return;}
  }
  const text=JSON.parse(document.getElementById('academy-staff-config')?.textContent||'{}');
  const forms=[];let allowLeave=false;
  const policy=document.querySelector('[data-as-policy]');
  if(policy){
   const retakes=policy.querySelector('[name=retakesenabled]'),limit=policy.querySelector('[name=attemptlimit]');
   function updateLimit(){policy.querySelector('[data-as-limit]').hidden=retakes.value==='0';if(retakes.value==='0')limit.value='1';}
   retakes.addEventListener('change',updateLimit);updateLimit();
  }
  document.querySelector('[data-as-scope]')?.addEventListener('change',event=>{
   if(!event.target.matches('select'))return;
   if(forms.some(check=>check())&&!window.confirm(text.leave)){
    event.currentTarget.querySelectorAll('select').forEach(select=>{select.value=Array.from(select.options).find(option=>option.defaultSelected)?.value||select.options[0].value;});return;
   }
   allowLeave=true;event.currentTarget.requestSubmit();
  });
  document.querySelectorAll('[data-as-preview]').forEach(button=>{
   const dialog=document.getElementById(button.dataset.asPreview);if(!dialog)return;
   button.addEventListener('click',()=>dialog.showModal());
   dialog.querySelector('[data-as-close]').addEventListener('click',()=>dialog.close());
   dialog.addEventListener('close',()=>button.focus());
  });
  document.querySelector('[data-as-confirm]')?.focus();
  document.querySelectorAll('.aw-page form[method=post]').forEach(form=>{
   let submitting=false;
   const fields=()=>Array.from(form.elements).filter(e=>e.name&&e.type!=='hidden'&&!['submit','button'].includes(e.type));
   const values=()=>JSON.stringify(fields().map(e=>[e.name,e.type==='checkbox'||e.type==='radio'?e.checked:e.type==='file'?Array.from(e.files||[]).map(f=>[f.name,f.size,f.lastModified]):e.value]));
   const baseline=values();let dirty=false;
   const status=document.createElement('p');status.className='as-form-status';status.setAttribute('role','status');status.setAttribute('aria-live','polite');form.append(status);
   function change(){dirty=values()!==baseline;status.textContent=dirty?(text.unsaved||''):'';}
   form.addEventListener('input',change);form.addEventListener('change',change);
   form.addEventListener('reset',()=>setTimeout(change,0));
   form.addEventListener('submit',event=>{
    if(submitting){event.preventDefault();return;}
    submitting=true;form.setAttribute('aria-busy','true');status.textContent=text.saving||'';
   });
   window.addEventListener('pageshow',()=>{submitting=false;form.removeAttribute('aria-busy');change();});
   forms.push(()=>dirty&&!submitting);
  });
  window.addEventListener('beforeunload',event=>{if(!allowLeave&&forms.some(check=>check())){event.preventDefault();event.returnValue='';}});
 }
 if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init,{once:true});}else{init();}
})();
