<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

/**
 * Interface for archive adapters which can write archives.
 *
 * @since  __DEPLOY_VERSION__
 */
interface CreatableInterface
{
    /**
     * Create an archive from an array of file data.
     *
     * Each entry is an array with the following keys:
     *
     * <pre>
     * 'name' --  Path of the entry inside the archive. Required.
     * 'data' --  Raw contents of the entry. Defaults to an empty string.
     * 'time' --  Modification time as a UNIX timestamp. Defaults to the current time.
     * </pre>
     *
     * @param   string  $archive  Path to save the archive to.
     * @param   array   $files    Array of file data to add to the archive.
     *
     * @return  boolean  True if successful.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if the file data is not usable
     * @throws  \RuntimeException if the archive cannot be written
     */
    public function create($archive, $files);

    /**
     * Tests whether this adapter can pack files on this computer.
     *
     * @return  boolean  True if supported
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function isSupported();
}
