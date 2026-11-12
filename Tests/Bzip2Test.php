<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Bzip2 as ArchiveBzip2;
use Joomla\Archive\Tar as ArchiveTar;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Test class for Joomla\Archive\Bzip2.
 */
#[CoversClass(ArchiveBzip2::class)]
class Bzip2Test extends ArchiveTestCase
{
    #[TestDox('The bzip2 adapter is instantiated correctly')]
    public function test__construct()
    {
        $object = new ArchiveBzip2();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['use_streams' => false];
        $object  = new ArchiveBzip2($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    #[TestDox('An archive can be extracted')]
    public function testExtract()
    {
        if (!ArchiveBzip2::isSupported()) {
            $this->markTestSkipped('Bzip2 files can not be extracted.');
        }

        $object = new ArchiveBzip2();

        $object->extract(
            $this->inputPath . '/logo.png.bz2',
            $this->outputPath . '/logo-bz2.png'
        );

        $this->assertFileExists($this->outputPath . '/logo-bz2.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-bz2.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-bz2.png');
    }

    #[TestDox('An archive can be extracted via streams')]
    public function testExtractWithStreams()
    {
        $this->markTestSkipped('There is a bug, see https://bugs.php.net/bug.php?id=63195&edit=1');

        if (!ArchiveBzip2::isSupported()) {
            $this->markTestSkipped('Bzip2 files can not be extracted.');
        }

        $object = new ArchiveBzip2(['use_streams' => true]);
        $object->extract(
            $this->inputPath . '/logo.png.bz2',
            $this->outputPath . '/logo-bz2.png'
        );

        $this->assertFileExists($this->outputPath . '/logo-bz2.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-bz2.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-bz2.png');
    }

    #[TestDox('The adapter detects if the environment is supported')]
    public function testIsSupported()
    {
        $this->assertSame(
            extension_loaded('bz2'),
            ArchiveBzip2::isSupported()
        );
    }

    #[TestDox('A single file is compressed as it is and can be read back')]
    public function testCreateWithASingleFile()
    {
        if (!ArchiveBzip2::isSupported()) {
            $this->markTestSkipped('Bzip2 files can not be created.');
        }

        $object  = new ArchiveBzip2();
        $archive = $this->outputPath . '/logo.png.bz2';

        $this->assertTrue(
            $object->create($archive, [
                ['name' => 'logo.png', 'data' => file_get_contents($this->inputPath . '/logo.png')],
            ])
        );

        $object->extract($archive, $this->outputPath . '/logo-created.png');

        $this->assertFileEquals($this->inputPath . '/logo.png', $this->outputPath . '/logo-created.png');

        @unlink($archive);
        @unlink($this->outputPath . '/logo-created.png');
    }

    #[TestDox('An archive named as a tarball wraps its contents in a tar')]
    #[DataProvider('tarballNameProvider')]
    public function testCreateWrapsTarballsInATar(string $name)
    {
        if (!ArchiveBzip2::isSupported()) {
            $this->markTestSkipped('Bzip2 files can not be created.');
        }

        $object  = new ArchiveBzip2();
        $archive = $this->outputPath . '/' . $name;

        $object->create($archive, [
            ['name' => 'readme.txt',     'data' => "Hello world\n"],
            ['name' => 'dir/nested.txt', 'data' => "Nested\n"],
        ]);

        $unpacked = $this->outputPath . '/unpacked.tar';
        $object->extract($archive, $unpacked);

        // The compressed payload is a tar, so the tar adapter has to be able to read it
        $destination = $this->outputPath . '/bz2-tarball';
        (new ArchiveTar())->extract($unpacked, $destination);

        $this->assertStringEqualsFile($destination . '/readme.txt', "Hello world\n");
        $this->assertStringEqualsFile($destination . '/dir/nested.txt', "Nested\n");

        @unlink($archive);
        @unlink($unpacked);
        @unlink($destination . '/readme.txt');
        @unlink($destination . '/dir/nested.txt');
        @rmdir($destination . '/dir');
        @rmdir($destination);
    }

    /**
     * Archive names that mean "there is a tar inside".
     *
     * @return  array
     */
    public static function tarballNameProvider(): array
    {
        return [
            'tar.bz2 suffix' => ['backup.tar.bz2'],
            'tbz2 suffix'    => ['backup.tbz2'],
        ];
    }

    #[TestDox('Several files cannot be stored in an archive that is not named as a tarball')]
    public function testCreateRejectsSeveralFilesWithoutATarballName()
    {
        $object = new ArchiveBzip2();

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/plain.bz2', [
            ['name' => 'a.txt', 'data' => 'A'],
            ['name' => 'b.txt', 'data' => 'B'],
        ]);
    }

    #[TestDox('An empty file list is rejected')]
    public function testCreateRejectsAnEmptyFileList()
    {
        $object = new ArchiveBzip2();

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/empty.bz2', []);
    }

    #[TestDox('An out of range block size is rejected')]
    public function testCreateRejectsAnInvalidBlockSize()
    {
        $object = new ArchiveBzip2(['bzip2_blocksize' => 0]);

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/blocksize.bz2', [['name' => 'a.txt', 'data' => 'A']]);
    }
}
