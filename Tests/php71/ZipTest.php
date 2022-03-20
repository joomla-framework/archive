<?php
/**
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests\php71;

use Joomla\Archive\Zip as ArchiveZip;
use Joomla\Test\TestHelper;

/**
 * Test class for Joomla\Archive\Zip.
 */
class ZipTest extends ArchiveTestCase
{
	/**
	 * @testdox  The zip adapter is instantiated correctly
	 *
	 * @covers   \Joomla\Archive\Zip::__construct
	 * @throws   \ReflectionException
	 */
	public function test__construct(): void
	{
		$object = new ArchiveZip;

		$this->assertEmpty(TestHelper::getValue($object, 'options'));

		$options = array('use_streams' => false);
		$object  = new ArchiveZip($options);

		$this->assertSame($options, TestHelper::getValue($object, 'options'));
	}

	/**
	 * @testdox  An archive can be created
	 *
	 * @covers   \Joomla\Archive\Zip::create
	 * @covers   \Joomla\Archive\Zip::addToZIPFile
	 * @covers   \Joomla\Archive\Zip::unix2DOSTime
	 * @covers   \Joomla\Archive\Zip::createZIPFile
	 */
	public function testCreate(): void
	{
		$object = new ArchiveZip;

		$result = $object->create(
			$this->outputPath . '/logo.zip',
			array(array(
				'name' => 'logo.png',
				'data' => file_get_contents($this->inputPath . '/logo.png'),
			))
		);

		$this->assertTrue($result);

		$dataZip = file_get_contents($this->outputPath . '/logo.zip');
		$this->assertTrue(
			$object->checkZipData($dataZip)
		);

		@unlink($this->outputPath . '/logo.zip');
	}

	/**
	 * @testdox  An archive can be extracted natively
	 *
	 * @covers   \Joomla\Archive\Zip::extractNative
	 * @throws   \ReflectionException
	 */
	public function testExtractNative(): void
	{
		if (!ArchiveZip::hasNativeSupport())
		{
			$this->markTestSkipped('ZIP files can not be extracted natively.');
		}

		$object = new ArchiveZip;

		TestHelper::invoke(
			$object,
			'extractNative',
			$this->inputPath . '/logo.zip', $this->outputPath
		);

		$this->assertFileExists($this->outputPath . '/logo-zip.png');
		$this->assertFileEquals(
			$this->outputPath . '/logo-zip.png',
			$this->inputPath . '/logo.png'
		);

		@unlink($this->outputPath . '/logo-zip.png');
	}

	/**
	 * @testdox  An archive can be extracted with the custom interface
	 *
	 * @covers   \Joomla\Archive\Zip::extractCustom
	 * @covers   \Joomla\Archive\Zip::readZipInfo
	 * @covers   \Joomla\Archive\Zip::getFileData
	 * @throws   \ReflectionException
	 */
	public function testExtractCustom(): void
	{
		if (!ArchiveZip::isSupported())
		{
			$this->markTestSkipped('ZIP files can not be extracted.');
		}

		$object = new ArchiveZip;

		TestHelper::invoke(
			$object,
			'extractCustom',
			$this->inputPath . '/logo.zip', $this->outputPath
		);

		$this->assertFileExists($this->outputPath . '/logo-zip.png');
		$this->assertFileEquals(
			$this->outputPath . '/logo-zip.png',
			$this->inputPath . '/logo.png'
		);

		@unlink($this->outputPath . '/logo-zip.png');
	}

	/**
	 * @testdox  An archive can be extracted
	 *
	 * @covers   \Joomla\Archive\Zip::extract
	 */
	public function testExtract(): void
	{
		if (!ArchiveZip::isSupported())
		{
			$this->markTestSkipped('ZIP files can not be extracted.');
		}

		$object = new ArchiveZip;

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

	/**
	 * @testdox  If the archive cannot be found an Exception is thrown
	 *
	 * @covers             \Joomla\Archive\Zip::extract
	 *
	 */
	public function testExtractException(): void
	{
		$this->expectException(\RuntimeException::class);
		$object = new ArchiveZip;

		$object->extract(
			$this->inputPath . '/foobar.zip',
			$this->outputPath
		);
	}

	/**
	 * @testdox  The adapter detects if the environment has native support
	 *
	 * @covers   \Joomla\Archive\Zip::hasNativeSupport
	 */
	public function testHasNativeSupport(): void
	{
		$this->assertEquals(
			extension_loaded('zip'),
			ArchiveZip::hasNativeSupport()
		);
	}

	/**
	 * @testdox  The adapter detects if the environment is supported
	 *
	 * @covers   \Joomla\Archive\Zip::isSupported
	 * @depends  testHasNativeSupport
	 */
	public function testIsSupported(): void
	{
		$this->assertEquals(
			ArchiveZip::hasNativeSupport() || extension_loaded('zlib'),
			ArchiveZip::isSupported()
		);
	}

	/**
	 * @testdox  The adapter correctly checks ZIP data
	 *
	 * @covers   \Joomla\Archive\Zip::checkZipData
	 */
	public function testCheckZipData(): void
	{
		$object = new ArchiveZip;

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
