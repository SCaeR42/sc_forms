/* SC Forms: standalone forms. No dependencies. */
(() => {
  'use strict';
  if (window.SCForms) return;
  const script = document.currentScript;
  let endpoint = new URL(script?.dataset.endpoint || '../form.php', script?.src || location.href).href;
  let modal, content, opener, loadController, generation = 0, previousOverflow = '';
  let jqueryBound = false;

  function configure(options = {}) {
    if (options.endpoint) endpoint = new URL(options.endpoint, location.href).href;
    bindJQuery();
    document.querySelectorAll('[data-sc-form-inline]').forEach(mountInline);
  }
  async function load(name, signal) {
    const url = new URL(endpoint);
    url.searchParams.set('form', name);
    const response = await fetch(url, {signal, credentials:'omit', cache:'no-store'});
    if (!response.ok) throw new Error('Не удалось загрузить форму. Попробуйте позже.');
    return response.text();
  }
  function ensureModal() {
    if (modal) return;
    modal = document.createElement('dialog');
    modal.className = 'scf-modal';
    modal.setAttribute('aria-label', 'Форма обратной связи');
    modal.innerHTML = '<div class="scf-panel"><button type="button" class="scf-close" aria-label="Закрыть">×</button><div class="scf-content" aria-live="polite"></div></div>';
    content = modal.querySelector('.scf-content');
    document.body.append(modal);
    modal.querySelector('.scf-close').addEventListener('click', close);
    modal.addEventListener('cancel', event => { event.preventDefault(); close(); });
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
  }
  function restore() {
    generation++;
    loadController?.abort();
    document.body.style.overflow = previousOverflow;
    if (opener?.isConnected) opener.focus();
    content.replaceChildren();
  }
  function close() {
    if (modal?.open) {
      modal.close();
      // Cleanup now: a queued native close event may arrive after the next open().
      restore();
    }
  }
  async function open(name = 'consultation') {
    ensureModal();
    const request = ++generation;
    loadController?.abort();
    loadController = new AbortController();
    if (!modal.open) {
      opener = document.activeElement;
      previousOverflow = document.body.style.overflow;
      modal.showModal();
      document.body.style.overflow = 'hidden';
    }
    content.textContent = 'Загрузка формы…';
    try {
      const html = await load(name, loadController.signal);
      if (request !== generation || !modal.open) return;
      content.innerHTML = html;
      const title = content.querySelector('.scf-title');
      if (title) modal.setAttribute('aria-label', title.textContent);
      content.querySelector('input:not([type="hidden"]):not([name="website"]),textarea,button')?.focus();
    } catch (error) {
      if (error.name !== 'AbortError' && request === generation && modal.open) content.textContent = error.message;
    }
  }
  async function mountInline(container) {
    if (container.dataset.scMounted) return;
    container.dataset.scMounted = 'true';
    container.textContent = 'Загрузка формы…';
    try { container.innerHTML = await load(container.dataset.scFormInline); }
    catch (error) {
      container.textContent = error.message + ' ';
      const retry = document.createElement('button');
      retry.type = 'button'; retry.textContent = 'Повторить';
      retry.addEventListener('click', () => { delete container.dataset.scMounted; mountInline(container); });
      container.append(retry);
    }
  }
  function bindJQuery() {
    if (!jqueryBound && window.jQuery) {
      window.jQuery(document).on('openForm.scForms', (_event, name = 'consultation') => open(name));
      jqueryBound = true;
    }
  }
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-sc-form],.js-showForm-consultation');
    if (!trigger) return;
    event.preventDefault();
    open(trigger.dataset.scForm || 'consultation');
  });
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('.scf-form')) return;
    event.preventDefault();
    if (form.dataset.busy === 'true' || !form.reportValidity()) return;
    form.dataset.busy = 'true';
    const button = form.querySelector('.scf-submit');
    const status = form.querySelector('.scf-status');
    form.querySelectorAll('[data-sc-error]').forEach(node => { node.textContent = ''; });
    form.querySelectorAll('[aria-invalid]').forEach(node => node.removeAttribute('aria-invalid'));
    status.textContent = 'Отправка…';
    button.disabled = true;
    form.setAttribute('aria-busy','true');
    try {
      // URLSearchParams keeps PHP's scform[...] convention and avoids multipart overhead.
      const response = await fetch(endpoint, {method:'POST',body:new URLSearchParams(new FormData(form)),credentials:'omit',cache:'no-store'});
      const result = await response.json();
      if (!form.isConnected) return;
      if (response.ok && result.CODE === 200) {
        const success = document.createElement('div');
        success.className = 'scf-success'; success.setAttribute('role','status'); success.tabIndex = -1;
        success.innerHTML = result.mess.join('<br>'); // Only server-controlled success templates, never submitted data.
        form.replaceWith(success); success.focus();
      } else {
        status.textContent = (result.mess || ['Не удалось отправить сообщение']).join(' ');
        for (const [name,message] of Object.entries(result.errors || {})) {
          const field = form.elements.namedItem(`scform[${name}]`);
          const error = [...form.querySelectorAll('[data-sc-error]')].find(node => node.dataset.scError === name);
          if (error) error.textContent = message;
          field?.setAttribute('aria-invalid','true');
        }
        if (response.status === 403) {
          const reload = document.createElement('button'); reload.type='button'; reload.textContent='Обновить форму';
          reload.addEventListener('click', () => {
            const inline = form.closest('[data-sc-form-inline]');
            if (inline) { delete inline.dataset.scMounted; mountInline(inline); }
            else open(form.dataset.scFormName);
          });
          status.append(document.createTextNode(' '),reload);
        }
        (form.querySelector('[aria-invalid="true"]') || status).focus();
      }
    } catch {
      if (form.isConnected) {
        status.textContent = 'Не удалось получить ответ сервера. Если запрос уже отправлен, письмо могло быть доставлено. Проверьте соединение перед повторной отправкой.';
        status.focus();
      }
    } finally {
      delete form.dataset.busy; button.disabled = false; form.removeAttribute('aria-busy');
    }
  });
  window.SCForms = Object.freeze({open,close,init:configure});
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',()=>configure(),{once:true});
  else configure();
})();
