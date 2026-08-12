<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Zip as ArchiveZip;
use Joomla\Test\TestHelper;

/**
 * Test class for Joomla\Archive\Zip.
 */
class ZipTest extends ArchiveTestCase
{
    /**
     * @testdox  The zip adapter is instantiated correctly
     *
     * @covers   Joomla\Archive\Zip
     */
    public function test__construct()
    {
        $object = new ArchiveZip();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['use_streams' => false];
        $object  = new ArchiveZip($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    /**
     * @testdox  An archive can be created
     *
     * @covers   Joomla\Archive\Zip
     */
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

    /**
     * @testdox  An archive can be extracted natively
     *
     * @covers   Joomla\Archive\Zip
     */
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

    /**
     * @testdox  An archive can be extracted with the custom interface
     *
     * @covers   Joomla\Archive\Zip
     */
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

    /**
     * @testdox  An archive can be extracted
     *
     * @covers   Joomla\Archive\Zip
     */
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

    /**
     * @testdox  If the archive cannot be found an Exception is thrown
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testExtractException()
    {
        $this->expectException(\RuntimeException::class);

        $object = new ArchiveZip();

        $object->extract(
            $this->inputPath . '/foobar.zip',
            $this->outputPath
        );
    }

    /**
     * @testdox  The adapter detects if the environment has native support
     *
     * @covers   Joomla\Archive\Zip::hasNativeSupport
     */
    public function testHasNativeSupport()
    {
        $this->assertEquals(
            extension_loaded('zip'),
            ArchiveZip::hasNativeSupport()
        );
    }

    /**
     * @testdox  The adapter detects if the environment is supported
     *
     * @covers   Joomla\Archive\Zip
     * @depends  testHasNativeSupport
     */
    public function testIsSupported()
    {
        $this->assertEquals(
            ArchiveZip::hasNativeSupport() || extension_loaded('zlib'),
            ArchiveZip::isSupported()
        );
    }

    /**
     * @testdox  The adapter correctly checks ZIP data
     *
     * @covers   Joomla\Archive\Zip
     */
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

    /**
     * @testdox  A created archive can be read back entry for entry
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateRoundTripsSeveralEntries()
    {
        $object  = new ArchiveZip();
        $archive = $this->outputPath . '/multi.zip';

        $files = [
            ['name' => 'readme.txt',     'data' => "Hello world\n"],
            ['name' => 'dir/nested.txt', 'data' => "Nested\n"],
            ['name' => 'logo.png',       'data' => file_get_contents($this->inputPath . '/logo.png')],
        ];

        $object->create($archive, $files);

        $destination = $this->outputPath . '/multi';
        $object->extract($archive, $destination);

        $this->assertStringEqualsFile($destination . '/readme.txt', "Hello world\n");
        $this->assertStringEqualsFile($destination . '/dir/nested.txt', "Nested\n");
        $this->assertFileEquals($this->inputPath . '/logo.png', $destination . '/logo.png');

        $this->cleanUp([$archive], [$destination]);
    }

    /**
     * @testdox  Entry offsets in the central directory point at the matching local headers
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateWritesCorrectEntryOffsets()
    {
        if (!ArchiveZip::hasNativeSupport()) {
            $this->markTestSkipped('Reading the central directory back needs ext-zip.');
        }

        $object  = new ArchiveZip();
        $archive = $this->outputPath . '/offsets.zip';

        // Varying, incompressible payloads so every entry starts at a different offset
        $files = [];

        for ($i = 0; $i < 12; $i++) {
            $files[] = ['name' => 'entry' . $i . '.bin', 'data' => random_bytes(500 + $i * 97)];
        }

        $object->create($archive, $files);

        $zip = new \ZipArchive();

        $this->assertTrue(
            $zip->open($archive, \ZipArchive::CHECKCONS) === true,
            'The archive has to pass a consistency check, which verifies the recorded offsets'
        );

        foreach ($files as $index => $file) {
            $this->assertSame(
                $file['data'],
                $zip->getFromIndex($index),
                'Entry ' . $index . ' has to be readable through its recorded offset'
            );
        }

        $zip->close();

        @unlink($archive);
    }

    /**
     * @testdox  A non-ASCII entry name is flagged as UTF-8
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateFlagsNonAsciiNamesAsUtf8()
    {
        $object  = new ArchiveZip();
        $archive = $this->outputPath . '/utf8.zip';

        $object->create($archive, [['name' => 'Größe.txt', 'data' => 'x']]);

        $raw = file_get_contents($archive);

        // Bit 11 of the general purpose bit flag; without it the name is read as CP437
        $this->assertSame(
            0x0800,
            unpack('v', substr($raw, 6, 2))[1] & 0x0800,
            'The local file header has to declare the name as UTF-8'
        );

        $central = strpos($raw, "\x50\x4b\x01\x02");

        $this->assertSame(
            0x0800,
            unpack('v', substr($raw, $central + 8, 2))[1] & 0x0800,
            'The central directory has to agree with the local header'
        );

        // The host system decides whether a reader translates the name from CP437 first
        $this->assertSame(
            3,
            unpack('v', substr($raw, $central + 4, 2))[1] >> 8,
            'The host system has to be Unix, otherwise readers re-encode the name and corrupt it'
        );

        @unlink($archive);
    }

    /**
     * @testdox  A pure ASCII entry name is left unflagged
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateDoesNotFlagAsciiNames()
    {
        $object  = new ArchiveZip();
        $archive = $this->outputPath . '/ascii.zip';

        $object->create($archive, [['name' => 'plain.txt', 'data' => 'x']]);

        $this->assertSame(
            0,
            unpack('v', substr(file_get_contents($archive), 6, 2))[1],
            'ASCII is common to CP437 and UTF-8, so the flag stays clear'
        );

        @unlink($archive);
    }

    /**
     * @testdox  A timestamp the DOS format has to clamp does not corrupt the header
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateHandlesTimestampsBeforeTheDosEpoch()
    {
        $object  = new ArchiveZip();
        $archive = $this->outputPath . '/epoch.zip';

        /*
         * The DOS timestamp for 1980-01-01 needs only six hex digits. Slicing the value up as a hex
         * string, as this used to do, read past the end for every such date.
         */
        $object->create($archive, [['name' => 'a.txt', 'data' => 'x', 'time' => 0]]);

        $raw = file_get_contents($archive);

        // 1980-01-01 00:00:00 is 0x00210000 in the DOS format, stored little endian
        $this->assertSame(
            0x00210000,
            unpack('V', substr($raw, 10, 4))[1],
            'The clamped timestamp has to be written in full'
        );

        $destination = $this->outputPath . '/epoch';
        $object->extract($archive, $destination);

        $this->assertStringEqualsFile($destination . '/a.txt', 'x');

        $this->cleanUp([$archive], [$destination]);
    }

    /**
     * @testdox  A directory entry records a directory mode
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateWritesDirectoryEntries()
    {
        $object  = new ArchiveZip();
        $archive = $this->outputPath . '/dir.zip';

        $object->create($archive, [
            ['name' => 'emptydir/'],
            ['name' => 'emptydir/file.txt', 'data' => 'x'],
        ]);

        $raw     = file_get_contents($archive);
        $central = strpos($raw, "\x50\x4b\x01\x02");

        // With a Unix host the high word of the external attributes is the mode
        $attributes = unpack('V', substr($raw, $central + 38, 4))[1];

        $this->assertSame(040755, $attributes >> 16, 'The directory entry carries a directory mode');
        $this->assertSame(0x10, $attributes & 0xFF, 'The DOS directory bit is set as well');

        @unlink($archive);
    }

    /**
     * @testdox  An entry without a name is rejected
     *
     * @covers   Joomla\Archive\Zip
     */
    public function testCreateRejectsAnEntryWithoutAName()
    {
        $object = new ArchiveZip();

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/nameless.zip', [['data' => 'nope']]);
    }

    /**
     * Remove files and directories created by a test.
     *
     * @param   string[]  $files        Files to unlink.
     * @param   string[]  $directories  Directories to remove recursively.
     *
     * @return  void
     */
    private function cleanUp(array $files, array $directories): void
    {
        foreach ($files as $file) {
            @unlink($file);
        }

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }

            @rmdir($directory);
        }
    }
}
