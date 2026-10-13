<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Library\Adapters\Storage\DiskCoverStorage;
use Library\Application\Exceptions\CoverTooLargeException;
use Tests\TestCase;

final class DiskCoverStorageTest extends TestCase
{
    use RefreshDatabase;

    private DiskCoverStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->storage = new DiskCoverStorage();
    }

    /** @test */
    public function it_stores_a_png_cover(): void
    {
        $content = str_repeat('x', 1024); // 1 KB
        $key = $this->storage->store($content, 'image/png');

        $this->assertStringStartsWith('covers/', $key);
        $this->assertStringEndsWith('.png', $key);
        $this->assertTrue($this->storage->exists($key));
    }

    /** @test */
    public function it_stores_a_jpeg_cover(): void
    {
        $content = str_repeat('x', 1024);
        $key = $this->storage->store($content, 'image/jpeg');

        $this->assertStringEndsWith('.jpg', $key);
        $this->assertTrue($this->storage->exists($key));
    }

    /** @test */
    public function it_accepts_2mb_file(): void
    {
        $content = str_repeat('x', 2 * 1024 * 1024); // Exactly 2 MB
        $key = $this->storage->store($content, 'image/png');

        $this->assertTrue($this->storage->exists($key));
    }

    /** @test */
    public function it_rejects_file_larger_than_2mb(): void
    {
        $content = str_repeat('x', (2 * 1024 * 1024) + 1); // 2 MB + 1 byte

        $this->expectException(CoverTooLargeException::class);
        $this->expectExceptionMessage('La portada excede el tamaño máximo de 2 MB');

        $this->storage->store($content, 'image/png');
    }

    /** @test */
    public function it_rejects_3mb_file(): void
    {
        $content = str_repeat('x', 3 * 1024 * 1024); // 3 MB

        $this->expectException(CoverTooLargeException::class);

        $this->storage->store($content, 'image/png');
    }

    /** @test */
    public function it_rejects_invalid_mime_type(): void
    {
        $content = str_repeat('x', 1024);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo de archivo no permitido');

        $this->storage->store($content, 'application/pdf');
    }

    /** @test */
    public function it_deletes_a_cover(): void
    {
        $content = str_repeat('x', 1024);
        $key = $this->storage->store($content, 'image/png');

        $this->assertTrue($this->storage->exists($key));

        $this->storage->delete($key);

        $this->assertFalse($this->storage->exists($key));
    }

    /** @test */
    public function it_generates_url_for_cover(): void
    {
        $content = str_repeat('x', 1024);
        $key = $this->storage->store($content, 'image/png');

        $url = $this->storage->getUrl($key);

        $this->assertStringContainsString($key, $url);
    }
}
