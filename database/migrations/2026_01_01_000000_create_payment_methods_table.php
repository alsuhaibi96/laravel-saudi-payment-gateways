<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('min_amount_halalas')->nullable();
            $table->unsignedBigInteger('max_amount_halalas')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->string('event_id');
            $table->string('order_reference')->index();
            $table->string('status', 20);
            $table->unsignedBigInteger('amount_halalas')->nullable();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            // The idempotency guarantee: a provider retry with the same event id is a no-op.
            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_methods');
    }
};
