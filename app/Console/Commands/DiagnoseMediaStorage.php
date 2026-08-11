<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DiagnoseMediaStorage extends Command
{
    protected $signature = 'media:diagnose {--connection : Testa PUT, EXISTS e DELETE no disk configurado}';

    protected $description = 'Exibe a configuração segura do storage e testa sua conexão';

    public function handle(): int
    {
        $diskName = (string) config('filesystems.default');
        $diskConfig = (array) config("filesystems.disks.{$diskName}", []);

        $this->table(['Configuração', 'Valor seguro'], [
            ['filesystem.default', $diskName],
            ['driver', (string) ($diskConfig['driver'] ?? 'não configurado')],
            ['bucket', (string) ($diskConfig['bucket'] ?? 'não configurado')],
            ['endpoint', (string) ($diskConfig['endpoint'] ?? 'não configurado')],
            ['access key presente', filled($diskConfig['key'] ?? null) ? 'sim' : 'não'],
            ['secret presente', filled($diskConfig['secret'] ?? null) ? 'sim' : 'não'],
        ]);

        if (! $this->option('connection')) {
            return self::SUCCESS;
        }

        $path = 'diagnostics/'.Str::uuid().'.txt';
        $disk = Storage::disk($diskName);

        try {
            $put = $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $deleted = $disk->delete($path);
            $gone = ! $disk->exists($path);

            $this->table(['Operação', 'Resultado'], [
                ['PUT', $put ? 'OK' : 'FALHOU'],
                ['EXISTS', $exists ? 'OK' : 'FALHOU'],
                ['DELETE', $deleted && $gone ? 'OK' : 'FALHOU'],
            ]);

            return $put && $exists && $deleted && $gone ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception::class.': '.$exception->getMessage());

            try {
                $disk->delete($path);
            } catch (Throwable) {
                // A tentativa de limpeza não deve esconder o erro original.
            }

            return self::FAILURE;
        }
    }
}
