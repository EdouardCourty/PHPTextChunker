<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Reader;

use Ecourty\TextChunker\Exception\SourceNotFoundException;
use Ecourty\TextChunker\Exception\SourceNotReadableException;
use Ecourty\TextChunker\Reader\LocalFileReader;
use PHPUnit\Framework\TestCase;

class LocalFileReaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'chunker_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testReadsFileContentInChunks(): void
    {
        file_put_contents($this->tempFile, 'Hello World');

        $reader = new LocalFileReader();
        $chunks = iterator_to_array($reader->readChunks($this->tempFile, 8192));

        $this->assertSame('Hello World', implode('', $chunks));
    }

    public function testReadsLargeFileAcrossMultipleChunks(): void
    {
        $content = str_repeat('a', 20000);
        file_put_contents($this->tempFile, $content);

        $reader = new LocalFileReader();
        $chunks = iterator_to_array($reader->readChunks($this->tempFile, 8192));

        $this->assertGreaterThan(1, \count($chunks));
        $this->assertSame($content, implode('', $chunks));
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(SourceNotFoundException::class);

        $reader = new LocalFileReader();
        iterator_to_array($reader->readChunks('/non/existent/file.txt', 8192));
    }

    public function testThrowsOnUnreadableFile(): void
    {
        chmod($this->tempFile, 0000);

        // Skip if the process can still read the file despite chmod (e.g. running as root)
        if (is_readable($this->tempFile)) {
            $this->markTestSkipped('Cannot test unreadable files when process bypasses file permissions.');
        }

        $this->expectException(SourceNotReadableException::class);

        $reader = new LocalFileReader();
        iterator_to_array($reader->readChunks($this->tempFile, 8192));
    }
}
