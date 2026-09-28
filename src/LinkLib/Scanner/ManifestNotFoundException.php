<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildFiles\LinkLib\Scanner;

use RuntimeException;

/**
 * Thrown when an extension folder has no XML manifest, e.g. a folder left behind by a branch switch because it only
 * contains ignored files. The detect() methods skip such folders instead of aborting the whole scan.
 */
class ManifestNotFoundException extends RuntimeException
{
}
