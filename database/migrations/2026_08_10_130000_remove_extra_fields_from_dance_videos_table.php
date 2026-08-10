<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dance_videos', function (Blueprint $table) {
            $table->dropColumn(['dance_style', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('dance_videos', function (Blueprint $table) {
            $table->string('dance_style', 80)->nullable();
            $table->text('description')->nullable();
        });
    }
};
