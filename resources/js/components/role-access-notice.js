const readStoredState = (key) => {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

const storeState = (key, value) => {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Notice tetap berfungsi selama halaman aktif jika storage diblokir.
    }
};

const removeStoredState = (key) => {
    try {
        window.localStorage.removeItem(key);
    } catch {
        // Tidak ada tindakan tambahan ketika storage tidak tersedia.
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-role-access-notice]').forEach((notice) => {
        const storageKey = notice.dataset.noticeKey;
        const header = notice.querySelector('[data-role-access-notice-header]');
        const content = notice.querySelector('[data-role-access-notice-content]');
        const toggle = notice.querySelector('[data-role-access-notice-toggle]');
        const dismiss = notice.querySelector('[data-role-access-notice-dismiss]');
        const restore = notice.querySelector('[data-role-access-notice-restore]');

        if (!storageKey || !header || !content || !toggle || !dismiss || !restore) return;

        const setExpanded = (expanded) => {
            content.hidden = !expanded;
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            notice.classList.toggle('is-collapsed', !expanded);
        };

        const setDismissed = (isDismissed) => {
            notice.classList.toggle('is-dismissed', isDismissed);
            header.hidden = isDismissed;
            content.hidden = isDismissed;
            restore.hidden = !isDismissed;

            if (!isDismissed) setExpanded(true);
        };

        setDismissed(readStoredState(storageKey) === 'dismissed');

        toggle.addEventListener('click', () => {
            setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
        });

        dismiss.addEventListener('click', () => {
            storeState(storageKey, 'dismissed');
            setDismissed(true);
        });

        restore.addEventListener('click', () => {
            removeStoredState(storageKey);
            setDismissed(false);
            toggle.focus();
        });
    });
});
