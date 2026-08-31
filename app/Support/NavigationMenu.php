<?php

namespace App\Support;

use App\Models\User;
use App\Services\PlatformFeatureService;

class NavigationMenu
{
    /**
     * Struktur menu navigasi aplikasi (dipakai bareng oleh sidebar & topbar,
     * satu-satunya sumber kebenaran supaya kedua tampilan tidak pernah beda
     * menu maupun aturan permission-nya).
     *
     * @return list<array<string, mixed>>
     */
    public static function groups(): array
    {
        return [
            [
                'type' => 'link',
                'label' => 'Dashboard',
                'icon' => 'bi-speedometer2',
                'route' => 'dashboard',
                'active_routes' => ['dashboard'],
                'permission' => null,
            ],
            [
                'type' => 'link',
                'label' => 'Request Produk',
                'icon' => 'bi-chat-square-text',
                'route' => 'product-requests.index',
                'active_routes' => ['product-requests.*'],
                'permission' => 'product-request.view',
                'feature' => 'product_requests',
            ],
            [
                'type' => 'group',
                'key' => 'akun-saya',
                'label' => 'Akun Saya',
                'items' => [
                    ['label' => 'Data Saya', 'icon' => 'bi-person-vcard', 'route' => 'me.profile', 'active_routes' => ['me.profile'], 'permission' => null, 'tenant_only' => true, 'feature' => 'my_profile'],
                    ['label' => 'Absensi Saya', 'icon' => 'bi-calendar-check', 'route' => 'me.attendance', 'active_routes' => ['me.attendance'], 'permission' => null, 'tenant_only' => true, 'feature' => 'my_attendance'],
                    ['label' => 'Slip Gaji Saya', 'icon' => 'bi-receipt', 'route' => 'me.salary-slips.index', 'active_routes' => ['me.salary-slips.*'], 'permission' => null, 'tenant_only' => true, 'feature' => 'my_salary_slips'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'sdm-kehadiran',
                'label' => 'SDM & Kehadiran',
                'items' => [
                    ['label' => 'Unit Kerja', 'icon' => 'bi-building', 'route' => 'unit-kerja.index', 'active_routes' => ['unit-kerja.*'], 'permission' => 'unit-kerja.view', 'feature' => 'work_units'],
                    ['label' => 'Karyawan', 'icon' => 'bi-people', 'route' => 'karyawan.index', 'active_routes' => ['karyawan.*'], 'permission' => 'karyawan.view', 'feature' => 'employees'],
                    ['label' => 'Absensi', 'icon' => 'bi-calendar3', 'route' => 'absensi.index', 'active_routes' => ['absensi.*'], 'permission' => 'absensi.view', 'feature' => 'attendance'],
                    ['label' => 'Hari Libur', 'icon' => 'bi-calendar-x', 'route' => 'hari-libur.index', 'active_routes' => ['hari-libur.*'], 'permission' => 'hari-libur.view', 'feature' => 'holidays'],
                    ['label' => 'Laporan Kepegawaian', 'icon' => 'bi-bar-chart-line', 'route' => 'laporan.kepegawaian', 'active_routes' => ['laporan.kepegawaian'], 'permission' => 'laporan.kepegawaian.view', 'feature' => 'employee_reports'],
                    ['label' => 'Laporan Absensi', 'icon' => 'bi-calendar-check', 'route' => 'laporan.absensi', 'active_routes' => ['laporan.absensi'], 'permission' => 'laporan.absensi.view', 'feature' => 'attendance_reports'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'penggajian',
                'label' => 'Penggajian',
                'items' => [
                    ['label' => 'Komponen Gaji', 'icon' => 'bi-sliders', 'route' => 'komponen-gaji.index', 'active_routes' => ['komponen-gaji.*'], 'permission' => 'komponen-gaji.view', 'feature' => 'salary_components'],
                    ['label' => 'Transaksi Gaji', 'icon' => 'bi-cash-stack', 'route' => 'transaksi-gaji.index', 'active_routes' => ['transaksi-gaji.*'], 'permission' => 'transaksi-gaji.view', 'feature' => 'salary_transactions'],
                    ['label' => 'Laporan Penggajian', 'icon' => 'bi-cash-coin', 'route' => 'laporan.penggajian', 'active_routes' => ['laporan.penggajian'], 'permission' => 'laporan.penggajian.view', 'feature' => 'payroll_reports'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'manajemen-aset',
                'label' => 'Manajemen Aset',
                'items' => [
                    ['label' => 'Inventaris Barang', 'icon' => 'bi-box-seam', 'route' => 'barang.index', 'active_routes' => ['barang.*'], 'permission' => 'barang.view', 'feature' => 'inventory'],
                    ['label' => 'Penyusutan Aset', 'icon' => 'bi-graph-down', 'route' => 'laporan.penyusutan', 'active_routes' => ['laporan.penyusutan'], 'permission' => 'laporan.penyusutan.view', 'feature' => 'depreciation_reports'],
                    ['label' => 'Laporan Inventaris', 'icon' => 'bi-clipboard-data', 'route' => 'laporan.inventaris', 'active_routes' => ['laporan.inventaris'], 'permission' => 'laporan.inventaris.view', 'feature' => 'inventory_reports'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'administrasi',
                'label' => 'Administrasi',
                'items' => [
                    ['label' => 'Manajemen Pengguna', 'icon' => 'bi-person-gear', 'route' => 'pengguna.index', 'active_routes' => ['pengguna.*'], 'permission' => 'pengguna.view', 'feature' => 'users'],
                    ['label' => 'Role & Hak Akses', 'icon' => 'bi-shield-lock', 'route' => 'role.index', 'active_routes' => ['role.*'], 'permission' => 'role.view', 'feature' => 'roles'],
                    ['label' => 'Pengaturan Aplikasi', 'icon' => 'bi-gear', 'route' => 'pengaturan.edit', 'active_routes' => ['pengaturan.*'], 'permission' => 'pengaturan.view', 'feature' => 'app_settings'],
                    ['label' => 'Manajemen Koperasi', 'icon' => 'bi-building-gear', 'route' => 'koperasi.index', 'active_routes' => ['koperasi.*'], 'permission' => null, 'super_admin_only' => true, 'feature' => 'cooperatives'],
                ],
            ],
        ];
    }

    /**
     * Daftar menu yang benar-benar boleh dilihat $user, sudah dilengkapi
     * status "active" (halaman yang sedang dibuka) untuk tiap item & grup.
     *
     * @return list<array<string, mixed>>
     */
    public static function visibleGroups(User $user): array
    {
        if ($user->isSystemOwner()) {
            return self::markActive($user, self::ownerGroups());
        }

        return self::markActive($user, self::groups());
    }

    /**
     * Navigasi owner hanya memuat control-plane platform. Pengelolaan role
     * ditempatkan di jalur owner tersendiri agar tidak membuka CRUD data
     * operasional tenant kepada identitas system_owner.
     *
     * @return list<array<string, mixed>>
     */
    private static function ownerGroups(): array
    {
        return [
            [
                'type' => 'link',
                'label' => 'Ringkasan Platform',
                'icon' => 'bi-grid-1x2',
                'route' => 'owner.dashboard',
                'active_routes' => ['owner.dashboard'],
                'permission' => null,
            ],
            [
                'type' => 'link',
                'label' => 'Request Produk',
                'icon' => 'bi-inboxes',
                'route' => 'owner.product-requests.index',
                'active_routes' => ['owner.product-requests.*'],
                'permission' => null,
            ],
            [
                'type' => 'group',
                'key' => 'keamanan-akses-platform',
                'label' => 'Keamanan & Akses',
                'items' => [
                    ['label' => 'Role & Hak Akses', 'icon' => 'bi-shield-lock', 'route' => 'owner.roles.index', 'active_routes' => ['owner.roles.*'], 'permission' => null],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'observability-platform',
                'label' => 'Observability',
                'items' => [
                    ['label' => 'Analitik Koperasi', 'icon' => 'bi-graph-up-arrow', 'route' => 'owner.analytics', 'active_routes' => ['owner.analytics', 'owner.analytics.koperasi'], 'permission' => null],
                    ['label' => 'Kesehatan Sistem', 'icon' => 'bi-heart-pulse', 'route' => 'owner.system-health', 'active_routes' => ['owner.system-health'], 'permission' => null],
                    ['label' => 'Penyimpanan', 'icon' => 'bi-device-ssd', 'route' => 'owner.storage', 'active_routes' => ['owner.storage'], 'permission' => null],
                    ['label' => 'Maintenance', 'icon' => 'bi-tools', 'route' => 'owner.maintenance.edit', 'active_routes' => ['owner.maintenance.*'], 'permission' => null],
                    ['label' => 'Akses Fitur', 'icon' => 'bi-toggles', 'route' => 'owner.features.index', 'active_routes' => ['owner.features.*'], 'permission' => null],
                    ['label' => 'Pengumuman', 'icon' => 'bi-megaphone', 'route' => 'owner.announcements.index', 'active_routes' => ['owner.announcements.*'], 'permission' => null],
                ],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $groups */
    private static function markActive(User $user, array $groups): array
    {
        $visible = [];

        foreach ($groups as $group) {
            if ($group['type'] === 'link') {
                if (! self::isVisibleTo($user, $group)) {
                    continue;
                }

                $group['active'] = request()->routeIs(...$group['active_routes']);
                $visible[] = $group;

                continue;
            }

            $items = array_values(array_filter(
                $group['items'],
                fn (array $item) => self::isVisibleTo($user, $item)
            ));

            if (empty($items)) {
                continue;
            }

            $items = array_map(function (array $item) {
                $item['active'] = request()->routeIs(...$item['active_routes']);

                return $item;
            }, $items);

            $activeRoutes = collect($items)->flatMap(fn (array $item) => $item['active_routes'])->all();

            $group['items'] = $items;
            $group['active'] = request()->routeIs(...$activeRoutes);
            $visible[] = $group;
        }

        return $visible;
    }

    /**
     * Item dengan 'super_admin_only' (mis. Manajemen Koperasi) tidak masuk
     * akal digerbang permission biasa — tidak ada role tenant manapun yang
     * seharusnya bisa melihatnya, jadi dicek lewat identitas super_admin
     * langsung, konsisten dengan guard di EnsureIsSuperAdmin.
     */
    private static function isVisibleTo(User $user, array $item): bool
    {
        if (isset($item['feature']) && ! app(PlatformFeatureService::class)->isEnabled($item['feature'])) {
            return false;
        }

        if (($item['tenant_only'] ?? false) && ! $user->isTenantUser()) {
            return false;
        }

        if ($item['super_admin_only'] ?? false) {
            return $user->isSuperAdmin();
        }

        return $item['permission'] === null || $user->can($item['permission']);
    }
}
