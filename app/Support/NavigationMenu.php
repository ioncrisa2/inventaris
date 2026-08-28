<?php

namespace App\Support;

use App\Models\User;

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
                'type' => 'group',
                'key' => 'sdm-kehadiran',
                'label' => 'SDM & Kehadiran',
                'items' => [
                    ['label' => 'Unit Kerja', 'icon' => 'bi-building', 'route' => 'unit-kerja.index', 'active_routes' => ['unit-kerja.*'], 'permission' => 'unit-kerja.view'],
                    ['label' => 'Karyawan', 'icon' => 'bi-people', 'route' => 'karyawan.index', 'active_routes' => ['karyawan.*'], 'permission' => 'karyawan.view'],
                    ['label' => 'Absensi', 'icon' => 'bi-calendar3', 'route' => 'absensi.index', 'active_routes' => ['absensi.*'], 'permission' => 'absensi.view'],
                    ['label' => 'Hari Libur', 'icon' => 'bi-calendar-x', 'route' => 'hari-libur.index', 'active_routes' => ['hari-libur.*'], 'permission' => 'hari-libur.view'],
                    ['label' => 'Laporan Kepegawaian', 'icon' => 'bi-bar-chart-line', 'route' => 'laporan.kepegawaian', 'active_routes' => ['laporan.kepegawaian'], 'permission' => 'laporan.kepegawaian.view'],
                    ['label' => 'Laporan Absensi', 'icon' => 'bi-calendar-check', 'route' => 'laporan.absensi', 'active_routes' => ['laporan.absensi'], 'permission' => 'laporan.absensi.view'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'penggajian',
                'label' => 'Penggajian',
                'items' => [
                    ['label' => 'Komponen Gaji', 'icon' => 'bi-sliders', 'route' => 'komponen-gaji.index', 'active_routes' => ['komponen-gaji.*'], 'permission' => 'komponen-gaji.view'],
                    ['label' => 'Transaksi Gaji', 'icon' => 'bi-cash-stack', 'route' => 'transaksi-gaji.index', 'active_routes' => ['transaksi-gaji.*'], 'permission' => 'transaksi-gaji.view'],
                    ['label' => 'Laporan Penggajian', 'icon' => 'bi-cash-coin', 'route' => 'laporan.penggajian', 'active_routes' => ['laporan.penggajian'], 'permission' => 'laporan.penggajian.view'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'manajemen-aset',
                'label' => 'Manajemen Aset',
                'items' => [
                    ['label' => 'Inventaris Barang', 'icon' => 'bi-box-seam', 'route' => 'barang.index', 'active_routes' => ['barang.*'], 'permission' => 'barang.view'],
                    ['label' => 'Penyusutan Aset', 'icon' => 'bi-graph-down', 'route' => 'laporan.penyusutan', 'active_routes' => ['laporan.penyusutan'], 'permission' => 'laporan.penyusutan.view'],
                    ['label' => 'Laporan Inventaris', 'icon' => 'bi-clipboard-data', 'route' => 'laporan.inventaris', 'active_routes' => ['laporan.inventaris'], 'permission' => 'laporan.inventaris.view'],
                ],
            ],
            [
                'type' => 'group',
                'key' => 'administrasi',
                'label' => 'Administrasi',
                'items' => [
                    ['label' => 'Manajemen Pengguna', 'icon' => 'bi-person-gear', 'route' => 'pengguna.index', 'active_routes' => ['pengguna.*'], 'permission' => 'pengguna.view'],
                    ['label' => 'Role & Hak Akses', 'icon' => 'bi-shield-lock', 'route' => 'role.index', 'active_routes' => ['role.*'], 'permission' => 'role.view'],
                    ['label' => 'Pengaturan Aplikasi', 'icon' => 'bi-gear', 'route' => 'pengaturan.edit', 'active_routes' => ['pengaturan.*'], 'permission' => 'pengaturan.view'],
                    ['label' => 'Manajemen Koperasi', 'icon' => 'bi-building-gear', 'route' => 'koperasi.index', 'active_routes' => ['koperasi.*'], 'permission' => null, 'super_admin_only' => true],
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
     * Navigasi owner sengaja tidak memuat satupun route CRUD operasional.
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
                'type' => 'group',
                'key' => 'observability-platform',
                'label' => 'Observability',
                'items' => [
                    ['label' => 'Analitik Koperasi', 'icon' => 'bi-graph-up-arrow', 'route' => 'owner.analytics', 'active_routes' => ['owner.analytics', 'owner.analytics.koperasi'], 'permission' => null],
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
        if ($item['super_admin_only'] ?? false) {
            return $user->isSuperAdmin();
        }

        return $item['permission'] === null || $user->can($item['permission']);
    }
}
