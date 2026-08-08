# Shepherd.js Onboarding Tour — Rencana Implementasi

## Context

Aplikasi belum memiliki interactive guided tour. User tenant baru (admin_primer maupun staff) harus menebak sendiri fungsi navigasi, dashboard, dan menu akun. Shepherd.js dipilih sebagai library tour karena ringan, accessible, dan mendukung modal overlay + keyboard navigation. Scope MVP: **tur dashboard + navigasi global saja**, auto-start sekali per akun, manual restart dari menu akun.

## Target User

| Kriteria | Termasuk? |
|----------|-----------|
| User dengan `koperasi_id` not null (semua role tenant) | ✅ |
| `admin_primer` tenant | ✅ |
| Staff/custom role tenant | ✅ |
| `super_admin` (`koperasi_id` null) | ❌ |
| User tenantless/invalid | ❌ |

Eligibility check: `User::isTenantUser()` → `$this->koperasi_id !== null`.

## File yang Dibuat / Dimodifikasi

### Baru

| File | Deskripsi |
|------|-----------|
| `database/migrations/..._add_onboarding_tour_finished_at_to_users_table.php` | Kolom nullable timestamp |
| `app/Http/Controllers/OnboardingTourController.php` | Invokable controller, pola `DashboardBannerController` |
| `resources/js/pages/onboarding-tour.js` | Modul JS tour, DOM-guarded |
| `tests/Feature/OnboardingTourTest.php` | Feature test endpoint + visibility |

### Dimodifikasi

| File | Perubahan |
|------|-----------|
| `package.json` | `npm install --save-dev shepherd.js` |
| `app/Models/User.php` | Tambah cast `onboarding_tour_finished_at`, method `isTenantUser()` |
| `routes/web.php` | Route `PATCH /onboarding/tour` |
| `resources/views/dashboard.blade.php` | Bootstrap element `[data-onboarding-tour]` untuk tenant user |
| `resources/views/partials/user-dropdown.blade.php` | Link "Ulangi tur aplikasi" untuk tenant user |
| `resources/js/app.js` | Import `./pages/onboarding-tour` |
| `resources/css/app.css` | Import Shepherd CSS |
| `resources/css/custom.css` | Override styling Shepherd sesuai tema aplikasi |

## Detail Implementasi

### 1. Migration

Tambah `onboarding_tour_finished_at` nullable timestamp ke tabel `users`, setelah `dashboard_banner_dismissed_at`. Tidak perlu index — tidak ada query lintas-user.

### 2. Model User

```php
// Cast
'onboarding_tour_finished_at' => 'datetime',

// Helper eligibility — bukan role-check, murni tenant membership
public function isTenantUser(): bool
{
    return $this->koperasi_id !== null;
}
```

### 3. Controller (Invokable)

`OnboardingTourController` — pola identik dengan `DashboardBannerController`:

- Hanya baca `$request->user()`, tidak terima input.
- Guard: abort 403 jika `!isTenantUser()`.
- Idempotent: set `onboarding_tour_finished_at = now()` hanya jika null, jika sudah terisi biarkan timestamp asli.
- Return 204 (JSON response, bukan redirect — dipanggil dari `fetch`).

### 4. Route

```php
Route::patch('/onboarding/tour', OnboardingTourController::class)
    ->name('onboarding.tour.finish');
```

Ditempatkan di dalam group `['auth', 'koperasi.active']` yang sudah ada.

### 5. Dashboard Blade — Bootstrap Element

Untuk `isTenantUser()` saja, render elemen hidden:

```html
<div data-onboarding-tour
     data-onboarding-url="{{ route('onboarding.tour.finish') }}"
     data-csrf="{{ csrf_token() }}"
     data-onboarding-auto-start="{{ $user->onboarding_tour_finished_at ? '0' : '1' }}"
     hidden></div>
<div data-onboarding-save-error role="status" aria-live="polite" class="visually-hidden"></div>
```

- Elemen selalu dirender untuk tenant user (termasuk yang sudah selesai) agar manual restart tetap bisa.
- Flag `data-onboarding-auto-start` mengontrol auto-start.

### 6. User Dropdown — Tombol Restart

Untuk `isTenantUser()` saja, tambah link di atas divider logout:

```html
<a class="dropdown-item" href="{{ route('dashboard') }}#onboarding-tour"
   data-onboarding-restart>
    <i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Ulangi tur aplikasi
</a>
```

- Dari halaman non-Dashboard: navigasi ke Dashboard, fragment triggers tour.
- Dari Dashboard: JS intercept klik, mulai tour tanpa reload.
- Fragment dihapus dengan `history.replaceState` sebelum tour mulai.

