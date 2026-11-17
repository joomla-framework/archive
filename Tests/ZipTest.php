<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Exception\UnsafeArchiveEntryException;
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
    public static function dataExtractionMethods(): array
    {
        return [
            'native' => ['extractNative'],
            'custom' => ['extractCustom'],
        ];
    }

    #[DataProvider('dataExtractionMethods')]
    #[TestDox('Directory entries are extracted, so empty directories are kept')]
    public function testExtractCreatesDirectoryEntries(string $method)
    {
        if (!ArchiveZip::hasNativeSupport()) {
            $this->markTestSkipped('The fixture is built with ZipArchive.');
        }

        $archive     = $this->outputPath . '/directories.zip';
        $destination = $this->outputPath . '/directories';

        $zip = new \ZipArchive();
        $zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addEmptyDir('empty');
        $zip->addFromString('file.txt', 'content');
        $zip->close();

        try {
            TestHelper::invoke(new ArchiveZip(), $method, $archive, $destination);

            $isDir = is_dir($destination . '/empty');
        } finally {
            @rmdir($destination . '/empty');
            @unlink($destination . '/file.txt');
            @rmdir($destination);
            @unlink($archive);
        }

        $this->assertTrue($isDir);
    }

    #[DataProvider('dataExtractionMethods')]
    #[TestDox('An entry outside of the destination raises an UnsafeArchiveEntryException')]
    public function testExtractThrowsUnsafeArchiveEntryException(string $method)
    {
        if ($method === 'extractNative' && !ArchiveZip::hasNativeSupport()) {
            $this->markTestSkipped('ZIP files can not be extracted natively.');
        }

        $archive     = $this->outputPath . '/unsafe.zip';
        $destination = $this->outputPath . '/unsafe';
        $object      = new ArchiveZip();

        $object->create($archive, [['name' => '../outside.txt', 'data' => 'content']]);

        $exception = null;

        try {
            TestHelper::invoke($object, $method, $archive, $destination);
        } catch (\RuntimeException $exception) {
        }

        @unlink($this->outputPath . '/outside.txt');
        @rmdir($destination);
        @unlink($archive);

        $this->assertInstanceOf(UnsafeArchiveEntryException::class, $exception);
        $this->assertInstanceOf(\OutOfBoundsException::class, $exception);
        $this->assertSame(100, $exception->getCode());
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
