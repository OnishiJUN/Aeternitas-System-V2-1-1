<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add foreign key for processed_by (uuid) to accounts.id
            if (Schema::hasColumn('payments', 'processed_by')) {
                $table->foreign('processed_by')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'processed_by')) {
                $table->dropForeign(['processed_by']);
            }
        });
    }
};
