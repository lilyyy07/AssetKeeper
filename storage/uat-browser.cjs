const fs=require('fs');
(async()=>{
 const targets=await(await fetch('http://127.0.0.1:9235/json')).json();const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 let id=0;const pending=new Map(),errors=[];
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){pending.get(m.id)?.(m);pending.delete(m.id)}if(m.method==='Runtime.exceptionThrown'||(m.method==='Runtime.consoleAPICalled'&&m.params.type==='error'))errors.push(m.params)});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,m=>m.error?reject(m.error):resolve(m.result));ws.send(JSON.stringify({id:n,method,params}));});
 const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
 await send('Page.enable');await send('Runtime.enable');
 const folder='storage/app/uat-visual/';const base='file:///C:/xampp/htdocs/AssetKeeper/'+folder;
 const pages=['dashboard','assets','asset-details','allocations','inventory','maintenance','maintenance-details','maintenance-completed','reports','allocation-report','users','profile','login','login-errors','otp','technician','management'];
 const modals={assets:['asset-new','manage-categories','import-assets'],allocations:['allocation-new','allocation-1','return-1'],maintenance:['maintenance-new','maintenance-1'],users:['user-new','user-1','manage-departments']};
 const results=[];
 for(const [width,height] of [[390,844],[430,932],[768,1024],[1366,768],[1440,900],[1920,1080]]){
  await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
  const captures=[];
  for(const page of pages){
   await send('Page.navigate',{url:base+page+'.html'});
   for(let n=0;n<60;n++){if(await evaluate("document.readyState==='complete'"))break;await new Promise(r=>setTimeout(r,100));}
   await evaluate('document.fonts.ready.then(()=>true)');
   const state=await evaluate(`(()=>{const rect=e=>{let r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height}};let qr=document.querySelector('.qr-output svg');return {overflow:document.documentElement.scrollWidth>innerWidth+1,sidebarVisible:!!document.querySelector('.sidebar.d-lg-flex')&&getComputedStyle(document.querySelector('.sidebar.d-lg-flex')).display!=='none',qr:qr?rect(qr):null,mainWidth:document.querySelector('main')?.clientWidth,buttons:[...document.querySelectorAll('main .btn')].filter(e=>e.getClientRects().length&&!e.closest('.modal')).map(e=>({text:e.innerText.trim(),...rect(e)}))}})()`);
   const filename=page+'-'+width+'.jpg';const shot=await send('Page.captureScreenshot',{format:'jpeg',quality:65,captureBeyondViewport:true});fs.writeFileSync(folder+filename,Buffer.from(shot.data,'base64'));captures.push({label:page,file:filename});results.push({page,width,height,state});
   for(const modal of modals[page]||[]){
    if(!(await evaluate(`!!document.getElementById('${modal}')`)))continue;
    await evaluate(`bootstrap.Modal.getOrCreateInstance(document.getElementById('${modal}')).show()`);await new Promise(r=>setTimeout(r,700));
    const modalState=await evaluate(`(()=>{let m=document.getElementById('${modal}'),c=m.querySelector('.modal-content'),b=m.querySelector('.modal-body'),f=m.querySelector('.modal-footer'),r=c.getBoundingClientRect();b.scrollTop=b.scrollHeight;return {visible:m.classList.contains('show'),fits:r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight+1,bodyScrollable:b.scrollHeight>b.clientHeight,footerAccessible:!f||f.getBoundingClientRect().bottom<=innerHeight,controlsOverflow:[...m.querySelectorAll('input,select,textarea,button')].filter(e=>e.getClientRects().length&&e.getBoundingClientRect().right>innerWidth+1).length}})()`);
    await evaluate(`document.getElementById('${modal}').querySelector('.modal-body').scrollTop=0`);
    const mf=modal+'-'+width+'.jpg';const ms=await send('Page.captureScreenshot',{format:'jpeg',quality:65});fs.writeFileSync(folder+mf,Buffer.from(ms.data,'base64'));captures.push({label:modal,file:mf});results.push({page,modal,width,height,state:modalState});
    await evaluate(`bootstrap.Modal.getInstance(document.getElementById('${modal}')).hide()`);await new Promise(r=>setTimeout(r,700));
   }
   if(page==='dashboard'&&width<992){
    await evaluate("document.querySelector('[data-bs-target=\"#mobileSidebar\"]').click()");await new Promise(r=>setTimeout(r,700));
    const nav=await evaluate("({visible:document.getElementById('mobileSidebar').classList.contains('show'),links:[...document.querySelectorAll('#mobileSidebar .sidebar-link')].map(e=>e.innerText.trim()),overflow:document.documentElement.scrollWidth>innerWidth})");results.push({page:'navigation',width,state:nav});
    const nf='navigation-'+width+'.jpg';const ns=await send('Page.captureScreenshot',{format:'jpeg',quality:65});fs.writeFileSync(folder+nf,Buffer.from(ns.data,'base64'));captures.push({label:'navigation',file:nf});
   }
   if(page==='profile'){
    await evaluate("document.querySelector('.user-menu').click()");await new Promise(r=>setTimeout(r,100));
    results.push({page:'profile-menu',width,state:await evaluate("(()=>{const e=document.querySelector('.dropdown-menu.show');const r=e?.getBoundingClientRect();return {visible:!!e,fits:r&&r.left>=0&&r.right<=innerWidth}})()")});
   }
   if(page==='login'){
    results.push({page:'password-toggle',width,state:await evaluate("(()=>{let b=document.querySelector('.password-toggle');b.click();return {visible:!b.hidden,works:document.getElementById('password').type==='text'}})()")});
   }
  }
  fs.writeFileSync(folder+'contact-'+width+'.html',`<html><head><style>body{margin:0;background:#ddd;font:16px sans-serif}.grid{display:grid;grid-template-columns:repeat(4,390px);gap:8px}figure{margin:0;background:#fff}figcaption{padding:8px}img{width:390px;height:550px;object-fit:contain;object-position:top}</style></head><body><h1>${width} × ${height}</h1><div class=grid>${captures.map(c=>`<figure><figcaption>${c.label}</figcaption><img src="${c.file}"></figure>`).join('')}</div></body></html>`);
  console.log('Completed viewport '+width+'x'+height+'; '+captures.length+' rendered captures');
 }
 fs.writeFileSync(folder+'checks.json',JSON.stringify({results,errors},null,2));
 const issues=results.filter(r=>r.state?.overflow||r.state?.fits===false||r.state?.footerAccessible===false||r.state?.controlsOverflow>0||r.state?.works===false);
 console.log(JSON.stringify({checks:results.length,errors:errors.length,issues},null,2));
 for(const width of [390,430,768,1366,1440,1920]){
  await send('Emulation.setDeviceMetricsOverride',{width:1590,height:1000,deviceScaleFactor:1,mobile:false});await send('Page.navigate',{url:base+'contact-'+width+'.html'});await new Promise(r=>setTimeout(r,300));
  const metrics=await send('Page.getLayoutMetrics');const s=await send('Page.captureScreenshot',{format:'jpeg',quality:60,captureBeyondViewport:true,clip:{x:0,y:0,width:1590,height:metrics.cssContentSize.height,scale:1}});fs.writeFileSync(folder+'contact-'+width+'.jpg',Buffer.from(s.data,'base64'));
 }
 await send('Browser.close');
})().catch(e=>{console.error(e);process.exit(1)});