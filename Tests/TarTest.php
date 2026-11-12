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

    #[TestDox('The adapter detects if the environment is supported')]
    public function testIsSupported()
    {
        $this->assertTrue(ArchiveTar::isSupported());
    }

    #[TestDox('An entry pointing into a sibling directory that shares the destination name as prefix is rejected')]
    public function testExtractRejectsEntriesInSiblingDirectories()
    {
        $archive     = $this->outputPath . '/sibling.tar';
        $destination = $this->outputPath . '/dest';
        $sibling     = $this->outputPath . '/dest_evil';

        file_put_contents($archive, $this->createTar(['../dest_evil/t.php' => '<?php echo "pwned";']));

        $exception = null;

        try {
            (new ArchiveTar())->extract($archive, $destination);
        } catch (\OutOfBoundsException $exception) {
        }

        $written = is_file($sibling . '/t.php');

        @unlink($sibling . '/t.php');
        @rmdir($sibling);
        @rmdir($destination);
        @unlink($archive);

        $this->assertFalse($written, 'The entry must not be written to the sibling directory');
        $this->assertInstanceOf(\OutOfBoundsException::class, $exception);
    }

    #[TestDox('An entry climbing above a relative destination path is rejected like any other entry outside of it')]
    public function testExtractRejectsEntriesAboveRelativeDestination()
    {
        $archive = $this->outputPath . '/relative.tar';

        file_put_contents($archive, $this->createTar(['../../t.txt' => 'content']));

        $exception = null;

        try {
            // Relative to the working directory; nothing is written there
            (new ArchiveTar())->extract($archive, 'archive-relative-destination');
        } catch (\RuntimeException $exception) {
        }

        @unlink($archive);

        $this->assertInstanceOf(\OutOfBoundsException::class, $exception);
    }

    #[TestDox('Entries below the destination are extracted when the destination has a trailing slash')]
    public function testExtractAcceptsDestinationWithTrailingSlash()
    {
        $archive     = $this->outputPath . '/trailing.tar';
        $destination = $this->outputPath . '/dest';

        file_put_contents($archive, $this->createTar(['sub/../file.txt' => 'content']));

        try {
            $this->assertTrue((new ArchiveTar())->extract($archive, $destination . '/'));
            $this->assertStringEqualsFile($destination . '/file.txt', 'content');
        } finally {
            @unlink($destination . '/file.txt');
            @rmdir($destination . '/sub');
            @rmdir($destination);
            @unlink($archive);
        }
    }

    /**
     * Build a ustar archive in memory.
     *
     * @param   array<string, string>  $entries  File contents keyed by entry name
     *
     * @return  string
     */
    private function createTar(array $entries): string
    {
        $data = '';

        foreach ($entries as $name => $contents) {
            $header = pack(
                'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
                $name,
                '0000644',
                '0000000',
                '0000000',
                sprintf('%011o', \strlen($contents)),
                sprintf('%011o', time()),
                '        ',
                '0',
                '',
                'ustar',
                '00',
                '',
                '',
                '',
                '',
                '',
                ''
            );

            $checksum = array_sum(array_map('ord', str_split($header)));
            $header   = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);

            $data .= $header . str_pad($contents, (int) ceil(\strlen($contents) / 512) * 512, "\0");
        }

        return $data . str_repeat("\0", 1024);
    }
}
