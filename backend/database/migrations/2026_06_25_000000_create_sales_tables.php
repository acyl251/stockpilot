<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Ventes (tickets de caisse) ──────────────────────────────────────
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('user_id')->constrained('users');
            $table->string('numero', 30);
            $table->decimal('total_ht', 12, 3)->default(0);
            $table->decimal('total_tva', 12, 3)->default(0);
            $table->decimal('total_ttc', 12, 3)->default(0);
            $table->string('mode_paiement', 20)->default('especes'); // especes | carte
            $table->decimal('montant_paye', 12, 3)->nullable();
            $table->decimal('monnaie_rendue', 12, 3)->default(0);
            $table->string('statut', 20)->default('payee');
            $table->timestamp('date_vente')->useCurrent();
            $table->timestamps();

            $table->index('organisation_id', 'idx_sales_org');
            $table->index(['organisation_id', 'date_vente'], 'idx_sales_date');
            $table->unique(['organisation_id', 'numero'], 'uq_sales_numero');
        });

        // ── Lignes de vente ─────────────────────────────────────────────────
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('designation', 200);
            $table->decimal('quantite', 12, 3);
            $table->decimal('prix_unitaire_ht', 12, 3);
            $table->decimal('taux_tva', 5, 2)->default(0);
            $table->decimal('prix_unitaire_ttc', 12, 3);
            $table->decimal('total_ligne_ttc', 12, 3);
            $table->timestamps();

            $table->index('organisation_id', 'idx_sale_items_org');
            $table->index('sale_id', 'idx_sale_items_sale');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_sales_updated_at
                BEFORE UPDATE ON sales
                FOR EACH ROW
                BEGIN
                    :NEW.updated_at := SYSTIMESTAMP;
                END;
            ");
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_sale_items_updated_at
                BEFORE UPDATE ON sale_items
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
            DB::statement('DROP TRIGGER trg_sale_items_updated_at');
            DB::statement('DROP TRIGGER trg_sales_updated_at');
        }
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
