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
     * @testdox  An archive written by GNU tar with a long path is extracted to the full path
     *
     * @covers   Joomla\Archive\Tar
     */
    public function testExtractRestoresPathsStoredInThePrefixField()
    {
        $object      = new ArchiveTar();
        $destination = $this->outputPath . '/long-path';

        // Written by GNU tar in ustar format: the leading directories live in the prefix field
        $object->extract($this->inputPath . '/long-path.tar', $destination);

        $expected = $destination
            . '/a-directory-with-a-fairly-long-name/and-another-nested-level-here'
            . '/and-one-more-to-push-past-100/payload.txt';

        $this->assertFileExists(
            $expected,
            'The prefix field carries the directories, so ignoring it drops them from the path'
        );

        $this->assertFileDoesNotExist(
            $destination . '/payload.txt',
            'The entry must not land flat in the destination'
        );

        $this->cleanUp([], [$destination]);
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
}
