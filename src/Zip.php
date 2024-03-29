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
 * ZIP format adapter for the Archive package
 *
 * The ZIP compression code is partially based on code from:
 * Eric Mueller <eric@themepark.com>
 * http://www.zend.com/codex.php?id=535&single=1
 *
 * Deins125 <webmaster@atlant.ru>
 * http://www.zend.com/codex.php?id=470&single=1
 *
 * The ZIP compression date code is partially based on code from
 * Peter Listiak <mlady@users.sourceforge.net>
 *
 * This class is inspired from and draws heavily in code and concept from the Compress package of
 * The Horde Project <http://www.horde.org>
 *
 * @contributor  Chuck Hagenbuch <chuck@horde.org>
 * @contributor  Michael Slusarz <slusarz@horde.org>
 * @contributor  Michael Cochrane <mike@graftonhall.co.nz>
 *
 * @since  1.0
 */
class Zip implements ExtractableInterface
{
    /**
     * ZIP compression methods.
     *
     * @var    array
     * @since  1.0
     */
    private const METHODS = [
        0x0 => 'None',
        0x1 => 'Shrunk',
        0x2 => 'Super Fast',
        0x3 => 'Fast',
        0x4 => 'Normal',
        0x5 => 'Maximum',
        0x6 => 'Imploded',
        0x8 => 'Deflated',
    ];

    /**
     * Beginning of central directory record.
     *
     * @var    string
     * @since  1.0
     */
    private const CTRL_DIR_HEADER = "\x50\x4b\x01\x02";

    /**
     * End of central directory record.
     *
     * @var    string
     * @since  1.0
     */
    private const CTRL_DIR_END = "\x50\x4b\x05\x06\x00\x00\x00\x00";

    /**
     * Beginning of file contents.
     *
     * @var    string
     * @since  1.0
     */
    private const FILE_HEADER = "\x50\x4b\x03\x04";

    /**
     * ZIP file data buffer
     *
     * @var    string
     * @since  1.0
     */
    private $data;

    /**
     * ZIP file metadata array
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
     * Create a ZIP compressed file from an array of file data.
     *
     * @param   string  $archive  Path to save archive.
     * @param   array   $files    Array of files to add to archive.
     *
     * @return  boolean  True if successful.
     *
     * @since   1.0
     * @todo    Finish Implementation
     */
    public function create($archive, $files)
    {
        $contents = [];
        $ctrldir  = [];

        foreach ($files as $file) {
            $this->addToZipFile($file, $contents, $ctrldir);
        }

        return $this->createZipFile($contents, $ctrldir, $archive);
    }

    /**
     * Extract a ZIP compressed file to a given path
     *
     * @param   string  $archive      Path to ZIP archive to extract
     * @param   string  $destination  Path to extract archive into
     *
     * @return  boolean  True if successful
     *
     * @since   1.0
     * @throws  \RuntimeException
     */
    public function extract($archive, $destination)
    {
        if (!is_file($archive)) {
            throw new \RuntimeException('Archive does not exist at ' . $archive);
        }

        if (static::hasNativeSupport()) {
            return $this->extractNative($archive, $destination);
        }

        return $this->extractCustom($archive, $destination);
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
        return self::hasNativeSupport() || \extension_loaded('zlib');
    }

    /**
     * Method to determine if the server has native zip support for faster handling
     *
     * @return  boolean  True if php has native ZIP support
     *
     * @since   1.0
     */
    public static function hasNativeSupport()
    {
        return \extension_loaded('zip');
    }

    /**
     * Checks to see if the data is a valid ZIP file.
     *
     * @param   string  $data  ZIP archive data buffer.
     *
     * @return  boolean  True if valid, false if invalid.
     *
     * @since   1.0
     */
    public function checkZipData($data)
    {
        return strpos($data, self::FILE_HEADER) !== false;
    }

