<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanceVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'artist', 'cover_path', 'video_path', 'video_original_name',
        'video_mime_type', 'video_size',
    ];

    public function getCoverUrlAttribute(): ?string
    {
        return $this->publicUrl($this->cover_path);
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->publicUrl($this->video_path);
    }

    private function publicUrl(?string $path): ?string
    {
        return app(MediaStorage::class)->url($path);
    }
}
