<?php

namespace Tests\Feature;

use App\Models\DanceVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_a_dance_video_can_be_published(): void
    {
        Storage::fake('public');

        $response = $this->post('/videos', [
            'title' => 'Minha Música',
            'artist' => 'Minha Cantora',
            'cover' => UploadedFile::fake()->image('capa.jpg'),
            'video' => UploadedFile::fake()->create('coreografia.mp4', 100, 'video/mp4'),
        ]);

        $video = DanceVideo::first();

        $response->assertRedirect(route('videos.show', $video));
        $this->assertDatabaseHas('dance_videos', [
            'title' => 'Minha Música',
            'artist' => 'Minha Cantora',
        ]);
        Storage::disk('public')->assertExists($video->video_path);
        $this->assertSame('/storage/'.$video->video_path, $video->video_url);
    }

    public function test_a_dance_video_can_be_edited(): void
    {
        Storage::fake('public');
        $oldCoverPath = UploadedFile::fake()->image('antiga.jpg')->store('covers', 'public');
        $video = DanceVideo::create([
            'title' => 'Título antigo',
            'artist' => 'Cantor antigo',
            'cover_path' => $oldCoverPath,
            'video_path' => UploadedFile::fake()->create('antigo.mp4', 100, 'video/mp4')->store('videos', 'public'),
        ]);

        $response = $this->put(route('videos.update', $video), [
            'title' => 'Título novo',
            'artist' => 'Cantora nova',
            'cover' => UploadedFile::fake()->image('nova.jpg'),
        ]);

        $response->assertRedirect(route('videos.show', $video));
        $this->assertDatabaseHas('dance_videos', ['title' => 'Título novo', 'artist' => 'Cantora nova']);
        Storage::disk('public')->assertMissing($oldCoverPath);
        Storage::disk('public')->assertExists($video->fresh()->cover_path);
    }
}
