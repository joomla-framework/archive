<?php

/**
 * Part of the Joomla Framework Archive Package
 *
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Archive\Exception;

/**
 * Exception thrown when an archive exceeds one of the max_files, max_size or max_ratio options
 *
 * @since  __DEPLOY_VERSION__
 */
class ExtractionLimitException extends \RuntimeException
{
}