### 7. JavaScript Module (`onboarding-tour.js`)

Flow:

1. `DOMContentLoaded` → bind `[data-onboarding-restart]` links.
2. Jika `[data-onboarding-tour]` tidak ada (bukan Dashboard / bukan tenant user) → restart links navigasi natural ke Dashboard.
3. Jika ada → build tour sekali:
   - Auto-start jika flag = `'1'`.
   - Manual start jika URL fragment `#onboarding-tour` ada atau restart link diklik.
4. Pada `complete` dan `cancel` event → panggil `fetch` PATCH sekali (guarded, tidak duplikat per run).
5. Retry sekali jika gagal; jika tetap gagal, tampilkan pesan error di live-region.

**Tour steps** (filter step yang target-nya tidak visible):

| # | Target | Konten |
|---|--------|--------|
| 1 | `#main-content` | Selamat datang, tur singkat dashboard & navigasi |
| 2 | `.app-sidebar` ATAU `.topbar-nav` (resolve berdasarkan layout aktif) | Menu navigasi, item tampil sesuai hak akses |
| 3 | `.tenant-context` (skip jika hidden) | Konteks koperasi aktif |
| 4 | `.summary-card:first-child` (skip jika tidak ada) | Ringkasan data dashboard |
| 5 | `.app-topbar-user` | Menu akun, bisa ulangi tur dari sini |

Layout-safe: resolve target dengan visibility check (`getBoundingClientRect` + computed display/visibility) karena shell merender duplikat sidebar/topbar dan satu disembunyikan CSS.

Progress text dihitung setelah filtering: "Langkah X dari Y".

**Shepherd config:**

- Modal overlay enabled
- `useModalOverlay: true`
- Escape → cancel
- `cancelIcon: { enabled: true, label: 'Tutup tur' }`
- Tombol: Kembali / Lanjut / Selesai / Lewati (bahasa Indonesia)
- `scrollTo: { behavior: 'smooth', block: 'center' }`
- Custom CSS class: `onboarding-tour`

### 8. CSS

Import di `app.css`:

```css
@import 'shepherd.js/dist/css/shepherd.css';
```

Override di `custom.css`:

- Warna mengikuti variabel Bootstrap/aplikasi (light + dark via `[data-bs-theme="dark"]`)
- Max-width tooltip: `min(360px, calc(100vw - 2rem))`
- Touch-target minimum sesuai mobile
- Z-index di atas sticky sidebar/topbar, di bawah Bootstrap modal
- `prefers-reduced-motion: reduce` → nonaktifkan animasi Shepherd
- Print: sembunyikan tour UI

### 9. Tests (`OnboardingTourTest.php`)

| Test case | Assertion |
|-----------|-----------|
| Admin primer tenant → Dashboard menampilkan bootstrap element + auto-start="1" + restart link | `assertSee` |
| Tenant staff → sama, tidak dibatasi role | `assertSee` |
| User sudah selesai tour → auto-start="0", restart link tetap ada | `assertSee` |
| PATCH → simpan timestamp, return 204 | `assertNoContent` |
| PATCH ulang → 204, timestamp asli tidak berubah | idempotent check |
| User A selesai → User B tidak terpengaruh | isolation |
| Guest → redirect login | `assertRedirect` |
| Super admin → tidak lihat bootstrap element, PATCH return 403 | `assertDontSee`, `assertForbidden` |
| User tenantless → fail-closed | `assertForbidden` |

## Yang TIDAK Diubah

- `DashboardBannerController` dan banner "Panduan singkat" → tetap independen
- Halaman `/panduan-singkat` → tetap tersedia
- `dashboard_banner_dismissed_at` → tidak terkait tour
- `NavigationMenu.php` → tidak ditambah link tour (tour adalah fitur akun, bukan navigasi domain)

## Verifikasi

```bash
# Backend
php artisan migrate
php artisan route:list --name=onboarding
php artisan test tests/Feature/OnboardingTourTest.php
php artisan test tests/Feature/DashboardBannerTest.php  # regresi
vendor/bin/pint --test

# Frontend
npm install
npm run build
# npm audit (opsional)

# Manual browser check
# - Fresh admin_primer → auto-start tour di Dashboard
# - Complete / Cancel / Escape / Close icon → tidak auto-start lagi di session baru
# - Menu akun → "Ulangi tur aplikasi" → tour mulai ulang
# - Super admin → tidak lihat tour atau restart link
# - Layout sidebar vs topbar → target step menyesuaikan
# - Dark mode + light mode
# - Mobile viewport
# - Keyboard-only navigation
```