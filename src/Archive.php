<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

use Joomla\Archive\Exception\UnknownArchiveException;
use Joomla\Archive\Exception\UnsupportedArchiveException;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

/**
 * An Archive handling class
 *
 * @since  1.0
 */
class Archive
{
    /**
     * The array of instantiated archive adapters.
     *
     * @var    ExtractableInterface[]
     * @since  1.0
     */
    protected $adapters = [];

    /**
     * Holds the options array.
     *
     * @var    array|\ArrayAccess
     * @since  1.0
     */
    public $options = [];

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

        // Make sure we have a tmp directory.
        isset($options['tmp_path']) || $options['tmp_path'] = realpath(sys_get_temp_dir());

        $this->options = $options;
    }

    /**
     * Extract an archive file to a directory.
     *
     * @param   string  $archivename  The name of the archive file
     * @param   string  $extractdir   Directory to unpack into
     *
     * @return  boolean  True for success
     *
     * @since   1.0
     * @throws  UnknownArchiveException if the archive type is not supported
     * @throws  \RuntimeException if the archive cannot be extracted
     */
    public function extract($archivename, $extractdir)
    {
        $ext = pathinfo($archivename, \PATHINFO_EXTENSION);

        switch (strtolower($ext)) {
            case 'zip':
                $result = $this->getAdapter('zip')->extract($archivename, $extractdir);

                break;

            case 'tar':
                $result = $this->getAdapter('tar')->extract($archivename, $extractdir);

                break;

            case 'tgz':
            case 'gz':
            case 'gzip':
                $result = $this->extractCompressedFile('gzip', $archivename, $extractdir);

                break;

            case 'tbz2':
            case 'bz2':
            case 'bzip2':
                $result = $this->extractCompressedFile('bzip2', $archivename, $extractdir);

                break;

            default:
                throw new UnknownArchiveException(sprintf('Unsupported archive type: %s', $ext));
        }

        return $result;
    }

    /**
     * Extract a gzip or bzip2 compressed file.
     *
     * The file is decompressed into a temporary file first. If it is a tarball (.tgz, .tbz2 or .tar in the name), the
     * tarball is extracted into the directory; otherwise the decompressed file is copied there under the archive's name
     * without the compression extension.
     *
     * @param   string  $type         The adapter type (gzip or bzip2)
     * @param   string  $archivename  The name of the archive file
     * @param   string  $extractdir   Directory to unpack into
     *
     * @return  boolean  True for success
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \RuntimeException if the archive cannot be extracted
     */
    private function extractCompressedFile(string $type, string $archivename, string $extractdir): bool
    {
        $ext      = strtolower(pathinfo($archivename, \PATHINFO_EXTENSION));
        $filename = pathinfo($archivename, \PATHINFO_FILENAME);

        // tempnam() creates the file exclusively under an unpredictable name, so nobody can plant a symlink there
        $tmpfname = @tempnam($this->options['tmp_path'], $type);

        if ($tmpfname === false) {
            throw new \RuntimeException('Unable to create a temporary file in ' . $this->options['tmp_path']);
        }

        try {
            $this->getAdapter($type)->extract($archivename, $tmpfname);

            if ($ext === 'tgz' || $ext === 'tbz2' || stripos($filename, '.tar') !== false) {
                return $this->getAdapter('tar')->extract($tmpfname, $extractdir);
            }

            // This is just an individual file (e.g. sql script)
            if (!empty($this->options['check_single_file'])) {
                $this->checkSingleFile($filename, $tmpfname);
            }

            Folder::create($extractdir);

            return File::copy($tmpfname, $extractdir . '/' . $filename, null, false);
        } finally {
            @unlink($tmpfname);
        }
    }

    /**
     * Check a decompressed single file with File::isSafeFile() before it is copied to the destination.
     *
     * @param   string  $filename  The name the file will get in the destination
     * @param   string  $tmpfname  The decompressed temporary file
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \RuntimeException if the file is not safe
     */
    private function checkSingleFile(string $filename, string $tmpfname): void
    {
        $options = \is_array($this->options['check_single_file']) ? $this->options['check_single_file'] : [];
        $file    = [
            'name'     => $filename,
            'type'     => '',
            'tmp_name' => $tmpfname,
            'error'    => 0,
            'size'     => filesize($tmpfname),
        ];

        if (!File::isSafeFile($file, $options)) {
            throw new \RuntimeException(sprintf('The extracted file "%s" is not safe', $filename));
        }
    }

    /**
     * Method to override the provided adapter with your own implementation.
     *
     * @param   string   $type      Name of the adapter to set.
     * @param   string   $class     FQCN of your class which implements ExtractableInterface.
     * @param   boolean  $override  True to force override the adapter type.
     *
     * @return  $this
     *
     * @since   1.0
     * @throws  UnsupportedArchiveException if the adapter type is not supported
     */
    public function setAdapter($type, $class, $override = true)
    {
        if ($override || !isset($this->adapters[$type])) {
            if (!\is_object($class) && !class_exists($class)) {
                throw new UnsupportedArchiveException($type, sprintf('Archive adapter "%s" (class "%s") not found.', $type, $class));
            }

            if (\is_object($class) && !$class::isSupported()) {
                throw new UnsupportedArchiveException($type, sprintf('Archive adapter "%s" (class "%s") not supported.', $type, $class));
            }

            $object = new $class($this->options);

            if (!($object instanceof ExtractableInterface)) {
                throw new UnsupportedArchiveException(
                    $type,
                    sprintf(
                        'The provided adapter "%s" (class "%s") must implement %s',
                        $type,
                        $class,
                        ExtractableInterface::class
                    )
                );
            }

            $this->adapters[$type] = $object;
        }

        return $this;
    }

    /**
     * Get a file compression adapter.
     *
     * @param   string  $type  The type of adapter (bzip2|gzip|tar|zip).
     *
     * @return  ExtractableInterface  Adapter for the requested type
     *
     * @since   1.0
     */
    public function getAdapter($type)
    {
        $type = strtolower($type);

        if (!isset($this->adapters[$type])) {
            // Try to load the adapter object
            $this->setAdapter($type, __NAMESPACE__ . '\\' . ucfirst($type));
        }

        return $this->adapters[$type];
    }
}
