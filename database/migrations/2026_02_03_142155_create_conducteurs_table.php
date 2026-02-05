<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('conducteurs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        Schema::create('conducteur_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conducteur_id')
                ->constrained('conducteurs')
                ->cascadeOnDelete();
            $table->string('time_slot'); // "6h-7h", "7h-8h", etc.
            $table->foreignId('campagne_id')
                ->nullable()
                ->constrained('campagnes')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('conducteur_slots');
        Schema::dropIfExists('conducteurs');
    }
};
