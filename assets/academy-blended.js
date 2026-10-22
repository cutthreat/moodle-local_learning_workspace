document.addEventListener('change',event=>{if(event.target.matches('[data-video-speed]')){const video=document.querySelector('.ab-video');const rate=Number(event.target.value);if(video&&[.75,1,1.25,1.5,2].includes(rate))video.playbackRate=rate;}});
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.querySelector('.ab-onboarding');if(!form)return;
 const language=form.querySelector('[name="language"]'),other=form.querySelector('[data-other-language]'),input=form.querySelector('[name="otherlanguage"]');
 // A rejected value must remain reachable even if the language choice has changed.
 if(language&&other&&input){const syncLanguage=()=>{const required=language.value==='other';other.hidden=!(required||input.getAttribute('aria-invalid')==='true');input.required=required;};syncLanguage();language.addEventListener('change',syncLanguage);}
 const template=form.querySelector('[data-language-template]');
 if(template?.content){
  const rows=form.querySelector('[data-language-rows]'),add=form.querySelector('[data-add-language]'),limit=form.querySelector('[data-language-limit]'),status=form.querySelector('[data-language-status]');
  const undo=form.querySelector('[data-language-undo-group]'),undoList=form.querySelector('[data-language-undo-list]'),undoHelp=form.querySelector('[data-language-undo-help]'),removed=[];
  let nextId=rows.querySelectorAll('[data-language-row]').length;
  const sync=()=>{const all=Array.from(rows.querySelectorAll('[data-language-row]'));all.forEach((row,index)=>{
   row.querySelector('[data-language-number]').textContent=form.dataset.languageNumber.replace('{n}',String(index+1));
   row.querySelector('[data-remove-language]').hidden=all.length===1;
   row.querySelector('[data-remove-language]').value=String(index);
   const language=row.querySelector('[data-language-language]'),level=row.querySelector('[data-language-level]'),input=row.querySelector('[data-language-otherlanguage]'),other=row.querySelector('[data-other-language]');
   const hasValue=!!(language.value||level.value||input.value.trim());
   language.required=level.required=index===0||hasValue;
   other.hidden=!(language.value==='other'||input.getAttribute('aria-invalid')==='true');input.required=language.value==='other';
  });add.hidden=all.length>=Number(add.dataset.maxLanguages);limit.hidden=!add.hidden;
   if(undo){undo.hidden=removed.length===0;
    removed.forEach(saved=>{saved.button.disabled=add.hidden;});
    if(undoHelp){undoHelp.textContent=add.hidden?form.dataset.languageUndoLimit:form.dataset.languageUndoScope;}
   }
  };
  const dirty=()=>form.dispatchEvent(new Event('input',{bubbles:true}));
  add.addEventListener('click',event=>{event.preventDefault();if(rows.querySelectorAll('[data-language-row]').length>=Number(add.dataset.maxLanguages))return;
   const fragment=template.content.cloneNode(true);fragment.querySelectorAll('[id],[for]').forEach(el=>{for(const attr of ['id','for']){if(el.hasAttribute(attr))el.setAttribute(attr,el.getAttribute(attr).replace('on-languages-0-','on-languages-'+nextId+'-'));}});nextId++;
   const row=fragment.querySelector('[data-language-row]');rows.append(fragment);sync();dirty();row.querySelector('select').focus();status.textContent='';
  });
  rows.addEventListener('click',event=>{const button=event.target.closest('[data-remove-language]');if(!button)return;event.preventDefault();const all=Array.from(rows.querySelectorAll('[data-language-row]'));if(all.length===1)return;
   const row=button.closest('[data-language-row]'),index=all.indexOf(row),focus=all[index+1]||all[index-1];
   const select=row.querySelector('[data-language-language]'),custom=row.querySelector('[data-language-otherlanguage]');
   const label=select.value==='other'?custom.value.trim():(select.value?Array.from(select.options).find(option=>option.value===select.value)?.textContent.trim():'');
   if(undo&&undoList){
    const restore=document.createElement('button');restore.type='button';restore.className='lw-secondary';
    const rowLabel=row.querySelector('[data-language-number]').textContent;
    restore.textContent=label?form.dataset.languageUndo.replace('{language}',label):
     (form.dataset.languageUndoUnnamed?form.dataset.languageUndoUnnamed.replace('{row}',rowLabel):form.dataset.languageUndo.replace('{language}',rowLabel));
    restore.setAttribute('aria-describedby','on-language-undo-help');undoList.append(restore);
    const saved={row,index,button:restore};removed.push(saved);
    restore.addEventListener('click',()=>{
     if(!removed.includes(saved))return;
     const all=Array.from(rows.querySelectorAll('[data-language-row]'));
     if(all.length>=Number(add.dataset.maxLanguages))return;
     // Use surviving neighbours to preserve order even when deletions are restored out of order.
     const next=saved.after.find(candidate=>all.includes(candidate));
     const previous=saved.before.slice().reverse().find(candidate=>all.includes(candidate));
     rows.insertBefore(saved.row,next||(previous?all[all.indexOf(previous)+1]:all[saved.index])||null);
     removed.splice(removed.indexOf(saved),1);restore.remove();sync();dirty();
     saved.row.querySelector('select').focus();status.textContent=form.dataset.languageRestored;
    });
    saved.before=all.slice(0,index);saved.after=all.slice(index+1);
   }
   row.remove();sync();dirty();focus.querySelector('select').focus();status.textContent=form.dataset.languageRemoved;
  });rows.addEventListener('change',sync);rows.addEventListener('input',sync);sync();
 }
 form.querySelectorAll('[data-multiple-choice]').forEach(group=>group.addEventListener('change',event=>{
  const boxes=Array.from(group.querySelectorAll('input[type="checkbox"]'));
  boxes.forEach(box=>box.setCustomValidity(''));
  const exclusive=boxes.find(box=>box.hasAttribute('data-exclusive-choice')),message=group.querySelector('[data-choice-conflict]');
  if(exclusive&&message){const conflict=exclusive.checked&&boxes.some(box=>box!==exclusive&&box.checked);message.hidden=!conflict;exclusive.setCustomValidity(conflict?message.textContent:'');}
 }));
 form.addEventListener('submit',event=>{if(event.submitter?.formNoValidate||event.submitter?.value==='0')return;
  const groups=Array.from(form.querySelectorAll('[data-multiple-choice]')).map(group=>({boxes:Array.from(group.querySelectorAll('input[type="checkbox"]')),message:form.dataset.multipleError}));
  groups.push({boxes:Array.from(form.querySelectorAll('[name="days[]"]')),message:form.dataset.daysError||'Выберите хотя бы один удобный день занятий.'});
  for(const group of groups){if(group.boxes.length&&!group.boxes.some(box=>box.checked)){event.preventDefault();group.boxes[0].setCustomValidity(group.message);group.boxes[0].reportValidity();break;}}
 });
 form.querySelectorAll('[name="days[]"]').forEach(day=>day.addEventListener('change',()=>form.querySelector('[name="days[]"]').setCustomValidity('')));
});
