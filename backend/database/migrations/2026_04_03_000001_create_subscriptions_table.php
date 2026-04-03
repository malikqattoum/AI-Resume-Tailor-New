<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsTable extends Migration
{
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->enum('plan', ['free', 'basic', 'pro'])->default('free');
            $table->enum('status', ['active', 'canceled', 'past_due', 'trialing'])->default('active');
            $table->timestamp('current_period_end')->nullable();
            $table->unsignedInteger('requests_used_this_period')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
}