    /**
     * Extract a ZIP compressed file to a given path using a php based algorithm that only requires zlib support
     *
     * @param   string  $archive      Path to ZIP archive to extract.
     * @param   string  $destination  Path to extract archive into.
     *
     * @return  boolean  True if successful
     *
     * @since   1.0
     * @throws  \RuntimeException
     */
    protected function extractCustom($archive, $destination)
    {
        $this->metadata = [];
        $this->data     = file_get_contents($archive);

        if (!$this->data) {
            throw new \RuntimeException('Unable to read archive');
        }

        if (!$this->readZipInfo($this->data)) {
            throw new \RuntimeException('Get ZIP Information failed');
        }

        foreach ($this->metadata as $i => $metadata) {
            $lastPathCharacter = substr($metadata['name'], -1, 1);

            if ($lastPathCharacter !== '/' && $lastPathCharacter !== '\\') {
                $buffer = $this->getFileData($i);
                $path   = Path::clean($destination . '/' . $metadata['name']);

                if (!$this->isBelow($destination, $destination . '/' . $metadata['name'])) {
                    throw new \OutOfBoundsException('Unable to write outside of destination path', 100);
                }

                // Make sure the destination folder exists
                if (!Folder::create(\dirname($path))) {
                    throw new \RuntimeException('Unable to create destination folder');
                }

                if (!File::write($path, $buffer)) {
                    throw new \RuntimeException('Unable to write file');
                }
            }
        }

        return true;
    }

    /**
     * Extract a ZIP compressed file to a given path using native php api calls for speed
     *
     * @param   string  $archive      Path to ZIP archive to extract
     * @param   string  $destination  Path to extract archive into
     *
     * @return  boolean  True on success
     *
     * @throws  \RuntimeException
     * @since   1.0
     */
    protected function extractNative($archive, $destination)
    {
        $zip = new \ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Unable to open archive');
        }

        // Make sure the destination folder exists
        if (!Folder::create($destination)) {
            throw new \RuntimeException('Unable to create destination folder ' . \dirname($destination));
        }

        // Read files in the archive
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $file = $zip->getNameIndex($index);

            if (substr($file, -1) === '/') {
                continue;
            }

            $buffer = $zip->getFromIndex($index);

            if ($buffer === false) {
                throw new \RuntimeException('Unable to read ZIP entry');
            }

            if (!$this->isBelow($destination, $destination . '/' . $file)) {
                throw new \RuntimeException('Unable to write outside of destination path', 100);
            }

