<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('user_id')->constrained('users');
            $table->string('type_mouvement', 20);
            $table->decimal('quantite', 12, 3);
            $table->decimal('quantite_avant', 12, 3);
            $table->decimal('quantite_apres', 12, 3);
            $table->string('note', 500)->nullable();
            $table->timestamp('date_mouvement')->useCurrent();
            $table->timestamps();

            $table->index('organisation_id', 'idx_mvt_org');
            $table->index('product_id', 'idx_mvt_product');
            $table->index(['organisation_id', 'date_mouvement'], 'idx_mvt_date');
        });

        if (DB::connection()->getDriverName() === 'oracle') {
            // Trigger updates product quantity at DB level (PHP StockService also computes it)
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_update_stock
                AFTER INSERT ON stock_movements
                FOR EACH ROW
                DECLARE
                    v_qty_before NUMBER(12,3);
                BEGIN
                    SELECT quantite INTO v_qty_before
                    FROM products WHERE id = :NEW.product_id
                    FOR UPDATE;

                    IF :NEW.type_mouvement = 'entree' THEN
                        UPDATE products SET quantite = quantite + :NEW.quantite
                        WHERE id = :NEW.product_id;
                    ELSIF :NEW.type_mouvement = 'sortie' THEN
                        UPDATE products SET quantite = GREATEST(0, quantite - :NEW.quantite)
                        WHERE id = :NEW.product_id;
                    ELSIF :NEW.type_mouvement = 'ajustement' THEN
                        UPDATE products SET quantite = :NEW.quantite
                        WHERE id = :NEW.product_id;
                    END IF;
                END;
            ");
            DB::statement("
                CREATE OR REPLACE TRIGGER trg_stock_movements_updated_at
                BEFORE UPDATE ON stock_movements
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
            DB::statement('DROP TRIGGER trg_update_stock');
            DB::statement('DROP TRIGGER trg_stock_movements_updated_at');
        }
        Schema::dropIfExists('stock_movements');
    }
};
