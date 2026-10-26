import {Browser, sleep} from '../../.agents/skills/design-taste-frontend/scripts/lib/cdp.mjs';
import {writeFileSync} from 'node:fs';

const browser = await Browser.launch();
const results = [];
const base = 'http://127.0.0.1:8031';
const check = (condition, message) => { if (!condition) throw new Error(message); };
try {
    const page = await browser.newPage();
    browser.on(page.sessionId,'Runtime.exceptionThrown',payload=>console.error(JSON.stringify({exception:payload.exceptionDetails,role:page.currentRole,width:page.currentWidth})));
    for (const role of (process.argv.includes('--quick') ? [] : ['logistics_admin', 'logistics_staff'])) {
        for (const width of [320, 375, 390, 768, 1024, 1440]) {
            page.currentRole=role;page.currentWidth=width;
            page.consoleErrors = [];
            await page.setViewport({width, height:900, mobile:width<600});
            await page.goto(base+'/logistics/dashboard?audit_role='+role, {settleMs:800});
            await page.eval('document.fonts.ready');
            const metrics = await page.eval(expectedWidth => ({
                title:document.querySelector('h1')?.textContent,
                width:innerWidth, scroll:document.documentElement.scrollWidth,
                fonts:['Inter','Fraunces'].map(font => document.fonts.check('16px '+font)),
                charts:[...document.querySelectorAll('.logistics-dashboard canvas')].map(canvas => ({id:canvas.id, rendered:!!Chart.getChart(canvas), font:Chart.defaults.font.size})),
                dateNames:[...document.querySelectorAll('.ld-date-form input')].map(input => input.name),
                recordCount:Alpine.$data(document.querySelector('.ld-records')).all.length,
                overflowElements:[...document.querySelectorAll('body *')].filter(el=>{const r=el.getBoundingClientRect();return r.width>0 && r.left>=0 && r.right>expectedWidth+1 && getComputedStyle(el).position!=='fixed'}).slice(0,12).map(el=>({tag:el.tagName,class:el.className,right:Math.round(el.getBoundingClientRect().right)})),
            }), width);
            check(metrics.title==='Delivery overview', role+' did not render');
            if(metrics.scroll>width+1) await page.screenshot({path:'.taste-skill/logistics-dashboard/overflow.png',format:'png'});
            check(metrics.scroll<=width+1, role+' overflow at '+width+': '+JSON.stringify(metrics));
            check(metrics.fonts.every(Boolean), 'Brand font did not load');
            check(metrics.charts.every(chart=>chart.rendered && chart.font>=13), 'Chart failed to render');
            check(page.consoleErrors.length===0, page.consoleErrors.join('\n'));
            results.push({role,...metrics,jsErrors:[...page.consoleErrors]});
            if (role==='logistics_admin' && [390,1440].includes(width)) {
                await page.screenshot({path:'.taste-skill/logistics-dashboard/'+(width===390?'mobile':'desktop')+'-final.png',format:'png'});
            }
        }
    }
    await page.setViewport({width:390,height:844,mobile:true});
    await page.goto(base+'/logistics/dashboard?audit_role=logistics_admin', {settleMs:600});
    page.consoleErrors=[];

    await page.eval("document.querySelector('.ld-mobile-menu').click()"); await sleep(300);
    check(await page.eval("document.querySelector('.ld-mobile-menu').getAttribute('aria-expanded')==='true' && document.querySelector('#app-sidebar').getBoundingClientRect().left===0"), 'Mobile drawer did not open');
    await page.eval("document.querySelector('[x-show=sidebarVisible]').click()"); await sleep(250);
    check(await page.eval("document.querySelector('.ld-mobile-menu').getAttribute('aria-expanded')==='false'"), 'Mobile drawer did not close');
    results.push({interaction:'Mobile navigation',passed:true});

    for (const name of ['Status','Trends','Center']) {
        const selector = '[data-dialog='+name.toLowerCase()+']';
        await page.eval(name=>{const button=[...document.querySelectorAll('button')].find(el=>el.getAttribute('@click')==='open'+name+'Modal()');button.focus();button.click()}, name);
        await sleep(400);
        const open = await page.eval(selector=>{
            const dialog=document.querySelector(selector),canvas=dialog.querySelector('canvas');
            return {visible:dialog.getClientRects().length>0,focus:dialog.contains(document.activeElement),active:document.activeElement.outerHTML.slice(0,250),tableRows:dialog.querySelectorAll('tbody tr').length,chart:!canvas||!!Chart.getChart(canvas)};
        },selector);
        check(open.visible && open.focus && open.tableRows>0 && open.chart, name+' dialog failed: '+JSON.stringify(open));
        if(name==='Status') await page.screenshot({path:'.taste-skill/logistics-dashboard/mobile-chart-expanded.png',format:'png'});
        if(name==='Status') {
            await page.eval(()=>{const nodes=[...document.querySelector('[data-dialog=status]').querySelectorAll('button:not([disabled]),a[href],[tabindex="0"]')].filter(el=>el.getClientRects().length);nodes.at(-1).focus()});
            await page.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab'});
            check(await page.eval("document.activeElement.hasAttribute('data-dialog-close')"),'Dialog keyboard focus escaped');
        }
        await page.send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});await sleep(100);
        check(await page.eval(selector=>document.querySelector(selector).getClientRects().length===0,selector), name+' dialog did not close');
        check(await page.eval(name=>document.activeElement.getAttribute('@click')==='open'+name+'Modal()',name),'Dialog did not restore opener focus');
        results.push({interaction:name+' expanded chart, table, focus and Escape',...open,passed:true});
    }
    // Repeat chart creation/destruction to catch reactive Chart.js lifecycle errors.
    await page.eval("[...document.querySelectorAll('button')].find(el=>el.getAttribute('@click')==='openStatusModal()').click()");await sleep(200);
    await page.eval("document.querySelector('[data-dialog=status] [data-dialog-close]').click()");await sleep(100);

    for(const [id,property] of [['statusChart','selectedStatus'],['trendsChart','selectedTrendDate'],['centerChart','selectedCenter']]) {
        await page.eval(id=>document.getElementById(id).scrollIntoView({block:'center'}),id);await sleep(150);
        const point=await page.eval(id=>{const canvas=document.getElementById(id),chart=Chart.getChart(canvas),r=canvas.getBoundingClientRect();const data=chart.getDatasetMeta(0).data;const element=data.find((el,i)=>chart.data.datasets[0].data[i]>0)||data[0];const p=element.getCenterPoint();return {x:r.left+p.x,y:r.top+p.y}},id);
        await page.send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...point});
        await page.send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...point});await sleep(150);
        check(await page.eval(property=>!!Alpine.$data(document.querySelector('[x-data="dashboardCharts()"]'))[property],property),id+' click did not select data');
    }
    check(await page.eval("Alpine.$data(document.querySelector('[x-data=\"dashboardCharts()\"]')).statusLink.includes('status=')"),'Status drill-down link is missing its filter');
    results.push({interaction:'All three chart selections and status drill-down link',passed:true});

    await page.eval("document.querySelector('[aria-controls=ld-record-browser]').click()");await sleep(100);
    const first = await page.eval("Alpine.$data(document.querySelector('.ld-records')).paginated[0]?.id");
    await page.eval("Alpine.$data(document.querySelector('.ld-records')).next()");await sleep(100);
    check(await page.eval(first=>{const s=Alpine.$data(document.querySelector('.ld-records'));return s.totalPages===1||(s.page===2&&s.paginated[0]?.id!==first)},first), 'Record pagination failed');
    await page.eval("(()=>{const input=document.querySelector('.ld-records input[type=search]');input.value='__NO_RECORD_MATCH__';input.dispatchEvent(new Event('input',{bubbles:true}))})()");await sleep(350);
    check(await page.eval("Alpine.$data(document.querySelector('.ld-records')).filtered.length===0"), 'Record search did not filter');
    await page.eval("Alpine.$data(document.querySelector('.ld-records')).clearFilters()");await sleep(100);
    check(await page.eval("(()=>{const s=Alpine.$data(document.querySelector('.ld-records'));return s.filtered.length===s.all.length&&s.page===1})()"), 'Record filter reset failed');
    check(await page.eval("Alpine.$data(document.querySelector('.ld-records')).all.every(row=>row.recipient!=='???')"),'Missing recipients have an unreadable placeholder');
    await page.screenshot({path:'.taste-skill/logistics-dashboard/mobile-records.png',format:'png',fullPage:true});
    await page.eval("document.querySelector('.ld-records').scrollIntoView({block:'start'});window.scrollBy(0,-80)");await sleep(100);
    await page.screenshot({path:'.taste-skill/logistics-dashboard/mobile-records-fold.png',format:'png'});
    results.push({interaction:'Record search, pagination and reset',passed:true});

    await page.eval("document.documentElement.classList.add('dark')");await sleep(400);
    const dark = await page.eval(()=>({
        background:getComputedStyle(document.querySelector('.ld-panel')).backgroundColor,
        label:Chart.defaults.color,
        expected:getComputedStyle(document.querySelector('.logistics-dashboard')).getPropertyValue('--ld-muted').trim(),
        chartRendered:!!Chart.getChart(document.getElementById('statusChart')),
    }));
    check(dark.label===dark.expected && dark.chartRendered,'Dark chart theme did not update');
    await page.eval('window.scrollTo(0,0)');
    await page.screenshot({path:'.taste-skill/logistics-dashboard/mobile-dark.png',format:'png'});
    check(page.consoleErrors.length===0,page.consoleErrors.join('\n'));
    results.push({interaction:'Dark theme',...dark,passed:true});

    await page.goto(base+'/logistics/dashboard?audit_role=logistics_admin&date_from=2099-01-01&date_to=2099-01-31&trends_range=30',{settleMs:600});
    const empty=await page.eval(()=>({
        records:Alpine.$data(document.querySelector('.ld-records')).all.length,
        counts:[...document.querySelectorAll('.ld-kpi strong')].map(el=>el.textContent),
        links:[...document.querySelectorAll('.ld-kpi')].every(el=>el.href.includes('date_from=2099-01-01')&&el.href.includes('date_to=2099-01-31')),
        period:document.querySelector('.ld-periods [aria-current=true]')?.textContent,
        width:innerWidth,scroll:document.documentElement.scrollWidth,
    }));
    check(empty.records===0 && empty.counts.every(count=>count==='0') && empty.links && empty.period==='30 days' && empty.scroll<=empty.width+1,'Date-filter empty state failed: '+JSON.stringify(empty));
    results.push({interaction:'Empty date range and 30-day trends',...empty,passed:true});
    check(page.consoleErrors.length===0,page.consoleErrors.join('\n'));

    await page.send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await page.goto(base+'/logistics/dashboard?audit_role=logistics_admin',{settleMs:500});
    check(await page.eval("Chart.defaults.animation.duration===0 && getComputedStyle(document.querySelector('.ld-button')).transitionDuration==='0s'"),'Reduced motion preference was not respected');
    results.push({interaction:'Reduced motion',passed:true});
    const hook=await page.send('Page.addScriptToEvaluateOnNewDocument',{source:`const realFetch=window.fetch;window.fetch=(url,...args)=>String(url).startsWith('/logistics/notifications')?Promise.resolve(new Response(JSON.stringify({message:'Unavailable'}),{status:503,headers:{'Content-Type':'application/json'}})):realFetch(url,...args);`});
    await page.goto(base+'/logistics/dashboard?audit_role=logistics_admin',{settleMs:500});
    check(await page.eval("(()=>{const s=Alpine.$data(document.querySelector('[x-data]'));return s.notificationsError===true && Array.isArray(s.notifications) && document.body.textContent.includes('Notifications could not load.')})()"),'Notification failure state did not render safely');
    await page.send('Page.removeScriptToEvaluateOnNewDocument',{identifier:hook.identifier});
    results.push({interaction:'Notification fetch failure fallback',passed:true});
    check(page.consoleErrors.length===0,page.consoleErrors.join('\n'));
    console.log(JSON.stringify({passed:true,checks:results.length,results},null,2));
} finally {
    writeFileSync('.taste-skill/logistics-dashboard/'+(process.argv.includes('--quick')?'verification-interactions.json':'verification.json'),JSON.stringify(results,null,2));
    await browser.close();
}
