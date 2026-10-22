(function () {
  'use strict';
  function init() {
    const form = document.getElementById('ac-review-form');
    if (!form || form.dataset.enhanced) return;
    form.dataset.enhanced = '1';
    form.noValidate = true;
    // Named submit buttons shadow HTMLFormElement.action; read the attribute explicitly.
    const endpoint = form.getAttribute('action') || location.href;
    const cards = Array.from(form.querySelectorAll('.ac-criterion'));
    const summary = document.getElementById('ac-review-errors');
    const progress = form.querySelector('[data-ac-review-progress]');
    const decision = document.getElementById('review-decision');
    const feedback = document.getElementById('review-feedback');
    const commit = form.querySelector('[data-ac-commit]');
    const nextCommit = form.querySelector('[value="review_next"]');
    const saveStatus = form.querySelector('[data-ac-save-status]');
    const ux = JSON.parse(document.getElementById('academy-staff-config')?.textContent || '{}');
    const values = () => JSON.stringify(Array.from(form.querySelectorAll('select,textarea')).map(field=>[field.name,field.value]));
    const baseline = values();
    let selected = null, dirty = false, submitting = false, navigating = false;
    let lockedFields = [];
    // Capture FormData before locking. Otherwise disabled fields are omitted.
    function lockForm() {
      lockedFields = Array.from(form.querySelectorAll('input,select,textarea,button'))
        .filter(field => !field.disabled);
      lockedFields.forEach(field => { field.disabled = true; });
      form.setAttribute('aria-busy','true');
    }
    function unlockForm() {
      lockedFields.forEach(field => { field.disabled = false; });
      lockedFields = [];
      form.removeAttribute('aria-busy');
    }
    const length = s => Array.from(s.trim()).length;
    const normalize = s => s.replace(/\r\n?/g, '\n');
    const needed = s => ['0', '1', 'fail'].includes(s.value);
    function texts(card) {
      try { return JSON.parse(card.closest('.ac-answer').querySelector('[data-ac-answer-text]').textContent); }
      catch (_) { return []; }
    }
    function checks(card) {
      const score = card.querySelector('select'), quote = card.querySelector('[data-ac-quote]');
      const next = card.querySelector('[data-ac-next]'), errors = [];
      if (!score.value) errors.push([score, 'Выберите оценку.']);
      if (needed(score)) {
        if (length(quote.value) < 8 || length(quote.value) > 300) errors.push([quote, 'Цитата: от 8 до 300 символов.']);
        else if (!texts(card).some(answer => normalize(answer).includes(normalize(quote.value.trim()))))
          errors.push([quote, 'Такого фрагмента нет в ответе. Вставьте точную цитату.']);
        if (length(next.value) < 15 || length(next.value) > 1000)
          errors.push([next, 'Опишите конкретное исправление: 15–1000 символов.']);
      }
      return errors;
    }
    function fieldError(field, message) {
      const id = field.id + '-error';
      let hint = document.getElementById(id);
      if (!hint) { hint = document.createElement('small'); hint.id = id; hint.className = 'ac-field-error'; field.after(hint); }
      hint.textContent = message; hint.hidden = !message;
      field.setAttribute('aria-invalid', message ? 'true' : 'false');
      const descriptions = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      descriptions.add(id); field.setAttribute('aria-describedby', Array.from(descriptions).join(' '));
    }
    function showErrors(errors, message) {
      summary.replaceChildren(); summary.hidden = false;
      const heading = document.createElement('strong');
      heading.textContent = message || 'Решение не сохранено. Исправьте поля: ' + errors.length + '.';
      summary.append(heading);
      if (errors.length) {
        const list = document.createElement('ul');
        errors.forEach(([field, copy]) => {
          fieldError(field, copy);
          const item = document.createElement('li'), link = document.createElement('a');
          link.href = '#' + field.id;
          const question = field.closest('.ac-answer');
          link.textContent = (question ? question.querySelector('h3').textContent + ' · ' : '') + copy;
          link.addEventListener('click', event => { event.preventDefault(); field.focus(); field.scrollIntoView({block:'center'}); });
          item.append(link); list.append(item);
        });
        summary.append(list);
      }
      summary.focus(); summary.scrollIntoView({block:'center'});
    }
    const labels = {ready:'Принять работу', practice_accepted:'Принять работу', rework:'Вернуть на доработку',
      practice_rework:'Вернуть на доработку', not_ready:'Сохранить решение «Пока не готов»', practice_feedback:'Отправить обратную связь'};
    function update() {
      let complete = 0;
      cards.forEach(card => {
        const score = card.querySelector('select'), panel = card.querySelector('.ac-criterion-evidence');
        panel.hidden = !needed(score);
        panel.querySelectorAll('textarea').forEach(field => {
          field.required = needed(score);
          const hint = document.getElementById(field.id + '-hint');
          if (hint) hint.textContent = length(field.value) + ' / ' + field.maxLength + ' · минимум ' + field.minLength;
          if (!needed(score)) fieldError(field, '');
        });
        if (!checks(card).length) complete++;
      });
      progress.textContent = 'Готово ' + complete + ' из ' + cards.length + ' критериев (оценка и обоснование)';
      commit.textContent = labels[decision.value] || 'Сохранить решение';
      if (nextCommit) {
        const nextLabels = {ready:'Принять', practice_accepted:'Принять',
          rework:'Вернуть на доработку', practice_rework:'Вернуть на доработку',
          not_ready:'Сохранить решение', practice_feedback:'Отправить комментарий'};
        nextCommit.textContent = (nextLabels[decision.value] || 'Сохранить решение') + ' и открыть следующую';
      }
      document.getElementById('ac-review-summary').textContent = decision.value ?
        'Кандидату будет отправлено: «' + decision.options[decision.selectedIndex].textContent + '». Готово критериев: ' + complete + '/' + cards.length + '.' :
        'Выберите итоговое решение. Черновик проверки ученику не отправляется.';
    }
    document.addEventListener('selectionchange', () => {
      const selection = window.getSelection();
      if (!selection || selection.isCollapsed || !selection.anchorNode || !selection.focusNode) return;
      const element = selection.anchorNode.nodeType === 1 ? selection.anchorNode : selection.anchorNode.parentElement;
      const source = element.closest('[data-ac-source]');
      if (source && source.contains(selection.focusNode)) selected = {question:source.closest('.ac-answer'), text:selection.toString().trim()};
    });
    cards.forEach(card => {
      card.querySelector('[data-ac-copy-quote]').addEventListener('click', () => {
        const quote = card.querySelector('[data-ac-quote]');
        if (!selected || selected.question !== card.closest('.ac-answer')) {
          fieldError(quote, 'Сначала выделите нужный фрагмент в ответе этого кандидата выше.'); quote.focus(); return;
        }
        if (length(selected.text) < 8 || length(selected.text) > 300) {
          fieldError(quote, 'Выделите от 8 до 300 символов одного ответа.'); quote.focus(); return;
        }
        quote.value = selected.text; changed(); fieldError(quote,''); update(); quote.focus();
      });
    });
    form.querySelector('[data-ac-review-find]').addEventListener('click', () => {
      const invalid = cards.flatMap(checks)[0];
      const target = invalid ? invalid[0] : (decision.value ? feedback : decision);
      target.focus(); target.scrollIntoView({block:'center'});
    });
    function changed(){dirty=values()!==baseline;if(saveStatus)saveStatus.textContent=dirty?(ux.unsaved||''):'';}
    form.addEventListener('input', event => { changed(); if (event.target.id) fieldError(event.target,''); update(); });
    form.addEventListener('change', () => { changed(); update(); });
    window.addEventListener('beforeunload', event => {
      if (!navigating && (dirty || submitting)) { event.preventDefault(); event.returnValue=''; }
    });
    form.addEventListener('submit', async event => {
      if (submitting) { event.preventDefault(); return; }
      const action = ['save_draft','review_next'].includes(event.submitter?.value) ? event.submitter.value : 'review';
      if (action !== 'save_draft') {
        const errors = cards.flatMap(checks);
        if (!decision.value) errors.push([decision,'Выберите итоговое решение.']);
        if (length(feedback.value) < 10 || length(feedback.value) > 4000) errors.push([feedback,'Комментарий: от 10 до 4000 символов.']);
        if (errors.length) { event.preventDefault(); showErrors(errors); return; }
      }
      if (!window.fetch || !window.AbortController) { submitting=true; navigating=true; return; }
      event.preventDefault(); submitting=true;
      if(saveStatus)saveStatus.textContent=ux.saving||'';
      const data = new FormData(form); data.set('action',action);
      lockForm();
      const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 20000);
      try {
        const response = await fetch(endpoint,{method:'POST',body:data,credentials:'same-origin',
          headers:{'X-Academy-Review':'1'},signal:controller.signal});
        if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('unexpected-response');
        const result = await response.json(), destination = new URL(result.redirect,location.href);
        if (destination.origin !== location.origin || !destination.pathname.endsWith('/academy_review.php')) throw new Error('unexpected-destination');
        dirty=false; navigating=true; location.assign(destination.href);
      } catch (_) {
        submitting=false; unlockForm();
        if(saveStatus)saveStatus.textContent=ux.unconfirmed||'';
        showErrors([], 'Не удалось подтвердить сохранение. Введённое осталось в форме. Не закрывайте её: сначала проверьте статус работы.');
        const link=document.createElement('a'); link.href=endpoint; link.target='_blank'; link.rel='noopener';
        link.textContent='Проверить статус в новой вкладке'; summary.append(document.createElement('br'),link);
      } finally { clearTimeout(timeout); }
    });
    summary.querySelectorAll('a[href^="#"]').forEach(link => {
      const field=document.getElementById(link.hash.slice(1));
      if (field) fieldError(field,link.textContent);
    });
    update();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
