import Shepherd from 'shepherd.js';

const STORAGE_KEY_COMPLETED = 'onboarding_tour_completed';
const FRAGMENT_ID = 'onboarding-tour';

/**
 * Check if an element is visible (not hidden by CSS/layout switching)
 */
function isElementVisible(el) {
    if (!el) return false;
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
        return false;
    }
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
}

/**
 * Find the first visible navigation target based on current layout
 */
function findVisibleNavTarget() {
    // Sidebar layout
    const sidebar = document.querySelector('.app-sidebar');
    if (isElementVisible(sidebar)) return sidebar;

    // Topbar layout - horizontal nav
    const topbarNav = document.querySelector('.topbar-nav');
    if (isElementVisible(topbarNav)) return topbarNav;

    // Mobile sidebar toggle (when sidebar is collapsed)
    const sidebarToggle = document.querySelector('.sidebar-desktop-toggle');
    if (isElementVisible(sidebarToggle)) return sidebarToggle;

    // Mobile topbar toggler
    const topbarToggler = document.querySelector('.app-topbar-nav .navbar-toggler');
    if (isElementVisible(topbarToggler)) return topbarToggler;

    return null;
}

/**
 * Find the first visible account/target element
 */
function findVisibleAccountTarget() {
    const accountBtn = document.querySelector('.app-topbar-user');
    if (isElementVisible(accountBtn)) return accountBtn;
    return null;
}

/**
 * Find the first visible tenant context element
 */
function findVisibleTenantContext() {
    const context = document.querySelector('.tenant-context');
    if (isElementVisible(context)) return context;
    return null;
}

/**
 * Build and return the Shepherd tour instance
 */
function buildTour(config) {
    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        keyboardNavigation: true,
        exitOnEsc: true,
        defaultStepOptions: {
            scrollTo: { behavior: 'smooth', block: 'center' },
            cancelIcon: {
                enabled: true,
                label: 'Tutup tur'
            },
            classes: 'onboarding-tour-step'
        }
    });

    // Step 1: Welcome / Main content area
    tour.addStep({
        id: 'welcome',
        title: 'Selamat Datang di Aplikasi',
        text: 'Tur singkat ini akan memperkenalkan dashboard dan navigasi utama. Anda bisa menjalankannya kembali kapan saja dari menu akun.',
        attachTo: {
            element: '#main-content',
            on: 'center'
        },
        buttons: [
            {
                text: 'Lanjut',
                action: tour.next,
                classes: 'btn btn-primary'
            },
            {
                text: 'Lewati',
                action: tour.cancel,
                classes: 'btn btn-secondary',
                secondary: true
            }
        ]
    });

    // Step 2: Navigation (resolved at runtime)
    tour.addStep({
        id: 'navigation',
        title: 'Navigasi Utama',
        text: 'Menu disamping (atau di atas) berisi domain: SDM & Kehadiran, Penggajian, Manajemen Aset, dan Administrasi. Item yang terlihat menyesuaikan hak akses Anda.',
        attachTo: {
            element: () => findVisibleNavTarget(),
            on: 'right'
        },
        buttons: [
            {
                text: 'Kembali',
                action: tour.back,
                classes: 'btn btn-secondary'
            },
            {
                text: 'Lanjut',
                action: tour.next,
                classes: 'btn btn-primary'
            },
            {
                text: 'Lewati',
                action: tour.cancel,
                classes: 'btn btn-outline-secondary',
                secondary: true
            }
        ]
    });

    // Step 3: Tenant context
    tour.addStep({
        id: 'tenant-context',
        title: 'Konteks Koperasi',
        text: 'Area ini menunjukkan koperasi Anda dan mode akses yang sedang aktif. Semua data yang Anda lihat dan kelola dibatasi dalam lingkup koperasi ini.',
        attachTo: {
            element: () => findVisibleTenantContext(),
            on: 'bottom'
        },
        when: {
            show: () => {
                const el = findVisibleTenantContext();
                return !!el;
            }
        },
        buttons: [
            {
                text: 'Kembali',
                action: tour.back,
                classes: 'btn btn-secondary'
            },
            {
                text: 'Lanjut',
                action: tour.next,
                classes: 'btn btn-primary'
            },
            {
                text: 'Lewati',
                action: tour.cancel,
                classes: 'btn btn-outline-secondary',
                secondary: true
            }
        ]
    });

    // Step 4: Dashboard summaries
    tour.addStep({
        id: 'dashboard-summaries',
        title: 'Ringkasan Dashboard',
        text: 'Kartu ringkasan menampilkan metrik kunci: total inventaris, nilai aset, barang perlu perbaikan, dan karyawan aktif. Klik untuk melihat detail.',
        attachTo: {
            element: () => {
                const card = document.querySelector('.summary-card');
                return isElementVisible(card) ? card : null;
            },
            on: 'bottom'
        },
        when: {
            show: () => {
                const card = document.querySelector('.summary-card');
                return !!card && isElementVisible(card);
            }
        },
        buttons: [
            {
                text: 'Kembali',
                action: tour.back,
                classes: 'btn btn-secondary'
            },
            {
                text: 'Lanjut',
                action: tour.next,
                classes: 'btn btn-primary'
            },
            {
                text: 'Lewati',
                action: tour.cancel,
                classes: 'btn btn-outline-secondary',
                secondary: true
            }
        ]
    });

    // Step 5: Account menu + restart hint
    tour.addStep({
        id: 'account-menu',
        title: 'Menu Akun',
        text: 'Di sini Anda bisa mengubah tema, tata letak, mengakses profil, pengaturan, dan menjalankan ulang tur ini melalui "Ulangi tur aplikasi".',
        attachTo: {
            element: () => findVisibleAccountTarget(),
            on: 'left'
        },
        when: {
            show: () => {
                return !!findVisibleAccountTarget();
            }
        },
        buttons: [
            {
                text: 'Kembali',
                action: tour.back,
                classes: 'btn btn-secondary'
            },
            {
                text: 'Selesai',
                action: tour.complete,
                classes: 'btn btn-primary'
            }
        ]
    });

    return tour;
}

