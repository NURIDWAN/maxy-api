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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0)->after('pin');
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sender_id')->index();
            $table->uuid('target_user_id')->index();
            $table->bigInteger('amount');
            $table->string('remarks')->nullable();
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users');
            $table->foreign('target_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
