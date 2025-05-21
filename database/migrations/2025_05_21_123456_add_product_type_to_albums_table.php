<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            if (!Schema::hasColumn('albums', 'product_type')) {
                $table->string('product_type')->default('airvinyl');
            }
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'delete_at']);
        });
    }
};
