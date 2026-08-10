<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dance_videos', function (Blueprint $table) {
            $table->string('video_original_name')->nullable()->after('video_path');
            $table->string('video_mime_type', 100)->nullable()->after('video_original_name');
            $table->unsignedBigInteger('video_size')->nullable()->after('video_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('dance_videos', function (Blueprint $table) {
            $table->dropColumn(['video_original_name', 'video_mime_type', 'video_size']);
        });
    }
};
