import {Browser} from '../../.agents/skills/design-taste-frontend/scripts/lib/cdp.mjs';
import {writeFileSync} from 'node:fs';
const browser=await Browser.launch(),results=[];
const base='http://127.0.0.1:8031';
const paths=['sorting-center','riders','pickup-requests','staff','centers','service-areas','rider-applications','center-applications','tracking','transactions','remittances','reports','settings','notifications','messages','profile'];
try{
 const page=await browser.newPage({width:390,height:900,mobile:true});
 for(const role of ['logistics_admin','logistics_staff','admin']){
  for(const path of paths){
   page.consoleErrors=[];
   await page.goto(base+'/logistics/'+path+'?audit_role='+role+(role==='logistics_staff'?'&audit_center=1':''),{settleMs:450});
   const m=await page.eval(w=>({root:!!document.querySelector('.logistics-workspace'),title:document.querySelector('h1')?.textContent.trim(),body:document.querySelector('.logistics-workspace')?'':document.body.innerText.trim().slice(0,200),scroll:document.documentElement.scrollWidth,links:[...document.querySelectorAll('.logistics-workspace a[href]')].map(a=>new URL(a.href).pathname).filter((v,i,a)=>a.indexOf(v)===i),overflow:[...document.querySelectorAll('.logistics-workspace *')].filter(el=>{const r=el.getBoundingClientRect();return r.width>0&&r.left>=0&&r.right>w+1&&!el.closest('table')&&getComputedStyle(el).position!=='fixed'}).slice(0,8).map(el=>({tag:el.tagName,class:el.className,right:Math.round(el.getBoundingClientRect().right)})),small:[...document.querySelectorAll('.logistics-workspace button')].filter(el=>el.getClientRects().length&&el.getBoundingClientRect().height<43).slice(0,8).map(el=>({text:el.textContent.trim().slice(0,40),height:el.getBoundingClientRect().height}))}),390);
   results.push({role,path,...m,jsErrors:[...page.consoleErrors]});
   console.log(JSON.stringify({role,path,title:m.title,root:m.root,scroll:m.scroll,errors:page.consoleErrors,overflow:m.overflow,small:m.small,body:m.body}));
   if(m.root&&role==='logistics_admin')await page.screenshot({path:'.taste-skill/logistics-workspace/'+path+'-mobile-round1.png',format:'png',fullPage:true});
  }
 }
}finally{writeFileSync('.taste-skill/logistics-workspace/discovery.json',JSON.stringify(results,null,2));await browser.close()}
