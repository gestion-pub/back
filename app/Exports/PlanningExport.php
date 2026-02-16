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
        $campaign = Campagne::findOrFail($this->campaignId);
        $plannings = Planning::where('id_campagne', $this->campaignId)->get();

        // 1. Generate date range
        $dates = [];
        $start = Carbon::parse($campaign->date_debut);
        $end = Carbon::parse($campaign->date_fin);
        $curr = $start->copy();
        while ($curr->lte($end)) {
            $dates[] = $curr->copy();
            $curr->addDay();
        }

        // 2. Generate time slots (deduplicated from plannings)
        $timeSlots = $plannings->map(function ($p) {
            return substr($p->heure, 0, 5); // HH:MM
        })->unique()->sort()->values();

        // If no plannings, default to 07:00 - 18:00
        if ($timeSlots->isEmpty()) {
            for ($h = 7; $h <= 18; $h++) {
                $timeSlots[] = sprintf('%02d:00', $h);
            }
        }

        return view('exports.planning', [
            'campaign' => $campaign,
            'dates' => $dates,
            'timeSlots' => $timeSlots,
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
