document.addEventListener('DOMContentLoaded', () => {
 document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {if (!confirm(form.dataset.confirm)) event.preventDefault();}));
 const retry = document.querySelector('[data-reopen="true"]');
 if (retry && window.bootstrap) bootstrap.Modal.getOrCreateInstance(retry).show();
 document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click',()=>{document.body.classList.toggle('print-label',button.dataset.print==='label');window.print();document.body.classList.remove('print-label');}));
});

// One real input per identifier keeps keyboard entry, paste and server validation intact.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select[name="borrower_type"]').forEach(select => {
        const form = select.closest('form');
        const input = form.querySelector('[name="borrower_identifier"]');
        const label = form.querySelector(`label[for="${input.id}"]`);
        const update = () => {
            const type = select.value;
            const staff = ['Academic Staff', 'Administrative Staff', 'ICT Staff'].includes(type);
            input.required = type === 'Student' || staff;
            const title = type === 'Student' ? 'Student ID' : staff ? 'Staff ID' : type ? 'Reference ID (optional)' : 'Student / Staff ID';
            label.textContent = title + (input.required ? ' *' : '');
            input.maxLength = 64;
        };
        select.addEventListener('change', update);
        update();
    });
});
// Make intentionally scrollable tables reachable by keyboard as well as touch.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.table-responsive').forEach(region => {
        region.tabIndex = 0;
        region.setAttribute('role', 'region');
        region.setAttribute('aria-label', 'Scrollable records table');
    });
});