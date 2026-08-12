<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Tar as ArchiveTar;
use Joomla\Test\TestHelper;

/**
 * Test class for Joomla\Archive\Tar.
 */
class TarTest extends ArchiveTestCase
{
    /**
     * @testdox  The tar adapter is instantiated correctly
     *
     * @covers   Joomla\Archive\Tar
     */
    public function test__construct()
    {
        $object = new ArchiveTar();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['foo' => 'bar'];
        $object  = new ArchiveTar($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    /**
     * @testdox  An archive can be extracted
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testExtract()
    {
        if (!ArchiveTar::isSupported()) {
            $this->markTestSkipped('Tar files can not be extracted.');
        }

        $object = new ArchiveTar();

        $object->extract($this->inputPath . '/logo.tar', $this->outputPath);
        $this->assertFileExists($this->outputPath . '/logo-tar.png');

        if (is_file($this->outputPath . '/logo-tar.png')) {
            unlink($this->outputPath . '/logo-tar.png');
        }
    }

    /**
     * @testdox  The adapter detects if the environment is supported
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testIsSupported()
    {
        $this->assertTrue(ArchiveTar::isSupported());
    }

    /**
     * @testdox  An archive can be created and read back
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreate()
    {
        $object  = new ArchiveTar();
        $archive = $this->outputPath . '/created.tar';

        $this->assertTrue(
            $object->create(
                $archive,
                [
                    ['name' => 'readme.txt',     'data' => "Hello world\n"],
                    ['name' => 'dir/nested.txt', 'data' => "Nested\n"],
                ]
            )
        );

        $this->assertFileExists($archive);

        $destination = $this->outputPath . '/created-tar';
        $object->extract($archive, $destination);

        $this->assertStringEqualsFile($destination . '/readme.txt', "Hello world\n");
        $this->assertStringEqualsFile($destination . '/dir/nested.txt', "Nested\n");

        $this->cleanUp([$archive], [$destination]);
    }

    /**
     * @testdox  A created archive stores binary data unchanged
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreatePreservesBinaryData()
    {
        $object  = new ArchiveTar();
        $archive = $this->outputPath . '/created-binary.tar';

        // A size that is not a multiple of the 512 byte block size, so the padding is exercised
        $binary = file_get_contents($this->inputPath . '/logo.png');

        $object->create($archive, [['name' => 'logo.png', 'data' => $binary]]);

        $destination = $this->outputPath . '/created-binary';
        $object->extract($archive, $destination);

        $this->assertFileEquals($this->inputPath . '/logo.png', $destination . '/logo.png');

        $this->cleanUp([$archive], [$destination]);
    }

    /**
     * @testdox  A created archive keeps paths that are too long for the name field
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreateSplitsLongPathsAcrossThePrefixField()
    {
        // Longer than the 100 characters of the tar name field, so it has to use the prefix field
        $name = 'a-directory-with-a-fairly-long-name/and-another-nested-level-here/and-one-more-to-push-past-100/deep.txt';

        $this->assertGreaterThan(100, \strlen($name));

        $object  = new ArchiveTar();
        $archive = $this->outputPath . '/created-long.tar';

        $object->create($archive, [['name' => $name, 'data' => "Deep\n"]]);

        $destination = $this->outputPath . '/created-long';
        $object->extract($archive, $destination);

        $this->assertStringEqualsFile(
            $destination . '/' . $name,
            "Deep\n",
            'The full path has to survive the round trip, not just the last segment'
        );

        $this->cleanUp([$archive], [$destination]);
    }

    /**
     * @testdox  A path that cannot be split to fit the format is rejected
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreateRejectsAPathThatCannotBeStored()
    {
        $object = new ArchiveTar();

        $this->expectException(\InvalidArgumentException::class);

        // No slash to split on, and too long for the name field on its own
        $object->create($this->outputPath . '/impossible.tar', [
            ['name' => str_repeat('x', 120), 'data' => 'nope'],
        ]);
    }

    /**
     * @testdox  An entry without a name is rejected
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreateRejectsAnEntryWithoutAName()
    {
        $object = new ArchiveTar();

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/nameless.tar', [['data' => 'nope']]);
    }

    /**
     * @testdox  A directory entry is written as one
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreateWritesDirectoryEntries()
    {
        $object  = new ArchiveTar();
        $archive = $this->outputPath . '/created-dir.tar';

        $object->create($archive, [
            ['name' => 'emptydir/'],
            ['name' => 'emptydir/file.txt', 'data' => 'x'],
        ]);

        $data = file_get_contents($archive);

        // The type flag sits at offset 156 of each 512 byte header: "5" is a directory, "0" a file
        $this->assertSame('5', $data[156], 'The directory entry is flagged as one');
        $this->assertSame('0', $data[512 + 156], 'The file entry is flagged as a file');

        // A directory carries no data, so its header is followed straight by the next header
        $this->assertSame('emptydir/file.txt', rtrim(substr($data, 512, 100), "\0"));

        $this->cleanUp([$archive], []);
    }

    /**
     * @testdox  A created archive carries a valid header checksum
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testCreateWritesAValidChecksum()
    {
        $object  = new ArchiveTar();
        $archive = $this->outputPath . '/created-checksum.tar';

        $object->create($archive, [['name' => 'readme.txt', 'data' => "Hello world\n"]]);

        $header = substr(file_get_contents($archive), 0, 512);

        // The checksum is calculated over the header with its own field blanked out with spaces
        $stored     = octdec(trim(substr($header, 148, 8), " \0"));
        $calculated = 0;
        $blanked    = substr_replace($header, '        ', 148, 8);

        for ($i = 0; $i < 512; $i++) {
            $calculated += \ord($blanked[$i]);
        }

        $this->assertSame($calculated, $stored);
        $this->assertSame('ustar', rtrim(substr($header, 257, 6), "\0"), 'The archive declares the USTAR format');

        $this->cleanUp([$archive], []);
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
