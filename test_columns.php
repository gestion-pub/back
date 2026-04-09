<?php
$campagnes = Schema::getColumnListing('campagnes');
$plannings = Schema::getColumnListing('plannings');
echo "CAMPAGNES: " . implode(", ", $campagnes) . "\n";
echo "PLANNINGS: " . implode(", ", $plannings) . "\n";
