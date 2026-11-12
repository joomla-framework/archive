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

    /**
     * @return  array<string, array{string}>
     */
    public static function dataListContents(): array
    {
        return [
            'native' => ['native'],
            'custom' => ['custom'],
        ];
    }

    #[DataProvider('dataListContents')]
    #[TestDox('The entries of an archive can be listed without extracting it')]
    public function testListContents(string $mode)
    {
        if (!ArchiveZip::hasNativeSupport()) {
            $this->markTestSkipped('The fixture is built with ZipArchive.');
        }

        $archive = $this->outputPath . '/list.zip';

        $zip = new \ZipArchive();
        $zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addEmptyDir('dir');
        $zip->addFromString('dir/file.txt', 'content');
        $zip->addFromString('../outside.txt', 'x');
        $zip->close();

        $object = $mode === 'native' ? new ArchiveZip() : new class () extends ArchiveZip {
            public static function hasNativeSupport()
            {
                return false;
            }
        };

        try {
            $entries = $object->listContents($archive);
        } finally {
            @unlink($archive);
        }

        $this->assertSame(
            [
                ['name' => 'dir/', 'size' => 0, 'type' => 'directory'],
                ['name' => 'dir/file.txt', 'size' => 7, 'type' => 'file'],
                ['name' => '../outside.txt', 'size' => 1, 'type' => 'file'],
            ],
            $entries
        );
    }

    #[TestDox('Listing a missing archive throws an Exception')]
    public function testListContentsMissingArchive()
    {
        $this->expectException(\RuntimeException::class);

        (new ArchiveZip())->listContents($this->inputPath . '/foobar.zip');
    }

    /**
     * @return  array<string, array{string, bool}>
     */
    public static function dataCheckZipData(): array
    {
        $created = 'PK' . chr(3) . chr(4);

        return [
            'text containing the local file header signature' => ['Some text ' . $created . ' with a signature', false],
            'too short'                                        => ['PK', false],
            'empty archive'                                    => ['PK' . chr(5) . chr(6) . str_repeat(chr(0), 18), true],
            'empty archive with a comment'                     => ['PK' . chr(5) . chr(6) . str_repeat(chr(0), 16) . pack('v', 3) . 'abc', true],
            'record pointing outside the data'                 => ['PK' . chr(5) . chr(6) . str_repeat(chr(0), 4) . pack('vvVVv', 1, 1, 46, 1000, 0), false],
        ];
    }

    #[DataProvider('dataCheckZipData')]
    #[TestDox('ZIP data is recognised by its end of central directory record')]
    public function testCheckZipDataUsesTheEndOfCentralDirectory(string $data, bool $expected)
    {
        $this->assertSame($expected, (new ArchiveZip())->checkZipData($data));
    }

    #[TestDox('A truncated ZIP archive is not valid ZIP data')]
    public function testCheckZipDataRejectsTruncatedArchives()
    {
        $data = file_get_contents($this->inputPath . '/logo.zip');

        $this->assertFalse((new ArchiveZip())->checkZipData(substr($data, 0, -10)));
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
