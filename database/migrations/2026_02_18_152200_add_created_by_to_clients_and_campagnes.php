<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('telephone');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        Schema::table('campagnes', function (Blueprint $table) {
            if (!Schema::hasColumn('campagnes', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('id_categorie');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('campagnes', function (Blueprint $table) {
            if (Schema::hasColumn('campagnes', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
