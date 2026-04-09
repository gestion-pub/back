<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$campagnes = Illuminate\Support\Facades\Schema::getColumnListing('campagnes');
$plannings = Illuminate\Support\Facades\Schema::getColumnListing('plannings');

file_put_contents('cols.json', json_encode(compact('campagnes', 'plannings')));
