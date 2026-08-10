<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorage
{
    public function diskName(): string
    {
        return (string) config('filesystems.default');
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->guessExtension() ?: 'bin');
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs(trim($directory, '/'), $filename, $this->diskName());

        if (! $path) {
            throw new RuntimeException('O storage não confirmou o envio do arquivo.');
        }

        return $path;
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if ($this->diskName() === 'public') {
            return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
        }

        return Storage::disk($this->diskName())->temporaryUrl(
            $path,
            now()->addMinutes((int) config('filesystems.temporary_url_ttl', 360)),
        );
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths !== [] && ! Storage::disk($this->diskName())->delete($paths)) {
            throw new RuntimeException('O storage não confirmou a exclusão dos arquivos.');
        }
    }

    /** @return resource */
    public function readStream(string $path)
    {
        $stream = Storage::disk($this->diskName())->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Não foi possível abrir o arquivo no storage.');
        }

        return $stream;
    }
}
