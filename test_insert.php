<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = [];

try {
    $campagne = App\Models\Campagne::create([
        'spot' => 'TEST',
        'id_client' => App\Models\Client::first()->id ?? 1,
        'date_debut' => '2026-03-26',
        'date_fin' => '2026-03-26',
        'duree' => 10,
        'type' => 'Automatique'
    ]);
    $output[] = "CAMPAGNE SUCCESS";
} catch (\Exception $e) {
    $output[] = "CAMPAGNE ERROR: " . $e->getMessage();
}

try {
    $planning = App\Models\Planning::create([
        'date' => '2026-03-26',
        'heure' => '08:00',
        'duree' => 10,
        'id_campagne' => App\Models\Campagne::first()->id ?? 1,
        'id_client' => 1,
        'spot' => 'TEST',
        'status' => 'programmé',
        'prix_HT' => 0
    ]);
    $output[] = "PLANNING SUCCESS";
} catch (\Exception $e) {
    $output[] = "PLANNING ERROR: " . $e->getMessage();
}

file_put_contents('test_insert_output.txt', implode("\n", $output));
