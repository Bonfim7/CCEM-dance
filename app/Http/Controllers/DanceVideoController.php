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
                    ->orWhere('artist', 'like', "%{$term}%")
                    ->orWhere('dance_style', 'like', "%{$term}%");
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
            'dance_style' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'max:5120'],
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
            ->where('dance_style', $video->dance_style)
            ->latest()->limit(3)->get();

        return view('videos.show', compact('video', 'related'));
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
