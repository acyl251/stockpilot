<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Coût d'achat HT figé au moment de la vente → marge exacte dans le temps.
            $table->decimal('prix_achat_unitaire', 12, 3)->default(0)->after('prix_unitaire_ht');
        });

        // Backfill : reprend le coût actuel du produit pour les lignes existantes.
        DB::statement('
            UPDATE sale_items
            SET prix_achat_unitaire = COALESCE(
                (SELECT p.prix_achat_ht FROM products p WHERE p.id = sale_items.product_id), 0
            )
        ');
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('prix_achat_unitaire');
        });
    }
};
