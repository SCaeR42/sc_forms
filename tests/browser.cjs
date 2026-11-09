const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = path.join(root, 'var', `browser-${Date.now()}`);
if(fs.existsSync(path.join(root,'config/config.local.php'))) throw new Error('Remove config.local.php before isolated integration tests; real SMTP must not be used.');
const php = process.env.PHP_BINARY || 'php';
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let server, smtp, browser;
(async () => {
  smtp = spawn(php, [path.join(__dirname, 'smtp-server.php'), `${base}.ready`, `${base}.json`, 'accept'], {cwd: root, stdio:'ignore'});
  for(let n=0;n<100 && !fs.existsSync(`${base}.ready`);n++) await delay(30);
  const port = fs.readFileSync(`${base}.ready`,'utf8').split(':').pop();
  server = spawn(php, ['-d',`sys_temp_dir=${path.join(root,'var')}`,'-d','display_errors=0','-S','127.0.0.1:8099','-t','public'], {cwd:root, stdio:'ignore', env:{...process.env, SC_FORMS_PUBLIC_URL:'http://127.0.0.1:8099',SC_FORMS_SECRET:'local-browser-test-secret-32-characters',SC_FORMS_STORAGE:base+'.rate',SC_SMTP_HOST:'127.0.0.1',SC_SMTP_PORT:port,SC_SMTP_ENCRYPTION:'',SC_SMTP_AUTH:'false',SC_MAIL_TO:'inbox@example.org',SC_MAIL_FROM:'sender@example.org'}});
  for(let n=0;n<100;n++) { try {await fetch('http://127.0.0.1:8099/demo.html'); break;} catch {await delay(30);} }
  browser = await chromium.launch({headless:true, ...(process.env.CHROME_PATH ? {executablePath:process.env.CHROME_PATH} : {})});
  const page = await browser.newPage();
  const errors=[]; page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8099/demo.html');
  await page.locator('[data-sc-form-inline] form').waitFor();
  await page.locator('button[data-sc-form="consultation"]').click();
  const dialog=page.getByRole('dialog');
  await dialog.locator('[name="scform[certificate]"]').waitFor();
  assert.equal(await dialog.isVisible(),true);
  await dialog.locator('[name="scform[certificate]"]').fill('CERT-123');
  await dialog.locator('[name="scform[email]"]').fill('visitor@example.org');
  await dialog.locator('[name="scform[comment]"]').fill('Browser test');
  await dialog.locator('[name="scform[tos_soglasie]"]').check();
  await dialog.locator('[name="scform[tos_privacy_policy]"]').check();
  await dialog.getByRole('button',{name:'Отправить'}).click();
  await dialog.getByText('Ваше сообщение успешно отправлено.').waitFor();
  await page.keyboard.press('Escape');
  assert.equal(await dialog.isVisible(),false);
  assert.equal(await page.locator('button[data-sc-form="consultation"]').evaluate(el=>document.activeElement===el),true);
  await page.locator('button[data-sc-form="partner"]').click();
  await dialog.locator('[name="scform[email]"]').waitFor();
  await dialog.getByRole('button',{name:'Закрыть'}).click();
  assert.equal(await dialog.isVisible(),false);
  // Closing while a delayed load is pending must never reopen or replace the dialog.
  await page.route('**/form.php?form=requestdemo',async route=>{await delay(250); await route.continue();});
  await page.locator('button[data-sc-form="requestdemo"]').click();
  await page.keyboard.press('Escape'); await delay(400);
  assert.equal(await dialog.isVisible(),false);
  assert.deepEqual(errors,[]);
  console.log('PASS: browser inline, modal, real SMTP submission, focus, repeated open, close during load');
})().catch(e=>{ console.error(e);process.exitCode=1; }).finally(async()=>{
  if(browser) await browser.close();
  if(server) server.kill(); if(smtp) smtp.kill();
  await delay(100);
  for(const ext of ['ready','json']) {try {fs.unlinkSync(`${base}.${ext}`);} catch{}}
  fs.rmSync(base+'.rate',{recursive:true,force:true});
});
