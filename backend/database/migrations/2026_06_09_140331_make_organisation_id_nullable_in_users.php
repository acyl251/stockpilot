<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->change();
        });

        // Detach existing super_admin users from any organisation
        DB::table('users')->where('role', 'super_admin')->update(['organisation_id' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable(false)->change();
        });
    }
};