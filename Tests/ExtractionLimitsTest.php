<?php

/**
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Archive;
use Joomla\Archive\Bzip2;
use Joomla\Archive\Exception\ExtractionLimitException;
use Joomla\Archive\Gzip;
use Joomla\Archive\Tar;
use Joomla\Archive\Zip;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Test class for the max_files, max_size and max_ratio options.
 */
#[CoversClass(Bzip2::class)]
#[CoversClass(Gzip::class)]
#[CoversClass(Tar::class)]
#[CoversClass(Zip::class)]
class ExtractionLimitsTest extends ArchiveTestCase
{
    /**
     * Files created by a test
     *
     * @var  string[]
     */
    private array $created = [];

    /**
     * Removes the files created by a test.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * @return  array<string, array{array, ?string}>
     */
    public static function dataLimits(): array
    {
        // Three files of 100 KiB each, highly compressible
        return [
            'no limits'           => [[], null],
            'generous limits'     => [['max_files' => 3, 'max_size' => 307200, 'max_ratio' => 10000], null],
            'too many files'      => [['max_files' => 2], 'The archive contains more than 2 entries'],
            'too much data'       => [['max_size' => 307199], 'The extracted data exceeds 307199 bytes'],
            'compressed too well' => [['max_ratio' => 10], 'The extracted data exceeds 10 times the size of the archive'],
        ];
    }

    #[DataProvider('dataLimits')]
    #[TestDox('The ZIP adapter enforces the limits natively')]
    public function testZipNative(array $options, ?string $message)
    {
        if (!Zip::hasNativeSupport()) {
            $this->markTestSkipped('ZIP files can not be extracted natively.');
        }

        $this->assertLimits('extractNative', $options, $message);
    }

    #[DataProvider('dataLimits')]
    #[TestDox('The ZIP adapter enforces the limits with the custom interface')]
    public function testZipCustom(array $options, ?string $message)
    {
        $this->assertLimits('extractCustom', $options, $message);
    }

    /**
     * @return  array<string, array{array, ?string}>
     */
    public static function dataTarLimits(): array
    {
        // A tar is not compressed, so its ratio is about 1
        return array_diff_key(self::dataLimits(), ['compressed too well' => true]);
    }

    #[DataProvider('dataTarLimits')]
    #[TestDox('The TAR adapter enforces the limits')]
    public function testTar(array $options, ?string $message)
    {
        $this->assertLimits('tar', $options, $message);
    }

    /**
     * @return  array<string, array{array, ?string}>
     */
    public static function dataSingleFileLimits(): array
    {
        // One file of 300 KiB, highly compressible
        return [
            'no limits'           => [[], null],
            'generous limits'     => [['max_files' => 1, 'max_size' => 307200, 'max_ratio' => 10000], null],
            'too much data'       => [['max_size' => 307199], 'The extracted data exceeds 307199 bytes'],
            'compressed too well' => [['max_ratio' => 10], 'The extracted data exceeds 10 times the size of the archive'],
        ];
    }

    #[DataProvider('dataSingleFileLimits')]
    #[TestDox('The gzip adapter enforces the limits')]
    public function testGzip(array $options, ?string $message)
    {
        if (!Gzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be extracted.');
        }

        $this->assertSingleFileLimits(new Gzip($options), gzencode(str_repeat('0', 307200)), $message);
    }

    #[DataProvider('dataSingleFileLimits')]
    #[TestDox('The bzip2 adapter enforces the limits')]
    public function testBzip2(array $options, ?string $message)
    {
        if (!Bzip2::isSupported()) {
            $this->markTestSkipped('Bzip2 files can not be extracted.');
        }

        $this->assertSingleFileLimits(new Bzip2($options), bzcompress(str_repeat('0', 307200)), $message);
    }

    #[TestDox('Corrupt data is still reported as such when limits are set')]
    public function testCorruptDataWithLimits()
    {
        $archive = $this->outputPath . '/corrupt.gz';
        file_put_contents($archive, substr(gzencode(str_repeat('0', 307200)), 0, 30));
        $this->created[] = $archive;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to decompress data');

        (new Gzip(['max_size' => 1000000]))->extract($archive, $this->outputPath . '/corrupt.txt');
    }

