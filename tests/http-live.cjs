// Full HTTP -> PHP -> PHPMailer -> local SMTP integration, no browser dependencies.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..'),base=path.join(root,'var',`live-${Date.now()}`);
if(fs.existsSync(path.join(root,'config/config.local.php'))) throw new Error('Remove config.local.php before isolated integration tests; real SMTP must not be used.');
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let smtp,server;
(async()=>{
  const php=process.env.PHP_BINARY || 'php';
  smtp=spawn(php,[path.join(__dirname,'smtp-server.php'),`${base}.ready`,`${base}.json`,'accept'],{cwd:root,stdio:'ignore'});
  for(let n=0;n<150 && !fs.existsSync(`${base}.ready`);n++) await delay(20);
  const port=fs.readFileSync(`${base}.ready`,'utf8').split(':').pop();
  server=spawn(php,['-d',`sys_temp_dir=${path.join(root,'var')}`,'-d','display_errors=0','-S','127.0.0.1:8101','-t','public'],{cwd:root,stdio:'ignore',env:{...process.env,SC_FORMS_PUBLIC_URL:'http://127.0.0.1:8101',SC_FORMS_SECRET:'local-http-test-secret-at-least-32-chars',SC_FORMS_STORAGE:base+'.rate',SC_SMTP_HOST:'127.0.0.1',SC_SMTP_PORT:port,SC_SMTP_ENCRYPTION:'',SC_SMTP_AUTH:'false',SC_MAIL_FROM:'sender@example.org',SC_MAIL_TO:'inbox@example.org'}});
  let response;
  for(let n=0;n<150;n++) { try {response=await fetch('http://127.0.0.1:8101/form.php?form=consultation');break;} catch {await delay(20);} }
  assert.equal(response.status,200);
  const html=await response.text();
  assert.ok(html.startsWith('<form '),'no PHP warnings before HTML');
  const token=html.match(/name="token" value="([^"]+)"/)[1];
  const data=new URLSearchParams({form:'consultation',token,send:'Y',website:'','scform[certificate]':'CERT-123','scform[email]':'visitor@example.org','scform[comment]':'Full HTTP test','scform[tos_soglasie]':'да','scform[tos_privacy_policy]':'да'});
  response=await fetch('http://127.0.0.1:8101/form.php',{method:'POST',headers:{Origin:'http://127.0.0.1:8101'},body:data});
  assert.equal(response.status,200);
  const result=await response.json();assert.equal(result.CODE,200);assert.match(result.mess[0],/Спасибо/);
  for(let n=0;n<100 && !fs.existsSync(`${base}.json`);n++) await delay(20);
  const captured=JSON.parse(fs.readFileSync(`${base}.json`,'utf8'));
  assert.match(captured.envelope.join('\n'),/RCPT TO:<inbox@example.org>/);
  assert.match(captured.message,/Reply-To: visitor@example.org/);
  assert.match(captured.message,/Full HTTP test/);
  data.set('scform[email]','not an email');
  response=await fetch('http://127.0.0.1:8101/form.php',{method:'POST',body:data});
  assert.equal(response.status,422);assert.ok((await response.json()).errors.email);
  response=await fetch('http://127.0.0.1:8101/form.php?form=feedback',{headers:{Origin:'https://evil.example'}});
  assert.equal(response.status,403);assert.equal(response.headers.get('Access-Control-Allow-Origin'),null);
  response=await fetch('http://127.0.0.1:8101/form.php',{method:'POST',body:'x'.repeat(32769)});
  assert.equal(response.status,413);
  console.log('PASS: live HTTP render, SMTP delivery, validation, foreign origin and body limit');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{
  server?.kill();smtp?.kill();await delay(100);
  for(const ext of ['ready','json']) {try{fs.unlinkSync(`${base}.${ext}`);}catch{}}
  fs.rmSync(base+'.rate',{recursive:true,force:true});
});
