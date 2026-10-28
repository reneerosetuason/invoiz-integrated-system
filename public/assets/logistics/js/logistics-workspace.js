// Presentation helpers keep the existing form targets, Alpine state and role checks intact.
(() => {
    let activeConfirmation=null;
    window.logisticsConfirm=({title='Confirm action',message,confirmLabel='Confirm',danger=false,reasonLabel='',minLength=0})=>new Promise(resolve=>{
        if(activeConfirmation){resolve(null);return;}
        const opener=document.activeElement,dialog=document.createElement('dialog');
        dialog.className='lw-action-dialog';dialog.setAttribute('aria-labelledby','logistics-confirm-title');dialog.setAttribute('aria-describedby','logistics-confirm-description');
        const form=document.createElement('form');form.method='dialog';
        const heading=document.createElement('h2');heading.id='logistics-confirm-title';heading.textContent=title;
        const description=document.createElement('p');description.id='logistics-confirm-description';description.textContent=message||'Review this action before continuing.';
        form.append(heading,description);
        let reason;
        if(reasonLabel){const label=document.createElement('label');label.htmlFor='logistics-confirm-reason';label.textContent=reasonLabel;reason=document.createElement('textarea');reason.id='logistics-confirm-reason';reason.required=true;reason.minLength=minLength;reason.rows=3;const note=document.createElement('small');note.textContent='Enter at least '+minLength+' characters.';form.append(label,reason,note)}
        const actions=document.createElement('div');actions.className='lw-confirm-actions';
        const cancel=document.createElement('button');cancel.type='submit';cancel.value='cancel';cancel.formNoValidate=true;cancel.textContent='Cancel';
        const confirm=document.createElement('button');confirm.type='submit';confirm.value='confirm';confirm.textContent=confirmLabel;confirm.className=danger?'lw-confirm-danger':'lw-confirm-primary';
        actions.append(cancel,confirm);form.append(actions);dialog.append(form);document.body.append(dialog);activeConfirmation=dialog;
        dialog.addEventListener('click',event=>{const r=dialog.getBoundingClientRect();if(event.target===dialog&&(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom))dialog.close('cancel')});
        dialog.addEventListener('close',()=>{const result=dialog.returnValue==='confirm'?{reason:reason?.value||''}:null;dialog.remove();activeConfirmation=null;if(opener?.isConnected)opener.focus({preventScroll:true});resolve(result)},{once:true});
        dialog.showModal();(reason||cancel).focus();
    });
    const fieldLabels={search:'Search',status:'Status',date_from:'From date',date_to:'To date',center_id:'Logistics center',service_area_id:'Service area',rider_id:'Rider',vehicle_type:'Vehicle',priority:'Priority',per_page:'Rows per page',sort:'Sort by',dir:'Sort order',tracking_number:'Tracking number',reference:'Reference',filter:'Filter',online:'Availability',type:'Category'};
    const init = () => {
        const root=document.querySelector('.logistics-workspace');
        if(!root)return;
        root.querySelectorAll('form[data-confirm-message]').forEach(form=>form.addEventListener('submit',async event=>{
            event.preventDefault();
            if(!form.checkValidity()){form.reportValidity();return;}
            const submit=event.submitter||form.querySelector('button[type=submit]');
            const label=submit?.textContent.trim().replace(/\s+/g,' ')||'Confirm';
            const confirmation=await window.logisticsConfirm({title:label,message:form.dataset.confirmMessage,confirmLabel:label,danger:/deactivate|reject|delete/i.test(form.action)||submit?.className.includes('bg-red'),reasonLabel:form.dataset.confirmReason,minLength:Number(form.dataset.confirmMinLength)||0});
            if(!confirmation)return;
            if(form.dataset.confirmReason&&form.elements.reason)form.elements.reason.value=confirmation.reason;
            HTMLFormElement.prototype.submit.call(form);
        }));
        let fieldId=0;
        root.querySelectorAll('form').forEach(form=>{
            if(form.getAttribute('method')?.toLowerCase()==='get')form.classList.add('lw-get-form');
            form.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file]),select,textarea').forEach(control=>{
                if(!control.id)control.id='logistics-field-'+(++fieldId);
                const previous=control.previousElementSibling;
                const existing=control.labels?.[0]||(previous?.matches('label')&&!previous.htmlFor&&!previous.querySelector('input,select,textarea')?previous:null);
                if(existing){existing.htmlFor=control.id;return;}
                if(form.getAttribute('method')?.toLowerCase()!=='get'){
                    if(!control.getAttribute('aria-label')&&control.placeholder)control.setAttribute('aria-label',control.placeholder);
                    return;
                }
                if(control.getAttribute('aria-label')&&form.closest('.lw-messages'))return;
                const label=document.createElement('label');label.className='lw-filter-field';label.htmlFor=control.id;
                const text=document.createElement('span');text.className='lw-filter-label';
                text.textContent=fieldLabels[control.name]||control.title||control.getAttribute('aria-label')||control.placeholder||'Filter';
                if(!control.getAttribute('aria-label'))control.setAttribute('aria-label',text.textContent);
                control.before(label);label.append(text,control);
                // Existing search icons belong beside the field; account for the added label line.
                const container=label.parentElement;
                if(container.classList.contains('relative')&&!form.closest('.lw-messages')){
                    container.querySelectorAll(':scope > span.absolute,:scope > svg.absolute').forEach(icon=>{icon.style.top='27px';icon.style.bottom='0';icon.style.pointerEvents='none'});
                }
            });
        });
        const arrangeTables = () => root.querySelectorAll('table').forEach(table=>{
            if(!table.tHead)return;
            table.classList.add('lw-table');
            const wrap=table.parentElement;wrap.classList.add('lw-table-wrap');
            const headers=[...table.tHead.rows[0].cells];
            const labels=headers.map(th=>th.textContent.trim().replace(/\s+/g,' '));
            for(const tbody of table.tBodies)for(const row of tbody.rows){
                if([...row.cells].some(cell=>cell.colSpan>1))continue;
                [...row.cells].forEach((cell,i)=>{
                    const label=labels[i];if(label&&!cell.hasAttribute('data-label'))cell.dataset.label=label;
                });
            }
            if(table.dataset.lwPrepared)return;
            table.dataset.lwPrepared='true';
            const sorts=headers.flatMap(th=>[...th.querySelectorAll('a[href]')]);
            if(sorts.length){
                const details=document.createElement('details');details.className='lw-table-sort';
                const summary=document.createElement('summary');summary.textContent='Sort records';details.append(summary);
                const nav=document.createElement('nav');nav.setAttribute('aria-label','Sort records');
                sorts.forEach(link=>{const a=document.createElement('a');a.href=link.href;a.textContent='Sort by '+link.textContent.trim().replace(/\s+/g,' ');nav.append(a)});
                details.append(nav);wrap.before(details);
            }
        });
        arrangeTables();
        let queued=false;
        new MutationObserver(records=>{
            if(queued||!records.some(record=>record.addedNodes.length))return;
            queued=true;requestAnimationFrame(()=>{queued=false;arrangeTables()});
        }).observe(root,{childList:true,subtree:true});
        // Keep charts readable after the initial Chart.js render, where present.
        const charts = () => {
            if(!window.Chart)return;
            const dark=document.documentElement.classList.contains('dark');
            root.querySelectorAll('canvas').forEach(canvas=>{
                const chart=Chart.getChart(canvas);if(!chart)return;
                chart.options.font={...(chart.options.font||{}),family:'Inter, system-ui, sans-serif',size:13};
                const legend=chart.options.plugins?.legend?.labels;if(legend)legend.font={...(legend.font||{}),size:13,family:'Inter, system-ui, sans-serif'};
                for(const scale of Object.values(chart.options.scales||{}))if(scale.ticks){scale.ticks.font={...(scale.ticks.font||{}),size:13,family:'Inter, system-ui, sans-serif'};scale.ticks.color=dark?'#B8CCCA':'#52676A'}
                chart.update('none');
            });
        };
        charts();window.addEventListener('load',charts,{once:true});
        const dialogs=[...root.querySelectorAll('[x-show]')].filter(el=>el.classList.contains('fixed')&&el.classList.contains('inset-0')&&!el.closest('.lw-dialog'));
        dialogs.forEach((dialog,index)=>{
            const panel=[...dialog.children].find(el=>!el.classList.contains('absolute')&&(el.tagName==='FORM'||el.classList.contains('relative')||el.getAttribute('role')==='dialog'||el.classList.contains('bg-white')));
            if(!panel)return;
            dialog.classList.add('lw-dialog');panel.classList.add('lw-dialog-panel');panel.tabIndex=-1;
            if(panel.getAttribute('role')==='dialog'){panel.removeAttribute('role');panel.removeAttribute('aria-modal');panel.removeAttribute('aria-labelledby');}
            const heading=panel.querySelector('h2,h3,h4');
            dialog.setAttribute('role','dialog');dialog.setAttribute('aria-modal','true');
            if(heading){heading.id||='logistics-dialog-title-'+index;dialog.setAttribute('aria-labelledby',heading.id)}
            else dialog.setAttribute('aria-label',dialog.getAttribute('x-show')==='lightbox'?'Image preview':'Confirm action');
            const scrim=[...dialog.children].find(el=>el.classList.contains('absolute')&&el.hasAttribute('@click'));
            let open=false,opener=null;
            const sync=()=>{
                const visible=getComputedStyle(dialog).display!=='none';if(visible===open)return;open=visible;
                if(open){opener=document.activeElement;requestAnimationFrame(()=>{if(getComputedStyle(dialog).display==='none')return;(panel.querySelector('button[type=button],input:not([type=hidden]),select,textarea')||panel).focus({preventScroll:true})})}
                else if(opener?.isConnected)opener.focus({preventScroll:true});
                document.body.classList.toggle('lw-dialog-open',dialogs.some(el=>getComputedStyle(el).display!=='none'));
            };
            new MutationObserver(sync).observe(dialog,{attributes:true,attributeFilter:['style']});
            dialog.addEventListener('keydown',event=>{
                if(event.key==='Escape'&&scrim){event.preventDefault();event.stopPropagation();scrim.click()}
                if(event.key!=='Tab')return;
                const controls=[...panel.querySelectorAll('a[href],button:not([disabled]),input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled])')].filter(el=>el.getClientRects().length);
                if(!controls.length){event.preventDefault();panel.focus();return;}
                const first=controls[0],last=controls.at(-1);
                if(event.shiftKey&&(document.activeElement===first||document.activeElement===panel)){event.preventDefault();last.focus()}
                else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
            });
            sync();
        });
    };
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
