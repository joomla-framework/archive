<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests;

use Joomla\Archive\Bzip2 as ArchiveBzip2;
use Joomla\Test\TestHelper;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
