import {Browser} from '../../.agents/skills/design-taste-frontend/scripts/lib/cdp.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
const browser=await Browser.launch(),results=[],base='http://127.0.0.1:8031';
const paths=JSON.parse(readFileSync('.taste-skill/logistics-workspace/paths.json'));
try{
 const page=await browser.newPage();
 for(const width of [1440,390,320,768]){
  await page.setViewport({width,height:900,mobile:width<600});
  for(const path of paths){
   page.consoleErrors=[];const url=new URL(base+path);url.searchParams.set('audit_role','logistics_admin');
   await page.goto(url.href,{settleMs:450});await page.eval(()=>document.fonts.ready);
   const m=await page.eval(w=>({root:!!document.querySelector('.logistics-workspace'),title:document.querySelector('h1')?.textContent.trim(),body:document.querySelector('.logistics-workspace')?'':document.body.innerText.trim().slice(0,160),scroll:document.documentElement.scrollWidth,font:document.querySelector('h1')?getComputedStyle(document.querySelector('h1')).fontFamily:'',overflow:[...document.querySelectorAll('.logistics-workspace *')].filter(el=>{const r=el.getBoundingClientRect();return r.width>0&&r.left>=0&&r.right>w+1&&!el.closest('table')&&getComputedStyle(el).position!=='fixed'}).slice(0,12).map(el=>({tag:el.tagName,class:el.className,right:Math.round(el.getBoundingClientRect().right)})),small:[...document.querySelectorAll('.logistics-workspace button,.logistics-workspace select,.logistics-workspace input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file])')].filter(el=>el.getClientRects().length&&(el.getBoundingClientRect().height<43)).slice(0,8).map(el=>({text:el.textContent.trim().slice(0,40),height:el.getBoundingClientRect().height})),unlabelled:[...document.querySelectorAll('.logistics-workspace button')].filter(el=>el.getClientRects().length&&!el.textContent.trim()&&!el.getAttribute('aria-label')&&!el.title).slice(0,8).map(el=>({click:el.getAttribute('@click'),class:el.className})),forms:[...document.querySelectorAll('.logistics-workspace form')].map(f=>({action:f.getAttribute('action'),method:f.method,csrf:!!f.querySelector('[name=_token]')}))}),width);
   const passed=m.root&&m.scroll<=width+1&&m.font.includes('Fraunces')&&!m.small.length&&!page.consoleErrors.length;
   results.push({path,width,passed,...m,errors:[...page.consoleErrors]});
   console.log(JSON.stringify({path,width,passed,title:m.title,scroll:m.scroll,overflow:m.overflow,small:m.small,unlabelled:m.unlabelled,errors:page.consoleErrors,body:m.body}));
   if([1440,390].includes(width)&&m.root){const file=path.replace('/logistics/','').replace(/[^a-z0-9-]/gi,'_');await page.screenshot({path:'.taste-skill/logistics-workspace/'+file+'-'+width+'.png',format:'png',fullPage:true});}
  }
 }
}finally{writeFileSync('.taste-skill/logistics-workspace/review-results.json',JSON.stringify(results,null,2));await browser.close()}
console.log('Review: '+results.filter(r=>r.passed).length+'/'+results.length+' passed');
