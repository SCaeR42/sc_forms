// Optional DOM integration tests: npm install --no-save jsdom
const { JSDOM } = require('jsdom');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root=path.resolve(__dirname,'..');
if(fs.existsSync(path.join(root,'config/config.local.php'))) throw new Error('Remove config.local.php before isolated integration tests.');
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(predicate) { for(let i=0;i<150;i++) {if(predicate()) return; await delay(10);} throw new Error('DOM condition timed out'); }
let server,dom;
(async()=>{
  server=spawn(process.env.PHP_BINARY || 'php',['-d',`sys_temp_dir=${path.join(root,'var')}`,'-d','display_errors=0','-S','127.0.0.1:8100','-t','public'],{cwd:root,stdio:'ignore',env:{...process.env,SC_FORMS_PUBLIC_URL:'http://127.0.0.1:8100',SC_FORMS_SECRET:'dom-test-secret-with-at-least-32-chars'}});
  for(let i=0;i<100;i++) { try {await fetch('http://127.0.0.1:8100/form.php?form=feedback'); break;} catch {await delay(20);} }
  dom=new JSDOM('<button id="trigger" data-sc-form="consultation">Open</button><div data-sc-form-inline="feedback"></div><script src="http://127.0.0.1:8100/assets/sc-forms.js"></script>',{url:'http://127.0.0.1:8100/demo.html',runScripts:'outside-only',pretendToBeVisual:true});
  const w=dom.window,d=w.document;
  // jsdom does not implement native dialogs: emulate only lifecycle, not browser focus trapping/layout.
  w.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
  w.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');setTimeout(()=>this.dispatchEvent(new w.Event('close')),0);};
  let submits=0,fail=true;
  w.fetch=async(url,options={})=>{
    if(options.method==='POST') {
      submits++; await delay(40);
      return fail ? {ok:false,status:422,json:async()=>({CODE:422,mess:['Проверьте поля'],errors:{email:'Проверьте email'}})} : {ok:true,status:200,json:async()=>({CODE:200,mess:['<h3>Спасибо!</h3><p>Успешно</p>']})};
    }
    await delay(30);
    if(options.signal?.aborted) throw new w.DOMException('Aborted','AbortError');
    return fetch(url,options);
  };
  w.eval(fs.readFileSync(path.join(root,'public/assets/sc-forms.js'),'utf8'));
  await until(()=>d.querySelector('[data-sc-form-inline] form'));
  d.getElementById('trigger').focus();d.getElementById('trigger').click();
  await until(()=>d.querySelector('dialog [name="scform[certificate]"]'));
  const dialog=d.querySelector('dialog');
  let form=dialog.querySelector('form');
  for(const [name,value] of Object.entries({certificate:'CERT123',email:'visitor@example.org',comment:'Question'})) form.elements.namedItem(`scform[${name}]`).value=value;
  form.querySelectorAll('[type=checkbox]').forEach(el=>el.checked=true);
  form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await until(()=>form.querySelector('[aria-invalid=true]'));
  assert.equal(submits,1,'double submission prevented');
  assert.equal(form.elements.namedItem('scform[comment]').value,'Question','input retained after error');
  fail=false; form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await until(()=>dialog.querySelector('.scf-success'));
  w.SCForms.close(); await delay(10);
  assert.equal(d.activeElement.id,'trigger','focus returned');
  await w.SCForms.open('consultation');
  w.SCForms.close();
  // Immediate re-open before the old native close event is delivered.
  await w.SCForms.open('partner');
  await delay(60);
  assert.ok(dialog.querySelector('form[data-sc-form-name="partner"]'),'reopen survives prior asynchronous close event');
  const opening=w.SCForms.open('requestdemo');w.SCForms.close();await opening;
  assert.equal(dialog.open,false,'close during pending load');
  assert.ok(d.querySelector('[data-sc-form-inline] form'),'inline form survives modal updates');
  console.log('PASS: DOM inline, modal, errors retain data, double submit, success, focus, immediate reopen, close during load');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{dom?.window.close();server?.kill();});
