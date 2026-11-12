<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Gzip as ArchiveGzip;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Test class for Joomla\Archive\Gzip.
 */
#[CoversClass(ArchiveGzip::class)]
class GzipTest extends ArchiveTestCase
{
    #[TestDox('The gzip adapter is instantiated correctly')]
    public function test__construct()
    {
        $object = new ArchiveGzip();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['use_streams' => false];
        $object  = new ArchiveGzip($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    #[TestDox('An archive can be extracted')]
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

    #[TestDox('An archive can be extracted via streams')]
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
     * @return  array<string, array{string}>
     */
    public static function dataFalsyContent(): array
    {
        return [
            'empty file'  => [''],
            'file with 0' => ['0'],
        ];
    }

    #[DataProvider('dataFalsyContent')]
    #[TestDox('A file whose content is empty or "0" can be extracted')]
    public function testExtractFalsyContent(string $content)
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be extracted.');
        }

        $archive     = $this->outputPath . '/falsy.txt.gz';
        $destination = $this->outputPath . '/falsy.txt';

        file_put_contents($archive, gzencode($content));

        try {
            $this->assertTrue((new ArchiveGzip())->extract($archive, $destination));
            $this->assertSame($content, file_get_contents($destination));
        } finally {
            @unlink($archive);
            @unlink($destination);
        }
    }

    #[TestDox('Corrupt data is reported as an error and nothing is written')]
    public function testExtractCorruptData()
    {
        if (!ArchiveGzip::isSupported()) {
            $this->markTestSkipped('Gzip files can not be extracted.');
        }

        $archive     = $this->outputPath . '/corrupt.txt.gz';
        $destination = $this->outputPath . '/corrupt.txt';

        file_put_contents($archive, substr(gzencode('content'), 0, 10) . 'garbage');

        try {
            @(new ArchiveGzip())->extract($archive, $destination);

            $exception = null;
        } catch (\RuntimeException $exception) {
        }

        $written = is_file($destination);

        @unlink($archive);
        @unlink($destination);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Unable to decompress data', $exception->getMessage());
        $this->assertFalse($written);
    }

    #[TestDox('The adapter detects if the environment is supported')]
    public function testIsSupported()
    {
        $this->assertSame(
            extension_loaded('zlib'),
            ArchiveGzip::isSupported()
        );
    }

    #[TestDox('The file position is detected')]
    public function testGetFilePosition()
    {
        $object = new ArchiveGzip();

        // @todo use an all flags enabled file
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
}
