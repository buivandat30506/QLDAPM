document.querySelectorAll('.m02 form').forEach(form=>{
    const route=form.querySelector('[data-plan-route]');
    const gate=form.querySelector('[data-plan-gate]');
    const sync=()=>{
        const option=route?.selectedOptions[0];
        const origin=option?.dataset.origin||form.dataset.fixedOrigin;
        form.querySelectorAll('[data-origin-zone]').forEach(el=>el.textContent=option?.dataset.originZone||el.textContent);
        form.querySelectorAll('[data-destination-zone]').forEach(el=>el.textContent=option?.dataset.destinationZone||el.textContent);
        if(gate){Array.from(gate.options).forEach(o=>{if(o.dataset.airport){o.hidden=o.dataset.airport!==origin;o.disabled=o.hidden;if(o.hidden&&o.selected)o.selected=false;}});if(!gate.selectedOptions.length)gate.value='';}
    };
    route?.addEventListener('change',sync);if(route||form.dataset.fixedOrigin)sync();
    const selectAll=form.querySelector('[data-select-flights]');
    const boxes=Array.from(form.querySelectorAll('input[name="flight_ids[]"][type="checkbox"]'));
    const count=form.querySelector('[data-selection-count]');
    const update=()=>{const n=boxes.filter(b=>b.checked).length;if(count)count.textContent=n?`Đã chọn ${n} chuyến`:'Chưa chọn chuyến';if(selectAll){selectAll.checked=n===boxes.length&&n>0;selectAll.indeterminate=n>0&&n<boxes.length;}};
    selectAll?.addEventListener('change',()=>{boxes.forEach(b=>b.checked=selectAll.checked);update();});boxes.forEach(b=>b.addEventListener('change',update));
});
