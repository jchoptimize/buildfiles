<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2026 Nicholas D. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

use DirectoryIterator;
use Phing\Exception\BuildException;
use Phing\Project;
use Phing\Task;

/**
 * Generates the component Joomla WebAsset registry from its build template and
 * updates stable asset versions in module and plugin media directories.
 */
class AssetVersionTask extends Task
{
	private ?string $repository = null;

	private ?string $version = null;

	public function setRepository(string $repository): void
	{
		$this->repository = $repository;
	}

	public function setVersion(string $version): void
	{
		$this->version = $version;
	}

	private function isStableVersion(): bool
	{
		return $this->version !== null
			&& preg_match('/^\d+\.\d+\.\d+(?:\.\d+)?$/', $this->version) === 1;
	}

	private function replaceVersions(string $filePath, string $version): bool
	{
		$fileData = file_get_contents($filePath);

		if ($fileData === false) {
			return false;
		}

		$updated = preg_replace(
			'/(^\s*"version"\s*:\s*)".*?"(\s*,?\s*)$/m',
			'$1"' . $version . '"$2',
			$fileData
		);

		if ($updated === null) {
			return false;
		}

		if ($updated !== $fileData && file_put_contents($filePath, $updated) === false) {
			return false;
		}

		return true;
	}

	private function generateComponentAsset(string $version): void
	{
		$template = $this->repository . '/build/templates/joomla.asset.json';

		if (!is_file($template)) {
			return;
		}

		$contents = file_get_contents($template);

		if ($contents === false) {
			throw new BuildException("Cannot read Joomla asset template {$template}");
		}

		$contents = str_replace('##VERSION##', $version, $contents);
		$destination = $this->repository . '/component/media/joomla.asset.json';

		if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0777, true) && !is_dir(dirname($destination))) {
			throw new BuildException("Cannot create component media directory for {$destination}");
		}

		if (file_put_contents($destination, $contents) === false) {
			throw new BuildException("Cannot write generated Joomla asset registry {$destination}");
		}

		$this->log("Generated {$destination}", Project::MSG_VERBOSE);
	}

	private function scan(string $baseDir, string $version): void
	{
		if (!is_dir($baseDir)) {
			return;
		}

		$directory = new DirectoryIterator($baseDir);

		/** @var DirectoryIterator $entry */
		foreach ($directory as $entry) {
			if ($entry->isDot() || $entry->isLink()) {
				continue;
			}

			if ($entry->isDir()) {
				$this->scan($entry->getPathname(), $version);
				continue;
			}

			if ($entry->isFile() && $entry->getBasename() === 'joomla.asset.json') {
				$filePath = $entry->getPathname();

				if (!$this->replaceVersions($filePath, $version)) {
					throw new BuildException("Cannot update Joomla asset registry {$filePath}");
				}

				$this->log("Updated {$filePath}", Project::MSG_VERBOSE);
			}
		}
	}

	public function main(): void
	{
		$repository = realpath($this->repository ?: $this->project->getBasedir());

		if ($repository === false || !is_dir($repository)) {
			throw new BuildException('Repository folder is not a valid directory');
		}

		$this->repository = $repository;

		if ($this->version === null || $this->version === '') {
			throw new BuildException('A build version is required');
		}

		$assetVersion = $this->isStableVersion() ? $this->version : 'auto';
		$this->generateComponentAsset($assetVersion);

		if ($this->isStableVersion()) {
			$this->scan($this->repository . '/modules', $assetVersion);
			$this->scan($this->repository . '/plugins', $assetVersion);
		}
	}
}
