<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Gzip as ArchiveGzip;
use Joomla\Archive\Tar as ArchiveTar;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for Joomla\Archive\Gzip.
 */
class GzipTest extends ArchiveTestCase
{
    /**
     * @testdox  The gzip adapter is instantiated correctly
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function test__construct()
    {
        $object = new ArchiveGzip();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['use_streams' => false];
        $object  = new ArchiveGzip($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    /**
     * @testdox  An archive can be extracted
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testExtract()
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be extracted.');

            return;
        }

        $object = new ArchiveGzip();

        $object->extract(
            $this->inputPath . '/logo.png.gz',
            $this->outputPath . '/logo-gz.png'
        );

        $this->assertFileExists($this->outputPath . '/logo-gz.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-gz.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-gz.png');
    }

    /**
     * @testdox  An archive can be extracted via streams
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testExtractWithStreams()
    {
        $this->markTestSkipped('There is a bug, see https://bugs.php.net/bug.php?id=63195&edit=1');

        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be extracted.');
        }

        $object = new ArchiveGzip(['use_streams' => true]);
        $object->extract(
            $this->inputPath . '/logo.png.gz',
            $this->outputPath . '/logo-gz.png'
        );

        $this->assertFileExists($this->outputPath . '/logo-gz.png');
        $this->assertFileEquals(
            $this->outputPath . '/logo-gz.png',
            $this->inputPath . '/logo.png'
        );

        @unlink($this->outputPath . '/logo-gz.png');
    }

    /**
     * @testdox  The adapter detects if the environment is supported
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testIsSupported()
    {
        $this->assertSame(
            extension_loaded('zlib'),
            ArchiveGzip::isSupported()
        );
    }

    /**
     * @testdox  The file position is detected
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testGetFilePosition()
    {
        $object = new ArchiveGzip();

        // This fixture carries a file name and nothing else
        TestHelper::setValue(
            $object,
            'data',
            file_get_contents($this->inputPath . '/logo.png.gz')
        );

        $this->assertEquals(
            22,
            $object->getFilePosition()
        );
    }

    /**
     * @testdox  The file position is detected with every optional header field present
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testGetFilePositionWithAllFlagsSet()
    {
        $payload = "The quick brown fox\n";
        $extra   = 'AB';
        $name    = 'logo.png';
        $comment = 'test comment';

        $gzip = self::buildGzip($payload, $extra, $name, $comment);

        /*
         * 10 bytes of fixed header, then two bytes of extra field length plus the field itself,
         * then the NUL terminated name and comment, then two bytes of header CRC.
         */
        $expected = 10 + (2 + \strlen($extra)) + (\strlen($name) + 1) + (\strlen($comment) + 1) + 2;

        $object = new ArchiveGzip();
        TestHelper::setValue($object, 'data', $gzip);

        $this->assertSame($expected, $object->getFilePosition());

        // The offset is only right if the payload starts there, so prove it by extracting
        $archive = $this->outputPath . '/allflags.gz';
        file_put_contents($archive, $gzip);

        $object->extract($archive, $this->outputPath . '/allflags.txt');

        $this->assertStringEqualsFile($this->outputPath . '/allflags.txt', $payload);

