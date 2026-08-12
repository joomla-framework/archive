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
class Tar implements ExtractableInterface, CreatableInterface
{
    /**
     * Length of a tar header block, and the multiple every entry is padded to.
     *
     * @var    integer
     * @since  __DEPLOY_VERSION__
     */
    private const BLOCK_SIZE = 512;

    /**
     * Largest file size the 11 octal digits of the USTAR size field can express.
     *
     * @var    integer
     * @since  __DEPLOY_VERSION__
     */
    private const MAX_ENTRY_SIZE = 8589934591;

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
     * Create a Tar archive from an array of file data.
     *
     * Each entry is an array with the following keys:
     *
     * <pre>
     * 'name' --  Path of the entry inside the archive. Required. A trailing slash makes it a directory.
     * 'data' --  Raw contents of the entry. Ignored for directories, defaults to an empty string.
     * 'time' --  Modification time as a UNIX timestamp. Defaults to the current time.
     * 'mode' --  Permissions as an integer. Defaults to 0644 for files and 0755 for directories.
     * </pre>
     *
     * The archive is assembled in memory before it is written, so the whole of it has to fit into
     * `memory_limit`. Individual entries are limited to 8 GiB by the tar format itself.
     *
     * @param   string  $archive  Path to save the archive to.
     * @param   array   $files    Array of file data to add to the archive.
     *
     * @return  boolean  True if successful.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if an entry is malformed or cannot be represented in the tar format
     * @throws  \RuntimeException if the archive cannot be written
     */
    public function create($archive, $files)
    {
        if (!File::write($archive, $this->createData($files))) {
            throw new \RuntimeException('Unable to write archive to file ' . $archive);
        }

        return true;
    }

    /**
     * Build a Tar archive from an array of file data and return it as a string.
     *
     * @param   array  $files  Array of file data to add to the archive. See `create()`.
     *
     * @return  string  The raw archive.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if an entry is malformed or cannot be represented in the tar format
     */
    public function createData(array $files): string
    {
        $data = '';

        foreach ($files as $file) {
            if (!\is_array($file) && !($file instanceof \ArrayAccess)) {
                throw new \InvalidArgumentException(
                    'Each entry must be an array or implement the ArrayAccess interface.'
                );
            }

            if (!isset($file['name']) || (string) $file['name'] === '') {
                throw new \InvalidArgumentException('Each entry must have a non-empty "name".');
            }

            $name        = str_replace('\\', '/', (string) $file['name']);
            $isDirectory = substr($name, -1) === '/';
            $contents    = $isDirectory ? '' : (string) ($file['data'] ?? '');

            if (\strlen($contents) > self::MAX_ENTRY_SIZE) {
                throw new \InvalidArgumentException(
                    sprintf('Entry "%s" exceeds the 8 GiB the tar format can store.', $name)
                );
            }

            $data .= $this->buildHeader(
                $name,
                \strlen($contents),
                (int) ($file['mode'] ?? ($isDirectory ? 0755 : 0644)),
                (int) ($file['time'] ?? time()),
                $isDirectory ? '5' : '0'
            );

            if ($contents !== '') {
                // Entry data is padded out to a whole number of blocks
                $data .= str_pad(
                    $contents,
                    (int) ceil(\strlen($contents) / self::BLOCK_SIZE) * self::BLOCK_SIZE,
                    "\0"
                );
            }
        }

        // A tar archive is terminated by two blocks of nulls
        return $data . str_repeat("\0", self::BLOCK_SIZE * 2);
    }

