<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanceVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'artist', 'cover_path', 'video_path',
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
        return $path ? '/storage/'.ltrim(str_replace('\\', '/', $path), '/') : null;
    }
}
