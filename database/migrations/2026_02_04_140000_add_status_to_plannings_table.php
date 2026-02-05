<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->enum('status', ['réservé', 'programmé'])->default('réservé')->after('id_campagne');
        });
    }

    public function down()
    {
        Schema::table('plannings', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
