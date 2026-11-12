<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

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

    #[TestDox('An archive written by GNU tar with a long path is extracted to the full path')]
    public function testExtractRestoresPathsStoredInThePrefixField()
    {
        $object      = new ArchiveTar();
        $destination = $this->outputPath . '/long-path';

        // Written by GNU tar in ustar format: the leading directories live in the prefix field
        $object->extract($this->inputPath . '/long-path.tar', $destination);

        $expected = $destination
            . '/a-directory-with-a-fairly-long-name/and-another-nested-level-here'
            . '/and-one-more-to-push-past-100/payload.txt';

        $exists     = is_file($expected);
        $flatExists = is_file($destination . '/payload.txt');

        $this->removeDirectory($destination);

        $this->assertTrue($exists, 'The prefix field carries the directories, so ignoring it drops them from the path');
        $this->assertFalse($flatExists, 'The entry must not land flat in the destination');
    }

    #[TestDox('The adapter detects if the environment is supported')]
    public function testIsSupported()
    {
        $this->assertTrue(ArchiveTar::isSupported());
    }

    /**
     * Remove a directory and everything below it.
     *
     * @param   string  $path  The directory to remove
     *
     * @return  void
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
