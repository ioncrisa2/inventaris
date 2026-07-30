<?php

namespace App\Services;

use App\Models\TemplateSlipGaji;
use App\Models\User;
use App\Support\SlipGajiTemplateSchema;
use Illuminate\Support\Facades\DB;

class SlipGajiTemplateService
{
    public function editorState(): array
    {
        $template = TemplateSlipGaji::query()->with(['pengubahDraf', 'penerbit'])->first();
        $configuration = $template?->konfigurasi_draf;

        return [
            'configuration' => is_array($configuration)
                ? SlipGajiTemplateSchema::normalize($configuration)
                : SlipGajiTemplateSchema::default(),
            'draft_revision' => $template?->revisi_draf ?? 1,
            'published_revision' => $template?->revisi_terbit,
            'published_at' => $template?->diterbitkan_pada,
            'draft_author' => $template?->pengubahDraf?->name,
            'publisher' => $template?->penerbit?->name,
        ];
    }

    public function publishedConfiguration(): array
    {
        $configuration = TemplateSlipGaji::query()->first()?->konfigurasi_terbit;

        return is_array($configuration)
            ? SlipGajiTemplateSchema::normalize($configuration)
            : SlipGajiTemplateSchema::default();
    }

    public function publishedPaperLayout(): string
    {
        return $this->publishedConfiguration()['page']['paper_layout'];
    }

    public function saveDraft(array $configuration, int $expectedRevision, User $user): TemplateSlipGaji
    {
        return $this->persist($configuration, $expectedRevision, $user, false);
    }

    public function publish(array $configuration, int $expectedRevision, User $user): TemplateSlipGaji
    {
        return $this->persist($configuration, $expectedRevision, $user, true);
    }

    private function persist(
        array $configuration,
        int $expectedRevision,
        User $user,
        bool $publish,
    ): TemplateSlipGaji {
        $normalized = SlipGajiTemplateSchema::normalize($configuration);

        return DB::transaction(function () use ($normalized, $expectedRevision, $user, $publish) {
            $template = TemplateSlipGaji::query()->lockForUpdate()->first();
            $currentRevision = $template?->revisi_draf ?? 1;

            abort_if(
                $currentRevision !== $expectedRevision,
                409,
                'Template telah diubah di tab lain. Muat ulang halaman sebelum menyimpan.',
            );

            $template ??= new TemplateSlipGaji([
                'nama' => 'Template Slip Gaji',
                'konfigurasi_draf' => SlipGajiTemplateSchema::default(),
                'revisi_draf' => 1,
            ]);

            $nextRevision = $currentRevision + 1;
            $template->konfigurasi_draf = $normalized;
            $template->revisi_draf = $nextRevision;
            $template->draf_diubah_oleh = $user->id;

            if ($publish) {
                $template->konfigurasi_terbit = $normalized;
                $template->revisi_terbit = $nextRevision;
                $template->diterbitkan_oleh = $user->id;
                $template->diterbitkan_pada = now();
            }

            $template->save();

            return $template;
        }, 3);
    }
}