        @unlink($archive);
        @unlink($this->outputPath . '/allflags.txt');
    }

    /**
     * Build a valid gzip stream with every optional header field present.
     *
     * PHP's own gzencode() writes none of them, so a stream exercising the FEXTRA, FNAME,
     * FCOMMENT and FHCRC branches of getFilePosition() has to be assembled by hand.
     *
     * @param   string  $payload  The data to compress.
     * @param   string  $extra    Contents of the extra field.
     * @param   string  $name     The original file name.
     * @param   string  $comment  The archive comment.
     *
     * @return  string
     */
    private static function buildGzip(string $payload, string $extra, string $name, string $comment): string
    {
        $header = "\x1f\x8b"                              // magic
            . "\x08"                                      // deflate
            . \chr(0x01 | 0x02 | 0x04 | 0x08 | 0x10)      // FTEXT|FHCRC|FEXTRA|FNAME|FCOMMENT
            . pack('V', 0)                                // modification time
            . "\x00"                                      // extra flags
            . "\x03";                                     // operating system

        $header .= pack('v', \strlen($extra)) . $extra;
        $header .= $name . "\0";
        $header .= $comment . "\0";

        // The header CRC covers everything written before it
        $header .= pack('v', crc32($header) & 0xFFFF);

        return $header . gzdeflate($payload) . pack('V', crc32($payload)) . pack('V', \strlen($payload));
    }

    /**
     * @testdox  A single file is compressed as it is and can be read back
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testCreateWithASingleFile()
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be created.');
        }

        $object  = new ArchiveGzip();
        $archive = $this->outputPath . '/logo.png.gz';

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

    /**
     * @testdox  A single file archive records the original file name in the header
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testCreateStoresTheOriginalFileName()
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be created.');
        }

        $object  = new ArchiveGzip();
        $archive = $this->outputPath . '/named.gz';

        $object->create($archive, [['name' => 'some/path/original.txt', 'data' => 'content']]);

        $data = file_get_contents($archive);

        // Bit 3 of the flag byte says an original file name follows the 10 byte header
        $this->assertSame(0x08, \ord($data[3]) & 0x08, 'The FNAME flag is set');
        $this->assertSame(
            'original.txt',
            substr($data, 10, strpos($data, "\0", 10) - 10),
            'Only the base name is stored, not the path it was read from'
        );

        @unlink($archive);
    }

    /**
     * @testdox  An archive named as a tarball wraps its contents in a tar
     *
     * @covers   Joomla\Archive\Gzip
     */
    #[DataProvider('tarballNameProvider')]
    public function testCreateWrapsTarballsInATar(string $name)
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be created.');
        }

        $object  = new ArchiveGzip();
        $archive = $this->outputPath . '/' . $name;

        $object->create($archive, [
            ['name' => 'readme.txt',     'data' => "Hello world\n"],
            ['name' => 'dir/nested.txt', 'data' => "Nested\n"],
        ]);

        $unpacked = $this->outputPath . '/unpacked.tar';
        $object->extract($archive, $unpacked);

        // The compressed payload is a tar, so the tar adapter has to be able to read it
        $destination = $this->outputPath . '/gz-tarball';
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
            'tar.gz suffix' => ['backup.tar.gz'],
            'tgz suffix'    => ['backup.tgz'],
        ];
    }

    /**
     * @testdox  Several files cannot be stored in an archive that is not named as a tarball
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testCreateRejectsSeveralFilesWithoutATarballName()
    {
        $object = new ArchiveGzip();

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/plain.gz', [
            ['name' => 'a.txt', 'data' => 'A'],
            ['name' => 'b.txt', 'data' => 'B'],
        ]);
    }

    /**
     * @testdox  An empty file list is rejected
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testCreateRejectsAnEmptyFileList()
    {
        $object = new ArchiveGzip();

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/empty.gz', []);
    }

    /**
     * @testdox  An out of range compression level is rejected
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testCreateRejectsAnInvalidCompressionLevel()
    {
        $object = new ArchiveGzip(['gzip_level' => 12]);

        $this->expectException(\InvalidArgumentException::class);

        $object->create($this->outputPath . '/level.gz', [['name' => 'a.txt', 'data' => 'A']]);
    }

    /**
     * @testdox  The compression level option is applied
     *
     * @covers   Joomla\Archive\Gzip
     */
    public function testCreateAppliesTheCompressionLevel()
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be created.');
        }

        $files = [['name' => 'repetitive.txt', 'data' => str_repeat('joomla framework ', 2000)]];

        $fast = $this->outputPath . '/fast.gz';
        $best = $this->outputPath . '/best.gz';

        (new ArchiveGzip(['gzip_level' => 1]))->create($fast, $files);
        (new ArchiveGzip(['gzip_level' => 9]))->create($best, $files);

        $this->assertLessThan(
            filesize($fast),
            filesize($best),
            'Level 9 has to compress this input further than level 1'
        );

        @unlink($fast);
        @unlink($best);
    }
}
