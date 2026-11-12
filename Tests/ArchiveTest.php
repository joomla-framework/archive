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

    #[TestDox('Tarballs with an upper-case .TGZ or .TBZ2 extension are extracted as tarballs')]
    public function testExtractUpperCaseTarballExtension()
    {
        $tar = file_get_contents($this->inputPath . '/logo.tar');

        foreach (['TGZ' => 'gzencode', 'TBZ2' => 'bzcompress'] as $extension => $compress) {
            if (!function_exists($compress)) {
                continue;
            }

            $archive = $this->outputPath . '/logo.' . $extension;
            file_put_contents($archive, $compress($tar));

            try {
                $this->fixture->extract($archive, $this->outputPath);

                $extracted = is_file($this->outputPath . '/logo-tar.png');
                $copied    = is_file($this->outputPath . '/logo');
            } finally {
                @unlink($archive);
                @unlink($this->outputPath . '/logo-tar.png');
                @unlink($this->outputPath . '/logo');
            }

            $this->assertTrue($extracted, $extension . ': the tarball must be extracted');
            $this->assertFalse($copied, $extension . ': the tarball must not be copied as a single file');
        }
    }

    #[TestDox('The temporary file is removed when the inner tarball cannot be extracted')]
    public function testExtractRemovesTemporaryFileOnError()
    {
        $tmpPath = $this->outputPath . '/tmp';
        $archive = $this->outputPath . '/broken.tgz';

        @mkdir($tmpPath);

        // A tarball with an entry outside of the destination
        $tar = file_get_contents($this->inputPath . '/logo.tar');
        $tar = substr_replace($tar, str_pad('../outside.png', 100, chr(0)), 0, 100);
        file_put_contents($archive, gzencode($tar));

        $exception = null;

        try {
            (new Archive(['tmp_path' => $tmpPath]))->extract($archive, $this->outputPath . '/broken');
        } catch (\OutOfBoundsException $exception) {
        }

        $leftOver = array_values(array_diff(scandir($tmpPath), ['.', '..']));

        @unlink($archive);
        @rmdir($this->outputPath . '/broken');
        array_map('unlink', glob($tmpPath . '/*') ?: []);
        @rmdir($tmpPath);

        $this->assertInstanceOf(\OutOfBoundsException::class, $exception);
        $this->assertSame([], $leftOver);
    }

    #[TestDox('A corrupt gzip file throws an exception like the other archive types')]
    public function testExtractCorruptGzipThrows()
    {
        $archive = $this->outputPath . '/corrupt.txt.gz';

        file_put_contents($archive, substr(gzencode('content'), 0, 10) . 'garbage');

        $this->expectException(\RuntimeException::class);

        try {
            @$this->fixture->extract($archive, $this->outputPath);
        } finally {
            @unlink($archive);
        }
    }

    #[TestDox('A single compressed file is extracted under the archive name, and can be checked with File::isSafeFile()')]
    public function testExtractSingleFileSafetyCheck()
    {
        $archive = $this->outputPath . '/shell.php.gz';

        file_put_contents($archive, gzencode('<?php echo "pwned";'));

        try {
            $this->assertTrue($this->fixture->extract($archive, $this->outputPath));
            $this->assertFileExists($this->outputPath . '/shell.php', 'Without the option the file is extracted under its own name');
            unlink($this->outputPath . '/shell.php');

            $exception = null;

            try {
                (new Archive(['check_single_file' => true]))->extract($archive, $this->outputPath);
            } catch (\RuntimeException $exception) {
            }

            $this->assertInstanceOf(\RuntimeException::class, $exception);
            $this->assertSame('The extracted file "shell.php" is not safe', $exception->getMessage());
            $this->assertFileDoesNotExist($this->outputPath . '/shell.php');
        } finally {
            @unlink($archive);
            @unlink($this->outputPath . '/shell.php');
        }
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
