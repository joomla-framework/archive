<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Zip as ArchiveZip;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
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

    #[TestDox('An entry pointing into a sibling directory that shares the destination name as prefix is rejected natively')]
    public function testExtractNativeRejectsEntriesInSiblingDirectories()
    {
        if (!ArchiveZip::hasNativeSupport()) {
            $this->markTestSkipped('ZIP files can not be extracted natively.');
        }

        $this->assertSiblingEntryIsRejected('extractNative');
    }

    #[TestDox('An entry pointing into a sibling directory that shares the destination name as prefix is rejected by the custom interface')]
    public function testExtractCustomRejectsEntriesInSiblingDirectories()
    {
        $this->assertSiblingEntryIsRejected('extractCustom');
    }

    /**
     * Extract an archive with an entry "../dest_evil/x.php" into "dest" and check that nothing is written to "dest_evil".
     *
     * @param   string  $method  The extraction method to use
     *
     * @return  void
     */
    private function assertSiblingEntryIsRejected(string $method): void
    {
        $archive     = $this->outputPath . '/sibling.zip';
        $destination = $this->outputPath . '/dest';
        $sibling     = $this->outputPath . '/dest_evil';

        $object = new ArchiveZip();
        $object->create($archive, [['name' => '../dest_evil/x.php', 'data' => '<?php echo "pwned";']]);

        $exception = null;

        try {
            TestHelper::invoke($object, $method, $archive, $destination);
        } catch (\RuntimeException $exception) {
        }

        $written = is_file($sibling . '/x.php');

        @unlink($sibling . '/x.php');
        @rmdir($sibling);
        @rmdir($destination);
        @unlink($archive);

        $this->assertFalse($written, 'The entry must not be written to the sibling directory');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Unable to write outside of destination path', $exception->getMessage());
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
