import {Browser,sleep} from '../../.agents/skills/design-taste-frontend/scripts/lib/cdp.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
const browser=await Browser.launch(),results=[];
const base='http://127.0.0.1:8031',{id}=JSON.parse(readFileSync('.taste-skill/logistics-deliveries/record.json'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
const record=(name,details={})=>{results.push({name,passed:true,...details});console.log('PASS '+name)};
let page;
const go=async(path,query='audit_role=logistics_admin')=>{page.consoleErrors=[];await page.goto(base+path+'?'+query,{settleMs:450});};
const key=async(key,modifiers=0)=>{await page.send('Input.dispatchKeyEvent',{type:'keyDown',key,code:key,modifiers});await page.send('Input.dispatchKeyEvent',{type:'keyUp',key,code:key,modifiers});await sleep(120)};
const dialog=async(selector,state)=>{
 await page.eval(selector=>{const el=document.querySelector(selector);if(!el)throw new Error('Missing dialog trigger '+selector);el.focus();el.click()},selector);await sleep(220);
 const result=await page.eval(state=>{const el=document.querySelector('.delivery-modal[x-show="'+state+'"]');return {visible:!!el&&getComputedStyle(el).display!=='none',focus:!!el&&el.contains(document.activeElement),role:el?.getAttribute('role'),label:el?.getAttribute('aria-labelledby'),width:el?.getBoundingClientRect().width}},state);
 assert(result.visible&&result.focus&&result.role==='dialog'&&result.label,'Dialog access failed '+state+JSON.stringify(result));
 await page.eval(state=>{const el=document.querySelector('.delivery-modal[x-show="'+state+'"]');const buttons=[...el.querySelectorAll('button')].filter(el=>el.getClientRects().length);buttons.at(-1).focus()},state);
 await key('Tab');
 assert(await page.eval(state=>document.querySelector('.delivery-modal[x-show="'+state+'"]').contains(document.activeElement),state),'Dialog focus escaped');
 await key('Escape');await sleep(240);
 assert(await page.eval(state=>getComputedStyle(document.querySelector('.delivery-modal[x-show="'+state+'"]')).display==='none',state),'Dialog did not close');
 assert(await page.eval(selector=>document.activeElement===document.querySelector(selector),selector),'Dialog focus not restored');
 record('Dialog '+state);
};
try{
 page=await browser.newPage();
 for(const role of (process.argv.includes('--actions-only')?[]:['logistics_admin','logistics_staff'])){
  const paths=[['list','/logistics/deliveries'],['create','/logistics/deliveries/create'],['archived','/logistics/deliveries-archived'],['scanner','/logistics/deliveries/scan']];
  if(role==='logistics_admin')paths.push(['details','/logistics/deliveries/'+id],['edit','/logistics/deliveries/'+id+'/edit']);
  for(const width of [320,375,390,768,1024,1440]){
   await page.setViewport({width,height:900,mobile:width<600});
   for(const [name,path] of paths){
    await go(path,'audit_role='+role+(role==='logistics_staff'?'&audit_center=1':''));await page.eval(()=>document.fonts.ready);
    const m=await page.eval(()=>({title:document.querySelector('h1')?.textContent,root:!!document.querySelector('.logistics-deliveries'),scroll:document.documentElement.scrollWidth,font:getComputedStyle(document.querySelector('h1')).fontFamily,small:[...document.querySelectorAll('.logistics-deliveries button,.logistics-deliveries select,.logistics-deliveries input:not([type=hidden]):not([type=checkbox])')].filter(el=>el.getClientRects().length&&el.getBoundingClientRect().height<43).map(el=>el.outerHTML.slice(0,120))}));
    assert(m.root&&m.scroll<=width+1,'Layout failed '+name+'/'+role+'/'+width+JSON.stringify(m));
    assert(m.font.includes('Fraunces'),'Heading type failed '+name);
    assert(m.small.length===0,'Small controls '+JSON.stringify(m.small));
    assert(page.consoleErrors.length===0,'JavaScript errors '+page.consoleErrors.join('\n'));
    record('Responsive '+name+'/'+role+'/'+width,m);
    if(role==='logistics_admin'&&[390,1440].includes(width))await page.screenshot({path:'.taste-skill/logistics-deliveries/'+name+'-'+width+'-final.png',format:'png',fullPage:true});
   }
  }
 }
 if(!process.argv.includes('--layouts-only')) {
 await page.setViewport({width:390,height:844,mobile:true});await go('/logistics/deliveries');
 await page.eval(()=>document.querySelector('.ld-mobile-menu').click());await sleep(180);
 assert(await page.eval(()=>document.querySelector('.ld-mobile-menu').getAttribute('aria-expanded')==='true'),'Drawer did not open');
 await page.eval(()=>document.querySelector('[x-show=sidebarVisible]').click());await sleep(180);
 assert(await page.eval(()=>document.querySelector('.ld-mobile-menu').getAttribute('aria-expanded')==='false'),'Drawer did not close');record('Mobile navigation');
 await dialog('button[data-archive-url]','archiveOpen');
 await go('/logistics/deliveries','audit_role=logistics_admin&status=waiting_for_rider&search=TRK&per_page=50&sort=tracking_number&dir=asc');
 const filters=await page.eval(()=>{const f=document.querySelector('.delivery-filters'),data=Object.fromEntries(new FormData(f));return {data,sorts:[...f.elements.sort.options].map(o=>o.value),links:[...document.querySelectorAll('.delivery-tabs a')].every(a=>new URL(a.href).searchParams.get('search')==='TRK')}});
 assert(filters.data.status==='waiting_for_rider'&&filters.data.per_page==='50'&&filters.data.dir==='asc'&&filters.sorts.length===11&&filters.links,'Filter state lost');record('All filters, eleven sorts and preserved query state',filters);
 await page.eval(()=>document.querySelector('#bulk-select-all').click());
 const bulk=await page.eval(()=>{const f=document.querySelector('#bulk-assign-form');return {selected:document.querySelectorAll('.bulk-delivery-checkbox:checked').length,ids:new FormData(f).getAll('delivery_ids[]').length,label:document.querySelector('#bulk-selected-count').textContent,enabled:!f.querySelector('button[type=submit]').disabled}});
 assert(bulk.selected>0&&bulk.selected<=25&&bulk.ids===bulk.selected&&bulk.enabled,'Bulk selection failed');
 await page.eval(()=>document.querySelector('#bulk-select-all').click());
 assert(await page.eval(()=>document.querySelector('#bulk-assign-form button[type=submit]').disabled),'Empty bulk submit enabled');record('Bulk selection cap and form association',bulk);
 const csv=await page.eval(async()=>{const r=await fetch('/logistics/deliveries/export?status=waiting_for_rider');const text=await r.text();return {ok:r.ok,type:r.headers.get('content-type'),download:r.headers.get('content-disposition'),header:text.split('\n')[0],rowCount:text.trim().split('\n').length-1}});
 assert(csv.ok&&csv.type.includes('text/csv')&&csv.header.includes('Tracking Number')&&csv.rowCount>0,'CSV export failed');record('Filtered CSV download',csv);
 await go('/logistics/deliveries','audit_role=logistics_admin&search=NO_MATCH_DELIVERY_DESIGN_7829');
 assert(await page.eval(()=>document.querySelector('.delivery-empty')?.textContent.includes('No deliveries found')),'Empty state missing');record('Search empty state');
 await go('/logistics/deliveries-archived');
 await dialog('button[data-restore-url]','restoreOpen');
 if(await page.eval(()=>!!document.querySelector('button[data-delete-url]')))await dialog('button[data-delete-url]','deleteOpen');
 const fixtures=[['waiting_for_rider','pending_arrival'],['assigned','received'],['picked_up','scanned'],['out_for_delivery','sorted'],['delivered','dispatched'],['delivery_failed','received'],['cancelled','received'],['lost','received'],['damaged','received'],['returned_to_seller','received'],['accepted','dispatched'],['going_to_pickup','dispatched'],['arrived_at_shop','dispatched'],['arrived_at_customer','dispatched']];
 for(const [status,parcel] of fixtures){
  await go('/logistics/deliveries/'+id,'audit_role=logistics_admin&audit_state='+status+'&audit_parcel='+parcel+'&audit_eligible=1&audit_return=1');
  const m=await page.eval(()=>({root:!!document.querySelector('.delivery-actions'),scroll:document.documentElement.scrollWidth,forms:[...document.querySelectorAll('.logistics-deliveries form')].map(f=>({action:f.getAttribute('action'),fields:[...f.elements].filter(el=>el.name).map(el=>el.name),method:f.querySelector('[name=_method]')?.value,csrf:!!f.querySelector('[name=_token]')})),buttons:[...document.querySelectorAll('.delivery-actions button')].filter(el=>el.getClientRects().length).map(el=>el.textContent.trim())}));
  assert(m.root&&m.scroll<=391,'Fixture failed '+status+JSON.stringify(m));
  assert(m.forms.every(f=>f.csrf),'CSRF missing '+status);
  assert(page.consoleErrors.length===0,'Fixture JS error '+status+page.consoleErrors.join('\n'));
  record('Action state '+status+'/'+parcel,m);
  if(status==='out_for_delivery'){
   assert(m.forms.some(f=>f.fields.includes('amount_received')),'COD input missing');
   await dialog('[\\@click="failOpen = true"]','failOpen');
   await page.eval(()=>{document.querySelector('[\\@click="failOpen = true"]').click()});await sleep(180);
   const other=await page.eval(()=>{const select=document.querySelector('.delivery-modal[x-show=failOpen] select');select.value='Other';select.dispatchEvent(new Event('change',{bubbles:true}));return true});await sleep(100);
   assert(await page.eval(()=>document.querySelector('.delivery-modal[x-show=failOpen] textarea').required),'Other failure reason not required');await key('Escape');await sleep(220);
  }
  if(status==='assigned'){const exception=await page.eval(()=>{const f=document.querySelector('form[enctype="multipart/form-data"]');const before=f.elements.photo.required;f.elements.reason.value=f.elements.reason.options[1].value;f.elements.type.value='lost';f.elements.type.dispatchEvent(new Event('change',{bubbles:true}));return {before,cleared:f.elements.reason.value===''}});await sleep(120);assert(exception.before&&exception.cleared&&await page.eval(()=>!document.querySelector('form[enctype="multipart/form-data"]').elements.photo.required),'Exception type controls failed');record('Exception reason and damage photo controls');}
  if(status==='assigned')await dialog('[\\@click="cancelOpen = true"]','cancelOpen');
  if(status==='delivered')await dialog('[\\@click="overrideOpen = true"]','overrideOpen');
 }
 await go('/logistics/deliveries/'+id,'audit_role=logistics_admin&audit_state=assigned&audit_archived=1');
 assert(await page.eval(()=>[...document.querySelectorAll('.delivery-actions button')].filter(el=>el.getClientRects().length).every(el=>el.textContent.trim()==='Restore Delivery')),'Archived actions were not restricted');record('Archived detail restore action');
 await go('/logistics/deliveries/'+id);
 const codes=await page.eval(async()=>{await new Promise((ok,no)=>{const s=document.createElement('script');s.src='/vendor/jsqr/jsQR.js';s.onload=ok;s.onerror=no;document.head.append(s)});const canvas=document.querySelector('#parcel-qr canvas'),pixels=canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height);return {decoded:jsQR(pixels.data,pixels.width,pixels.height)?.data,tracking:document.querySelector('.delivery-tracking').textContent.trim(),barcode:document.querySelector('#parcel-barcode').children.length,qrImage:canvas.toDataURL()}});
 assert(codes.decoded===codes.tracking&&codes.barcode>0,'Waybill QR/barcode failed');
 await page.send('Emulation.setEmulatedMedia',{media:'print'});
 const print=await page.eval(()=>{const el=document.querySelector('#shipping-label'),style=getComputedStyle(el);return {position:style.position,width:el.getBoundingClientRect().width,visibility:style.visibility}});
 assert(print.position==='fixed'&&print.width===384&&print.visibility==='visible','Four-inch label changed '+JSON.stringify(print));await page.send('Emulation.setEmulatedMedia',{media:''});record('Printed waybill, decoded QR and barcode',{codes,print});
 await go('/logistics/deliveries/create');
 assert(await page.eval(()=>{const f=document.querySelector('.delivery-entry-form form');return !f.checkValidity()&&f.querySelectorAll('[required]').length===6&&f.querySelector('[name=sender_phone]').type==='tel'}),'Create form validation failed');record('Create form validation and phone fields');
 await go('/logistics/deliveries/scan');
 const refs=await page.eval(()=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));return {video:!!data.$refs.video,canvas:!!data.$refs.canvas,result:!!data.$refs.resultPanel}});
 assert(refs.video&&refs.canvas&&refs.result,'Camera references missing');record('Scanner Alpine references',refs);
 await page.eval(()=>{window.__realFetch=window.fetch;window.__scanPayloads=[];window.fetch=(url,options)=>{if(String(url).includes('scan-verify')){window.__scanPayloads.push(JSON.parse(options.body));const delivery={tracking_number:JSON.parse(options.body).tracking_number,recipient_name:'Browser verification fixture',parcel_status:'scanned',scanned_at:new Date().toISOString(),show_url:'/logistics/deliveries/58'};return Promise.resolve(new Response(JSON.stringify({ok:true,already:window.__scanAlready||false,delivery}),{status:200,headers:{'Content-Type':'application/json'}}))}return window.__realFetch(url,options)}});
 await page.eval(()=>{const input=document.querySelector('#manual_code');input.value='INVALID';input.dispatchEvent(new Event('input',{bubbles:true}));input.closest('form').requestSubmit()});await sleep(160);
 assert(await page.eval(()=>window.__scanPayloads.length===0&&document.querySelector('[role=alert]').textContent.includes('not a valid')),'Invalid scanner input accepted');record('Invalid scanner tracking validation');
 for(const already of [false,true]){
  await page.eval(already=>{window.__scanAlready=already;const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));data.scanAnother();const input=document.querySelector('#manual_code');input.value=' trk-20261026-ab12 ';input.dispatchEvent(new Event('input',{bubbles:true}));input.closest('form').requestSubmit()},already);await sleep(220);
  assert(await page.eval(already=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));return data.state==='done'&&data.alreadyScanned===already&&window.__scanPayloads.at(-1).tracking_number==='TRK-20261026-AB12'&&document.activeElement===data.$refs.resultPanel},already),'Scanner result failed '+JSON.stringify(await page.eval(()=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));return {state:data.state,already:data.alreadyScanned,payload:window.__scanPayloads.at(-1),focus:document.activeElement.outerHTML.slice(0,300),hasRef:!!data.$refs.resultPanel,alert:data.alertText}}))); 
  record('Scanner '+(already?'already scanned':'manual success'));
 }
 await page.eval(()=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));data.expect='TRK-20261026-ZZ99';data.scanAnother();data.submitManual()});await sleep(180);
 assert(await page.eval(()=>Alpine.$data(document.querySelector('.delivery-scan-grid')).mismatch),'Expected parcel mismatch not shown');record('Scanner expected parcel mismatch');
 await page.eval(async qrImage=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));data.expect='';data.scanAnother();const image=new Image();image.src=qrImage;await image.decode();const canvas=document.createElement('canvas');canvas.width=320;canvas.height=320;const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,320,320);ctx.drawImage(image,96,96);const stream=canvas.captureStream(15);Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:()=>Promise.resolve(stream)});data.start();},codes.qrImage);await sleep(800);
 assert(await page.eval(()=>Alpine.$data(document.querySelector('.delivery-scan-grid')).state==='done'),'Camera video QR decode failed');record('Camera video QR decoding with synthetic stream');
 await page.eval(()=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));data.scanAnother();Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:()=>Promise.reject(new DOMException('Denied','NotAllowedError'))});data.start()});await sleep(180);
 assert(await page.eval(()=>Alpine.$data(document.querySelector('.delivery-scan-grid')).state==='denied'),'Camera denied fallback failed');record('Camera permission fallback');
 await page.eval(()=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));window.__trackStopped=false;Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:()=>new Promise(resolve=>{window.__resolveCamera=resolve})});data.start();data.stop();window.__resolveCamera({getTracks:()=>[{stop(){window.__trackStopped=true}}]})});await sleep(160);
 assert(await page.eval(()=>window.__trackStopped&&Alpine.$data(document.querySelector('.delivery-scan-grid')).state==='stopped'),'Cancelled camera request leaked stream');record('Camera cancellation stops late stream');
 await page.eval(()=>{const data=Alpine.$data(document.querySelector('.delivery-scan-grid'));window.fetch=(url,options)=>String(url).includes('scan-verify')?Promise.resolve(new Response('<html>Expired session</html>',{status:419})):window.__realFetch(url,options);data.submitManual()});await sleep(180);
 assert(await page.eval(()=>Alpine.$data(document.querySelector('.delivery-scan-grid')).alertText.includes('session may have expired')),'Expired scan session failed');record('Scanner expired-session response');
 for(const path of ['/logistics/deliveries','/logistics/deliveries/create','/logistics/deliveries/'+id,'/logistics/deliveries/scan']){
  await go(path);await page.eval(()=>document.documentElement.classList.add('dark'));
  assert(await page.eval(()=>getComputedStyle(document.body).backgroundColor==='rgb(18, 33, 38)'),'Dark paper missing');assert(await page.eval(()=>document.documentElement.scrollWidth<=391),'Dark overflow');
  await page.screenshot({path:'.taste-skill/logistics-deliveries/dark-'+path.split('/').at(-1)+'.png',format:'png'});record('Dark theme '+path);
 }
 assert(page.consoleErrors.length===0,'Final JavaScript errors '+page.consoleErrors.join('\n'));
 }
}catch(error){results.push({name:'FAIL',error:error.stack});if(page)await page.screenshot({path:'.taste-skill/logistics-deliveries/check-failure.png',format:'png'});throw error}
finally{writeFileSync('.taste-skill/logistics-deliveries/'+(process.argv.includes('--actions-only')?'actions-results':process.argv.includes('--layouts-only')?'layout-results':'check-results')+'.json',JSON.stringify(results,null,2));await browser.close()}
console.log(results.length+' check groups passed');
