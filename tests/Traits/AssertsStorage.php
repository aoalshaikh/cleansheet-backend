<?php

namespace Tests\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait AssertsStorage
{
    protected function assertFileExists(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertTrue(
            $storage->exists($path),
            "Failed asserting that file '{$path}' exists."
        );
    }

    protected function assertFileNotExists(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertFalse(
            $storage->exists($path),
            "Failed asserting that file '{$path}' does not exist."
        );
    }

    protected function assertFileDeleted(string $path, string $disk = null): void
    {
        $this->assertFileNotExists($path, $disk);
    }

    protected function assertFileUploaded(UploadedFile $file, string $path, string $disk = null): void
    {
        $this->assertFileExists($path, $disk);
        
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertEquals(
            $file->getSize(),
            $storage->size($path),
            "Failed asserting that uploaded file size matches."
        );
    }

    protected function assertFileContains(string $path, string $content, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertStringContainsString(
            $content,
            $storage->get($path),
            "Failed asserting that file '{$path}' contains '{$content}'."
        );
    }

    protected function assertFileEquals(string $path, string $expectedContent, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertEquals(
            $expectedContent,
            $storage->get($path),
            "Failed asserting that file '{$path}' equals expected content."
        );
    }

    protected function assertDirectoryExists(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertTrue(
            $storage->exists($path) && count($storage->files($path)) >= 0,
            "Failed asserting that directory '{$path}' exists."
        );
    }

    protected function assertDirectoryNotExists(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertFalse(
            $storage->exists($path),
            "Failed asserting that directory '{$path}' does not exist."
        );
    }

    protected function assertDirectoryEmpty(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertEmpty(
            $storage->files($path),
            "Failed asserting that directory '{$path}' is empty."
        );
    }

    protected function assertDirectoryNotEmpty(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertNotEmpty(
            $storage->files($path),
            "Failed asserting that directory '{$path}' is not empty."
        );
    }

    protected function assertFileCount(string $path, int $count, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertCount(
            $count,
            $storage->files($path),
            "Failed asserting that directory '{$path}' contains {$count} files."
        );
    }

    protected function assertFileSize(string $path, int $size, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $this->assertEquals(
            $size,
            $storage->size($path),
            "Failed asserting that file '{$path}' has size {$size}."
        );
    }

    protected function assertFileIsImage(string $path, string $disk = null): void
    {
        $storage = $disk ? Storage::disk($disk) : Storage::fake('public');
        $content = $storage->get($path);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);
        
        $this->assertStringStartsWith(
            'image/',
            $mimeType,
            "Failed asserting that file '{$path}' is an image."
        );
    }
}
