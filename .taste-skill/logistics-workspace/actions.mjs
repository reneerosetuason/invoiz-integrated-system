import {Browser,sleep} from '../../.agents/skills/design-taste-frontend/scripts/lib/cdp.mjs';
import {writeFileSync} from 'node:fs';
const browser=await Browser.launch(),results=[],base='http://127.0.0.1:8031';let page;
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
const record=(name,details={})=>{results.push({name,passed:true,...details});console.log('PASS '+name)};
const go=async(path,role='logistics_admin',extra={})=>{const url=new URL(base+path);url.searchParams.set('audit_role',role);if(role==='logistics_staff')url.searchParams.set('audit_center','1');for(const [key,value] of Object.entries(extra))url.searchParams.set(key,value);page.consoleErrors=[];await page.goto(url.href,{settleMs:550});};
const key=async(key,modifiers=0)=>{await page.send('Input.dispatchKeyEvent',{type:'keyDown',key,code:key,modifiers,windowsVirtualKeyCode:key==='Escape'?27:key==='Tab'?9:0,nativeVirtualKeyCode:key==='Escape'?27:key==='Tab'?9:0});await page.send('Input.dispatchKeyEvent',{type:'keyUp',key,code:key,modifiers,windowsVirtualKeyCode:key==='Escape'?27:key==='Tab'?9:0,nativeVirtualKeyCode:key==='Escape'?27:key==='Tab'?9:0});await sleep(120)};
const snapshot=async()=>page.eval(()=>({scroll:document.documentElement.scrollWidth,root:!!document.querySelector('.logistics-workspace'),title:document.querySelector('h1')?.textContent,unlabelled:[...document.querySelectorAll('.logistics-workspace button')].filter(el=>el.getClientRects().length&&!el.textContent.trim()&&!el.getAttribute('aria-label')&&!el.title).map(el=>el.outerHTML.slice(0,200))}));
try{
 page=await browser.newPage({width:390,height:844,mobile:true});
 for(const role of (process.argv.includes('--interactions-only')?[]:['logistics_admin','logistics_staff']))for(const width of [320,390,768,1440]){
  await page.setViewport({width,height:900,mobile:width<600});
  for(const path of ['sorting-center','riders','pickup-requests','tracking','transactions','remittances','reports','settings','notifications','messages','profile']){
   await go('/logistics/'+path,role);const m=await snapshot();
   assert(m.root&&m.scroll<=width+1,'Role layout failed '+role+'/'+path+'/'+width+JSON.stringify(m));
   assert(m.unlabelled.length===0,'Unlabelled controls '+JSON.stringify(m.unlabelled));
   assert(page.consoleErrors.length===0,'JavaScript error '+page.consoleErrors.join('\n'));
   record('Role layout '+role+'/'+path+'/'+width);
  }
 }
 await page.setViewport({width:390,height:844,mobile:true});
 for(const path of ['staff','staff/create','centers','centers/create','service-areas','service-areas/create','rider-applications','center-applications']){
  await go('/logistics/'+path,'logistics_staff');
  assert(await page.eval(()=>!document.querySelector('.logistics-workspace')&&document.body.textContent.includes('403')),'Admin page exposed to staff '+path);record('Staff denied '+path);
 }
 for(const path of ['riders','settings','messages']){
  await go('/logistics/'+path);await page.eval(()=>document.querySelector('.ld-mobile-menu').click());await sleep(150);
  assert(await page.eval(()=>document.querySelector('.ld-mobile-menu').getAttribute('aria-expanded')==='true'),'Mobile menu failed '+path);
  await page.eval(()=>document.querySelector('[x-show=sidebarVisible]').click());record('Mobile navigation '+path);
 }
 await go('/logistics/riders');
 const sorts=await page.eval(()=>({labels:[...document.querySelectorAll('.lw-table-sort a')].map(el=>el.textContent),cells:[...document.querySelectorAll('.lw-table tbody tr:first-child td')].every(el=>el.dataset.label)}));
 assert(sorts.labels.length===6&&sorts.cells,'Mobile sorting or table labels missing');record('Mobile table records and all rider sorts',sorts);
 const filter=await page.eval(()=>{const form=document.querySelector('form[action$="/riders"]');form.elements.search.value='Rider';form.elements.online.value='offline';return {data:Object.fromEntries(new FormData(form)),padding:getComputedStyle(form.elements.search).paddingLeft,labels:[...form.querySelectorAll('input:not([type=hidden]),select')].every(el=>el.labels?.length||el.getAttribute('aria-label'))}});
 assert(filter.data.search==='Rider'&&filter.data.online==='offline'&&filter.padding==='40px'&&filter.labels,'Filter input state or labels failed');record('Rider filters and field spacing',filter);
 // Confirmation submissions are captured locally rather than sent to a real endpoint.
 const confirmations=[['/logistics/riders','deactivate'],['/logistics/riders/2','reset-credentials'],['/logistics/staff/29','/staff/29']];
 for(const [path,action] of confirmations){
  await go(path);await page.eval(()=>{window.__submitted=null;HTMLFormElement.prototype.submit=function(){window.__submitted={action:this.action,fields:Object.fromEntries(new FormData(this))}}});
  const found=await page.eval(action=>{const form=[...document.querySelectorAll('form[data-confirm-message]')].find(f=>f.action.includes(action));if(!form)return false;window.__testForm=form;const button=form.querySelector('button[type=submit]');button.focus();button.click();return true},action);
  assert(found,'Confirmation form missing '+action);await sleep(180);
  assert(await page.eval(()=>!!document.querySelector('dialog[open]')&&document.querySelector('dialog').contains(document.activeElement)),'Confirmation did not open');
  await key('Escape');assert(await page.eval(()=>!document.querySelector('dialog')&&!window.__submitted),'Escape did not cancel: '+JSON.stringify(await page.eval(()=>({open:!!document.querySelector('dialog[open]'),submitted:window.__submitted,dialog:!!document.querySelector('dialog')}))));
  await page.eval(()=>window.__testForm.querySelector('button[type=submit]').click());await sleep(120);
  if(action==='deactivate'){
   assert(await page.eval(()=>!document.querySelector('dialog form').checkValidity()),'Suspension reason is optional');
   await page.eval(()=>document.querySelector('dialog textarea').focus());await page.send('Input.insertText',{text:'Suspension reason for browser verification only'});
   assert(await page.eval(()=>document.querySelector('dialog form').checkValidity()),'Valid suspension reason rejected');
  }
  await page.screenshot({path:'.taste-skill/logistics-workspace/confirmation-'+action.replaceAll('/','_')+'.png',format:'png'});
  await page.eval(()=>document.querySelector('dialog button[value=confirm]').click());await sleep(180);
  const submitted=await page.eval(()=>({action:window.__submitted?.action,keys:Object.keys(window.__submitted?.fields||{}),reason:window.__submitted?.fields?.reason,method:window.__submitted?.fields?._method,closed:!document.querySelector('dialog')}));
  assert(submitted.action?.includes(action)&&submitted.keys.includes('_token')&&submitted.closed,'Confirmation form contract changed '+JSON.stringify(submitted));
  if(action==='deactivate')assert(submitted.reason?.length>=10,'Suspension reason lost');
  if(action==='/staff/29')assert(submitted.method==='DELETE','Staff method changed');
  record('Confirmed action captured without writing '+action,submitted);
 }
 await go('/logistics/settings');
 await page.eval(()=>{const input=document.querySelector('input[aria-label="Search settings"]');input.value='appearance';input.dispatchEvent(new Event('input',{bubbles:true}))});await sleep(180);
 assert(await page.eval(()=>[...document.querySelectorAll('[data-section]')].filter(el=>el.getClientRects().length).length===1&&document.querySelector('[data-section*=appearance]').getClientRects().length>0),'Settings search failed');record('Settings search');
 await page.eval(()=>{const input=document.querySelector('input[aria-label="Search settings"]');input.value='';input.dispatchEvent(new Event('input',{bubbles:true}))});await sleep(180);
 await page.eval(()=>{const button=[...document.querySelectorAll('button')].find(el=>el.getAttribute('@click')==='pwOpen = true');button.focus();button.click()});await sleep(200);
 assert(await page.eval(()=>document.querySelector('.lw-dialog').getAttribute('role')==='dialog'&&document.querySelector('.lw-dialog').contains(document.activeElement)),'Password dialog focus failed');
 await page.eval(()=>{const button=document.querySelector('.lw-dialog button[aria-label="Show password"]');if(button)button.click()});
 await key('Escape');await sleep(200);assert(await page.eval(()=>getComputedStyle(document.querySelector('.lw-dialog')).display==='none'),'Password dialog did not close');record('Password dialog and Escape');
 const settings=await page.eval(()=>[...document.querySelectorAll('.logistics-workspace form')].map(form=>({action:form.getAttribute('action'),keys:[...new FormData(form).keys()]})));
 assert(settings.filter(f=>f.keys.includes('_token')).length===6,'Settings form lost CSRF');record('All six settings form contracts', {forms:settings});
 await go('/logistics/messages');
 await page.eval(()=>document.querySelector('[\\@click="openCompose()"]')?.click());await sleep(550);
 assert(await page.eval(()=>{const el=document.querySelector('.lw-dialog[x-show=composeOpen]');return !!el&&getComputedStyle(el).display!=='none'&&el.contains(document.activeElement)}),'Compose dialog failed');
 assert(await page.eval(()=>Alpine.$data(document.querySelector('.lw-messages')).recipients.length>0),'Recipient fetch failed');
 await key('Escape');await sleep(160);record('New message dialog and real recipient lookup');
 const conversation=await page.eval(()=>Alpine.$data(document.querySelector('.lw-messages')).conversations[0]?.id);
 if(conversation){
  await page.eval(conversation=>Alpine.$data(document.querySelector('.lw-messages')).selectConversation(conversation),conversation);await sleep(450);
  assert(await page.eval(()=>!Alpine.$data(document.querySelector('.lw-messages')).mobileList),'Mobile conversation did not open '+JSON.stringify(await page.eval(()=>{const d=Alpine.$data(document.querySelector('.lw-messages'));return {error:d.error,active:d.active?.id,list:d.mobileList}})));
  await page.eval(()=>Alpine.$data(document.querySelector('.lw-messages')).mobileList=true);record('Mobile conversation and back navigation');
 }
 // A message-delete confirmation may be cancelled; no message is removed.
 await page.eval(()=>{const data=Alpine.$data(document.querySelector('.lw-messages'));const message=data.messages.find(m=>m.mine&&!m.deleted);window.__messageToCheck=message;if(message)data.deleteMessage(message)});await sleep(150);
 if(await page.eval(()=>!!window.__messageToCheck)){assert(await page.eval(()=>document.querySelector('dialog h2').textContent==='Delete message?'),'Message delete dialog missing');await key('Escape');record('Message deletion cancel');}
 for(const tab of ['delivery','center','area','rider','financial','all']){
  await go('/logistics/reports', 'logistics_admin', {tab});
  assert(await page.eval(()=>!!document.querySelector('.lw-stat-grid, .lw-table')),'Report body missing '+tab);
  assert(await page.eval(()=>document.documentElement.scrollWidth<=391),'Report overflow '+tab);
  const exportLinks=await page.eval(()=>[...document.querySelectorAll('a[href*="reports/export"]')].every(a=>new URL(a.href).searchParams.get('tab')===document.querySelector('[name=tab]').value));
  assert(exportLinks,'Export tab state lost '+tab);record('Report '+tab);
 }
 await go('/logistics/reports');assert(await page.eval(()=>document.querySelector('.lw-stat-grid')?.textContent.includes('Total')),'Default report body missing');record('Default delivery report regression');
 for(const status of ['accepted','going_to_pickup','arrived_at_shop','arrived_at_customer']){
  await go('/logistics/tracking/search?tracking_number=TRK-20261020-SQ76','logistics_admin',{audit_tracking_state:status});
  assert(await page.eval(()=>document.querySelector('.lw-tracking-steps')&&!document.querySelector('.logistics-workspace').textContent.includes('Final Status:')),'Active rider stage shown as final '+status);record('Active tracking stage '+status);
 }
 for(const path of ['riders','settings','messages','reports','profile']){
  await go('/logistics/'+path);await page.eval(()=>document.documentElement.classList.add('dark'));
  assert(await page.eval(()=>getComputedStyle(document.body).backgroundColor==='rgb(18, 33, 38)'&&document.documentElement.scrollWidth<=391),'Dark theme failed '+path);
  await page.screenshot({path:'.taste-skill/logistics-workspace/'+path+'-dark.png',format:'png'});record('Dark theme '+path);
 }
 assert(page.consoleErrors.length===0,'Final JavaScript errors '+page.consoleErrors.join('\n'));
}catch(error){results.push({name:'FAIL',error:error.stack});if(page)await page.screenshot({path:'.taste-skill/logistics-workspace/actions-failure.png',format:'png'});throw error}
finally{writeFileSync('.taste-skill/logistics-workspace/'+(process.argv.includes('--interactions-only')?'interactions-results':'actions-results')+'.json',JSON.stringify(results,null,2));await browser.close()}
console.log(results.length+' action and role groups passed');