    #[TestDox('Archive passes the limits on to the adapters, also for a tarball')]
    public function testArchivePassesLimits()
    {
        $tar = file_get_contents($this->inputPath . '/logo.tar');

        $archive = $this->outputPath . '/limits.tar.gz';
        file_put_contents($archive, gzencode($tar));
        $this->created[] = $archive;

        $this->created[] = $this->outputPath . '/limits';
        $this->created[] = $this->outputPath . '/limits/logo-tar.png';

        // Archive::extract() reports gzip errors with false (joomla-framework/archive#62 lets them propagate)
        try {
            $result = (new Archive(['max_size' => 1000]))->extract($archive, $this->outputPath . '/limits');
        } catch (ExtractionLimitException $exception) {
            $result = false;
        }

        $this->assertFalse($result);
        $this->assertFileDoesNotExist($this->outputPath . '/limits/logo-tar.png');
    }

    /**
     * Build an archive with three files of 100 KiB, extract it with the given method and check the outcome.
     *
     * @param   string       $method   extractNative, extractCustom or tar
     * @param   array        $options  The adapter options
     * @param   string|null  $message  The expected exception message, null if the extraction must succeed
     *
     * @return  void
     */
    private function assertLimits(string $method, array $options, ?string $message): void
    {
        $destination     = $this->outputPath . '/limits';
        $this->created[] = $destination;
        $files           = [];

        foreach (['a.txt', 'b.txt', 'c.txt'] as $name) {
            $files[]         = ['name' => $name, 'data' => str_repeat('0', 102400)];
            $this->created[] = $destination . '/' . $name;
        }

        if ($method === 'tar') {
            $archive = $this->outputPath . '/limits.tar';
            $data    = '';

            foreach ($files as $file) {
                $header = pack(
                    'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
                    $file['name'],
                    '0000644',
                    '0000000',
                    '0000000',
                    sprintf('%011o', \strlen($file['data'])),
                    sprintf('%011o', 1700000000),
                    '        ',
                    '0',
                    '',
                    'ustar',
                    '00',
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                );
                $data .= $header . str_pad($file['data'], (int) ceil(\strlen($file['data']) / 512) * 512, \chr(0));
            }

            file_put_contents($archive, $data . str_repeat(\chr(0), 1024));
            $object = new Tar($options);
        } else {
            $archive = $this->outputPath . '/limits.zip';
            $object  = new Zip($options);
            $object->create($archive, $files);
        }

        array_unshift($this->created, $archive);

        if ($message !== null) {
            $this->expectException(ExtractionLimitException::class);
            $this->expectExceptionMessage($message);
        }

        if ($method === 'tar') {
            $object->extract($archive, $destination);
        } else {
            TestHelper::invoke($object, $method, $archive, $destination);
        }

        foreach ($files as $file) {
            $this->assertStringEqualsFile($destination . '/' . $file['name'], $file['data']);
        }
    }

    /**
     * Extract a gzip or bzip2 file holding 300 KiB and check the outcome.
     *
     * @param   Gzip|Bzip2   $object      The adapter
     * @param   string       $compressed  The compressed data
     * @param   string|null  $message     The expected exception message, null if the extraction must succeed
     *
     * @return  void
     */
    private function assertSingleFileLimits(Gzip|Bzip2 $object, string $compressed, ?string $message): void
    {
        $archive     = $this->outputPath . '/limits.bin';
        $destination = $this->outputPath . '/limits.txt';

        file_put_contents($archive, $compressed);
        $this->created[] = $archive;
        $this->created[] = $destination;

        if ($message !== null) {
            $this->expectException(ExtractionLimitException::class);
            $this->expectExceptionMessage($message);
        }

        $object->extract($archive, $destination);

        $this->assertStringEqualsFile($destination, str_repeat('0', 307200));
    }
}
