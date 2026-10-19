import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Progressive feedback for real POST forms; server transactions remain authoritative.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || form.method.toLowerCase() === 'get') return;
    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    const button = event.submitter;
    if (button) {
        button.dataset.originalText = button.textContent;
        setTimeout(() => { button.disabled = true; button.textContent = 'Đang xử lý…'; }, 0);
    }
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach(form => {
        delete form.dataset.submitting; form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-original-text]').forEach(button => {
            button.disabled = false; button.textContent = button.dataset.originalText;
        });
    });
});
