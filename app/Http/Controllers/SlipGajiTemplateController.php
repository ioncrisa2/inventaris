<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pengaturan\UpdateSlipGajiTemplateRequest;
use App\Services\SlipGajiTemplateService;
use App\Support\SlipGajiTemplateSchema;
use Illuminate\Http\Request;

class SlipGajiTemplateController extends Controller
{
    public function __construct(private SlipGajiTemplateService $templateService) {}

    public function edit(Request $request)
    {
        abort_unless($request->user()->can('pengaturan.view'), 403);

        return view('pengaturan.slip-gaji.edit', [
            'templateState' => $this->templateService->editorState(),
            'defaultConfiguration' => SlipGajiTemplateSchema::default(),
            'fontFamilies' => SlipGajiTemplateSchema::FONT_FAMILIES,
            'colors' => SlipGajiTemplateSchema::COLORS,
            'canUpdate' => $request->user()->can('pengaturan.update'),
        ]);
    }

    public function saveDraft(UpdateSlipGajiTemplateRequest $request)
    {
        $this->templateService->saveDraft(
            $request->configuration(),
            $request->expectedRevision(),
            $request->user(),
        );

        return redirect()->route('pengaturan.slip-gaji.edit')
            ->with('success', 'Draf format slip gaji berhasil disimpan.');
    }

    public function publish(UpdateSlipGajiTemplateRequest $request)
    {
        $this->templateService->publish(
            $request->configuration(),
            $request->expectedRevision(),
            $request->user(),
        );

        return redirect()->route('pengaturan.slip-gaji.edit')
            ->with('success', 'Format slip gaji berhasil diterbitkan dan mulai dipakai saat mencetak.');
    }
}
