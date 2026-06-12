<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
            $table->string('nom', 255);
            $table->string('reference', 100)->nullable();
            $table->string('description', 1000)->nullable();
            $table->decimal('quantite', 12, 3)->default(0);
            $table->decimal('seuil_alerte', 12, 3)->default(0);
            $table->string('unite_mesure', 30)->default('unité');
            $table->decimal('prix_achat_ht', 12, 3)->default(0);
            $table->decimal('taux_tva', 5, 2)->default(19);
            $table->decimal('prix_vente_ht', 12, 3)->default(0);
            // prix_achat_ttc / prix_vente_ttc: virtual on Oracle, computed via model appends on SQLite
            $table->text('attributs')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'reference'], 'uq_products_ref');
            $table->index('organisation_id', 'idx_products_org');
            $table->index('category_id', 'idx_products_cat');
            $table->index('product_type_id', 'idx_products_type');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                ALTER TABLE products ADD (
                    prix_achat_ttc NUMBER(12,3) GENERATED ALWAYS AS (prix_achat_ht * (1 + taux_tva / 100)) VIRTUAL,
                    prix_vente_ttc NUMBER(12,3) GENERATED ALWAYS AS (prix_vente_ht * (1 + taux_tva / 100)) VIRTUAL
                )
            ");
            DB::statement("CREATE INDEX idx_products_qty ON products(organisation_id, quantite)");
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_products_updated_at
                BEFORE UPDATE ON products
                FOR EACH ROW
                BEGIN
                    :NEW.updated_at := SYSTIMESTAMP;
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement('DROP TRIGGER trg_products_updated_at');
        }
        Schema::dropIfExists('products');
    }
};
