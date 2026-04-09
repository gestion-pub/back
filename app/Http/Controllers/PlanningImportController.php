<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class PlanningImportController extends Controller
{
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headerRow = -1;
            $timeColumn = -1;
            $dateColumn = -1;
            
            // 1. Find the "Horaire" header
            for ($row = 1; $row <= 30; $row++) {
                for ($col = 1; $col <= 20; $col++) {
                    $cellValue = trim((string)$worksheet->getCellByColumnAndRow($col, $row)->getValue());
                    if (stripos($cellValue, 'Horaire') !== false || stripos($cellValue, 'Heure') !== false) {
                        $headerRow = $row;
                        $timeColumn = $col;
                    }
                    if (stripos($cellValue, 'Date') !== false) {
                        $dateColumn = $col;
                    }
                    if ($headerRow !== -1 && $dateColumn !== -1) break 2;
                }
            }

            if ($headerRow === -1) {
                return response()->json(['error' => 'En-tête "Horaire" introuvable dans le fichier.'], 422);
            }

            // 2. Identify day columns (columns to the right of "Horaire")
            $dayColumns = [];
            $frWeekdays = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
            
            for ($col = $timeColumn + 1; $col <= $highestColumnIndex; $col++) {
                $headerValue = trim((string)$worksheet->getCellByColumnAndRow($col, $headerRow)->getValue());
                $aboveValue = $headerRow > 1 ? trim((string)$worksheet->getCellByColumnAndRow($col, $headerRow - 1)->getValue()) : '';
                
                // Stop at "N Pass" or similar total column
                if (stripos($headerValue, 'N Pass') !== false || stripos($headerValue, 'Total') !== false) {
                    break;
                }

                // Try to resolve a day label
                $dayLabel = 'jour_' . ($col - $timeColumn);
                foreach ($frWeekdays as $wk) {
                    if (stripos($aboveValue, $wk) !== false || stripos($headerValue, $wk) !== false) {
                        $dayLabel = $wk;
                        break;
                    }
                }
                
                $dayColumns[$col] = $dayLabel;
            }

            if (empty($dayColumns)) {
                return response()->json(['error' => 'Aucune colonne de jour détectée.'], 422);
            }

            $allDetections = [];
            $allDates = [];
            
            // 3. Parse rows below header
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $horaire = trim((string)$worksheet->getCellByColumnAndRow($timeColumn, $row)->getValue());
                if (empty($horaire)) continue;

                // Handle Excel numeric time
                if (is_numeric($horaire) && $horaire < 1) {
                    $totalSeconds = (int)round($horaire * 86400);
                    $hours = floor($totalSeconds / 3600);
                    $minutes = floor(($totalSeconds % 3600) / 60);
                    $horaire = sprintf('%02dH%02d', $hours, $minutes);
                }

                foreach ($dayColumns as $colIndex => $dayLabel) {
                    $cell = $worksheet->getCellByColumnAndRow($colIndex, $row);
                    $cellValue = trim((string)$cell->getValue());
                    
                    $fill = $cell->getStyle()->getFill();
                    $rgb = $fill->getStartColor()->getRGB();
                    
                    // Skip empty/white cells with no content
                    if ((empty($rgb) || in_array(strtoupper($rgb), ['000000', 'FFFFFF', 'FFFFFFFF'])) && empty($cellValue)) {
                        continue;
                    }

                    $colorHex = "#" . (empty($rgb) ? 'FFFFFF' : $rgb);
                    
                    // Enhanced duration detection from name (e.g., "10WMAJ1" -> 10)
                    $duration = 0;
                    if (is_numeric($cellValue)) {
                        $duration = (int)$cellValue;
                    } elseif (preg_match('/^(\d+)/', $cellValue, $matches)) {
                        $duration = (int)$matches[1];
                    }

                    // Extract date if column exists
                    $cellDate = '';
                    if ($dateColumn !== -1) {
                        $dateVal = $worksheet->getCellByColumnAndRow($dateColumn, $row)->getValue();
                        if (!empty($dateVal)) {
                           if (is_numeric($dateVal)) {
                               $cellDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal)->format('Y-m-d');
                           } else {
                               try {
                                   $cellDate = \Carbon\Carbon::parse($dateVal)->format('Y-m-d');
                               } catch (\Exception $e) {}
                           }
                           if ($cellDate) $allDates[] = $cellDate;
                        }
                    }

                    $allDetections[] = [
                        'time' => $horaire,
                        'duration' => $duration,
                        'color' => $colorHex,
                        'day' => $dayLabel,
                        'spot_name' => $cellValue
                    ];
                }
            }

            // Deduplicate for the "Spots" cards
            $spotsMap = [];
            foreach ($allDetections as $det) {
                $key = $det['color'] . '-' . $det['spot_name'];
                if (!isset($spotsMap[$key])) {
                    $spotsMap[$key] = [
                        'name' => $det['spot_name'], 
                        'color' => $det['color'],
                        'duration' => $det['duration'],
                        'value' => '', 
                        'id' => '' 
                    ];
                }
            }

            $dateDebut = !empty($allDates) ? min($allDates) : null;
            $dateFin = !empty($allDates) ? max($allDates) : null;

            return response()->json([
                'detections' => $allDetections,
                'spots' => array_values($spotsMap),
                'count' => count($allDetections),
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
