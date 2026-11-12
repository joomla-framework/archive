<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

/**
 * Interface for archive adapters which can list the entries of an archive without extracting it.
 *
 * @since  __DEPLOY_VERSION__
 */
interface ListableInterface
{
    /**
     * List the entries of an archive without extracting it.
     *
     * Each entry is an array with the following keys:
     *
     * <pre>
     * 'name' --  Path of the entry inside the archive, exactly as stored. It is not sanitised, so it can
     *            contain "../" or be absolute.
     * 'size' --  Uncompressed size in bytes, as declared by the archive.
     * 'type' --  "file", "directory" or "other" (links, devices and other entries that are not extracted).
     * </pre>
     *
     * @param   string  $archive  Path to the archive
     *
     * @return  array<int, array{name: string, size: int, type: string}>
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \RuntimeException if the archive cannot be read
     */
    public function listContents(string $archive): array;
}
