<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Stream;

/**
 * Gzip format adapter for the Archive package
 *
 * This class is inspired from and draws heavily in code and concept from the Compress package of
 * The Horde Project <http://www.horde.org>
 *
 * @contributor  Michael Slusarz <slusarz@horde.org>
 * @contributor  Michael Cochrane <mike@graftonhall.co.nz>
 *
 * @since  1.0
 */
class Gzip implements ExtractableInterface, CreatableInterface
{
    use TarWrappingTrait;

    /**
     * Gzip file flags.
     *
     * @var    array
     * @since  1.0
     */
    private const FLAGS = ['FTEXT' => 0x01, 'FHCRC' => 0x02, 'FEXTRA' => 0x04, 'FNAME' => 0x08, 'FCOMMENT' => 0x10];

    /**
     * Gzip file data buffer
     *
     * @var    ?string
     * @since  1.0
     */
    private $data;

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
     * @param   array|\ArrayAccess  $options  An array of options
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
     * Create a Gzip compressed file from an array of file data.
     *
     * Gzip compresses a single stream and has no concept of entries. One entry is therefore
     * compressed as it stands, unless the archive is named as a tarball - `backup.tar.gz` or
     * `backup.tgz` - in which case it is packed into a tar first. Several entries always need that
     * tar, so an archive name that does not ask for one is rejected rather than written.
     *
     * The name is what decides, using the test `Archive::extract()` applies when unpacking.
     *
     * Set the `gzip_level` option to pick a compression level between 0 and 9; the default, -1,
     * leaves the choice to zlib.
     *
     * @param   string  $archive  Path to save the archive to.
     * @param   array   $files    Array of file data to add to the archive. See `CreatableInterface`.
     *
     * @return  boolean  True if successful.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if there is nothing to compress, if several entries are given
     *                                    for an archive not named as a tarball, or if the level is invalid
     * @throws  \RuntimeException if the data cannot be compressed or written
     */
    public function create($archive, $files)
    {
        $level = $this->options['gzip_level'] ?? -1;

        if (!\is_int($level) || $level < -1 || $level > 9) {
            throw new \InvalidArgumentException(
                'The "gzip_level" option must be an integer between 0 and 9, or -1 for the zlib default.'
            );
        }

        $payload = $this->buildPayload($archive, $files);
        $buffer  = gzencode($payload['data'], $level);

        if ($buffer === false) {
            throw new \RuntimeException('Unable to compress data');
        }

        if ($payload['name'] !== null) {
            $buffer = $this->addOriginalName($buffer, $payload['name']);
        }

        if (!File::write($archive, $buffer)) {
            throw new \RuntimeException('Unable to write archive to file ' . $archive);
        }

        return true;
    }

    /**
     * Record the original file name in the gzip header.
     *
     * `gzencode()` does not store one, which leaves `gunzip` guessing the name from the archive
     * instead. Writing the FNAME field is what every other gzip implementation does, and this
     * package reads it back in `getFilePosition()`.
     *
     * @param   string  $buffer  The gzip data returned by `gzencode()`.
     * @param   string  $name    The original file name.
     *
     * @return  string
     *
     * @since   __DEPLOY_VERSION__
     */
    private function addOriginalName(string $buffer, string $name): string
    {
        // The name is stored NUL terminated, so it cannot contain one itself
        $name = str_replace("\0", '', $name);

        /*
         * Only splice into a header we recognise: the 10 bytes gzencode() writes, with no flags
         * set. Anything else and the offset below would not be the end of the header.
         */
        if ($name === '' || \strlen($buffer) < 10 || $buffer[0] !== "\x1f" || $buffer[1] !== "\x8b" || $buffer[3] !== "\0") {
            return $buffer;
        }

        $buffer[3] = \chr(self::FLAGS['FNAME']);

        return substr($buffer, 0, 10) . $name . "\0" . substr($buffer, 10);
    }

    /**
     * Extract a Gzip compressed file to a given path
     *
     * @param   string  $archive      Path to Gzip archive to extract
     * @param   string  $destination  Path to extract archive to
     *
     * @return  boolean  True if successful
     *
     * @since   1.0
     * @throws  \RuntimeException
     */
    public function extract($archive, $destination)
    {
        $this->data = '';

        if (!isset($this->options['use_streams']) || $this->options['use_streams'] == false) {
            $this->data = file_get_contents($archive);

            if (!$this->data) {
                throw new \RuntimeException('Unable to read archive');
            }

            $position = $this->getFilePosition();
            $buffer   = gzinflate(substr($this->data, $position, \strlen($this->data) - $position));

            if (empty($buffer)) {
                throw new \RuntimeException('Unable to decompress data');
            }

            if (!File::write($destination, $buffer)) {
                throw new \RuntimeException('Unable to write archive to file ' . $destination);
            }
        } else {
            // New style! streams!
            $input = Stream::getStream();

            // Use gz
            $input->set('processingmethod', 'gz');

            if (!$input->open($archive)) {
                throw new \RuntimeException('Unable to read archive');
            }

            $output = Stream::getStream();

            if (!$output->open($destination, 'w')) {
                $input->close();

                throw new \RuntimeException('Unable to open file "' . $destination . '" for writing');
            }

            do {
                $this->data = $input->read($input->get('chunksize', 8196));

                if ($this->data) {
                    if (!$output->write($this->data)) {
                        $input->close();

                        throw new \RuntimeException('Unable to write archive to file ' . $destination);
                    }
                }
            } while ($this->data);

            $output->close();
            $input->close();
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
        return \extension_loaded('zlib');
    }

    /**
     * Get file data offset for archive
     *
     * @return  integer  Data position marker for archive
     *
     * @since   1.0
     * @throws  \RuntimeException
     */
    public function getFilePosition()
    {
        // Gzipped file... unpack it first
        $position = 0;
        $info     = @ unpack('CCM/CFLG/VTime/CXFL/COS', $this->data, $position + 2);

        if (!$info) {
            throw new \RuntimeException('Unable to decompress data.');
        }

        $position += 10;

        if ($info['FLG'] & self::FLAGS['FEXTRA']) {
            $XLEN = unpack('vLength', $this->data, $position);
            $XLEN = $XLEN['Length'];
            $position += $XLEN + 2;
        }

        if ($info['FLG'] & self::FLAGS['FNAME']) {
            $filenamePos = strpos($this->data, "\x0", $position);
            $position    = $filenamePos + 1;
        }

        if ($info['FLG'] & self::FLAGS['FCOMMENT']) {
            $commentPos = strpos($this->data, "\x0", $position);
            $position   = $commentPos + 1;
        }

        if ($info['FLG'] & self::FLAGS['FHCRC']) {
            $hcrc = unpack('vCRC', $this->data, $position);
            $hcrc = $hcrc['CRC'];
            $position += 2;
        }

        return $position;
    }
}
