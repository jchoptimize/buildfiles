<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

use DOMDocument;
use Phing\Io\File;
use Phing\Project;
use Phing\Task;
use XSLTProcessor;

/**
 * Renders a DocBook XML file to chunked HTML with PHP's XSL extension, for hosts without the xsltproc binary.
 */
class DocBookToHtmlTask extends Task
{
	/**
	 * Path where the DocBook XML Stylesheet distribution is stored
	 *
	 * @var   File
	 */
	protected $xsltRoot;

	/**
	 * The source DocBook XML file to convert
	 *
	 * @var   File
	 */
	protected $docBookFile;

	/**
	 * Folder where the HTML files are written
	 *
	 * @var   File
	 */
	protected $outputDir;

	/**
	 * Runs the XML to HTML conversion
	 *
	 * @return  void
	 */
	public function main()
	{
		if (!class_exists(XSLTProcessor::class))
		{
			throw new \RuntimeException('PHP\'s xsl extension is required; set docs.php_xsltproc=0 to use xsltproc instead');
		}

		$xslPath = $this->xsltRoot->getAbsolutePath() . '/html/chunk.xsl';
		$xslDoc  = new DOMDocument();

		$this->log(sprintf("Loading XSLT file %s", $xslPath), Project::MSG_INFO);

		if (!$xslDoc->load($xslPath))
		{
			throw new \RuntimeException(sprintf("Cannot load XSLT file %s", $xslPath));
		}

		$xmlDoc = new DOMDocument();
		$source = $this->docBookFile->getAbsolutePath();

		if (!$xmlDoc->load($source, LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_NONET | LIBXML_XINCLUDE))
		{
			throw new \RuntimeException(sprintf("Cannot load DocBook XML file %s", $source));
		}

		// Apply XInclude directives (include sub-files)
		$xmlDoc->xinclude(LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_NONET | LIBXML_XINCLUDE);

		// The same parameters the xsltproc branch of the docbook-zip target passes
		$parameters = [
			'base.dir'                      => rtrim($this->outputDir->getAbsolutePath(), '/') . '/',
			'body.start.indent'             => 0,
			'variablelist.term.break.after' => 1,
			'variablelist.term.separator'   => '',
			'variablelist.max.termlength'   => 12,
			'admon.graphics'                => 0,
			'use.id.as.filename'            => 1,
			'chunk.section.depth'           => 3,
			'section.autolabel'             => 1,
			'toc.section.depth'             => 5,
			'highlight.source'              => 1,
		];

		$xslt = new XSLTProcessor();
		$xslt->importStylesheet($xslDoc);

		if (!$xslt->setParameter('', $parameters))
		{
			throw new \RuntimeException("Cannot set XSLTProcessor parameters");
		}

		set_time_limit(0);

		// The chunker writes each chunk with exsl:document, which the default security preferences forbid
		$oldval = $xslt->setSecurityPrefs(XSL_SECPREF_NONE);

		// libxslt reports every chunk it writes as a warning; that is progress, not a problem
		set_error_handler(
			function (int $errno, string $message): bool {
				$isProgress = strpos($message, 'Writing ') !== false;

				$this->log(preg_replace('/^.*?\\(\\): /', '', $message), $isProgress ? Project::MSG_VERBOSE : Project::MSG_WARN);

				return true;
			},
			E_WARNING
		);

		try
		{
			$result = $xslt->transformToXml($xmlDoc);
		}
		finally
		{
			restore_error_handler();
		}

		$xslt->setSecurityPrefs($oldval);

		unset($xslt);

		if ($result === false)
		{
			throw new \RuntimeException(sprintf("Failed to process DocBook XML file %s", $source));
		}
	}

	/**
	 * @param   File $xsltRoot  Absolute path of the DocBook XSL Stylesheets distribution
	 *
	 * @return  void
	 */
	public function setXsltRoot(File $xsltRoot)
	{
		$this->xsltRoot = $xsltRoot;
	}

	/**
	 * @param   File $docBookFile  Absolute filename of the DocBook XML file to process
	 *
	 * @return  void
	 */
	public function setDocBookFile(File $docBookFile)
	{
		$this->docBookFile = $docBookFile;
	}

	/**
	 * @param   File $outputDir  Folder the HTML files are written to
	 *
	 * @return  void
	 */
	public function setOutputDir(File $outputDir)
	{
		$this->outputDir = $outputDir;
	}
}
