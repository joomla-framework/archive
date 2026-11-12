<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Archive;
use Joomla\Archive\Bzip2;
use Joomla\Archive\Exception\UnknownArchiveException;
use Joomla\Archive\Exception\UnsupportedArchiveException;
use Joomla\Archive\Gzip;
use Joomla\Archive\Tar;
use Joomla\Archive\Zip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Test class for Joomla\Archive\Archive.
 */
#[CoversClass(Archive::class)]
#[UsesClass(Bzip2::class)]
#[UsesClass(Gzip::class)]
#[UsesClass(Tar::class)]
#[UsesClass(Zip::class)]
class ArchiveTest extends ArchiveTestCase
{
    /**
     * Object under test
     *
     * @var  Archive
     */
    protected $fixture;

    /**
     * Sets up the fixture.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new Archive();
    }

    /**
     * Data provider for retrieving adapters.
     *
     * @return  array
     */
    public static function dataAdaptersProvider(): array
    {
        // Adapter Type, Expected Exception
        return [
            'Zip Adapter' => ['Zip', false],
            'Tar Adapter' => ['Tar', false],
            'Gzip Adapter' => ['Gzip', false],
            'Bzip2 Adapter' => ['Bzip2', false],
            'Unknown Adapter' => ['Unknown', true],
        ];
    }

    /**
     * Data provider for extracting archives.
     *
     * @return  array
     */
    public static function dataExtractProvider(): array
    {
        // Filename, Adapter Type, Extracted Filename, Output is a File
        return [
            'Zip adapter with capitalised file extension' => ['Caps-Logo.ZIP', 'Zip', 'logo-zip.png'],
            'Zip adapter' => ['logo.zip', 'Zip', 'logo-zip.png'],
            'Tar adapter' => ['logo.tar', 'Zip', 'logo-tar.png'],
            'Gzip adapter with .gz file type' => ['logo.png.gz', 'Gzip', 'logo.png'],
            'Bzip2 adapter with .bz2 file type' => ['logo.png.bz2', 'Bzip2', 'logo.png'],
            'Gzip adapter with .tar.gz file type' => ['logo.tar.gz', 'Gzip', 'logo-tar-gz.png'],
            'Bzip2 adapter with .tar.bz2 file type' => ['logo.tar.bz2', 'Bzip2', 'logo-tar-bz2.png'],
        ];
    }

    #[TestDox('The Archive object is instantiated correctly')]
    public function test__construct()
    {
        $options = ['tmp_path' => __DIR__];

        $fixture = new Archive($options);

        $this->assertSame($options, $fixture->options);
    }

    /**
     * @param   string   $filename           Name of the file to extract
     * @param   string   $adapterType        Type of adaptar that will be used
     * @param   string   $extractedFilename  Name of the file to extracted file
     */
    #[DataProvider('dataExtractProvider')]
    #[TestDox('Archives can be extracted')]
    public function testExtract($filename, $adapterType, $extractedFilename)
    {
        if (!is_writable($this->outputPath) || !is_writable($this->fixture->options['tmp_path'])) {
            $this->markTestSkipped('Folder not writable.');
        }

        $adapter = "Joomla\\Archive\\$adapterType";

        if (!$adapter::isSupported()) {
            $this->markTestSkipped($adapterType . ' files can not be extracted.');
        }

        $this->assertTrue(
            $this->fixture->extract($this->inputPath . "/$filename", $this->outputPath)
        );

        $this->assertFileExists($this->outputPath . "/$extractedFilename");

        @unlink($this->outputPath . "/$extractedFilename");
    }

    #[TestDox('Extracting an unknown archive type throws an Exception')]
    public function testExtractUnknown()
    {
        $this->expectException(UnknownArchiveException::class);

        $this->fixture->extract(
            $this->inputPath . '/logo.dat',
            $this->outputPath
        );
    }

    /**
     * @param   string   $adapterType        Type of adapter to load
     * @param   boolean  $expectedException  Flag if an Exception is expected
     */
    #[DataProvider('dataAdaptersProvider')]
    #[TestDox('Adapters can be retrieved')]
    public function testGetAdapter($adapterType, $expectedException)
    {
        if ($expectedException) {
            $this->expectException(UnsupportedArchiveException::class);
        }

        $adapter = $this->fixture->getAdapter($adapterType);

        $this->assertInstanceOf('Joomla\\Archive\\' . $adapterType, $adapter);
    }

    #[TestDox('Adapters can be set to the Archive')]
    public function testSetAdapter()
    {
        $this->assertSame(
            $this->fixture,
            $this->fixture->setAdapter('zip', '\\Joomla\\Archive\\Zip'),
            'The setAdapter method should return the current object.'
        );
    }

    #[TestDox('Setting an unknown adapter throws an Exception')]
    public function testSetAdapterUnknownException()
    {
        $this->expectException(UnsupportedArchiveException::class);

        $this->fixture->setAdapter('unknown', 'unknown-class');
    }
}