            if (File::write($destination . '/' . $file, $buffer) === false) {
                throw new \RuntimeException('Unable to write ZIP entry to file ' . $destination . '/' . $file);
            }
        }

        $zip->close();

        return true;
    }

    /**
     * Get the list of files/data from a ZIP archive buffer.
     *
     * <pre>
     * KEY: Position in zipfile
     * VALUES: 'attr'  --  File attributes
     *         'crc'   --  CRC checksum
     *         'csize' --  Compressed file size
     *         'date'  --  File modification time
     *         'name'  --  Filename
     *         'method'--  Compression method
     *         'size'  --  Original file size
     *         'type'  --  File type
     * </pre>
     *
     * @param   string  $data  The ZIP archive buffer.
     *
     * @return  boolean True on success
     *
     * @since   1.0
     * @throws  \RuntimeException
     */
    private function readZipInfo($data)
    {
        $entries = [];

        // Find the last central directory header entry
        $fhLast = strpos($data, self::CTRL_DIR_END);

        do {
            $last = $fhLast;
        } while (($fhLast = strpos($data, self::CTRL_DIR_END, $fhLast + 1)) !== false);

        // Find the central directory offset
        $offset = 0;

        if ($last) {
            $endOfCentralDirectory = unpack(
                'vNumberOfDisk/vNoOfDiskWithStartOfCentralDirectory/vNoOfCentralDirectoryEntriesOnDisk/' .
                'vTotalCentralDirectoryEntries/VSizeOfCentralDirectory/VCentralDirectoryOffset/vCommentLength',
                $data,
                $last + 4
            );
            $offset = $endOfCentralDirectory['CentralDirectoryOffset'];
        }

        // Get details from central directory structure.
        $fhStart    = strpos($data, self::CTRL_DIR_HEADER, $offset);
        $dataLength = \strlen($data);

        do {
            if ($dataLength < $fhStart + 31) {
                throw new \RuntimeException('Invalid ZIP Data');
            }

            $info = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength', $data, $fhStart + 10);
            $name = substr($data, $fhStart + 46, $info['Length']);

            $entries[$name] = [
                'attr'       => null,
                'crc'        => sprintf('%08s', dechex($info['CRC32'])),
                'csize'      => $info['Compressed'],
                'date'       => null,
                '_dataStart' => null,
                'name'       => $name,
                'method'     => self::METHODS[$info['Method']],
                '_method'    => $info['Method'],
                'size'       => $info['Uncompressed'],
                'type'       => null,
            ];

            $entries[$name]['date'] = mktime(
                ($info['Time'] >> 11) & 0x1f,
                ($info['Time'] >> 5) & 0x3f,
                ($info['Time'] << 1) & 0x3e,
                ($info['Time'] >> 21) & 0x07,
                ($info['Time'] >> 16) & 0x1f,
                (($info['Time'] >> 25) & 0x7f) + 1980
            );

            if ($dataLength < $fhStart + 43) {
                throw new \RuntimeException('Invalid ZIP data');
            }

            $info = unpack('vInternal/VExternal/VOffset', $data, $fhStart + 36);

            $entries[$name]['type'] = ($info['Internal'] & 0x01) ? 'text' : 'binary';
            $entries[$name]['attr'] = (($info['External'] & 0x10) ? 'D' : '-') . (($info['External'] & 0x20) ? 'A' : '-')
                . (($info['External'] & 0x03) ? 'S' : '-') . (($info['External'] & 0x02) ? 'H' : '-') . (($info['External'] & 0x01) ? 'R' : '-');
            $entries[$name]['offset'] = $info['Offset'];

            // Get details from local file header since we have the offset
            $lfhStart = strpos($data, self::FILE_HEADER, $entries[$name]['offset']);

            if ($dataLength < $lfhStart + 34) {
                throw new \RuntimeException('Invalid ZIP Data');
            }

            $info                         = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength/vExtraLength', $data, $lfhStart + 8);
            $name                         = substr($data, $lfhStart + 30, $info['Length']);
            $entries[$name]['_dataStart'] = $lfhStart + 30 + $info['Length'] + $info['ExtraLength'];

            // Bump the max execution time because not using the built in php zip libs makes this process slow.
            @set_time_limit(ini_get('max_execution_time'));
        } while (($fhStart = strpos($data, self::CTRL_DIR_HEADER, $fhStart + 46)) !== false);

        $this->metadata = array_values($entries);

        return true;
    }

    /**
     * Returns the file data for a file by offset in the ZIP archive
     *
     * @param   integer  $key  The position of the file in the archive.
     *
     * @return  string  Uncompressed file data buffer.
     *
     * @since   1.0
     */
    private function getFileData(int $key): string
    {
        if ($this->metadata[$key]['_method'] == 0x8) {
            return gzinflate(substr($this->data, $this->metadata[$key]['_dataStart'], $this->metadata[$key]['csize']));
        }

        if ($this->metadata[$key]['_method'] == 0x0) {
            // Files that aren't compressed.
            return substr($this->data, $this->metadata[$key]['_dataStart'], $this->metadata[$key]['csize']);
        }

        if ($this->metadata[$key]['_method'] == 0x12) {
            // If bz2 extension is loaded use it
            if (\extension_loaded('bz2')) {
                return bzdecompress(substr($this->data, $this->metadata[$key]['_dataStart'], $this->metadata[$key]['csize']));
            }
        }

        return '';
    }

    /**
     * Converts a UNIX timestamp to a 4-byte DOS date and time format (date in high 2-bytes, time in low 2-bytes allowing magnitude comparison).
     *
     * @param   integer  $unixtime  The current UNIX timestamp.
     *
     * @return  integer  The current date in a 4-byte DOS format.
     *
     * @since   1.0
     */
    protected function unix2DosTime($unixtime = null)
    {
        $timearray = $unixtime === null ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        }

        return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) | ($timearray['hours'] << 11) |
            ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
    }

    /**
     * Adds a "file" to the ZIP archive.
     *
     * @param   array  $file      File data array to add
     * @param   array  $contents  An array of existing zipped files.
     * @param   array  $ctrldir   An array of central directory information.
     *
     * @return  void
     *
     * @since   1.0
     * @todo    Review and finish implementation
     */
    private function addToZipFile(array &$file, array &$contents, array &$ctrldir): void
    {
        $data = &$file['data'];
        $name = str_replace('\\', '/', $file['name']);

        // See if time/date information has been provided.
        $ftime = null;

        if (isset($file['time'])) {
            $ftime = $file['time'];
        }

        // Get the hex time.
        $dtime    = dechex($this->unix2DosTime($ftime));
        $hexdtime = \chr(hexdec($dtime[6] . $dtime[7])) . \chr(hexdec($dtime[4] . $dtime[5])) . \chr(hexdec($dtime[2] . $dtime[3]))
            . \chr(hexdec($dtime[0] . $dtime[1]));

        // Begin creating the ZIP data.
        $fr = self::FILE_HEADER;

        // Version needed to extract.
        $fr .= "\x14\x00";

        // General purpose bit flag.
        $fr .= "\x00\x00";

        // Compression method.
        $fr .= "\x08\x00";

        // Last modification time/date.
        $fr .= $hexdtime;

        // "Local file header" segment.
        $uncLen = \strlen($data);
        $crc    = crc32($data);
        $zdata  = gzcompress($data);
        $zdata  = substr(substr($zdata, 0, -4), 2);
        $cLen   = \strlen($zdata);

        // CRC 32 information.
        $fr .= pack('V', $crc);

        // Compressed filesize.
        $fr .= pack('V', $cLen);

        // Uncompressed filesize.
        $fr .= pack('V', $uncLen);

        // Length of filename.
        $fr .= pack('v', \strlen($name));

        // Extra field length.
        $fr .= pack('v', 0);

        // File name.
        $fr .= $name;

        // "File data" segment.
        $fr .= $zdata;

        // Add this entry to array.
        $oldOffset  = \strlen(implode('', $contents));
        $contents[] = &$fr;

        // Add to central directory record.
        $cdrec = self::CTRL_DIR_HEADER;

        // Version made by.
        $cdrec .= "\x00\x00";

        // Version needed to extract
        $cdrec .= "\x14\x00";

        // General purpose bit flag
        $cdrec .= "\x00\x00";

        // Compression method
        $cdrec .= "\x08\x00";

        // Last mod time/date.
        $cdrec .= $hexdtime;

        // CRC 32 information.
        $cdrec .= pack('V', $crc);

        // Compressed filesize.
        $cdrec .= pack('V', $cLen);

        // Uncompressed filesize.
        $cdrec .= pack('V', $uncLen);

        // Length of filename.
        $cdrec .= pack('v', \strlen($name));

        // Extra field length.
        $cdrec .= pack('v', 0);

        // File comment length.
        $cdrec .= pack('v', 0);

        // Disk number start.
        $cdrec .= pack('v', 0);

        // Internal file attributes.
        $cdrec .= pack('v', 0);

        // External file attributes -'archive' bit set.
        $cdrec .= pack('V', 32);

        // Relative offset of local header.
        $cdrec .= pack('V', $oldOffset);

        // File name.
        $cdrec .= $name;

        // Save to central directory array.
        $ctrldir[] = &$cdrec;
    }

    /**
     * Creates the ZIP file.
     *
     * Official ZIP file format: http://www.pkware.com/appnote.txt
     *
     * @param   array   $contents  An array of existing zipped files.
     * @param   array   $ctrlDir   An array of central directory information.
     * @param   string  $path      The path to store the archive.
     *
     * @return  boolean  True if successful
     *
     * @since   1.0
     * @todo    Review and finish implementation
     */
    private function createZipFile(array $contents, array $ctrlDir, string $path): bool
    {
        $data = implode('', $contents);
        $dir  = implode('', $ctrlDir);

        /*
         * Buffer data:
         * Total # of entries "on this disk".
         * Total # of entries overall.
         * Size of central directory.
         * Offset to start of central dir.
         * ZIP file comment length.
         */
        $buffer = $data . $dir . self::CTRL_DIR_END .
        pack('v', \count($ctrlDir)) .
        pack('v', \count($ctrlDir)) .
        pack('V', \strlen($dir)) .
        pack('V', \strlen($data)) .
        "\x00\x00";

        return File::write($path, $buffer);
    }

    /**
     * Check if a path is below a given destination path
     *
     * @param   string  $destination  The destination path
     * @param   string  $path         The path to be checked
     *
     * @return  boolean
     *
     * @since   1.1.10
     */
    private function isBelow($destination, $path): bool
    {
        $absoluteRoot = Path::clean(Path::resolve($destination));
        $absolutePath = Path::clean(Path::resolve($path));

        return strpos($absolutePath, $absoluteRoot) === 0;
    }
}
