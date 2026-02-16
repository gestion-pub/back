<?php

namespace App\Exports;

use App\Models\Conducteur;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class ConducteurExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function view(): View
    {
        $conducteur = Conducteur::with('slots.campagne.client')->findOrFail($this->id);

        // Generate time slots (same logic as frontend)
        $timeSlots = [];
        for ($hour = 6; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 15) {
                $timeSlots[] = sprintf('%02d:%02d', $hour, $minute);
            }
        }
        $timeSlots[] = '00:00';

        // Map slots to a quick lookup array (grouped by time)
        $schedule = [];
        foreach ($conducteur->slots as $slot) {
            $time = substr($slot->time_slot, 0, 5);
            if (!isset($schedule[$time])) {
                $schedule[$time] = [];
            }
            $schedule[$time][] = [
                'annonceur' => $slot->campagne->client->name ?? $slot->campagne->spot ?? '',
                'spot' => $slot->campagne->spot ?? '',
                'duree' => $slot->campagne->duree ?? '',
                'numero' => $slot->campagne->spot_id ?? '',
            ];
        }

        return view('exports.conducteur', [
            'conducteur' => $conducteur,
            'timeSlots' => $timeSlots,
            'schedule' => $schedule,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
