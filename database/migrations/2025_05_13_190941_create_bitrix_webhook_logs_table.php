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
        Schema::create('bitrix_webhook_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('portal')->index();
            $table->json('payload');
            $table->string('dialog_id')->nullable();
            $table->json('response')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitrix_webhook_logs');
    }

};
