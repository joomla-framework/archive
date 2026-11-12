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
 * Bzip2 format adapter for the Archive package
 *
 * @since  1.0
 */
class Bzip2 implements ExtractableInterface, CreatableInterface
{
    use TarWrappingTrait;

    /**
     * Bzip2 file data buffer
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
     * Create a Bzip2 compressed file from an array of file data.
     *
     * Bzip2 compresses a single stream and has no concept of entries. One entry is therefore
     * compressed as it stands, unless the archive is named as a tarball - `backup.tar.bz2` or
     * `backup.tbz2` - in which case it is packed into a tar first. Several entries always need that
     * tar, so an archive name that does not ask for one is rejected rather than written.
     *
     * Set the `bzip2_blocksize` option to pick a block size between 1 and 9, in units of 100 kB.
     * The default is 4.
     *
     * @param   string  $archive  Path to save the archive to.
     * @param   array   $files    Array of file data to add to the archive. See `CreatableInterface`.
     *
     * @return  boolean  True if successful.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if there is nothing to compress, if several entries are given
     *                                    for an archive not named as a tarball, or if the block size is invalid
     * @throws  \RuntimeException if the data cannot be compressed or written
     */
    public function create($archive, $files)
    {
        $blockSize = $this->options['bzip2_blocksize'] ?? 4;

        if (!\is_int($blockSize) || $blockSize < 1 || $blockSize > 9) {
            throw new \InvalidArgumentException(
                'The "bzip2_blocksize" option must be an integer between 1 and 9.'
            );
        }

        $payload = $this->buildPayload($archive, $files);
        $buffer  = bzcompress($payload['data'], $blockSize);

        // bzcompress() reports failure by returning an error number instead of a string
        if (!\is_string($buffer)) {
            throw new \RuntimeException(sprintf('Unable to compress data, bzip2 error %d', $buffer));
        }

        if (!File::write($archive, $buffer)) {
            throw new \RuntimeException('Unable to write archive to file ' . $archive);
        }

        return true;
    }

    /**
     * Extract a Bzip2 compressed file to a given path
     *
     * @param   string  $archive      Path to Bzip2 archive to extract
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
            // Old style: read the whole file and then parse it
            $this->data = file_get_contents($archive);

            if (!$this->data) {
                throw new \RuntimeException('Unable to read archive');
            }

            $buffer = bzdecompress($this->data);
            unset($this->data);

            if (empty($buffer)) {
                throw new \RuntimeException('Unable to decompress data');
            }

            if (!File::write($destination, $buffer)) {
                throw new \RuntimeException('Unable to write archive to file ' . $destination);
            }
        } else {
            // New style! streams!
            $input = Stream::getStream();

            // Use bzip
            $input->set('processingmethod', 'bz');

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
        return \extension_loaded('bz2');
    }
}
