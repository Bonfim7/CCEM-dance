<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DiagnoseMediaStorage extends Command
{
    protected $signature = 'media:diagnose {--connection : Testa PUT, EXISTS, READ e DELETE no disk configurado}';

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
        $results = [
            'PUT' => 'NÃO EXECUTADO',
            'EXISTS' => 'NÃO EXECUTADO',
            'READ' => 'NÃO EXECUTADO',
            'DELETE' => 'NÃO EXECUTADO',
        ];
        $failure = null;
        $put = false;
        $exists = false;
        $read = false;
        $deleted = false;
        $gone = false;

        try {
            $put = $disk->put($path, 'ok');
            $results['PUT'] = $put ? 'OK' : 'ERRO';
        } catch (Throwable $exception) {
            $results['PUT'] = 'ERRO';
            $failure = $exception;
        }

        if ($put) {
            try {
                $exists = $disk->exists($path);
                $results['EXISTS'] = $exists ? 'OK' : 'ERRO';
            } catch (Throwable $exception) {
                $results['EXISTS'] = 'ERRO';
                $failure ??= $exception;
            }
        }

        if ($exists) {
            try {
                $stream = $disk->readStream($path);
                $read = is_resource($stream) && stream_get_contents($stream) === 'ok';
                $results['READ'] = $read ? 'OK' : 'ERRO';

                if (is_resource($stream)) {
                    fclose($stream);
                }
            } catch (Throwable $exception) {
                $results['READ'] = 'ERRO';
                $failure ??= $exception;
            }
        }

        if ($put) {
            try {
                $deleted = $disk->delete($path);
                $gone = ! $disk->exists($path);
                $results['DELETE'] = $deleted && $gone ? 'OK' : 'ERRO';
            } catch (Throwable $exception) {
                $results['DELETE'] = 'ERRO';
                $failure ??= $exception;
            }
        }

        $this->table(
            ['Operação', 'Resultado'],
            collect($results)->map(fn ($result, $operation) => [$operation, $result])->values()->all(),
        );

        if ($failure) {
            $this->error($failure::class.': '.$failure->getMessage());
        }

        if ($put && ! $gone) {
            try {
                $disk->delete($path);
            } catch (Throwable) {
                // A tentativa de limpeza não deve esconder o erro original.
            }
        }

        return $put && $exists && $read && $deleted && $gone ? self::SUCCESS : self::FAILURE;
    }
}