/**
 * Persist completion to server via PATCH
 */
async function persistCompletion(url, csrf) {
    try {
        const res = await fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            keepalive: true
        });

        if (res.ok) return true;
        if (res.status === 401 || res.status === 403) {
            // Not eligible or session expired
            return false;
        }
        // Retry once on other errors
        await new Promise(r => setTimeout(r, 300));
        const retry = await fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            keepalive: true
        });
        return retry.ok;
    } catch {
        // Retry once on network error
        try {
            await new Promise(r => setTimeout(r, 300));
            const retry = await fetch(url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                keepalive: true
            });
            return retry.ok;
        } catch {
            return false;
        }
    }
}

/**
 * Show save error message to live region
 */
function showSaveError() {
    const errorEl = document.querySelector('[data-onboarding-save-error]');
    if (errorEl) {
        errorEl.textContent = 'Gagal menyimpan status tur; tur mungkin muncul lagi saat login berikutnya.';
        errorEl.classList.remove('visually-hidden');
        setTimeout(() => {
            errorEl.classList.add('visually-hidden');
        }, 8000);
    }
}

/**
 * Main initialization
 */
document.addEventListener('DOMContentLoaded', () => {
    const bootstrapEl = document.querySelector('[data-onboarding-tour]');
    if (!bootstrapEl) return; // Not on dashboard or not tenant user

    const url = bootstrapEl.dataset.onboardingUrl;
    const csrf = bootstrapEl.dataset.csrf;
    const autoStart = bootstrapEl.dataset.onboardingAutoStart === '1';

    // Handle restart links (from account menu)
    const restartLinks = document.querySelectorAll('[data-onboarding-restart]');
    restartLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // If we're already on dashboard, intercept and start manually
            if (window.location.pathname === '/dashboard' || window.location.pathname === '/dashboard/') {
                e.preventDefault();
                history.replaceState(null, '', window.location.pathname);
                startTour({ manual: true });
            }
            // Otherwise let it navigate normally (will trigger on page load)
        });
    });

    // Check if we should start manually (fragment present)
    const shouldStartManual = window.location.hash === `#${FRAGMENT_ID}`;
    if (shouldStartManual) {
        history.replaceState(null, '', window.location.pathname);
        startTour({ manual: true });
        return;
    }

    // Auto-start if flagged
    if (autoStart) {
        // Small delay to ensure layout is settled
        setTimeout(() => startTour({ manual: false }), 100);
    }

    function startTour({ manual }) {
        const tour = buildTour({ url, csrf });
        let persisted = false;

        tour.on('complete', async () => {
            if (!persisted) {
                persisted = true;
                const ok = await persistCompletion(url, csrf);
                if (!ok) showSaveError();
            }
        });

        tour.on('cancel', async () => {
            if (!persisted) {
                persisted = true;
                const ok = await persistCompletion(url, csrf);
                if (!ok) showSaveError();
            }
        });

        tour.start();
    }
});
