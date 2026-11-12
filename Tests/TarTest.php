<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Exception\UnsafeArchiveEntryException;
use Joomla\Archive\Tar as ArchiveTar;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Test class for Joomla\Archive\Tar.
 */
#[CoversClass(ArchiveTar::class)]
class TarTest extends ArchiveTestCase
{
    #[TestDox('The tar adapter is instantiated correctly')]
    public function test__construct()
    {
        $object = new ArchiveTar();

        $this->assertEmpty(TestHelper::getValue($object, 'options'));

        $options = ['foo' => 'bar'];
        $object  = new ArchiveTar($options);

        $this->assertSame($options, TestHelper::getValue($object, 'options'));
    }

    #[TestDox('An archive can be extracted')]
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

    #[TestDox('An entry outside of the destination raises an UnsafeArchiveEntryException')]
    public function testExtractThrowsUnsafeArchiveEntryException()
    {
        $archive     = $this->outputPath . '/unsafe.tar';
        $destination = $this->outputPath . '/unsafe';

        // The fixture with its only entry renamed
        $tar = file_get_contents($this->inputPath . '/logo.tar');
        file_put_contents($archive, substr_replace($tar, str_pad('../outside.png', 100, chr(0)), 0, 100));

        $exception = null;

        try {
            (new ArchiveTar())->extract($archive, $destination);
        } catch (\RuntimeException $exception) {
        }

        @unlink($this->outputPath . '/outside.png');
        @rmdir($destination);
        @unlink($archive);

        $this->assertInstanceOf(UnsafeArchiveEntryException::class, $exception);
        $this->assertSame(100, $exception->getCode());
    }

    #[TestDox('The adapter detects if the environment is supported')]
    public function testIsSupported()
    {
        $this->assertTrue(ArchiveTar::isSupported());
    }
}
