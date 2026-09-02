import './components/app-shell';
import './components/bulk-actions';
import './components/date-range-picker';
import './components/dokumen-repeater';
import './components/filter-form';
import './components/file-upload';
import './components/localized-inputs';
import './components/mobile-tables';
import './components/modals';
import './components/password-peek';
import './components/role-access-notice';
import './pages/absensi';
import './pages/barang';
import './pages/dashboard';
import './pages/onboarding-tour';
import './pages/owner-dashboard';
import './pages/platform-features';
import './pages/hari-libur';
import './pages/karyawan';
import './pages/komponen-gaji';
import './pages/laporan';
import './pages/pengaturan';
import './pages/slip-gaji-editor';
import './pages/role';
import './pages/transaksi-gaji';
import './pages/unit-kerja';

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-submit-on-change]') && !event.target.closest('[data-filter-form]')) {
        event.target.form?.requestSubmit();
    }
});
