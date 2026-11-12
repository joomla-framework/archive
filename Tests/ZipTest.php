<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Zip as ArchiveZip;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Test class for Joomla\Archive\Zip.
 */
#[CoversClass(ArchiveZip::class)]
class ZipTest extends ArchiveTestCase
{
    #[TestDox('The zip adapter is instantiated correctly')]
    public function test__construct()
    {
        $object = new ArchiveZip();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['use_streams' => false];
        $object  = new ArchiveZip($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    #[TestDox('An archive can be created')]
    public function testCreate()
    {
        $object = new ArchiveZip();

        $result = $object->create(
            $this->outputPath . '/logo.zip',
            [
                [
                    'name' => 'logo.png',
                    'data' => file_get_contents($this->inputPath . '/logo.png'),
                ],
            ]
        );

        $this->assertTrue($result);

        $dataZip = file_get_contents($this->outputPath . '/logo.zip');
        $this->assertTrue(
            $object->checkZipData($dataZip)
        );

        @unlink($this->outputPath . '/logo.zip');
    }

    #[TestDox('An archive can be extracted natively')]
    public function testExtractNative()
    {
        if (!ArchiveZip::hasNativeSupport()) {
            $this->markTestSkipped('ZIP files can not be extracted natively.');
        }

        $object = new ArchiveZip();

        TestHelper::invoke(
            $object,
            'extractNative',
            $this->inputPath . '/logo.zip',
            $this->outputPath
        );

        $this->assertFileExists($this->outputPath . '/logo-zip.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-zip.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-zip.png');
    }

    #[TestDox('An archive can be extracted with the custom interface')]
    public function testExtractCustom()
    {
        if (!ArchiveZip::isSupported()) {
            $this->markTestSkipped('ZIP files can not be extracted.');
        }

        $object = new ArchiveZip();

        TestHelper::invoke(
            $object,
            'extractCustom',
            $this->inputPath . '/logo.zip',
            $this->outputPath
        );

        $this->assertFileExists($this->outputPath . '/logo-zip.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-zip.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-zip.png');
    }

    #[TestDox('An archive can be extracted')]
    public function testExtract()
    {
        if (!ArchiveZip::isSupported()) {
            $this->markTestSkipped('ZIP files can not be extracted.');

            return;
        }

        $object = new ArchiveZip();

        $object->extract(
            $this->inputPath . '/logo.zip',
            $this->outputPath
        );

        $this->assertFileExists($this->outputPath . '/logo-zip.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-zip.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-zip.png');
    }

    #[TestDox('If the archive cannot be found an Exception is thrown')]
    public function testExtractException()
    {
        $this->expectException(\RuntimeException::class);

        $object = new ArchiveZip();

        $object->extract(
            $this->inputPath . '/foobar.zip',
            $this->outputPath
        );
    }

    #[TestDox('The adapter detects if the environment has native support')]
    public function testHasNativeSupport()
    {
        $this->assertEquals(
            extension_loaded('zip'),
            ArchiveZip::hasNativeSupport()
        );
    }

    #[Depends('testHasNativeSupport')]
    #[TestDox('The adapter detects if the environment is supported')]
    public function testIsSupported()
    {
        $this->assertEquals(
            ArchiveZip::hasNativeSupport() || extension_loaded('zlib'),
            ArchiveZip::isSupported()
        );
    }

    #[TestDox('The custom interface extracts bzip2 compressed entries')]
    public function testExtractCustomBzip2Entry()
    {
        if (!extension_loaded('bz2')) {
            $this->markTestSkipped('The bz2 extension is not available.');
        }

        $content = str_repeat('bzip2 compressed content ', 10);

        $this->assertSame(
            ['file.txt' => $content],
            $this->extractCustom([['name' => 'file.txt', 'method' => 12, 'data' => bzcompress($content), 'content' => $content]])
        );
    }

    /**
     * @return  array<string, array{array, string}>
     */
    public static function dataUnreadableEntries(): array
    {
        $content = 'some content';

        return [
            'unsupported method (LZMA)' => [
                ['name' => 'file.txt', 'method' => 14, 'data' => 'lzma data', 'content' => $content],
                'Unsupported compression method 14 (LZMA) of the ZIP entry file.txt',
            ],
            'unknown method' => [
                ['name' => 'file.txt', 'method' => 99, 'data' => 'data', 'content' => $content],
                'Unsupported compression method 99 (Unknown) of the ZIP entry file.txt',
            ],
            'encrypted entry' => [
                ['name' => 'file.txt', 'method' => 8, 'flags' => 1, 'data' => gzdeflate($content), 'content' => $content],
                'Unable to extract the encrypted ZIP entry file.txt',
            ],
            'corrupt deflate data' => [
                ['name' => 'file.txt', 'method' => 8, 'data' => 'not deflated', 'content' => $content],
                'Unable to decompress the ZIP entry file.txt',
            ],
            'CRC32 mismatch' => [
                ['name' => 'file.txt', 'method' => 0, 'data' => $content, 'content' => $content, 'crc' => 12345],
                'CRC32 mismatch in the ZIP entry file.txt',
            ],
        ];
    }

    #[DataProvider('dataUnreadableEntries')]
    #[TestDox('The custom interface reports entries it cannot extract instead of writing empty files')]
    public function testExtractCustomUnreadableEntry(array $entry, string $message)
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->extractCustom([$entry]);
    }

    #[TestDox('The custom interface rejects data that is not a ZIP archive with a RuntimeException')]
    public function testExtractCustomNotAZipArchive()
    {
        $archive = $this->outputPath . '/not-a-zip.zip';

        file_put_contents($archive, str_repeat('This is not a ZIP archive. ', 10));

        try {
            TestHelper::invoke(new ArchiveZip(), 'extractCustom', $archive, $this->outputPath . '/not-a-zip');

            $exception = null;
        } catch (\Throwable $exception) {
        }

        @unlink($archive);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Invalid ZIP Data', $exception->getMessage());
    }

    /**
     * Build a ZIP archive from raw entries, extract it with the custom interface and return the extracted files.
     *
     * @param   array  $entries  Entries with the keys name, method, data (as stored), content (uncompressed), and
     *                           optionally flags and crc
     *
     * @return  array<string, string>  The contents of the extracted files keyed by name
     */
    private function extractCustom(array $entries): array
    {
        $archive     = $this->outputPath . '/raw.zip';
        $destination = $this->outputPath . '/raw';
        $local       = '';
        $central     = '';

        foreach ($entries as $entry) {
            $common = pack(
                'vvvVVVVv',
                20,
                $entry['flags'] ?? 0,
                $entry['method'],
                0x21000000,
                $entry['crc'] ?? crc32($entry['content']),
                \strlen($entry['data']),
                \strlen($entry['content']),
                \strlen($entry['name'])
            );

            $central .= 'PK' . chr(1) . chr(2) . pack('v', 20) . $common . pack('vvvvVV', 0, 0, 0, 0, 0, \strlen($local)) . $entry['name'];
            $local   .= 'PK' . chr(3) . chr(4) . $common . pack('v', 0) . $entry['name'] . $entry['data'];
        }

        $eocd = 'PK' . chr(5) . chr(6) . pack('vvvvVVv', 0, 0, \count($entries), \count($entries), \strlen($central), \strlen($local), 0);

        file_put_contents($archive, $local . $central . $eocd);

        $files = [];

        try {
            TestHelper::invoke(new ArchiveZip(), 'extractCustom', $archive, $destination);

            foreach ($entries as $entry) {
                $files[$entry['name']] = @file_get_contents($destination . '/' . $entry['name']);
            }
        } finally {
            foreach ($entries as $entry) {
                @unlink($destination . '/' . $entry['name']);
            }

            @rmdir($destination);
            @unlink($archive);
        }

        return $files;
    }

    #[TestDox('The adapter correctly checks ZIP data')]
    public function testCheckZipData()
    {
        $object = new ArchiveZip();

        $dataZip = file_get_contents($this->inputPath . '/logo.zip');
        $this->assertTrue(
            $object->checkZipData($dataZip)
        );

        $dataTar = file_get_contents($this->inputPath . '/logo.tar');
        $this->assertFalse(
            $object->checkZipData($dataTar)
        );
    }
}
