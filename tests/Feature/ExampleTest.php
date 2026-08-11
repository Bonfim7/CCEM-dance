<?php

namespace Tests\Feature;

use App\Models\DanceVideo;
use App\Services\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_publication_form_is_available_and_posts_to_the_store_route(): void
    {
        $response = $this->get(route('videos.create'));

        $response->assertOk()
            ->assertSee('action="'.route('videos.store').'"', false)
            ->assertSee('method="post"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="video"', false)
            ->assertSee('data-loading-text="Enviando vídeo…"', false);
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
            'video_original_name' => 'coreografia.mp4',
            'video_mime_type' => 'video/mp4',
        ]);
        $this->assertMatchesRegularExpression(
            '/^videos\/[0-9a-f-]{36}\.mp4$/',
            $video->video_path,
        );
        Storage::disk('public')->assertExists($video->cover_path);
        Storage::disk('public')->assertExists($video->video_path);
        $this->assertSame('/storage/'.$video->video_path, $video->video_url);
    }

    public function test_production_upload_cannot_fall_back_to_local_storage(): void
    {
        Storage::fake('public');
        config([
            'filesystems.default' => 'public',
            'media.require_cloud_disk' => true,
        ]);

        $response = $this->from(route('videos.create'))->post(route('videos.store'), [
            'title' => 'Sem fallback local',
            'artist' => 'CCEM',
            'cover' => UploadedFile::fake()->image('capa.jpg'),
            'video' => UploadedFile::fake()->create('video.mp4', 100, 'video/mp4'),
        ]);

        $response->assertRedirect(route('videos.create'))
            ->assertSessionHasErrors('video');
        $this->assertDatabaseCount('dance_videos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_published_video_appears_in_the_listing(): void
    {
        $video = DanceVideo::create([
            'title' => 'Alegria do CCEM',
            'artist' => 'Ministério de Dança',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($video->title)
            ->assertSee($video->artist)
            ->assertSee(route('videos.show', $video));
    }

    public function test_publication_validation_returns_clear_feedback(): void
    {
        Storage::fake('public');

        $response = $this->from(route('videos.create'))->post(route('videos.store'), [
            'title' => 'Arquivo inválido',
            'artist' => 'CCEM',
            'cover' => UploadedFile::fake()->create('capa.txt', 10, 'text/plain'),
            'video' => UploadedFile::fake()->create('video.txt', 10, 'text/plain'),
        ]);

        $response->assertRedirect(route('videos.create'))
            ->assertSessionHasErrors(['cover', 'video']);
        $this->assertDatabaseCount('dance_videos', 0);
    }

    public function test_a_dance_video_can_be_edited(): void
    {
        Storage::fake('public');
        $oldCoverPath = UploadedFile::fake()->image('antiga.jpg')->storeAs('covers', 'antiga.jpg', 'public');
        $oldVideoPath = UploadedFile::fake()->create('antigo.mp4', 100, 'video/mp4')
            ->storeAs('videos', 'antigo.mp4', 'public');
        $video = DanceVideo::create([
            'title' => 'Título antigo',
            'artist' => 'Cantor antigo',
            'cover_path' => $oldCoverPath,
            'video_path' => $oldVideoPath,
        ]);

        $response = $this->put(route('videos.update', $video), [
            'title' => 'Título novo',
            'artist' => 'Cantora nova',
            'cover' => UploadedFile::fake()->image('nova.jpg'),
            'video_file' => UploadedFile::fake()->create('novo.mp4', 120, 'video/mp4'),
        ]);

        $response->assertRedirect(route('videos.show', $video));
        $this->assertDatabaseHas('dance_videos', [
            'title' => 'Título novo',
            'artist' => 'Cantora nova',
            'video_original_name' => 'novo.mp4',
        ]);
        Storage::disk('public')->assertMissing($oldCoverPath);
        Storage::disk('public')->assertMissing($oldVideoPath);
        Storage::disk('public')->assertExists($video->fresh()->cover_path);
        Storage::disk('public')->assertExists($video->fresh()->video_path);
    }

    public function test_a_dance_video_and_its_files_can_be_deleted(): void
    {
        Storage::fake('public');
        $coverPath = UploadedFile::fake()->image('capa.jpg')->storeAs('covers', 'capa.jpg', 'public');
        $videoPath = UploadedFile::fake()->create('video.mp4', 100, 'video/mp4')
            ->storeAs('videos', 'video.mp4', 'public');
        $video = DanceVideo::create([
            'title' => 'Música para excluir',
            'artist' => 'CCEM',
            'cover_path' => $coverPath,
            'video_path' => $videoPath,
        ]);

        $response = $this->delete(route('videos.destroy', $video));

        $response->assertRedirect(route('home'));
        $this->assertDatabaseMissing('dance_videos', ['id' => $video->id]);
        Storage::disk('public')->assertMissing($coverPath);
        Storage::disk('public')->assertMissing($videoPath);
    }

    public function test_a_storage_failure_does_not_create_an_incomplete_record(): void
    {
        $storage = Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('store')
            ->once()
            ->with(Mockery::type(UploadedFile::class), 'covers')
            ->andReturn('covers/550e8400-e29b-41d4-a716-446655440000.jpg');
        $storage->shouldReceive('store')
            ->once()
            ->with(Mockery::type(UploadedFile::class), 'videos')
            ->andThrow(new \RuntimeException('R2 indisponível'));
        $storage->shouldReceive('delete')
            ->once()
            ->with(['covers/550e8400-e29b-41d4-a716-446655440000.jpg']);
        $storage->shouldReceive('diskName')->twice()->andReturn('s3');
        $this->app->instance(MediaStorage::class, $storage);

        $response = $this->from(route('videos.create'))->post(route('videos.store'), [
            'title' => 'Upload com falha',
            'artist' => 'CCEM',
            'cover' => UploadedFile::fake()->image('capa.jpg'),
            'video' => UploadedFile::fake()->create('video.mp4', 100, 'video/mp4'),
        ]);

        $response->assertRedirect(route('videos.create'));
        $response->assertSessionHasErrors('video');
        $this->assertDatabaseCount('dance_videos', 0);
    }

    public function test_an_r2_video_receives_a_temporary_signed_url(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3.key' => 'fake-access-key',
            'filesystems.disks.s3.secret' => 'fake-secret-key',
            'filesystems.disks.s3.region' => 'auto',
            'filesystems.disks.s3.bucket' => 'ccem-dance-videos',
            'filesystems.disks.s3.endpoint' => 'https://example-account.r2.cloudflarestorage.com',
        ]);
        Storage::forgetDisk('s3');

        $url = app(MediaStorage::class)->url('videos/550e8400-e29b-41d4-a716-446655440000.mp4');

        $this->assertStringStartsWith(
            'https://ccem-dance-videos.example-account.r2.cloudflarestorage.com/videos/',
            $url,
        );
        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }

    public function test_an_r2_download_redirects_to_storage_without_reopening_the_video(): void
    {
        $video = DanceVideo::create([
            'title' => 'Vontade do Pai',
            'artist' => 'Aline Barros',
            'video_path' => 'videos/550e8400-e29b-41d4-a716-446655440000.mp4',
        ]);
        $storage = Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('temporaryDownloadUrl')
            ->once()
            ->with($video->video_path, 'vontade-do-pai-aline-barros.mp4')
            ->andReturn('https://example.r2.cloudflarestorage.com/signed-download');
        $storage->shouldReceive('diskName')->once()->andReturn('s3');
        $storage->shouldNotReceive('readStream');
        $this->app->instance(MediaStorage::class, $storage);

        $this->get(route('videos.download', $video))
            ->assertRedirect('https://example.r2.cloudflarestorage.com/signed-download');
    }

    public function test_a_storage_deletion_failure_keeps_the_database_record(): void
    {
        $video = DanceVideo::create([
            'title' => 'Música protegida',
            'artist' => 'CCEM',
            'cover_path' => 'covers/capa.jpg',
            'video_path' => 'videos/video.mp4',
        ]);
        $storage = Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('delete')
            ->once()
            ->with(['covers/capa.jpg', 'videos/video.mp4'])
            ->andThrow(new \RuntimeException('R2 indisponível'));
        $storage->shouldReceive('diskName')->once()->andReturn('s3');
        $this->app->instance(MediaStorage::class, $storage);

        $response = $this->from(route('videos.edit', $video))
            ->delete(route('videos.destroy', $video));

        $response->assertRedirect(route('videos.edit', $video));
        $response->assertSessionHasErrors('video');
        $this->assertDatabaseHas('dance_videos', ['id' => $video->id]);
    }

    public function test_the_media_diagnostic_checks_put_exists_and_delete(): void
    {
        Storage::fake('public');

        $this->artisan('media:diagnose', ['--connection' => true])
            ->expectsOutputToContain('PUT')
            ->expectsOutputToContain('EXISTS')
            ->expectsOutputToContain('READ')
            ->expectsOutputToContain('DELETE')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('public')->allFiles('diagnostics'));
    }
}
