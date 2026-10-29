<?php

namespace App\Support;

use App\Models\Barang;

class KelengkapanBarangCalculator
{
    /**
     * Hitung kelengkapan data aset inventaris.
     *
     * @return array{
     *     persentase: int,
     *     is_lengkap: bool,
     *     total_kriteria: int,
     *     total_terpenuhi: int,
     *     checklist: array<int, array{kategori: string, label: string, terpenuhi: bool, keterangan: string}>,
     *     item_belum_lengkap: list<string>,
     *     dokumen_disarankan: list<string>,
     * }
     */
    public static function hitung(Barang $barang): array
    {
        $checklist = [];

        // 1. Data Utama
        $informasiDasarLengkap = filled($barang->kode_barang)
            && filled($barang->nama_barang)
            && filled($barang->kategori)
            && filled($barang->jenis_barang)
            && ! empty($barang->unit_kerja_id)
            && filled($barang->lokasi_penempatan)
            && $barang->tanggal_perolehan !== null
            && (float) $barang->harga_perolehan > 0;

        $checklist[] = [
            'kategori' => 'identitas',
            'label' => 'Informasi Dasar Aset',
            'terpenuhi' => $informasiDasarLengkap,
            'keterangan' => $informasiDasarLengkap ? 'Semua field wajib telah terisi' : 'Field wajib belum lengkap',
        ];

        // 2. Pemeriksaan Kondisi Fisik
        $adaKondisi = $barang->relationLoaded('kondisiTerakhir')
            ? ($barang->kondisiTerakhir !== null)
            : ($barang->kondisiTerakhir()->exists() || $barang->riwayatKondisi()->exists());

        $checklist[] = [
            'kategori' => 'kondisi',
            'label' => 'Pemeriksaan Kondisi Fisik',
            'terpenuhi' => (bool) $adaKondisi,
            'keterangan' => $adaKondisi ? 'Kondisi fisik telah dicatat' : 'Belum pernah dicatat pemeriksaan kondisi',
        ];

        // 3. Foto Fisik Aset (Minimal 1 foto dari foto_sampul atau fotoPendukung)
        $adaFotoSampul = filled($barang->foto_sampul);
        $adaFotoPendukung = $barang->relationLoaded('fotoPendukung')
            ? $barang->fotoPendukung->isNotEmpty()
            : $barang->fotoPendukung()->exists();
        $adaFoto = $adaFotoSampul || $adaFotoPendukung;

        $checklist[] = [
            'kategori' => 'foto',
            'label' => 'Foto Fisik Aset',
            'terpenuhi' => $adaFoto,
            'keterangan' => $adaFoto ? 'Minimal 1 foto aset tersedia' : 'Belum ada foto fisik aset',
        ];

        // 4. Dokumen per Golongan
        $dokumenWajib = config("inventaris.dokumen_wajib_per_golongan.{$barang->kategori}")
            ?? ['Nota Pembelian'];

        $dokumenTersedia = $barang->relationLoaded('dokumen')
            ? $barang->dokumen->pluck('jenis_dokumen')->map('trim')->filter()->all()
            : $barang->dokumen()->pluck('jenis_dokumen')->map('trim')->filter()->all();

        foreach ($dokumenWajib as $jenisWajib) {
            $adaDokumen = collect($dokumenTersedia)->contains(function ($jenis) use ($jenisWajib) {
                return strcasecmp(trim((string) $jenis), trim((string) $jenisWajib)) === 0;
            });

            $checklist[] = [
                'kategori' => 'dokumen',
                'label' => "Dokumen {$jenisWajib}",
                'terpenuhi' => $adaDokumen,
                'keterangan' => $adaDokumen ? "Dokumen {$jenisWajib} telah diunggah" : "Dokumen {$jenisWajib} belum diunggah",
            ];
        }

        $totalKriteria = count($checklist);
        $totalTerpenuhi = collect($checklist)->where('terpenuhi', true)->count();
        $persentase = $totalKriteria > 0 ? (int) round(($totalTerpenuhi / $totalKriteria) * 100) : 100;

        $itemBelumLengkap = collect($checklist)
            ->where('terpenuhi', false)
            ->map(function ($item) {
                return match ($item['kategori']) {
                    'foto' => 'Foto fisik aset',
                    'kondisi' => 'Pencatatan kondisi fisik',
                    'identitas' => 'Informasi dasar aset',
                    default => $item['label'],
                };
            })
            ->values()
            ->all();

        return [
            'persentase' => $persentase,
            'is_lengkap' => $persentase === 100,
            'total_kriteria' => $totalKriteria,
            'total_terpenuhi' => $totalTerpenuhi,
            'checklist' => $checklist,
            'item_belum_lengkap' => $itemBelumLengkap,
            'dokumen_disarankan' => $dokumenWajib,
        ];
    }
}

