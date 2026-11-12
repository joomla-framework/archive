<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive;

/**
 * Shared logic for the single stream compressors, `Gzip` and `Bzip2`.
 *
 * Neither format is a container: each one compresses exactly one stream and has no concept of
 * entries. To store more than one file - or to produce the `.tar.gz` and `.tar.bz2` that everyone
 * expects - the payload has to be packed into a tar first. This trait decides when that happens.
 *
 * The rule mirrors `Archive::extract()`, so that what this package writes it also reads back.
 *
 * @since  __DEPLOY_VERSION__
 */
trait TarWrappingTrait
{
    /**
     * Build the single stream to compress from an array of file data.
     *
     * @param   string  $archive  Path the archive will be saved to. Its name decides whether the
     *                            payload is wrapped in a tar.
     * @param   array   $files    Array of file data. See `CreatableInterface::create()`.
     *
     * @return  array  A ['data' => string, 'name' => ?string] pair, where the name is the original
     *                 file name when a single file is stored raw, and null when it is tar wrapped.
     *
     * @since   __DEPLOY_VERSION__
     * @throws  \InvalidArgumentException if there is nothing to compress
     */
    private function buildPayload(string $archive, array $files): array
    {
        if ($files === []) {
            throw new \InvalidArgumentException('There are no files to compress.');
        }

        if ($this->shouldWrapInTar($archive)) {
            return [
                'data' => (new Tar($this->options))->createData($files),
                'name' => null,
            ];
        }

        if (\count($files) > 1) {
            $extension = strtolower(pathinfo($archive, \PATHINFO_EXTENSION));

            throw new \InvalidArgumentException(
                sprintf(
                    'A %s archive holds a single stream, so %d files can only be stored by packing'
                    . ' them into a tar first. Name the archive "%s.tar.%s" or "%s.t%s" and that'
                    . ' happens automatically - as it stands, nothing could tell the result apart'
                    . ' from a single compressed file when unpacking it.',
                    $extension === '' ? 'compressed' : $extension,
                    \count($files),
                    pathinfo($archive, \PATHINFO_FILENAME),
                    $extension,
                    pathinfo($archive, \PATHINFO_FILENAME),
                    $extension === 'bz2' ? 'bz2' : 'gz'
                )
            );
        }

        $file = reset($files);

        if (!\is_array($file) && !($file instanceof \ArrayAccess)) {
            throw new \InvalidArgumentException(
                'Each entry must be an array or implement the ArrayAccess interface.'
            );
        }

        if (!isset($file['name']) || (string) $file['name'] === '') {
            throw new \InvalidArgumentException('Each entry must have a non-empty "name".');
        }

        return [
            'data' => (string) ($file['data'] ?? ''),
            'name' => basename(str_replace('\\', '/', (string) $file['name'])),
        ];
    }

    /**
     * Decide whether the payload has to be packed into a tar first.
     *
     * The archive name alone decides, using the same test `Archive::extract()` applies when
     * unpacking. Deciding on the number of files instead would be tempting, but it would produce
     * archives this package cannot read back: a `.gz` whose name says nothing about a tar is
     * unpacked as a single file, whatever is actually inside it.
     *
     * @param   string  $archive  Path the archive will be saved to.
     *
     * @return  boolean
     *
     * @since   __DEPLOY_VERSION__
     */
    private function shouldWrapInTar(string $archive): bool
    {
        if (\in_array(strtolower(pathinfo($archive, \PATHINFO_EXTENSION)), ['tgz', 'tbz2'], true)) {
            return true;
        }

        return stripos(pathinfo($archive, \PATHINFO_FILENAME), '.tar') !== false;
    }
}
