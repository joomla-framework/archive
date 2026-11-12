<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

use Joomla\Archive\Exception\ExtractionLimitException;

/**
 * Enforces the optional limits against decompression bombs.
 *
 * Supported options, each disabled when missing or 0:
 *
 * <pre>
 * 'max_files' --  Maximum number of entries to extract.
 * 'max_size'  --  Maximum number of bytes to extract in total.
 * 'max_ratio' --  Maximum ratio between the extracted bytes and the size of the archive file.
 * </pre>
 *
 * @since     __DEPLOY_VERSION__
 * @internal
 */
trait ExtractionLimitsTrait
{
    /**
     * Number of entries extracted so far
     *
     * @var    integer
     * @since  __DEPLOY_VERSION__
     */
    private int $extractedEntries = 0;

    /**
     * Number of bytes extracted so far
     *
     * @var    integer
     * @since  __DEPLOY_VERSION__
     */
    private int $extractedBytes = 0;

    /**
     * Size of the archive file in bytes
     *
     * @var    integer
     * @since  __DEPLOY_VERSION__
     */
    private int $archiveBytes = 0;

    /**
     * Start counting for a new archive.
     *
     * @param   string  $archive  Path to the archive file
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    private function startLimits(string $archive): void
    {
        $this->extractedEntries = 0;
        $this->extractedBytes   = 0;
        $this->archiveBytes     = (int) @filesize($archive);
    }

    /**
     * Whether any limit is configured.
     *
     * @return  boolean
     *
     * @since   __DEPLOY_VERSION__
     */
    private function hasLimits(): bool
    {
        return $this->getLimit('max_files') > 0 || $this->getLimit('max_size') > 0 || $this->getLimit('max_ratio') > 0;
    }

    /**
     * Count entries and bytes and throw if a limit is exceeded.
     *
     * @param   integer  $entries  Number of entries to add
     * @param   integer  $bytes    Number of bytes to add
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     * @throws  ExtractionLimitException
     */
    private function countExtracted(int $entries, int $bytes): void
    {
        $this->extractedEntries += $entries;
        $this->extractedBytes += $bytes;

        $maxFiles = (int) $this->getLimit('max_files');

        if ($maxFiles > 0 && $this->extractedEntries > $maxFiles) {
            throw new ExtractionLimitException(sprintf('The archive contains more than %d entries', $maxFiles));
        }

        $maxSize = (int) $this->getLimit('max_size');

        if ($maxSize > 0 && $this->extractedBytes > $maxSize) {
            throw new ExtractionLimitException(sprintf('The extracted data exceeds %d bytes', $maxSize));
        }

        $maxRatio = $this->getLimit('max_ratio');

        if ($maxRatio > 0 && $this->extractedBytes > $maxRatio * max($this->archiveBytes, 1)) {
            throw new ExtractionLimitException(sprintf('The extracted data exceeds %s times the size of the archive', $maxRatio));
        }
    }

    /**
     * Get a limit from the options.
     *
     * @param   string  $name  The option name
     *
     * @return  float  The limit, 0 if not set
     *
     * @since   __DEPLOY_VERSION__
     */
    private function getLimit(string $name): float
    {
        return isset($this->options[$name]) ? (float) $this->options[$name] : 0.0;
    }
}
