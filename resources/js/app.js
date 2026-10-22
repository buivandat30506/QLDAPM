import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

const sidebar = document.getElementById('sidebarMenu');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarContent = document.querySelector('[data-sidebar-dismiss]');

if (sidebar && sidebarToggle && sidebarContent) {
    const root = document.documentElement;
    const setSidebarOpen = (open, persist = false) => {
        if (!open && sidebar.contains(document.activeElement)) sidebarToggle.focus();
        root.dataset.sidebarState = open ? 'open' : 'closed';
        sidebarToggle.setAttribute('aria-expanded', String(open));
        sidebar.setAttribute('aria-hidden', String(!open));
        sidebar.inert = !open;
        if (persist) {
            try {
                localStorage.setItem('smartAirline.sidebar', root.dataset.sidebarState);
            } catch {
                // Keep the drawer usable when browser storage is unavailable.
            }
        }
    };

    const restoreSidebar = () => {
        let open = root.dataset.sidebarState === 'open';
        try {
            open = localStorage.getItem('smartAirline.sidebar') === 'open';
        } catch {
            // The state restored in the head remains the in-memory fallback.
        }
        setSidebarOpen(open);
    };

    restoreSidebar();
    requestAnimationFrame(() => root.classList.add('sidebar-ready'));
    sidebarToggle.addEventListener('click', () => setSidebarOpen(root.dataset.sidebarState !== 'open', true));
    sidebarContent.addEventListener('click', () => {
        if (root.dataset.sidebarState === 'open') setSidebarOpen(false, true);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.dataset.sidebarState === 'open') {
            setSidebarOpen(false, true);
            sidebarToggle.focus();
        }
    });
    window.addEventListener('pageshow', restoreSidebar);

    const topbar = document.querySelector('[data-admin-topbar]');
    if (topbar) {
        const updateTopbarHeight = () => root.style.setProperty('--admin-topbar-height', `${topbar.getBoundingClientRect().height}px`);
        updateTopbarHeight();
        if ('ResizeObserver' in window) new ResizeObserver(updateTopbarHeight).observe(topbar);
        else window.addEventListener('resize', updateTopbarHeight);
    }
}

document.querySelectorAll('[data-infrastructure]').forEach((section) => {
    const toggle = section.querySelector('[data-infrastructure-toggle]');
    const content = section.querySelector('[data-infrastructure-content]');
    if (!toggle || !content) return;

    const storageKey = 'smartAirline.infrastructure';
    const collapse = bootstrap.Collapse.getOrCreateInstance(content, { toggle: false });
    let preferredOpen = true;
    let desiredOpen = content.classList.contains('show');
    let transitioning = false;

    const syncAccessibility = (open) => {
        if (!open && content.contains(document.activeElement)) toggle.focus({ preventScroll: true });
        toggle.setAttribute('aria-expanded', String(open));
        toggle.classList.toggle('collapsed', !open);
        content.setAttribute('aria-hidden', String(!open));
        content.inert = !open;
    };

    const applyDesiredState = () => {
        if (transitioning) return;
        const open = content.classList.contains('show');
        if (open === desiredOpen) syncAccessibility(open);
        else if (desiredOpen) collapse.show();
        else collapse.hide();
    };

    const startTransition = (event, open) => {
        // Nested filter/add collapses also bubble these Bootstrap events.
        if (event.target !== content || event.defaultPrevented) return;
        transitioning = true;
        syncAccessibility(open);
        queueMicrotask(() => {
            if (!event.defaultPrevented) return;
            transitioning = false;
            syncAccessibility(content.classList.contains('show'));
        });
    };
    const finishTransition = (event, open) => {
        if (event.target !== content) return;
        transitioning = false;
        syncAccessibility(open);
        // Honor the latest click even if it arrived while Bootstrap was animating.
        applyDesiredState();
    };
    content.addEventListener('show.bs.collapse', (event) => startTransition(event, true));
    content.addEventListener('hide.bs.collapse', (event) => startTransition(event, false));
    content.addEventListener('shown.bs.collapse', (event) => finishTransition(event, true));
    content.addEventListener('hidden.bs.collapse', (event) => finishTransition(event, false));

    const restoreInfrastructure = () => {
        try {
            preferredOpen = localStorage.getItem(storageKey) !== 'closed';
        } catch {
            // Retain the in-memory preference when browser storage is unavailable.
        }
        // Validation can reveal controls without changing the saved choice.
        desiredOpen = section.dataset.infrastructureForceOpen === 'true' || preferredOpen;
        applyDesiredState();
    };
    toggle.addEventListener('click', () => {
        desiredOpen = !desiredOpen;
        preferredOpen = desiredOpen;
        try {
            localStorage.setItem(storageKey, preferredOpen ? 'open' : 'closed');
        } catch {
            // Closing and opening must also work with storage disabled.
        }
        applyDesiredState();
    });
    restoreInfrastructure();
    window.addEventListener('pageshow', restoreInfrastructure);
});

document.querySelectorAll('[data-flight-create-form]').forEach((form) => {
    const schedule = form.querySelector('[name="schedule_id"]');
    const crewFields = form.querySelector('[data-flight-crew-fields]');
    const notice = form.querySelector('[data-flight-crew-unavailable]');
    if (!schedule || !crewFields || !notice) return;

    const syncCrewPermission = () => {
        const allowed = schedule.selectedOptions[0]?.dataset.crewAllowed === 'true';
        crewFields.disabled = !allowed;
        notice.hidden = allowed;
    };
    schedule.addEventListener('change', syncCrewPermission);
    window.addEventListener('pageshow', syncCrewPermission);
    syncCrewPermission();
});

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
