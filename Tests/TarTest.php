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

    #[TestDox('The path from a pax extended header replaces the truncated name of the next entry')]
    public function testExtractUsesPathFromPaxHeader()
    {
        $longPath = str_repeat('directory-name/', 8) . 'file-with-a-long-name.txt';

        $deprecations = $this->extractTar(
            [
                ['name' => 'PaxHeaders/file', 'type' => 'x', 'data' => $this->paxRecord('path', $longPath)],
                ['name' => substr($longPath, 0, 100), 'data' => 'pax'],
            ],
            $destination
        );

        $contents = @file_get_contents($destination . '/' . $longPath);

        $this->removeDirectory($destination);

        $this->assertSame('pax', $contents);
        $this->assertSame([], $deprecations);
    }

    #[TestDox('A pax global header (as written by git archive) is not extracted and raises no deprecation')]
    public function testExtractSkipsPaxGlobalHeader()
    {
        $deprecations = $this->extractTar(
            [
                ['name' => 'pax_global_header', 'type' => 'g', 'data' => $this->paxRecord('comment', 'abcdef')],
                ['name' => 'file.txt', 'data' => 'content'],
            ],
            $destination
        );

        $files = scandir($destination);

        $this->removeDirectory($destination);

        $this->assertSame(['.', '..', 'file.txt'], $files);
        $this->assertSame([], $deprecations);
    }

    #[TestDox('Directory entries are extracted, so empty directories are kept')]
    public function testExtractCreatesDirectoryEntries()
    {
        $this->extractTar(
            [
                ['name' => 'empty/', 'type' => '5'],
                ['name' => 'file.txt', 'data' => 'content'],
            ],
            $destination
        );

        $isDir = is_dir($destination . '/empty');

        $this->removeDirectory($destination);

        $this->assertTrue($isDir);
    }

    #[TestDox('Size fields terminated by a space or stored in base-256 are read correctly')]
    public function testExtractReadsSizeFieldVariants()
    {
        $this->extractTar(
            [
                ['name' => 'space.txt', 'data' => 'space', 'size' => sprintf('%011o ', 5)],
                ['name' => 'base256.txt', 'data' => 'base-256', 'size' => chr(0x80) . str_repeat(chr(0), 10) . chr(8)],
            ],
            $destination
        );

        $space   = @file_get_contents($destination . '/space.txt');
        $base256 = @file_get_contents($destination . '/base256.txt');

        $this->removeDirectory($destination);

        $this->assertSame('space', $space);
        $this->assertSame('base-256', $base256);
    }

    /**
     * Build a tar archive from the given entries, extract it and return the deprecations raised.
     *
     * @param   array        $entries      Entries with the keys name, data, type (default '0') and size (raw field)
     * @param   string|null  $destination  Set to the destination directory
     *
     * @return  string[]
     */
    private function extractTar(array $entries, ?string &$destination): array
    {
        $archive     = $this->outputPath . '/built.tar';
        $destination = $this->outputPath . '/built';
        $data        = '';

        foreach ($entries as $entry) {
            $contents = $entry['data'] ?? '';
            $header   = pack(
                'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
                $entry['name'],
                '0000644',
                '0000000',
                '0000000',
                $entry['size'] ?? sprintf('%011o', \strlen($contents)),
                sprintf('%011o', 1700000000),
                '        ',
                $entry['type'] ?? '0',
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
            $header   = substr_replace($header, sprintf('%06o', $checksum) . chr(0) . ' ', 148, 8);

            $data .= $header . str_pad($contents, (int) ceil(\strlen($contents) / 512) * 512, chr(0));
        }

        file_put_contents($archive, $data . str_repeat(chr(0), 1024));

        $deprecations = [];

        set_error_handler(
            static function (int $errno, string $errstr) use (&$deprecations): bool {
                $deprecations[] = $errstr;

                return true;
            },
            \E_DEPRECATED
        );

        try {
            (new ArchiveTar())->extract($archive, $destination);
        } finally {
            restore_error_handler();
            unlink($archive);
        }

        return $deprecations;
    }

    /**
     * Build a pax extended header record.
     *
     * @param   string  $keyword  The keyword
     * @param   string  $value    The value
     *
     * @return  string
     */
    private function paxRecord(string $keyword, string $value): string
    {
        $record = ' ' . $keyword . '=' . $value . chr(10);
        $length = \strlen($record) + 1;

        while (\strlen($length . $record) !== $length) {
            $length++;
        }

        return $length . $record;
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
