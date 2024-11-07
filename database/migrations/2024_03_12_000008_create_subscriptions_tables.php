<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Subscription plans (e.g., Basic, Pro, Enterprise)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('USD');
            $table->integer('duration_in_days');
            $table->json('features'); // Available features and limits
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        // Organization subscriptions
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('price_paid', 10, 2);
            $table->string('currency');
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable();
            $table->json('features_snapshot'); // Snapshot of plan features at time of purchase
            $table->json('metadata')->nullable();
            $table->string('status'); // active, cancelled, expired
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index('ends_at');
        });

        // Player subscriptions to organizations
        Schema::create('player_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_paid', 10, 2);
            $table->string('currency');
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->json('metadata')->nullable();
            $table->string('status'); // active, cancelled, expired
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'organization_id', 'status']);
            $table->index('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_subscriptions');
        Schema::dropIfExists('organization_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
