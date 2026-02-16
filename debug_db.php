<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conducteurs = \App\Models\Conducteur::all();
echo "CONDUCTEURS COUNT: " . $conducteurs->count() . "\n";
foreach ($conducteurs as $c) {
    echo "ID: {$c->id}, Name: {$c->name}, Date: {$c->date}\n";
}

$plannings = \App\Models\Planning::all();
echo "\nPLANNINGS COUNT: " . $plannings->count() . "\n";
if ($plannings->count() > 0) {
    echo "First 5 plannings:\n";
    foreach ($plannings->take(5) as $p) {
        echo "ID: {$p->id}, Date: {$p->date}, Status: {$p->status}\n";
    }
}
