<?php

namespace App\Imports;

use App\Models\Planning;
use App\Models\Campagne;
use App\Models\Client;
use App\Models\Categorie;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;

class PlanningsImport implements ToModel, WithHeadingRow, WithChunkReading, WithBatchInserts, ShouldQueue
{
    private $defaultCategoryId;

    public function __construct()
    {
        // En cache pour éviter de le requêter à chaque ligne
        $this->defaultCategoryId = Categorie::first()->id ?? 1;
    }

    public function model(array $row)
    {
        $nomClient = 'Inconnu';
        try {
            // Nettoyage des clés car Excel génère parfois des espaces invisibles ou des différences de casse
            $keys = array_keys($row);
            
            $timeKey     = $this->findKey($keys, ['heure', 'horaire', 'time']);
            $clientKey   = $this->findKey($keys, ['annonceur', 'client']);
            $spotKey     = $this->findKey($keys, ['spot']);
            $durationKey = $this->findKey($keys, ['duree', 'durée']);
            $dateKey     = $this->findKey($keys, ['date']);
            $codeKey     = $this->findKey($keys, ['code']);

            // Si on n'a pas de client, on ignore la ligne (ligne vide probable)
            if (!$clientKey || empty(trim($row[$clientKey]))) {
                return null;
            }

            // 1) Nettoyage de l'heure : conversion de "6,46" ou "7::45" en HH:mm:ss
            // Convertir '6,46' -> '06:46:00'
            $rawHeure = $timeKey && isset($row[$timeKey]) ? trim($row[$timeKey]) : '00:00';
            $rawHeure = preg_replace('/[hH,\.\s:]+/', ':', strtolower($rawHeure)); // Normaliser en ':'
            $parts = explode(':', $rawHeure);
            $h = isset($parts[0]) ? str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT) : '00';
            $m = isset($parts[1]) ? str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT) : '00';
            $heure = "$h:$m:00";

            // 2) Nettoyage basiques
            $nomClient = trim($row[$clientKey]);
            $nomSpot   = $spotKey && isset($row[$spotKey]) ? trim($row[$spotKey]) : 'Spot inconnu';
            $duree     = $durationKey && isset($row[$durationKey]) ? (int) preg_replace('/[^0-9]/', '', $row[$durationKey]) : 0;

            // 3) Nettoyage de la date (Excel format ou string format)
            $dateDiff = now()->format('Y-m-d');
            if ($dateKey && !empty($row[$dateKey])) {
                $val = trim($row[$dateKey]);
                try {
                    if (is_numeric($val)) {
                        $dateDiff = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
                    } else {
                        $dateDiff = Carbon::createFromFormat('d/m/Y', $val)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    try {
                        $dateDiff = Carbon::parse($val)->format('Y-m-d');
                    } catch (\Exception $e2) {
                        // Restera la date par défaut
                    }
                }
            }

            // 4) Convertir spot_id de 'auto' à null, sinon Integer
            $codeStr = $codeKey && isset($row[$codeKey]) ? trim($row[$codeKey]) : null;
            $spotId = null;
            if ($codeStr !== 'auto' && !empty($codeStr)) {
                $spotId = is_numeric($codeStr) ? (int) $codeStr : null;
            }

            // Vérifier et créer le client
            $clientDb = Client::firstOrCreate(['name' => $nomClient], [
                'contact_name' => 'À définir',
                'email'        => 'contact@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nomClient)) . '.com',
                'adresse'      => 'À définir',
                'telephone'    => '00000000',
                'campagne_nom' => $nomSpot,
                'created_by'   => 1, // En mode Queue, l'auth() n'est pas toujours disponible
            ]);

            // Vérifier et créer la campagne
            $campagneDb = Campagne::firstOrCreate(
                ['spot' => $nomSpot, 'id_client' => $clientDb->id],
                [
                    'date_debut'   => $dateDiff, 
                    'date_fin'     => $dateDiff, 
                    'duree'        => $duree, 
                    'type'         => 'Automatique',
                    'ranking'      => 0,
                    'id_categorie' => $this->defaultCategoryId,
                    'spot_id'      => $spotId, // Désormais NULL si 'auto'
                ]
            );

            // Retourner l'instance Planning, Laravel fera un insert groupé (BatchInsert)
            return new Planning([
                'date'        => $dateDiff,
                'heure'       => $heure,
                'duree'       => $duree,
                'id_campagne' => $campagneDb->id,
                'spot'        => $nomSpot,
                'status'      => 'programmé',
                'prix_HT'     => 0,
            ]);

        } catch (\Exception $e) {
            // Ignorer silencieusement la ligne corrompue pour ne pas arrêter l'importation massive complète
            \Log::error("Erreur à la ligne Excel concernant client ({$nomClient}): " . $e->getMessage());
            return null; // Retourner null permet d'ignorer la ligne pour ToModel
        }
    }

    /**
     * Recherche dynamique de la clé de colonne dans le tableau d'entêtes
     */
    private function findKey(array $keys, array $searchWords)
    {
        foreach ($keys as $key) {
            $lowerKey = strtolower(trim($key));
            foreach ($searchWords as $word) {
                if (str_contains($lowerKey, $word)) {
                    return $key;
                }
            }
        }
        return null;
    }

    /**
     * @return int
     */
    public function batchSize(): int
    {
        return 300; // Requêtes d'insertion en lot de 300
    }

    /**
     * @return int
     */
    public function chunkSize(): int
    {
        return 300; // Lit le fichier excel par bloc de 300 lignes pour préserver la RAM
    }
}