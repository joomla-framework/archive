<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * Tar format adapter for the Archive package
 *
 * This class is inspired from and draws heavily in code and concept from the Compress package of
 * The Horde Project <http://www.horde.org>
 *
 * @contributor  Michael Slusarz <slusarz@horde.org>
 * @contributor  Michael Cochrane <mike@graftonhall.co.nz>
 *
 * @since  1.0
 */
class Tar implements ExtractableInterface
{
    /**
     * Tar file types.
     *
     * @var    array
     * @since  1.0
     */
    private const TYPES = [
        0x0  => 'Unix file',
        0x30 => 'File',
        0x31 => 'Link',
        0x32 => 'Symbolic link',
        0x33 => 'Character special file',
        0x34 => 'Block special file',
        0x35 => 'Directory',
        0x36 => 'FIFO special file',
        0x37 => 'Contiguous file',
    ];

    /**
     * Tar file data buffer
     *
     * @var    ?string
     * @since  1.0
     */
    private $data;

    /**
     * Tar file metadata array
     *
     * @var    array
     * @since  1.0
     */
    private $metadata;

    /**
     * Holds the options array.
     *
     * @var    array|\ArrayAccess
     * @since  1.0
     */
    protected $options = [];

    /**
     * Create a new Archive object.
     *
     * @param   array|\ArrayAccess  $options  An array of options or an object that implements \ArrayAccess
     *
     * @since   1.0
     * @throws  \InvalidArgumentException
     */
    public function __construct($options = [])
    {
        if (!\is_array($options) && !($options instanceof \ArrayAccess)) {
            throw new \InvalidArgumentException(
                'The options param must be an array or implement the ArrayAccess interface.'
            );
        }

        $this->options = $options;
    }

    /**
     * Extract a Tar archive to a given path
     *
     * @param   string  $archive      Path to Tar archive to extract
     * @param   string  $destination  Path to extract archive into
     *
     * @return  boolean True if successful
     *
     * @since   1.0
     * @throws  \RuntimeException if the archive cannot be read or an entry cannot be written
     * @throws  \OutOfBoundsException if an entry would be written outside the destination
     */
    public function extract($archive, $destination)
    {
        $this->metadata = [];
        $this->data     = file_get_contents($archive);

        if (!$this->data) {
            throw new \RuntimeException('Unable to read archive');
        }

        $this->getTarInfo($this->data);

        for ($i = 0, $n = \count($this->metadata); $i < $n; $i++) {
            $type = strtolower((string) $this->metadata[$i]['type']);

            // Links, devices and unknown entry types are not extracted
            if ($type !== 'file' && $type !== 'unix file' && $type !== 'directory') {
                continue;
            }

            $path = Path::clean($destination . '/' . $this->metadata[$i]['name']);

            if (!$this->isBelow($destination, $destination . '/' . $this->metadata[$i]['name'])) {
                throw new \OutOfBoundsException('Unable to write outside of destination path', 100);
            }

            if ($type === 'directory') {
                if (!Folder::create($path)) {
                    throw new \RuntimeException('Unable to create destination folder ' . $path);
                }

                continue;
            }

            // Make sure the destination folder exists
            if (!Folder::create(\dirname($path))) {
                throw new \RuntimeException('Unable to create destination folder ' . \dirname($path));
            }

            if (!File::write($path, $this->metadata[$i]['data'])) {
                throw new \RuntimeException('Unable to write entry to file ' . $path);
            }
        }

        return true;
    }

    /**
     * Tests whether this adapter can pack and unpack files on this computer.
     *
     * @return  boolean  True if supported
     *
     * @since   1.0
     */
    public static function isSupported()
    {
        return true;
    }

