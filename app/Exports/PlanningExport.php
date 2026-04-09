<?php

namespace App\Exports;

use App\Models\Planning;
use App\Models\Campagne;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class PlanningExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function view(): View
    {
        $campaign = Campagne::with(['client', 'creator'])->findOrFail($this->campaignId);
        $plannings = Planning::where('id_campagne', $this->campaignId)
            ->orderBy('date')
            ->orderBy('heure')
            ->get();

        return view('exports.planning', [
            'campaign' => $campaign,
            'plannings' => $plannings
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row
            1 => ['font' => ['bold' => true]],
        ];
    }
}
