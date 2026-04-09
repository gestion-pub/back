<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('campagnes', function (Blueprint $table) {
            $table->json('spots')->nullable()->after('spot_id');
        });
    }

    public function down()
    {
        Schema::table('campagnes', function (Blueprint $table) {
            $table->dropColumn('spots');
        });
    }
};
