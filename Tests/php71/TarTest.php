<?php
/**
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests\php71;

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
	 * @covers   \Joomla\Archive\Tar::__construct
	 * @throws   \ReflectionException
	 */
	public function test__construct(): void
	{
		$object = new ArchiveTar;

		$this->assertEmpty(TestHelper::getValue($object, 'options'));

		$options = array('foo' => 'bar');
		$object = new ArchiveTar($options);

		$this->assertSame($options, TestHelper::getValue($object, 'options'));
	}

	/**
	 * @testdox  An archive can be extracted
	 *
	 * @covers   \Joomla\Archive\Tar::extract
	 * @covers   \Joomla\Archive\Tar::getTarInfo
	 */
	public function testExtract(): void
	{
		if (!ArchiveTar::isSupported())
		{
			$this->markTestSkipped('Tar files can not be extracted.');
		}

		$object = new ArchiveTar;

		$object->extract($this->inputPath . '/logo.tar', $this->outputPath);
		$this->assertFileExists($this->outputPath . '/logo-tar.png');

		if (is_file($this->outputPath . '/logo-tar.png'))
		{
			unlink($this->outputPath . '/logo-tar.png');
		}
	}

	/**
	 * @testdox  The adapter detects if the environment is supported
	 *
	 * @covers   \Joomla\Archive\Tar::isSupported
	 */
	public function testIsSupported(): void
	{
		$this->assertTrue(ArchiveTar::isSupported());
	}
}
