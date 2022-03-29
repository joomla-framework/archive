<?php
/**
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Tests\php71;

use Joomla\Archive\Archive;
use Joomla\Archive\Exception\UnknownArchiveException;
use Joomla\Archive\Exception\UnsupportedArchiveException;
use Joomla\Archive\Zip as ArchiveZip;

/**
 * Test class for Joomla\Archive\Archive.
 */
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
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->fixture = new Archive;
	}

	/**
	 * Data provider for retrieving adapters.
	 *
	 * @return  array
	 */
	public function dataAdapters(): array
	{
		// Adapter Type, Expected Exception
		return array(
			array('Zip', false),
			array('Tar', false),
			array('Gzip', false),
			array('Bzip2', false),
			array('Unknown', true),
		);
	}

	/**
	 * Data provider for extracting archives.
	 *
	 * @return  array
	 */
	public function dataExtract(): array
	{
		// Filename, Adapter Type, Extracted Filename, Output is a File
		return array(
			// See https://github.com/joomla-framework/archive/issues/17
			array('Caps-Logo.ZIP', 'Zip', 'logo-zip.png'),
			array('logo.zip', 'Zip', 'logo-zip.png'),
			array('logo.tar', 'Tar', 'logo-tar.png'),
			array('logo.png.gz', 'Gzip', 'logo.png'),
			array('logo.png.bz2', 'Bzip2', 'logo.png'),
			array('logo.tar.gz', 'Gzip', 'logo-tar-gz.png'),
			array('logo.tar.bz2', 'Bzip2', 'logo-tar-bz2.png'),
		);
	}

	/**
	 * @testdox  The Archive object is instantiated correctly
	 *
	 * @covers   \Joomla\Archive\Archive::__construct
	 */
	public function test__construct(): void
	{
		$options = array('tmp_path' => __DIR__);

		$fixture = new Archive($options);

		$this->assertSame($options, $fixture->options);
	}

	/**
	 * @testdox       Archives can be extracted
	 *
	 * @param   string  $filename           Name of the file to extract
	 * @param   string  $adapterType        Type of adaptar that will be used
	 * @param   string  $extractedFilename  Name of the file to extracted file
	 *
	 * @covers        \Joomla\Archive\Archive::extract
	 * @dataProvider  dataExtract
	 */
	public function testExtract(string $filename, string $adapterType, string $extractedFilename): void
	{
		if (!is_writable($this->outputPath) || !is_writable($this->fixture->options['tmp_path']))
		{
			$this->markTestSkipped('Folder not writable.');
		}

		$adapter = "Joomla\\Archive\\$adapterType";

		/** @noinspection PhpUndefinedMethodInspection */
		if (!$adapter::isSupported())
		{
			$this->markTestSkipped($adapterType . ' files can not be extracted.');
		}

		$this->assertTrue(
			$this->fixture->extract($this->inputPath . "/$filename", $this->outputPath)
		);

		$this->assertFileExists($this->outputPath . "/$extractedFilename");

		@unlink($this->outputPath . "/$extractedFilename");
	}

	/**
	 * @testdox  Extracting an unknown archive type throws an Exception
	 *
	 * @covers   \Joomla\Archive\Archive::extract
	 *
	 */
	public function testExtractUnknown(): void
	{
		$this->expectException(UnknownArchiveException::class);

		$this->fixture->extract(
			$this->inputPath . '/logo.dat',
			$this->outputPath
		);
	}

	/**
	 * @testdox       Adapters can be retrieved
	 *
	 * @param   string   $adapterType        Type of adapter to load
	 * @param   boolean  $expectedException  Flag if an Exception is expected
	 *
	 * @covers        \Joomla\Archive\Archive::getAdapter
	 * @dataProvider  dataAdapters
	 */
	public function testGetAdapter(string $adapterType, bool $expectedException): void
	{
		if ($expectedException)
		{
			$this->expectException(UnsupportedArchiveException::class);
		}

		$adapter = $this->fixture->getAdapter($adapterType);

		$this->assertInstanceOf('Joomla\\Archive\\' . $adapterType, $adapter);
	}

	/**
	 * @testdox  Adapters can be set to the Archive
	 *
	 * @covers   \Joomla\Archive\Archive::setAdapter
	 */
	public function testSetAdapter(): void
	{
		$this->assertSame(
			$this->fixture,
			$this->fixture->setAdapter('zip', new ArchiveZip),
			'The setAdapter method should return the current object.'
		);
	}

	/**
	 * @testdox            Setting an unknown adapter throws an Exception
	 *
	 * @covers             \Joomla\Archive\Archive::setAdapter
	 *
	 */
	public function testSetAdapterUnknownException(): void
	{
		$this->expectException(UnsupportedArchiveException::class);

		$this->fixture->setAdapter('unknown', 'unknown-class');
	}
}