    /**
     * Get the list of files/data from a Tar archive buffer and builds a metadata array.
     *
     * Array structure:
     * <pre>
     * KEY: Position in the array
     * VALUES: 'attr'  --  File attributes
     * 'data'  --  Raw file contents
     * 'date'  --  File modification time
     * 'name'  --  Filename
     * 'size'  --  Original file size
     * 'type'  --  File type
     * </pre>
     *
     * @param   string  $data  The Tar archive buffer.
     *
     * @return  void
     *
     * @since   1.0
     * @throws  \RuntimeException
     */
    protected function getTarInfo(&$data)
    {
        $position    = 0;
        $returnArray = [];

        // Records of pax extended headers: global ones apply to all following entries, the others to the next one only
        $paxGlobal = [];
        $paxNext   = [];

        while ($position < \strlen($data)) {
            $info = @unpack(
                'Z100filename/Z8mode/Z8uid/Z8gid/a12size/a12mtime/Z8checksum/Ctypeflag/Z100link/Z6magic/Z2version/Z32uname/Z32gname/Z8devmajor/Z8devminor/Z155prefix',
                $data,
                $position
            );

            /*
             * This variable has been set in the previous loop, meaning that the filename was present in the previous block
             * to allow more than 100 characters - see below
             */
            $usedLongLink = false;

            if (isset($longlinkfilename)) {
                $info['filename'] = $longlinkfilename;
                unset($longlinkfilename);
                $usedLongLink = true;
            }

            if (!$info) {
                throw new \RuntimeException('Unable to decompress data');
            }

            /*
             * USTAR stores a path longer than the 100 character name field split across a separate
             * 155 character prefix field. Only the POSIX format uses it - the old GNU format writes
             * "ustar " into the magic and puts unrelated data where the prefix would be.
             */
            if (!$usedLongLink && $info['magic'] === 'ustar' && $info['prefix'] !== '') {
                $info['filename'] = $info['prefix'] . '/' . $info['filename'];
            }

            $isPaxHeader = $info['typeflag'] === 0x78 || $info['typeflag'] === 0x67;
            $pax         = $isPaxHeader ? [] : array_merge($paxGlobal, $paxNext);

            if (isset($pax['path'])) {
                $info['filename'] = $pax['path'];
            }

            $size = isset($pax['size']) ? (int) $pax['size'] : $this->parseNumber($info['size']);

            $position += 512;
            $contents = substr($data, $position, $size);
            $position += (int) ceil($size / 512) * 512;

            // pax extended headers (POSIX.1-2001): "x" applies to the next entry, "g" to all following entries
            if ($isPaxHeader) {
                $records = $this->parsePaxRecords($contents);

                if ($info['typeflag'] === 0x67) {
                    $paxGlobal = array_merge($paxGlobal, $records);
                } else {
                    $paxNext = $records;
                }

                continue;
            }

            $paxNext = [];

            if ($info['filename']) {
                $file = [
                    'attr' => null,
                    'data' => null,
                    'date' => isset($pax['mtime']) ? (int) $pax['mtime'] : $this->parseNumber($info['mtime']),
                    'name' => trim($info['filename']),
                    'size' => $size,
                    'type' => self::TYPES[$info['typeflag']] ?? null,
                ];

                if (($info['typeflag'] == 0) || ($info['typeflag'] == 0x30) || ($info['typeflag'] == 0x35)) {
                    // File or folder.
                    $file['data'] = $contents;

                    $mode         = hexdec(substr($info['mode'], 4, 3));
                    $file['attr'] = (($info['typeflag'] == 0x35) ? 'd' : '-')
                        . (($mode & 0x400) ? 'r' : '-')
                        . (($mode & 0x200) ? 'w' : '-')
                        . (($mode & 0x100) ? 'x' : '-')
                        . (($mode & 0x040) ? 'r' : '-')
                        . (($mode & 0x020) ? 'w' : '-')
                        . (($mode & 0x010) ? 'x' : '-')
                        . (($mode & 0x004) ? 'r' : '-')
                        . (($mode & 0x002) ? 'w' : '-')
                        . (($mode & 0x001) ? 'x' : '-');
                } elseif (\chr($info['typeflag']) == 'L' && $info['filename'] == '././@LongLink') {
                    // GNU tar ././@LongLink support - the filename is actually in the contents, set a variable here so we can test in the next loop
                    $longlinkfilename = $contents;

                    // And the file contents are in the next block so we'll need to skip this
                    continue;
                }

                $returnArray[] = $file;
            }
        }

        $this->metadata = $returnArray;
    }

    /**
     * Parse a numeric header field.
     *
     * The field is usually an octal number terminated by a NUL or a space. GNU tar and others store
     * values that do not fit, such as sizes of 8 GiB and more, in base-256 instead, which is flagged by
     * the high bit of the first byte.
     *
     * @param   string  $field  The raw header field
     *
     * @return  integer
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \RuntimeException if the field holds a negative base-256 number
     */
    private function parseNumber(string $field): int
    {
        if ($field !== '' && (\ord($field[0]) & 0x80)) {
            if (\ord($field[0]) & 0x40) {
                throw new \RuntimeException('Unable to decompress data: negative number in header');
            }

            $value = \ord($field[0]) & 0x3f;

            for ($i = 1, $n = \strlen($field); $i < $n; $i++) {
                $value = ($value << 8) | \ord($field[$i]);
            }

            return $value;
        }

        $field = trim(substr($field, 0, strcspn($field, "\0")), ' ');

        return $field === '' ? 0 : (int) octdec($field);
    }

    /**
     * Parse the records of a pax extended header.
     *
     * Each record has the form "<length> <keyword>=<value>\n", where the length counts the whole record.
     *
     * @param   string  $contents  The data of the pax header entry
     *
     * @return  array<string, string>  The values keyed by keyword
     *
     * @since   __DEPLOY_VERSION__
     */
    private function parsePaxRecords(string $contents): array
    {
        $records  = [];
        $position = 0;
        $length   = \strlen($contents);

        while ($position < $length) {
            $space = strpos($contents, ' ', $position);

            if ($space === false) {
                break;
            }

            $recordLength = (int) substr($contents, $position, $space - $position);

            if ($recordLength <= $space - $position + 1 || $position + $recordLength > $length) {
                break;
            }

            // The record without its length prefix and the trailing newline
            $record = substr($contents, $space + 1, $position + $recordLength - $space - 2);
            $equals = strpos($record, '=');

            if ($equals !== false) {
                $records[substr($record, 0, $equals)] = substr($record, $equals + 1);
            }

            $position += $recordLength;
        }

        return $records;
    }

    /**
     * Check if a path is below a given destination path
     *
     * @param   string  $destination  The destination path
     * @param   string  $path         The path to be checked
     *
     * @return  boolean
     *
     * @since   2.0.1
     */
    private function isBelow($destination, $path): bool
    {
        $absoluteRoot = Path::clean(Path::resolve($destination));
        $absolutePath = Path::clean(Path::resolve($path));

        return strpos($absolutePath, $absoluteRoot) === 0;
    }
}