    /**
     * Build the 512 byte USTAR header block for one entry.
     *
     * @param   string   $name  Path of the entry inside the archive.
     * @param   integer  $size  Size of the entry data in bytes.
     * @param   integer  $mode  Permissions.
     * @param   integer  $time  Modification time as a UNIX timestamp.
     * @param   string   $type  The type flag: "0" for a file, "5" for a directory.
     *
     * @return  string
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if the name is too long for the format
     */
    private function buildHeader(string $name, int $size, int $mode, int $time, string $type): string
    {
        [$prefix, $name] = $this->splitName($name);

        $header = str_pad($name, 100, "\0")            // name
            . sprintf("%07o\0", $mode & 0777)          // mode
            . sprintf("%07o\0", 0)                     // uid
            . sprintf("%07o\0", 0)                     // gid
            . sprintf("%011o\0", $size)                // size
            . sprintf("%011o\0", $time)                // mtime
            . '        '                               // checksum, spaces while it is calculated
            . $type                                    // typeflag
            . str_repeat("\0", 100)                    // linkname
            . "ustar\0"                                // magic
            . '00'                                     // version
            . str_repeat("\0", 32)                     // uname
            . str_repeat("\0", 32)                     // gname
            . sprintf("%07o\0", 0)                     // devmajor
            . sprintf("%07o\0", 0)                     // devminor
            . str_pad($prefix, 155, "\0");             // prefix

        $header = str_pad($header, self::BLOCK_SIZE, "\0");

        $checksum = 0;

        for ($i = 0; $i < self::BLOCK_SIZE; $i++) {
            $checksum += \ord($header[$i]);
        }

        // The checksum itself occupies bytes 148 to 155
        return substr_replace($header, sprintf("%06o\0 ", $checksum), 148, 8);
    }

    /**
     * Split a path into the USTAR prefix and name fields.
     *
     * @param   string  $name  Path of the entry inside the archive.
     *
     * @return  array  A [prefix, name] pair.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if the path cannot be split to fit
     */
    private function splitName(string $name): array
    {
        if (\strlen($name) <= 100) {
            return ['', $name];
        }

        /*
         * Split as late as possible: the further right the slash, the shorter the remainder. The
         * first candidate scanning left therefore gives the only chance of fitting - if its
         * remainder is too long, every shorter prefix produces a longer one still.
         */
        $slash = strrpos(substr($name, 0, 156), '/');

        if ($slash !== false && $slash > 0 && \strlen($name) - $slash - 1 <= 100) {
            return [substr($name, 0, $slash), substr($name, $slash + 1)];
        }

        throw new \InvalidArgumentException(
            sprintf(
                'The path "%s" cannot be stored in a tar archive: it does not fit the 100 character'
                . ' name field and has no slash that would split it across the 155 character prefix field.',
                $name
            )
        );
    }

    /**
     * Extract a ZIP compressed file to a given path
     *
     * @param   string  $archive      Path to ZIP archive to extract
     * @param   string  $destination  Path to extract archive into
     *
     * @return  boolean True if successful
     *
     * @since   1.0
     * @throws  \RuntimeException
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
            $type = strtolower($this->metadata[$i]['type']);

            if ($type == 'file' || $type == 'unix file') {
                $buffer = $this->metadata[$i]['data'];
                $path   = Path::clean($destination . '/' . $this->metadata[$i]['name']);

                if (!$this->isBelow($destination, $destination . '/' . $this->metadata[$i]['name'])) {
                    throw new \OutOfBoundsException('Unable to write outside of destination path', 100);
                }

                // Make sure the destination folder exists
                if (!Folder::create(\dirname($path))) {
                    throw new \RuntimeException('Unable to create destination folder ' . \dirname($path));
                }

                if (!File::write($path, $buffer)) {
                    throw new \RuntimeException('Unable to write entry to file ' . $path);
                }
            }
        }

        return true;
    }

    /**
     * Tests whether this adapter can unpack files on this computer.
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

        while ($position < \strlen($data)) {
            $info = @unpack(
                'Z100filename/Z8mode/Z8uid/Z8gid/Z12size/Z12mtime/Z8checksum/Ctypeflag/Z100link/Z6magic/Z2version/Z32uname/Z32gname/Z8devmajor/Z8devminor',
                $data,
                $position
            );

            /*
             * This variable has been set in the previous loop, meaning that the filename was present in the previous block
             * to allow more than 100 characters - see below
             */
            if (isset($longlinkfilename)) {
                $info['filename'] = $longlinkfilename;
                unset($longlinkfilename);
            }

            if (!$info) {
                throw new \RuntimeException('Unable to decompress data');
            }

            $position += 512;
            $contents = substr($data, $position, octdec($info['size']));
            $position += (int) ceil(octdec($info['size']) / 512) * 512;

            if ($info['filename']) {
                $file = [
                    'attr' => null,
                    'data' => null,
                    'date' => octdec($info['mtime']),
                    'name' => trim($info['filename']),
                    'size' => octdec($info['size']),
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
