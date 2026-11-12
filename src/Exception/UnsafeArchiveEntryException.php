<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Exception;

/**
 * Exception thrown when an archive entry would be written outside of the destination path
 *
 * It extends OutOfBoundsException, which in turn extends RuntimeException, so code catching either of those keeps
 * working.
 *
 * @since  __DEPLOY_VERSION__
 */
class UnsafeArchiveEntryException extends \OutOfBoundsException
{
}
