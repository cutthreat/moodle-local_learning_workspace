/* Account menus and informational banners; Moodle owns the login lifetime. */
(function () {
 'use strict';
 function init() {
  var template=document.getElementById('academy-account-tools'),header=document.querySelector('.lw-top');
  if(template&&header){header.appendChild(template.content.cloneNode(true));template.remove();}
  // A compact mobile nav must reveal the current destination, not leave it off-screen.
  var navigation=header&&header.querySelector('.aw-primary');
  var currentLink=navigation&&navigation.querySelector('[aria-current="page"]');
  if(currentLink&&navigation.scrollWidth>navigation.clientWidth){
   currentLink.scrollIntoView({block:'nearest',inline:'nearest',behavior:'instant'});
  }
  var configNode=document.getElementById('academy-workspace-config');
  if(!configNode){return;}
  var config=JSON.parse(configNode.textContent);
  function closeMenus(except){
   document.querySelectorAll('[data-aw-popover-toggle]').forEach(function(toggle){
    if(toggle===except){return;} toggle.setAttribute('aria-expanded','false');
    var menu=document.getElementById(toggle.getAttribute('aria-controls')); if(menu){menu.hidden=true;}
   });
  }
  function positionMenu(toggle,menu){
   menu.style.setProperty('--aw-menu-top',Math.ceil(toggle.getBoundingClientRect().bottom+8)+'px');
  }
  function openMenu(toggle,menu){closeMenus(toggle);positionMenu(toggle,menu);toggle.setAttribute('aria-expanded','true');menu.hidden=false;}
  document.querySelectorAll('[data-aw-popover-toggle]').forEach(function(toggle){
   var menu=document.getElementById(toggle.getAttribute('aria-controls'));
   if(!menu){return;}
   toggle.addEventListener('click',function(){if(toggle.getAttribute('aria-expanded')==='true'){closeMenus();}else{openMenu(toggle,menu);}});
   var links=Array.from(menu.querySelectorAll('a'));
   if(toggle.classList.contains('aw-profile-toggle')){links.forEach(function(link){if(new URL(link.href).pathname===location.pathname&&!link.hash){link.setAttribute('aria-current','page');}});}
   toggle.closest('[data-aw-popover]').addEventListener('keydown',function(event){
    if(event.key==='Escape'){event.preventDefault();closeMenus();toggle.focus();return;}
    if(!['ArrowDown','ArrowUp','Home','End'].includes(event.key)){return;}
    event.preventDefault();openMenu(toggle,menu);
    var at=links.indexOf(document.activeElement),last=links.length-1;
    var index=event.key==='Home'?0:event.key==='End'?last:event.key==='ArrowDown'?(at+1)%links.length:(at<=0?last:at-1);
    if(links[index]){links[index].focus();}
   });
  });
  document.addEventListener('click',function(event){if(!event.target.closest('[data-aw-popover]')){closeMenus();}});
  document.addEventListener('focusin',function(event){var owner=event.target.closest('[data-aw-popover]');closeMenus(owner?owner.querySelector('[data-aw-popover-toggle]'):null);});
  window.addEventListener('resize',function(){document.querySelectorAll('[data-aw-popover-toggle][aria-expanded="true"]').forEach(function(toggle){positionMenu(toggle,document.getElementById(toggle.getAttribute('aria-controls')));});});
  // Browser focus/scroll-into-view must not close a menu before its link can be activated.
  window.addEventListener('scroll',function(event){if(event.target===document||event.target===window){document.querySelectorAll('[data-aw-popover-toggle][aria-expanded="true"]').forEach(function(toggle){positionMenu(toggle,document.getElementById(toggle.getAttribute('aria-controls')));});}},true);
  function revealNotification(){
   if(!/^#notification-(notice|review|help)-[0-9-]+$/.test(location.hash)){return;}
   var selected=document.getElementById(location.hash.slice(1));
   if(selected&&selected.classList.contains('aw-event')){selected.scrollIntoView({block:'start',behavior:'instant'});selected.focus({preventScroll:true});}
  }
  function scheduleNotification(){requestAnimationFrame(revealNotification);}
  scheduleNotification();window.addEventListener('hashchange',scheduleNotification);
  if(document.readyState!=='complete'){window.addEventListener('load',scheduleNotification,{once:true});}
  async function keyFor(text){
   var hash=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text.trim().replace(/\s+/g,' ')));
   return Array.from(new Uint8Array(hash)).map(function(b){return b.toString(16).padStart(2,'0');}).join('');
  }
  document.querySelectorAll('.lw-alert:not(.lw-error):not([role="alert"]):not([data-required-notice])').forEach(async function(notice){
   if(!window.crypto||!crypto.subtle){return;} var key=await keyFor(notice.textContent);
   if(config.dismissed.indexOf(key)!==-1){notice.hidden=true;return;}
   var button=document.createElement('button');button.type='button';button.className='aw-dismiss';
   button.setAttribute('aria-label',config.close);button.title=config.close;button.textContent='×';
   notice.classList.add('aw-dismissible');notice.appendChild(button);
   button.addEventListener('click',async function(){
    button.disabled=true;
    try{
     var response=await fetch(config.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'dismiss',key:key,sesskey:config.sesskey}).toString()});
     var result=await response.json();if(!response.ok||!result.ok){throw new Error('dismiss failed');}
     var next=notice.nextElementSibling;notice.hidden=true;if(next){next.setAttribute('tabindex','-1');next.focus({preventScroll:true});}
    }catch(error){
     button.disabled=false;var status=notice.querySelector('.aw-dismiss-error');
     if(!status){status=document.createElement('span');status.className='aw-dismiss-error';status.setAttribute('role','status');notice.appendChild(status);}
     status.textContent=config.error;
    }
   });
  });
 }
 if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
