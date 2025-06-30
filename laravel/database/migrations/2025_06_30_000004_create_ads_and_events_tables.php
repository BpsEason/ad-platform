<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateAdsAndEventsTables extends Migration
{
    public function up()
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->text('content');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->json('target_audience')->nullable();
            $table->timestamps();

            # Add indexes for performance
            $table->index(['tenant_id', 'start_time', 'end_time']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('ad_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_type'); // 'click', 'impression'
            $table->json('data')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            # Add indexes for performance
            $table->index(['tenant_id', 'event_type', 'occurred_at']);
            $table->index(['ad_id', 'event_type', 'occurred_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('ads');
    }
}
