import {Browser} from '../../.agents/skills/design-taste-frontend/scripts/lib/cdp.mjs';
import {writeFileSync} from 'node:fs';
const browser=await Browser.launch();
const base='http://127.0.0.1:8031';
const results=[];
try {
 const page=await browser.newPage({width:1440,height:960});
 await page.goto(base+'/logistics/deliveries?audit_role=logistics_admin',{settleMs:800});
 const show=await page.eval(()=>document.querySelector('.delivery-record-link')?.getAttribute('href'));
 if(!show) throw new Error('No delivery record found: '+await page.eval(()=>document.body.innerText.slice(-1200)));
 const id=new URL(show).pathname.split('/').at(-1);
 writeFileSync('.taste-skill/logistics-deliveries/record.json',JSON.stringify({id}));
 for(const width of [1440,390,320]){
  await page.setViewport({width,height:960,mobile:width<600});
  for(const [name,path] of [['list','/logistics/deliveries'],['create','/logistics/deliveries/create'],['details','/logistics/deliveries/'+id],['edit','/logistics/deliveries/'+id+'/edit'],['archived','/logistics/deliveries-archived'],['scanner','/logistics/deliveries/scan']]){
   page.consoleErrors=[];
   await page.goto(base+path+'?audit_role=logistics_admin',{settleMs:650});
   await page.eval(()=>document.fonts.ready);
   const metrics=await page.eval(w=>({title:document.querySelector('h1')?.textContent,scroll:document.documentElement.scrollWidth,width:innerWidth,overflow:[...document.querySelectorAll('.logistics-deliveries *')].filter(el=>{const r=el.getBoundingClientRect();return r.width>0&&r.left>=0&&r.right>w+1&&getComputedStyle(el).position!=='fixed'}).slice(0,12).map(el=>({tag:el.tagName,class:el.className,right:el.getBoundingClientRect().right})),small:[...document.querySelectorAll('.logistics-deliveries button,.logistics-deliveries input:not([type=hidden]):not([type=checkbox]),.logistics-deliveries select')].filter(el=>el.getClientRects().length&&el.getBoundingClientRect().height<43).slice(0,8).map(el=>({text:el.textContent.trim().slice(0,30),height:el.getBoundingClientRect().height}))}),width);
   results.push({name,width,...metrics,jsErrors:[...page.consoleErrors]});
   if(width!==320) await page.screenshot({path:'.taste-skill/logistics-deliveries/'+name+'-'+width+'.png',format:'png',fullPage:true});
  }
 }
} finally {await browser.close();writeFileSync('.taste-skill/logistics-deliveries/preview-results.json',JSON.stringify(results,null,2));}
console.log(JSON.stringify(results));
