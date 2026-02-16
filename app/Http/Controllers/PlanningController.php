<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Planning;
use Illuminate\Support\Facades\Http;
use App\Imports\PlanningsImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlanningExport;
use Illuminate\Support\Facades\Log; // Added this use statement for Log

class PlanningController extends Controller
{
    public function uploadAndAnalyze(Request $request)
    {
        set_time_limit(300); // 5 minutes max
        Log::info('Upload Request Data:', $request->all());
        Log::info('Upload Files:', $request->allFiles());

        // 1. Validation élargie
        $request->validate([
            'scan' => 'required|file|mimes:jpg,jpeg,png,pdf,xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        $file = $request->file('scan');
        $extension = strtolower($file->getClientOriginalExtension());

        // --- CAS 1 : EXCEL / CSV (Matrix Parsing) ---
        if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
            try {
                $array = Excel::toArray(new PlanningsImport, $file);
                $rows = $array[0];

                $grouped = [];

                // Defaults
                $detectedMonth = date('n');
                $detectedYear = date('Y');

                // Optional: Try to scan for year in first few rows just in case, but don't fail if missing
                for ($r = 0; $r < 5 && $r < count($rows); $r++) {
                    if (!isset($rows[$r]))
                        continue;
                    foreach ($rows[$r] as $cell) {
                        if (is_string($cell) && preg_match('/20\d{2}/', $cell, $matches)) {
                            $detectedYear = (int) $matches[0];
                        }
                    }
                }

                // 2. Find "Day Numbers" Row
                // Look for the row that contains the most integers between 1 and 31
                $dayRowIndex = -1;
                $maxIntegersFound = 0;

                foreach ($rows as $rowIndex => $row) {
                    $integersCount = 0;
                    foreach ($row as $cell) {
                        // Loose check for numbers (string or int)
                        if (is_numeric($cell) && $cell >= 1 && $cell <= 31) {
                            $integersCount++;
                        }
                    }
                    if ($integersCount > 5 && $integersCount > $maxIntegersFound) {
                        $maxIntegersFound = $integersCount;
                        $dayRowIndex = $rowIndex;
                    }
                }

                if ($dayRowIndex === -1) {
                    return response()->json(['status' => 'error', 'message' => 'Structure du fichier non reconnue (Ligne des jours introuvable)'], 422);
                }

                // 3. Parse Matrix & Resolve Dates
                $campaignStartDate = $request->input('start_date');
                $campaignEndDate = $request->input('end_date');

                for ($c = 0; $c < count($rows[$dayRowIndex]); $c++) {
                    $dayNum = $rows[$dayRowIndex][$c];

                    if (is_numeric($dayNum) && $dayNum >= 1 && $dayNum <= 31) {
                        try {
                            $resolvedDate = null;

                            // Logic A: Use Campaign Context
                            if ($campaignStartDate && $campaignEndDate) {
                                // Look for Weekday in row above (rowIndex - 1)
                                $weekdayName = null;
                                if ($dayRowIndex > 0 && isset($rows[$dayRowIndex - 1][$c]) && is_string($rows[$dayRowIndex - 1][$c])) {
                                    $weekdayName = strtolower(trim($rows[$dayRowIndex - 1][$c]));
                                }

                                $start = \Carbon\Carbon::parse($campaignStartDate);
                                $end = \Carbon\Carbon::parse($campaignEndDate);
                                $curr = $start->copy();

                                // Iterate through days in range
                                while ($curr->lte($end)) {
                                    if ($curr->day == $dayNum) {
                                        if ($weekdayName) {
                                            // French mapping
                                            $frWeekdays = ['lun' => 1, 'mar' => 2, 'mer' => 3, 'jeu' => 4, 'ven' => 5, 'sam' => 6, 'dim' => 0];
                                            $colWk = -1;
                                            foreach ($frWeekdays as $k => $v) {
                                                if (str_contains($weekdayName, $k)) {
                                                    $colWk = $v;
                                                    break;
                                                }
                                            }

                                            // 0 = Sunday in Carbon dayOfWeek
                                            if ($colWk !== -1 && $curr->dayOfWeek === $colWk) {
                                                $resolvedDate = $curr->format('Y-m-d');
                                                break;
                                            }
                                        } else {
                                            // No weekday found, ambiguity possible. Take first valid day in range?
                                            // Or try to match month if detectedYear/Month matches?
                                            if (!$resolvedDate)
                                                $resolvedDate = $curr->format('Y-m-d');
                                        }
                                    }
                                    $curr->addDay();
                                }
                            }

                            // Logic B: Default (Header detection or current)
                            if (!$resolvedDate) {
                                $resolvedDate = sprintf('%04d-%02d-%02d', $detectedYear, $detectedMonth, (int) $dayNum);
                            }

                            $dateStr = $resolvedDate;

                            // Iterate rows below the day row to find Times
                            for ($r = $dayRowIndex + 1; $r < count($rows); $r++) {
                                // Check Time Column (Column 0 usually)
                                $timeVal = $rows[$r][0] ?? null;

                                $formattedTime = null;
                                if (is_numeric($timeVal)) {
                                    // Excel time fraction
                                    $totalMinutes = round($timeVal * 24 * 60);
                                    $hours = floor($totalMinutes / 60);
                                    $mins = $totalMinutes % 60;
                                    $formattedTime = sprintf('%02d:%02d', $hours, $mins);
                                } elseif (is_string($timeVal) && preg_match('/^\d{1,2}:\d{2}/', $timeVal)) {
                                    // simple cleanup
                                    $formattedTime = substr($timeVal, 0, 5);
                                    if (strlen($formattedTime) === 4)
                                        $formattedTime = '0' . $formattedTime;
                                }

                                // Only process if we found a valid time in column 0 AND the cell has content
                                if ($formattedTime) {
                                    // Check cell value
                                    $cellValue = $rows[$r][$c] ?? null;
                                    if (!empty($cellValue)) {
                                        // Found a slot

                                        // Add to grouped
                                        $foundIndex = -1;
                                        foreach ($grouped as $gIndex => $item) {
                                            if ($item['date'] === $dateStr) {
                                                $foundIndex = $gIndex;
                                                break;
                                            }
                                        }

                                        if ($foundIndex >= 0) {
                                            if (!in_array($formattedTime, $grouped[$foundIndex]['hours'])) {
                                                $grouped[$foundIndex]['hours'][] = $formattedTime;
                                            }
                                        } else {
                                            $grouped[] = [
                                                'date' => $dateStr,
                                                'hours' => [$formattedTime]
                                            ];
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                        }
                    }
                }

                usort($grouped, function ($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });

                return response()->json([
                    'status' => 'success',
                    'mode' => 'excel_matrix_import',
                    'message' => "Importation réussie (" . count($grouped) . " jours trouvés)",
                    'data' => $grouped
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Erreur lors de la lecture du fichier Excel: ' . $e->getMessage()
                ], 500);
            }
        }

        // --- CAS 2 : PDF / IMAGES (Lecture par IA) ---
        else {
            $apiUrl = env('PYTHON_API_URL') . '/analyser';
            try {
                $response = Http::timeout(300) // Increase internal timeout to 5 mins
                    ->attach('file', file_get_contents($file), $file->getClientOriginalName())
                    ->post($apiUrl);

                $aiData = $response->json();
                Log::info('AI Response Payload Summary:', [
                    'status' => $response->status(),
                    'keys' => array_keys($aiData ?? []),
                    'has_resultat_formate' => isset($aiData['resultat_formate']),
                    'has_extracted_data' => isset($aiData['extracted_data'])
                ]);

                // ALWAYS LOG RAW RESPONSE FOR DEBUGGING
                Log::debug('RAW AI PAYLOAD: ' . ($response->body() ?: 'EMPTY BODY'));

                if (!$response->successful() || !$aiData) {
                    throw new \Exception("Le service d'analyse IA n'a pas renvoyé de réponse valide (Status: " . $response->status() . ").");
                }

                if (!isset($aiData['extracted_data'])) {
                    foreach ($aiData as $key => $value) {
                        if (!is_string($value))
                            continue;

                        // Case A: JSON (Markdown or Raw)
                        if (strpos($value, '[{') !== false || strpos($value, '```json') !== false) {
                            Log::info("Attempting to parse JSON from AI field: $key");
                            $jsonStr = $value;

                            if (preg_match('/```json\s*(.*)/s', $jsonStr, $matches)) {
                                $jsonStr = $matches[1];
                                if (($pos = strpos($jsonStr, '```')) !== false) {
                                    $jsonStr = substr($jsonStr, 0, $pos);
                                }
                            } elseif (preg_match('/\[\s*\{.*/s', $jsonStr, $matches)) {
                                $jsonStr = $matches[0];
                            }

                            $jsonStr = trim($jsonStr);
                            $parsed = json_decode($jsonStr, true);

                            if (!$parsed) {
                                $repairedJson = $this->repairJson($jsonStr);
                                if ($repairedJson)
                                    $parsed = json_decode($repairedJson, true);
                            }

                            if ($parsed) {
                                $aiData['extracted_data'] = $parsed;
                                break;
                            }
                        }

                        // Case B: Pipe format (|) - Ultra compact for small models
                        if (strpos($value, '|') !== false) {
                            Log::info("Attempting to parse Pipe format (|) from AI field: $key");
                            $lines = explode("\n", $value);
                            $pipeData = [];
                            foreach ($lines as $line) {
                                if (strpos($line, '|') !== false) {
                                    $parts = explode('|', $line);
                                    if (count($parts) >= 2) {
                                        $pipeData[] = [
                                            'd' => trim($parts[0]),
                                            'h' => trim($parts[1])
                                        ];
                                    }
                                }
                            }
                            if (count($pipeData) > 0) {
                                Log::info("Parsed " . count($pipeData) . " items from Pipe format");
                                $aiData['extracted_data'] = $pipeData;
                                break;
                            }
                        }
                    }
                }

                if (!isset($aiData['extracted_data'])) {
                    $aiData['extracted_data'] = $aiData['resultat_formate'] ?? ($aiData['resultat'] ?? ($aiData['data'] ?? ($aiData['results'] ?? [])));
                }

                $grouped = [];

                // --- NEW: TEXT-BASED TRANSCRIPTION PARSING ---
                // If the AI returns a raw string or array of strings (e.g., ["Date | h1, h2"])
                $rawReport = $aiData['resultat_formate'] ?? ($aiData['resultat'] ?? ($aiData['data'] ?? ($aiData['results'] ?? null)));

                if ($rawReport) {
                    Log::info('Parsing text-based AI report (String or Array)');
                    $reportLines = [];
                    if (is_array($rawReport)) {
                        $reportLines = $rawReport;
                    } elseif (is_string($rawReport)) {
                        $reportLines = explode("\n", $rawReport);
                    }

                    foreach ($reportLines as $line) {
                        if (!is_string($line))
                            continue;

                        // New Format: "Date | h1, h2, h3"
                        if (strpos($line, '|') !== false) {
                            $this->processJsonEntry($line, $grouped, $request);
                            continue;
                        }

                        // Regex to find "HH:MM : LIST OF DAYS"
                        if (preg_match('/^(\d{1,2}:\d{2})\s*[:\-]\s*(.*)$/i', trim($line), $matches)) {
                            $hour = trim($matches[1]);
                            $daysStr = trim($matches[2]);

                            if (strtolower($daysStr) === 'aucun' || strtolower($daysStr) === 'none') {
                                continue;
                            }

                            $days = explode(',', $daysStr);
                            foreach ($days as $rawDay) {
                                $rawDay = trim($rawDay);
                                if (empty($rawDay))
                                    continue;

                                $resolvedDate = $this->resolveDate($rawDay, $request->input('start_date'), $request->input('end_date'));
                                if ($resolvedDate) {
                                    $this->groupHours($grouped, $resolvedDate, [$hour]);
                                }
                            }
                        }
                    }
                }

                // --- NEW: DAY-KEYED JSON PARSING (e.g., {"Ven 20": ["07:30", "08:00"]}) ---
                if (empty($grouped) && is_array($aiData['resultat_formate'] ?? ($aiData['resultat'] ?? ($aiData['data'] ?? [])))) {
                    $potentialKeyed = $aiData['resultat_formate'] ?? ($aiData['resultat'] ?? ($aiData['data'] ?? []));

                    // Check if it's an associative array (object in JSON) where values are arrays
                    $isKeyed = false;
                    foreach ($potentialKeyed as $key => $value) {
                        if (is_string($key) && is_array($value)) {
                            $isKeyed = true;
                            break;
                        }
                    }

                    if ($isKeyed) {
                        Log::info('Parsing day-keyed JSON map');
                        foreach ($potentialKeyed as $dayLabel => $hours) {
                            $resolvedDate = $this->resolveDate($dayLabel, $request->input('start_date'), $request->input('end_date'));
                            if ($resolvedDate && is_array($hours)) {
                                $this->groupHours($grouped, $resolvedDate, $hours);
                            }
                        }
                    }
                }

                // --- EXISTING: JSON-BASED PARSING (as fallback) ---
                if (empty($grouped) && is_array($aiData['extracted_data'] ?? null)) {
                    Log::info('Processing ' . count($aiData['extracted_data']) . ' JSON items from AI');
                    foreach ($aiData['extracted_data'] as $entry) {
                        $this->processJsonEntry($entry, $grouped, $request);
                    }
                }

                if (empty($grouped)) {
                    Log::warning('AI Analysis finished with 0 extracted dates. This might mean the image contains no planning data or the AI failed to detect it. AI Data: ' . json_encode($aiData));
                }

                Log::info('Final grouped results: ' . count($grouped) . ' dates');

                // Sort by date
                usort($grouped, function ($a, $b) {
                    return strcmp($a['date'], $b['date']);
                });

                return response()->json([
                    'status' => 'success',
                    'mode' => 'ai_ocr',
                    'data' => $grouped
                ]);
            } catch (\Exception $e) {
                Log::error('AI Analysis Error: ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }
    }

    private function groupHours(&$grouped, $resolvedDate, $hours)
    {
        Log::info("Grouping " . count($hours) . " hours for date: $resolvedDate");

        foreach ($hours as $hour) {
            if (empty($hour))
                continue;

            // Normalize time format to HH:mm (e.g. "7:30" -> "07:30")
            if (preg_match('/^(\d{1,2})[:h](\d{2})/', trim($hour), $matches)) {
                $hour = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
            } else {
                continue; // Skip invalid time formats
            }

            $found = false;
            foreach ($grouped as &$g) {
                if ($g['date'] === $resolvedDate) {
                    if (!in_array($hour, $g['hours'])) {
                        $g['hours'][] = $hour;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $grouped[] = [
                    'date' => $resolvedDate,
                    'hours' => [$hour]
                ];
            }
        }
    }

    private function processJsonEntry($entry, &$grouped, $request)
    {
        $rawDates = [];
        $hours = [];

        if (is_string($entry)) {
            if (strpos($entry, '|') !== false) {
                $parts = explode('|', $entry);
                $rawDates[] = trim($parts[0]);
                if (isset($parts[1])) {
                    $hoursList = explode(',', $parts[1]);
                    foreach ($hoursList as $h) {
                        $hours[] = trim($h);
                    }
                }
            } else {
                $rawDates[] = trim($entry);
            }
        } elseif (is_array($entry)) {
            $rawDate = null;
            if (isset($entry['jour']) && isset($entry['numero'])) {
                $rawDate = trim($entry['jour'] . ' ' . $entry['numero']);
            }
            if (!$rawDate) {
                $rawDate = $entry['colonne'] ?? ($entry['d'] ?? ($entry['date'] ?? ($entry['day'] ?? ($entry['jour'] ?? ''))));
            }
            $rawHour = $entry['heure'] ?? ($entry['heures'] ?? ($entry['h'] ?? ($entry['hour'] ?? ($entry['time'] ?? ''))));

            if (isset($entry['jours']) && is_array($entry['jours'])) {
                $rawDates = $entry['jours'];
            } elseif (isset($entry['dates']) && is_array($entry['dates'])) {
                $rawDates = $entry['dates'];
            } elseif ($rawDate) {
                $rawDates = [$rawDate];
            }

            if (is_array($rawHour)) {
                $hours = $rawHour;
            } elseif ($rawHour) {
                $hours = [$rawHour];
            }
        }

        foreach ($rawDates as $d) {
            $resolvedDate = $this->resolveDate($d, $request->input('start_date'), $request->input('end_date'));
            Log::info("Resolved '$d' to '$resolvedDate' with " . count($hours) . " hours");
            if ($resolvedDate && !empty($hours)) {
                $this->groupHours($grouped, $resolvedDate, $hours);
            }
        }
    }

    private function resolveDate($rawDate, $startDate = null, $endDate = null)
    {
        if (empty($rawDate))
            return null;

        // Cleanup: remove noise and normalize
        $rawDate = trim($rawDate);
        $rawDateLower = strtolower($rawDate);

        Log::debug("Attempting to resolve date: '$rawDate'");

        // 1. If it's already an ISO date, return it
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate))
            return $rawDate;

        // 1b. Handle DD/MM/YYYY or D/M/YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $rawDate, $matches)) {
            $d = (int) $matches[1];
            $m = (int) $matches[2];
            $y = (int) $matches[3];
            if ($y < 100)
                $y += 2000;
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }

        // 2. Resolve 'today' or 'tomorrow'
        if ($rawDateLower === 'aujourd\'hui' || $rawDateLower === 'aujourdhui')
            return date('Y-m-d');
        if ($rawDateLower === 'demain')
            return date('Y-m-d', strtotime('+1 day'));

        // 2b. French Month mapping
        $frMonths = [
            'janv' => 1,
            'janvier' => 1,
            'févr' => 2,
            'fevr' => 2,
            'février' => 2,
            'fevrier' => 2,
            'fév' => 2,
            'fev' => 2,
            'mars' => 3,
            'mar' => 3,
            'avr' => 4,
            'avril' => 4,
            'mai' => 5,
            'juin' => 6,
            'juil' => 7,
            'juillet' => 7,
            'août' => 8,
            'aout' => 8,
            'sept' => 9,
            'oct' => 10,
            'nov' => 11,
            'déc' => 12,
            'dec' => 12,
            'décembre' => 12,
            'decembre' => 12
        ];

        $detectedMonth = null;
        foreach ($frMonths as $name => $num) {
            if (stripos($rawDateLower, $name) !== false) {
                $detectedMonth = $num;
                break;
            }
        }

        // 3. Extract day number (most reliable part of "Ven 20")
        $dayNum = null;
        if (preg_match('/(\d{1,2})/', $rawDate, $matches)) {
            $dayNum = (int) $matches[1];
        }

        if (!$dayNum) {
            Log::warning("Could not extract day number from '$rawDate'");
            return null;
        }

        // 4. Extract weekday
        $weekdayName = null;
        $frWeekdays = [
            'lun' => 1,
            'mon' => 1,
            'lundi' => 1,
            'mar' => 2,
            'tue' => 2,
            'mardi' => 2,
            'mer' => 3,
            'wed' => 3,
            'mercredi' => 3,
            'jeu' => 4,
            'thu' => 4,
            'jeudi' => 4,
            'ven' => 5,
            'fri' => 5,
            'vendredi' => 5,
            'sam' => 6,
            'sat' => 6,
            'samedi' => 6,
            'dim' => 0,
            'sun' => 0,
            'dimanche' => 0
        ];

        foreach ($frWeekdays as $name => $val) {
            if (stripos($rawDateLower, $name) !== false) {
                $weekdayName = $name;
                break;
            }
        }

        // 5. Resolve using campaign context (Year/Month)
        if ($startDate && $endDate) {
            try {
                $start = \Carbon\Carbon::parse($startDate);
                $end = \Carbon\Carbon::parse($endDate);
                $curr = $start->copy();

                // Increase limit to prevent infinite loops (though lte handles it)
                $maxIter = 1000;
                while ($curr->lte($end) && $maxIter-- > 0) {
                    if ($curr->day == $dayNum) {
                        if ($weekdayName) {
                            $targetWk = $frWeekdays[$weekdayName];
                            if ($curr->dayOfWeek === $targetWk) {
                                Log::info("Resolved '$rawDate' to '{$curr->format('Y-m-d')}' using campaign range and weekday match.");
                                return $curr->format('Y-m-d');
                            }
                        } else {
                            Log::info("Resolved '$rawDate' to '{$curr->format('Y-m-d')}' using campaign range (no weekday match).");
                            return $curr->format('Y-m-d');
                        }
                    }
                    $curr->addDay();
                }
            } catch (\Exception $e) {
                Log::error("Error resolving date with context: " . $e->getMessage());
            }
        }

        // 6. Fallback: use campaign start date year/month, or detected month
        $y = date('Y');
        $m = $detectedMonth ?: date('n');

        if ($startDate) {
            try {
                $sDate = \Carbon\Carbon::parse($startDate);
                $y = $sDate->year;
                if (!$detectedMonth) {
                    $m = $sDate->month;

                    // MONTH ROLLOVER HEURISTIC: 如果 dayNum 比 startDate 的 day 小很多，大概率是下个月
                    if ($dayNum < $sDate->day - 10) {
                        $temp = $sDate->copy()->addMonth();
                        $y = $temp->year;
                        $m = $temp->month;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        $resolved = sprintf('%04d-%02d-%02d', $y, $m, $dayNum);
        Log::info("Fallback resolution for '$rawDate' (detected month: " . ($detectedMonth ?: 'None') . "): $resolved");
        return $resolved;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Planning::with(['campagne.client', 'campagne.categorie'])->latest();

        if ($request->has('all')) {
            $plannings = $query->get();
            return response()->json(['data' => $plannings]);
        }

        return response()->json($query->paginate(15));
    }

    public function getByCampaign($campaignId)
    {
        $plannings = Planning::where('id_campagne', $campaignId)->latest()->get();
        return response()->json(['data' => $plannings]);
    }

    public function export($campaignId)
    {
        try {
            return Excel::download(new PlanningExport($campaignId), 'planning.xlsx');
        } catch (\Exception $e) {
            Log::error('Export Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'heure' => 'required|date_format:H:i',
            'spot' => 'required|string|max:255',
            'duree' => 'required|integer|min:1',
            'prix_HT' => 'required|numeric|min:0',
            'id_campagne' => 'required|exists:campagnes,id',
        ]);

        $planning = Planning::create($validated);

        return response()->json($planning, 201);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'id_campagne' => 'required|exists:campagnes,id',
            'plannings' => 'required|array',
            'plannings.*.date' => 'required|date',
            'plannings.*.heure' => 'required|date_format:H:i',
            'plannings.*.spot' => 'required|string|max:255',
            'plannings.*.duree' => 'required|integer|min:1',
            'plannings.*.prix_HT' => 'required|numeric',
            'plannings.*.status' => 'sometimes|in:réservé,programmé',
        ]);

        return \DB::transaction(function () use ($validated) {
            // Delete existing plannings for this campaign
            Planning::where('id_campagne', $validated['id_campagne'])->delete();

            $created = [];
            foreach ($validated['plannings'] as $item) {
                $created[] = Planning::create(array_merge($item, [
                    'id_campagne' => $validated['id_campagne']
                ]));
            }

            return response()->json([
                'status' => 'success',
                'message' => count($created) . ' plannings synchronisés.',
                'data' => $created
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return response()->json(Planning::findOrFail($id));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $planning = Planning::findOrFail($id);

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'heure' => 'sometimes|date_format:H:i',
            'spot' => 'sometimes|string|max:255',
            'duree' => 'sometimes|integer|min:1',
            'prix_HT' => 'sometimes|numeric|min:0',
            'id_campagne' => 'sometimes|exists:campagnes,id',
            'status' => 'sometimes|in:réservé,programmé',
        ]);

        $planning->update($validated);

        return response()->json($planning);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $planning = Planning::findOrFail($id);
        $planning->delete();

        return response()->noContent();
    }

    /**
     * Attempts to repair truncated JSON string by cutting at last valid object
     * and closing open brackets.
     */
    private function repairJson($jsonStr)
    {
        $jsonStr = trim($jsonStr);
        if (empty($jsonStr))
            return null;

        // 1. Find the last complete object closing brace '}'
        $lastBrace = strrpos($jsonStr, '}');
        if ($lastBrace === false)
            return null;

        // 2. Cut string up to that brace
        $repaired = substr($jsonStr, 0, $lastBrace + 1);

        // 3. Count opening vs closing for levels
        $openBrackets = substr_count($repaired, '[');
        $closeBrackets = substr_count($repaired, ']');

        // If we are inside an array, close it
        if ($openBrackets > $closeBrackets) {
            $repaired .= ']';
        }

        // Add final wrap if missing (though usually handled by brackets)
        Log::debug("Repaired JSON string: " . substr($repaired, -100));

        return $repaired;
    }
}
