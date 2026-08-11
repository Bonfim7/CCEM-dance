<?php

namespace App\Http\Controllers;

use App\Models\DanceVideo;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DanceVideoController extends Controller
{
    public function __construct(private readonly MediaStorage $mediaStorage) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('busca'));
        $videos = DanceVideo::query()
            ->when($term, fn ($query) => $query->where(function ($query) use ($term) {
                $query->where('title', 'like', "%{$term}%")
                    ->orWhere('artist', 'like', "%{$term}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('videos.index', compact('videos', 'term'));
    }

    public function create(): View
    {
        return view('videos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $uploadId = (string) Str::uuid();
        $context = $this->uploadContext($request, $uploadId);

        Log::info('Publicação de vídeo recebida.', $context);

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:150'],
            'artist' => ['required', 'string', 'max:150'],
            'cover' => ['required', 'image', 'max:5120'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:512000'],
        ], [
            'cover.required' => 'Selecione uma capa para a música.',
            'cover.image' => 'A capa deve ser uma imagem JPG, PNG, WEBP ou formato compatível.',
            'cover.max' => 'A capa pode ter no máximo 5 MB.',
            'video.required' => 'Selecione o arquivo do vídeo.',
            'video.mimetypes' => 'O vídeo deve estar no formato MP4, WebM ou MOV.',
            'video.max' => 'O vídeo pode ter no máximo 500 MB.',
            'uploaded' => 'O arquivo não pôde ser recebido. Confirme o tamanho e tente novamente.',
        ]);

        if ($validator->fails()) {
            Log::warning('Validação da publicação falhou.', $context + [
                'fields' => array_keys($validator->errors()->toArray()),
            ]);
        }

        $data = $validator->validate();

        Log::info('Validação da publicação concluída.', $context);

        $storedPaths = [];

        try {
            $cover = $request->file('cover');
            $videoFile = $request->file('video');
            Log::info('Iniciando upload da capa.', $context);
            $data['cover_path'] = $this->mediaStorage->store($cover, 'covers');
            $storedPaths[] = $data['cover_path'];
            Log::info('Upload da capa concluído.', $context + ['path' => $data['cover_path']]);
            Log::info('Iniciando upload do vídeo.', $context);
            $data['video_path'] = $this->mediaStorage->store($videoFile, 'videos');
            $storedPaths[] = $data['video_path'];
            Log::info('Upload do vídeo concluído.', $context + ['path' => $data['video_path']]);
            $data['video_original_name'] = $videoFile->getClientOriginalName();
            $data['video_mime_type'] = $videoFile->getMimeType();
            $data['video_size'] = $videoFile->getSize();
            unset($data['cover'], $data['video']);

            $video = DB::transaction(fn () => DanceVideo::create($data));
            Log::info('Registro do vídeo criado.', $context + ['video_id' => $video->id]);
        } catch (Throwable $exception) {
            $this->cleanUpFailedUpload($storedPaths);
            Log::error('Falha ao publicar mídia.', [
                'upload_id' => $uploadId,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'disk' => $this->mediaStorage->diskName(),
                'stored_paths' => $storedPaths,
            ]);

            return back()->withInput()->withErrors([
                'video' => 'Não foi possível enviar os arquivos. Tente novamente em alguns instantes.',
            ]);
        }

        Log::info('Publicação concluída; redirecionando.', $context + ['video_id' => $video->id]);

        return redirect()->route('videos.show', $video)->with('success', 'Vídeo publicado com sucesso!');
    }

    public function show(DanceVideo $video): View
    {
        $related = DanceVideo::whereKeyNot($video->id)
            ->latest()->limit(3)->get();

        return view('videos.show', compact('video', 'related'));
    }

    public function edit(DanceVideo $video): View
    {
        return view('videos.edit', compact('video'));
    }

    public function update(Request $request, DanceVideo $video): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'artist' => ['required', 'string', 'max:150'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:512000'],
        ]);

        $newPaths = [];
        $oldPaths = [];

        try {
            if ($request->hasFile('cover')) {
                $data['cover_path'] = $this->mediaStorage->store($request->file('cover'), 'covers');
                $newPaths[] = $data['cover_path'];
                $oldPaths[] = $video->cover_path;
            }

            if ($request->hasFile('video_file')) {
                $videoFile = $request->file('video_file');
                $data['video_path'] = $this->mediaStorage->store($videoFile, 'videos');
                $newPaths[] = $data['video_path'];
                $oldPaths[] = $video->video_path;
                $data['video_original_name'] = $videoFile->getClientOriginalName();
                $data['video_mime_type'] = $videoFile->getMimeType();
                $data['video_size'] = $videoFile->getSize();
            }

            unset($data['cover'], $data['video_file']);
            DB::transaction(fn () => $video->update($data));
        } catch (Throwable $exception) {
            $this->cleanUpFailedUpload($newPaths);
            Log::error('Falha ao atualizar mídia.', [
                'exception' => $exception,
                'video_id' => $video->id,
                'disk' => $this->mediaStorage->diskName(),
            ]);

            return back()->withInput()->withErrors([
                'video_file' => 'Não foi possível salvar as alterações. Os arquivos anteriores foram mantidos.',
            ]);
        }

        try {
            $this->mediaStorage->delete($oldPaths);
        } catch (Throwable $exception) {
            Log::warning('Mídia antiga não pôde ser removida após substituição.', [
                'exception' => $exception,
                'video_id' => $video->id,
                'paths' => $oldPaths,
            ]);
        }

        return redirect()->route('videos.show', $video)->with('success', 'Música atualizada com sucesso!');
    }

    public function download(DanceVideo $video): StreamedResponse
    {
        abort_unless($video->video_path, 404);

        $extension = pathinfo($video->video_path, PATHINFO_EXTENSION);
        $filename = str($video->title.' - '.$video->artist)->slug().'.'.$extension;

        return response()->streamDownload(function () use ($video) {
            $stream = $this->mediaStorage->readStream($video->video_path);

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $filename);
    }

    public function destroy(DanceVideo $video): RedirectResponse
    {
        try {
            $this->mediaStorage->delete([$video->cover_path, $video->video_path]);
            DB::transaction(fn () => $video->delete());
        } catch (Throwable $exception) {
            Log::error('Falha ao excluir mídia.', [
                'exception' => $exception,
                'video_id' => $video->id,
                'disk' => $this->mediaStorage->diskName(),
            ]);

            return back()->withErrors([
                'video' => 'Não foi possível excluir esta música. Nenhuma nova tentativa será feita automaticamente.',
            ]);
        }

        return redirect()->route('home')->with('success', 'Música e arquivos excluídos com sucesso!');
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    private function cleanUpFailedUpload(array $paths): void
    {
        try {
            $this->mediaStorage->delete($paths);
        } catch (Throwable $cleanupException) {
            Log::warning('Falha ao limpar arquivos de um upload incompleto.', [
                'exception' => $cleanupException,
                'paths' => $paths,
            ]);
        }
    }

    /** @return array<string, int|string|null> */
    private function uploadContext(Request $request, string $uploadId): array
    {
        $cover = $request->file('cover');
        $video = $request->file('video');

        return [
            'upload_id' => $uploadId,
            'disk' => $this->mediaStorage->diskName(),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'cover_name' => $cover?->getClientOriginalName(),
            'cover_size' => $cover?->getSize(),
            'cover_mime' => $cover?->getClientMimeType(),
            'video_name' => $video?->getClientOriginalName(),
            'video_size' => $video?->getSize(),
            'video_mime' => $video?->getClientMimeType(),
        ];
    }
}
