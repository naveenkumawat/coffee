<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('configuration_hash', 64)->nullable()->after('product_variant_id');
        });

        // Backfill existing lines (no add-ons) then enforce uniqueness.
        DB::table('cart_items')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $hash = hash('sha256', json_encode([
                    'product_variant_id' => (int) $row->product_variant_id,
                    'add_ons' => [],
                ], JSON_THROW_ON_ERROR));

                DB::table('cart_items')->where('id', $row->id)->update([
                    'configuration_hash' => $hash,
                ]);
            }
        });

        // MySQL may use the composite unique as the supporting index for cart_id FK.
        // Ensure a dedicated cart_id index exists before dropping the old unique key.
        $cartItemIndexes = collect(Schema::getIndexes('cart_items'))->pluck('name')->all();
        if (! in_array('cart_items_cart_id_index', $cartItemIndexes, true)
            && ! in_array('cart_items_cart_id_foreign', $cartItemIndexes, true)) {
            Schema::table('cart_items', function (Blueprint $table): void {
                $table->index('cart_id', 'cart_items_cart_id_index');
            });
        }

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'product_variant_id']);
            $table->unique(['cart_id', 'configuration_hash'], 'cart_items_cart_configuration_unique');
        });

        Schema::table('dining_round_drafts', function (Blueprint $table): void {
            $table->string('configuration_hash', 64)->nullable()->after('product_variant_id');
        });

        DB::table('dining_round_drafts')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $hash = hash('sha256', json_encode([
                    'product_variant_id' => (int) $row->product_variant_id,
                    'add_ons' => [],
                ], JSON_THROW_ON_ERROR));

                DB::table('dining_round_drafts')->where('id', $row->id)->update([
                    'configuration_hash' => $hash,
                ]);
            }
        });

        $diningDraftIndexes = collect(Schema::getIndexes('dining_round_drafts'))->pluck('name')->all();
        if (! in_array('dining_round_drafts_dining_session_id_index', $diningDraftIndexes, true)
            && ! in_array('dining_round_drafts_dining_session_id_foreign', $diningDraftIndexes, true)) {
            Schema::table('dining_round_drafts', function (Blueprint $table): void {
                $table->index('dining_session_id', 'dining_round_drafts_dining_session_id_index');
            });
        }

        Schema::table('dining_round_drafts', function (Blueprint $table): void {
            $table->dropUnique('dining_draft_session_variant_unique');
            $table->unique(['dining_session_id', 'configuration_hash'], 'dining_draft_session_configuration_unique');
        });

        Schema::create('dining_round_draft_add_ons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dining_round_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['dining_round_draft_id', 'add_on_id'], 'dining_draft_add_on_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_round_draft_add_ons');

        Schema::table('dining_round_drafts', function (Blueprint $table): void {
            $table->dropUnique('dining_draft_session_configuration_unique');
            $table->unique(['dining_session_id', 'product_variant_id'], 'dining_draft_session_variant_unique');
            $table->dropColumn('configuration_hash');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique('cart_items_cart_configuration_unique');
            $table->unique(['cart_id', 'product_variant_id']);
            $table->dropColumn('configuration_hash');
        });
    }
};
