<?php

namespace App\Http\Controllers;

use App\Models\DanceVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DanceVideoController extends Controller
{
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'artist' => ['required', 'string', 'max:150'],
            'cover' => ['required', 'image', 'max:5120'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:512000'],
        ]);

        $data['cover_path'] = $request->file('cover')?->store('covers', 'public');
        $data['video_path'] = $request->file('video')->store('videos', 'public');
        unset($data['cover'], $data['video']);

        $video = DanceVideo::create($data);

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

    public function update(Request $request, DanceVideo $video)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'artist' => ['required', 'string', 'max:150'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:512000'],
        ]);

        if ($request->hasFile('cover')) {
            $newCoverPath = $request->file('cover')->store('covers', 'public');
            if ($video->cover_path) {
                Storage::disk('public')->delete($video->cover_path);
            }
            $data['cover_path'] = $newCoverPath;
        }

        if ($request->hasFile('video_file')) {
            $newVideoPath = $request->file('video_file')->store('videos', 'public');
            if ($video->video_path) {
                Storage::disk('public')->delete($video->video_path);
            }
            $data['video_path'] = $newVideoPath;
        }

        unset($data['cover'], $data['video_file']);
        $video->update($data);

        return redirect()->route('videos.show', $video)->with('success', 'Música atualizada com sucesso!');
    }

    public function download(DanceVideo $video): StreamedResponse
    {
        abort_unless($video->video_path, 404);

        $extension = pathinfo($video->video_path, PATHINFO_EXTENSION);
        $filename = str($video->title.' - '.$video->artist)->slug().'.'.$extension;

        return response()->streamDownload(function () use ($video) {
            $stream = Storage::disk('public')->readStream($video->video_path);
            fpassthru($stream);
            fclose($stream);
        }, $filename);
    }
}
