<?php

namespace App\Console\Commands;

use Aws\Exception\AwsException;
use Composer\InstalledVersions;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
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
            ['root/prefixo', (string) ($diskConfig['root'] ?? '') ?: '(vazio)'],
            ['path-style', ($diskConfig['use_path_style_endpoint'] ?? false) ? 'true' : 'false'],
            ['region', (string) ($diskConfig['region'] ?? 'não configurada')],
            ['assinatura', (string) ($diskConfig['signature_version'] ?? 'v4 (padrão do SDK S3)')],
            ['access key presente', filled($diskConfig['key'] ?? null) ? 'sim' : 'não'],
            ['secret presente', filled($diskConfig['secret'] ?? null) ? 'sim' : 'não'],
            ['access key sem espaços externos', $this->hasOuterWhitespace($diskConfig['key'] ?? null) ? 'não' : 'sim'],
            ['secret sem espaços externos', $this->hasOuterWhitespace($diskConfig['secret'] ?? null) ? 'não' : 'sim'],
        ]);

        if (! $this->option('connection')) {
            return self::SUCCESS;
        }

        if (($diskConfig['driver'] ?? null) === 's3') {
            $this->runTlsDiagnostics((string) ($diskConfig['endpoint'] ?? ''));
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
            $this->reportAwsFailure($failure);
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

    private function runTlsDiagnostics(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_string($host) || $host === '' || ($parts['scheme'] ?? null) !== 'https') {
            $this->warn('Diagnóstico TLS não executado: AWS_ENDPOINT não é uma URL HTTPS válida.');

            return;
        }

        $this->newLine();
        $this->info('Ambiente TLS do container');
        $this->table(['Item', 'Valor seguro'], [
            ['Imagem base', 'php:8.3-apache-bookworm'],
            ['PHP', PHP_VERSION.' ('.PHP_SAPI.')'],
            ['libcurl do PHP', $this->phpCurlVersion()],
            ['OpenSSL do PHP', OPENSSL_VERSION_TEXT],
            ['AWS SDK PHP', $this->packageVersion('aws/aws-sdk-php')],
            ['Flysystem AWS S3', $this->packageVersion('league/flysystem-aws-s3-v3')],
            ['Flysystem', $this->packageVersion('league/flysystem')],
            ['Guzzle', $this->packageVersion('guzzlehttp/guzzle')],
            ['curl.cainfo', $this->configuredPath('curl.cainfo')],
            ['openssl.cafile', $this->configuredPath('openssl.cafile')],
            ['openssl.capath', $this->configuredPath('openssl.capath')],
        ]);

        $this->info('Proxy do container');
        $proxyRows = [];
        foreach (['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'NO_PROXY'] as $name) {
            $proxyRows[] = [$name, $this->safeProxyDescription(getenv($name))];
        }
        $this->table(['Variável', 'Estado seguro'], $proxyRows);

        $this->runDiagnosticProcess('php -v', ['php', '-v']);
        $this->runDiagnosticProcess('curl --version', ['curl', '--version']);
        $this->runDiagnosticProcess('openssl version -a', ['openssl', 'version', '-a']);
        $this->runDiagnosticProcess('curl -Iv', [
            'curl', '-I', '-v', '--connect-timeout', '15', '--max-time', '30', $endpoint,
        ]);
        $this->runDiagnosticProcess('curl --tlsv1.2 -Iv', [
            'curl', '--tlsv1.2', '--tls-max', '1.2', '-I', '-v',
            '--connect-timeout', '15', '--max-time', '30', $endpoint,
        ]);
        $this->runDiagnosticProcess('curl --tlsv1.3 -Iv', [
            'curl', '--tlsv1.3', '--tls-max', '1.3', '-I', '-v',
            '--connect-timeout', '15', '--max-time', '30', $endpoint,
        ]);

        $this->runPhpCurlProbe($endpoint);
        $this->runGuzzleProbe($endpoint);

        $openssl = new Process([
            'openssl', 's_client', '-brief', '-connect', $host.':443', '-servername', $host,
        ]);
        $openssl->setInput('');
        $this->runDiagnosticProcess('openssl s_client', process: $openssl);
    }

    private function runPhpCurlProbe(string $endpoint): void
    {
        $this->newLine();
        $this->line('<fg=cyan>### PHP ext-curl anônimo</>');
        $verbose = fopen('php://temp', 'w+');
        $curl = curl_init($endpoint);

        curl_setopt_array($curl, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => $verbose,
        ]);

        curl_exec($curl);
        $errorNumber = curl_errno($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        rewind($verbose);
        $this->line($this->redactDiagnosticOutput((string) stream_get_contents($verbose)));
        fclose($verbose);
        $this->line("HTTP: {$status}; cURL errno: {$errorNumber}; erro: ".($error ?: 'nenhum'));
        $this->line('Resultado: '.($errorNumber === 0 ? 'OK' : 'ERRO'));
    }

    private function runGuzzleProbe(string $endpoint): void
    {
        $this->newLine();
        $this->line('<fg=cyan>### Guzzle anônimo</>');
        $debug = fopen('php://temp', 'w+');

        try {
            $response = (new Client)->request('HEAD', $endpoint, [
                'connect_timeout' => 15,
                'debug' => $debug,
                'http_errors' => false,
                'timeout' => 30,
                'verify' => true,
            ]);
            $this->line('HTTP: '.$response->getStatusCode());
            $this->line('Resultado: OK');
        } catch (Throwable $exception) {
            $this->line('Resultado: ERRO');
            $this->line($exception::class.': '.$this->redactDiagnosticOutput($exception->getMessage()));
        } finally {
            rewind($debug);
            $this->line($this->redactDiagnosticOutput((string) stream_get_contents($debug)));
            fclose($debug);
        }
    }

    /** @param array<int, string> $command */
    private function runDiagnosticProcess(string $label, array $command = [], ?Process $process = null): void
    {
        $this->newLine();
        $this->line("<fg=cyan>### {$label}</>");

        try {
            $process ??= new Process($command);
            $process->setTimeout(35);
            $process->run();
            $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());

            $this->line($this->redactDiagnosticOutput($output));
            $this->line('Resultado: '.($process->isSuccessful() ? 'OK' : 'ERRO').' (exit '.$process->getExitCode().')');
        } catch (Throwable $exception) {
            $this->line('Resultado: ERRO');
            $this->line($exception::class.': '.$this->redactDiagnosticOutput($exception->getMessage()));
        }
    }

    private function redactDiagnosticOutput(string $output): string
    {
        $output = preg_replace('/^(>\s*Proxy-Authorization:).*$/mi', '$1 <redacted>', $output) ?? $output;
        $output = preg_replace('#(https?://)[^/@\s]+:[^/@\s]+@#i', '$1<redacted>@', $output) ?? $output;

        return $output;
    }

    private function safeProxyDescription(string|false $value): string
    {
        if ($value === false || trim($value) === '') {
            return 'não configurado';
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return 'configurado (valor oculto)';
        }

        $description = 'configurado; host='.$parts['host'];
        $description .= isset($parts['port']) ? '; porta='.$parts['port'] : '';
        $description .= isset($parts['user']) || isset($parts['pass']) ? '; credenciais=sim' : '; credenciais=não';

        return $description;
    }

    private function phpCurlVersion(): string
    {
        if (! function_exists('curl_version')) {
            return 'ext-curl não carregada';
        }

        $version = curl_version();

        return ($version['version'] ?? 'desconhecida').' / '.($version['ssl_version'] ?? 'SSL desconhecido');
    }

    private function packageVersion(string $package): string
    {
        return class_exists(InstalledVersions::class)
            ? (InstalledVersions::getPrettyVersion($package) ?? 'não instalado')
            : 'indisponível';
    }

    private function configuredPath(string $setting): string
    {
        $value = (string) ini_get($setting);

        return $value !== '' ? $value : 'padrão do sistema';
    }

    private function hasOuterWhitespace(mixed $value): bool
    {
        return is_string($value) && $value !== trim($value);
    }

    private function reportAwsFailure(Throwable $failure): void
    {
        $exception = $failure;

        while (! $exception instanceof AwsException && $exception->getPrevious()) {
            $exception = $exception->getPrevious();
        }

        if (! $exception instanceof AwsException) {
            $this->warn('A exceção não contém uma resposta estruturada do AWS SDK.');

            return;
        }

        $request = $exception->getRequest();
        $authorization = $request?->getHeaderLine('Authorization') ?? '';
        preg_match('/Credential=[^\/]+\/([^,\s]+)/', $authorization, $scope);
        preg_match('/SignedHeaders=([^,\s]+)/', $authorization, $signedHeaders);

        $response = $exception->getResponse();
        $this->newLine();
        $this->info('Resposta assinada do R2 (sem credenciais)');
        $this->table(['Item', 'Valor seguro'], [
            ['HTTP status', (string) ($exception->getStatusCode() ?: 'indisponível')],
            ['AWS error code', (string) ($exception->getAwsErrorCode() ?: 'indisponível')],
            ['AWS error type', (string) ($exception->getAwsErrorType() ?: 'indisponível')],
            ['AWS request ID', (string) ($exception->getAwsRequestId() ?: 'indisponível')],
            ['método', $request?->getMethod() ?? 'indisponível'],
            ['host', $request?->getUri()->getHost() ?? 'indisponível'],
            ['path', $request?->getUri()->getPath() ?? 'indisponível'],
            ['algoritmo', str_starts_with($authorization, 'AWS4-HMAC-SHA256') ? 'AWS4-HMAC-SHA256' : 'não identificado'],
            ['credential scope', $scope[1] ?? 'não identificado'],
            ['headers assinados', $signedHeaders[1] ?? 'não identificado'],
            ['x-amz-date presente', $request?->hasHeader('x-amz-date') ? 'sim' : 'não'],
            ['payload hash presente', $request?->hasHeader('x-amz-content-sha256') ? 'sim' : 'não'],
            ['cf-ray', $response?->getHeaderLine('cf-ray') ?: 'indisponível'],
        ]);

        $code = (string) $exception->getAwsErrorCode();
        if (in_array($code, ['SignatureDoesNotMatch', 'AuthorizationHeaderMalformed', 'InvalidRequest'], true)) {
            $this->warn('Classificação: provável problema de assinatura/configuração SigV4.');
        } elseif (in_array($code, ['AccessDenied', 'InvalidAccessKeyId'], true)) {
            $this->warn('Classificação: assinatura aceita pelo serviço; verifique token, bucket e permissão Object Read & Write.');
        } else {
            $this->warn('Classificação inconclusiva; use o código AWS e o request ID acima.');
        }
    }
}
