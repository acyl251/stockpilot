<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedInteger('ia_catalog_seeded_count')->default(0)->after('onboarding_complete');
            $table->timestamp('ia_catalog_seeded_at')->nullable()->after('ia_catalog_seeded_count');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['ia_catalog_seeded_count', 'ia_catalog_seeded_at']);
        });
    }
};
