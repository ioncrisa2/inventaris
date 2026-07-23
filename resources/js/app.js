import './components/app-shell';
import './components/bulk-actions';
import './components/dokumen-repeater';
import './components/filter-form';
import './components/localized-inputs';
import './components/modals';
import './pages/absensi';
import './pages/dashboard';
import './pages/komponen-gaji';
import './pages/pengaturan';
import './pages/role';
import './pages/transaksi-gaji';
import './pages/unit-kerja';

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-submit-on-change]') && !event.target.closest('[data-filter-form]')) {
        event.target.form?.requestSubmit();
    }
});
