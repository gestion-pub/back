<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Planning;
use App\Models\Client;
use App\Models\Campagne;
use Carbon\Carbon;

class ImportController extends Controller
{
    /**
     * Extract spreadsheet ID from a Google Sheets URL.
     */
    private function extractSheetId(string $url): string
    {
        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches);
        if (!isset($matches[1])) {
            throw new \InvalidArgumentException('Lien Google Sheets invalide.');
        }
        return $matches[1];
    }

    public function importerDepuisLienPrive(Request $request)
    {
        set_time_limit(0); // Prevent timeout for large files
        ini_set('memory_limit', '512M');
        try {
            $url = $request->input('url');
            if (empty($url)) {
                return response()->json(['erreur' => 'Lien Google Sheets manquant.'], 400);
            }

            $sheetId     = $this->extractSheetId($url);
            $analyzeOnly = $request->input('analyze', false);
            
            // ── SOLUTION SANS AUTHENTIFICATION (CSV PUBLIC EXPORT) ──
            // On télécharge la feuille au format CSV, ce qui fonctionne sans clé d'API
            // ni compte de service, à condition que le lien soit partagé en "Lecteur public".
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv";
            $response = Http::withoutVerifying()->get($csvUrl);

            if (!$response->ok() || str_contains($response->body(), '<html')) {
                return response()->json([
                    'erreur' => 'Impossible de lire le Google Sheet. Assurez-vous que le document est partagé avec "Toute personne ayant le lien" en tant que "Lecteur". (Aucun fichier JSON n\'est nécessaire !)'
                ], 403);
            }

            // Parser le CSV
            $csvData = $response->body();
            $lignes = [];
            
            // Handle different line endings (CRLF, LF, CR)
            $linesArray = preg_split('/\r\n|\r|\n/', $csvData);
            foreach ($linesArray as $line) {
                if (trim($line) !== '') {
                    $lignes[] = str_getcsv($line);
                }
            }

            if (empty($lignes)) {
                return response()->json(['erreur' => 'Le document est vide.'], 404);
            }

            // Détection dynamique des colonnes via l'entête
            $header = array_shift($lignes);
            $colMap = [];
            foreach ($header as $idx => $name) {
                $name = strtolower(trim($name));
                if (str_contains($name, 'heure') || str_contains($name, 'horaire')) $colMap['time'] = $idx;
                if (str_contains($name, 'annonceur') || str_contains($name, 'client'))  $colMap['client'] = $idx;
                if (str_contains($name, 'spot'))    $colMap['spot'] = $idx;
                if (str_contains($name, 'durée') || str_contains($name, 'duree'))   $colMap['duration'] = $idx;
                if (str_contains($name, 'date'))    $colMap['date'] = $idx;
                if (str_contains($name, 'code'))    $colMap['code'] = $idx;
            }

            $idxTime     = $colMap['time']     ?? 0;
            $idxClient   = $colMap['client']   ?? 1;
            $idxSpot     = $colMap['spot']     ?? 2;
            $idxDuration = $colMap['duration'] ?? 3;
            $idxCode     = $colMap['code']     ?? 5;
            $idxDate     = $colMap['date']     ?? 6;

            // ── ANALYZE ONLY ──────────────────────────────────────────────────────────
            if ($analyzeOnly) {
                $allDetections = [];
                $spotsMap      = [];
                $allDates      = [];

                foreach ($lignes as $row) {
                    if (!isset($row[$idxSpot]) || empty(trim($row[$idxSpot]))) continue;

                    $rawHeure = isset($row[$idxTime]) ? trim($row[$idxTime]) : '00:00';
                    $rawHeure = preg_replace('/[hH,\.\s:]+/', ':', $rawHeure);
                    $parts = explode(':', $rawHeure);
                    $h = isset($parts[0]) ? str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT) : '00';
                    $m = isset($parts[1]) ? str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT) : '00';
                    $heure = "$h:$m";

                    $duration = isset($row[$idxDuration])
                        ? (int) preg_replace('/[^0-9]/', '', $row[$idxDuration])
                        : 0;

                    $cellDate = isset($row[$idxDate]) ? trim($row[$idxDate]) : '';
                    $isoDate  = '';
                    if ($cellDate) {
                        try {
                            $isoDate = Carbon::createFromFormat('d/m/Y', $cellDate)->format('Y-m-d');
                        } catch (\Exception $e) {
                            try { $isoDate = Carbon::parse($cellDate)->format('Y-m-d'); } catch (\Exception $e2) {}
                        }
                    }
                    if ($isoDate) $allDates[] = $isoDate;

                    $spotName = trim($row[$idxSpot]);
                    $color    = '#FFFFFF'; // Pas de couleur dispo via export CSV

                    $allDetections[] = [
                        'time'      => $heure,
                        'duration'  => $duration,
                        'color'     => $color,
                        'day'       => $isoDate ?: 'N/A',
                        'spot_name' => $spotName,
                    ];

                    $key = $color . '-' . $spotName;
                    if (!isset($spotsMap[$key])) {
                        $spotsMap[$key] = [
                            'name'     => $spotName,
                            'color'    => $color,
                            'duration' => $duration,
                            'value'    => '',
                            'id'       => '',
                        ];
                    }
                }

                return response()->json([
                    'detections' => $allDetections,
                    'spots'      => array_values($spotsMap),
                    'count'      => count($allDetections),
                    'date_debut' => !empty($allDates) ? min($allDates) : null,
                    'date_fin'   => !empty($allDates) ? max($allDates) : null,
                ]);
            }

            // ── FULL IMPORT ───────────────────────────────────────────────────────────
            $compteurClients = 0; $compteurCampagnes = 0; $compteurPlannings = 0;

            // Cache these once outside the loop to avoid N+1 queries
            $defaultCategoryId = \App\Models\Categorie::firstOrCreate(['nom_categorie' => 'Aucun'])->id;
            $clientCache   = [];
            $campagneCache = [];

            // Process in chunks of 200 rows wrapped in a DB transaction for speed
            $chunks = array_chunk($lignes, 200);

            foreach ($chunks as $chunk) {
                \DB::transaction(function () use (
                    $chunk, $idxClient, $idxSpot, $idxDuration, $idxDate, $idxCode, $idxTime,
                    $defaultCategoryId, &$clientCache, &$campagneCache,
                    &$compteurClients, &$compteurCampagnes, &$compteurPlannings
                ) {
                    foreach ($chunk as $row) {
                        if (!isset($row[$idxClient]) || empty(trim($row[$idxClient]))) continue;

                        try {
                            $nomSpot   = isset($row[$idxSpot]) ? trim($row[$idxSpot]) : 'Spot inconnu';
                            $nomClient = trim($row[$idxClient]);
                            $duree     = isset($row[$idxDuration]) ? (int) preg_replace('/[^0-9]/', '', $row[$idxDuration]) : 0;

                            // Normalize date
                            try {
                                $dateDiff = isset($row[$idxDate])
                                    ? Carbon::createFromFormat('d/m/Y', trim($row[$idxDate]))->format('Y-m-d')
                                    : now()->format('Y-m-d');
                            } catch (\Exception $e) {
                                $dateDiff = now()->format('Y-m-d');
                            }

                            // Normalize time robustly
                            $rawHeure = isset($row[$idxTime]) ? trim($row[$idxTime]) : '00:00';
                            $rawHeure = preg_replace('/[hH,\.\s:]+/', ':', $rawHeure);
                            $parts    = explode(':', $rawHeure);
                            $hh       = str_pad((int)($parts[0] ?? 0), 2, '0', STR_PAD_LEFT);
                            $mm       = str_pad((int)($parts[1] ?? 0), 2, '0', STR_PAD_LEFT);
                            $heureB   = "$hh:$mm:00";

                            // Code column IS the spot_id (integer)
                            $code   = isset($row[$idxCode]) ? trim($row[$idxCode]) : null;
                            $spotId = (is_numeric($code) && $code > 0) ? (int) $code : null;

                            // Client lookup (use memory cache to avoid repeated DB hits)
                            if (!isset($clientCache[$nomClient])) {
                                $clientDb = Client::firstOrCreate(['name' => $nomClient], [
                                    'contact_name' => 'À définir',
                                    'email'        => 'contact@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nomClient)) . '.com',
                                    'adresse'      => 'À définir',
                                    'telephone'    => '00000000',
                                    'campagne_nom' => $nomSpot,
                                    'created_by'   => auth()->id() ?? 1,
                                ]);
                                if ($clientDb->wasRecentlyCreated) $compteurClients++;
                                $clientCache[$nomClient] = $clientDb->id;
                            }
                            $clientId = $clientCache[$nomClient];

                            // Campagne lookup (memory cache)
                            $campKey = $nomSpot . ':' . $clientId;
                            if (!isset($campagneCache[$campKey])) {
                                $campagneDb = Campagne::firstOrCreate(
                                    ['spot' => $nomSpot, 'id_client' => $clientId],
                                    [
                                        'date_debut'   => $dateDiff,
                                        'date_fin'     => $dateDiff,
                                        'duree'        => $duree,
                                        'type'         => 'Automatique',
                                        'ranking'      => 0,
                                        'id_categorie' => $defaultCategoryId,
                                        'spot_id'      => $spotId,
                                    ]
                                );
                                if ($campagneDb->wasRecentlyCreated) $compteurCampagnes++;
                                $campagneCache[$campKey] = $campagneDb->id;
                            }
                            $campagneId = $campagneCache[$campKey];

                            // Planning insert (upsert on Code ID, or plain create)
                            $planningData = [
                                'date'        => $dateDiff,
                                'heure'       => $heureB,
                                'duree'       => $duree,
                                'id_campagne' => $campagneId,
                                'spot'        => $nomSpot,
                                'status'      => 'réservé',
                                'prix_HT'     => 0,
                            ];

                            if ($spotId) {
                                $planningData['id'] = $spotId;
                                $pl = Planning::updateOrCreate(['id' => $spotId], $planningData);
                                if ($pl->wasRecentlyCreated) $compteurPlannings++;
                            } else {
                                Planning::create($planningData);
                                $compteurPlannings++;
                            }

                        } catch (\Exception $rowError) {
                            // Skip bad rows silently, log for debug
                            \Log::warning('Ligne ignorée lors de l\'import Google Sheets: ' . $rowError->getMessage(), ['row' => $row]);
                        }
                    } // end foreach row
                }); // end DB::transaction
            } // end foreach chunk

            return response()->json([
                'message' => 'Importation terminée avec succès !',
                'details' => [
                    'clients'   => $compteurClients,
                    'campagnes' => $compteurCampagnes,
                    'plannings' => $compteurPlannings,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error("Erreur d'importation Google Sheets: " . $e->getMessage());
            return response()->json(['erreur' => "Erreur d'importation. Détail : " . $e->getMessage()], 500);
        }
    }
}