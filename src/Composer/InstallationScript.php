<?php
/*
 * @package   buildfiles
 * @Copyright (c)2010-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildFiles\Composer;

use Composer\Script\Event;
use DirectoryIterator;

final class InstallationScript
{
	private function __construct()
	{
		throw new \RuntimeException('This class is not meant to be instantiated');
	}

	public static function installDbXsl(Event $event): void
	{
		$workDir   = realpath(__DIR__ . '/../..');
		$targetDir = $workDir . '/phing/bin/dbxsl';

		// An empty folder is left behind when an earlier download failed
		if (@is_file($targetDir . '/VERSION.xsl'))
		{
			return;
		}

		if (!@is_dir($targetDir))
		{
			mkdir($targetDir, 0755, true);
		}

		$zipFile = self::downloadDbXslSource($event);

		$zip = new \ZipArchive();
		$zip->open($zipFile);
		$zip->extractTo($targetDir);
		$zip->close();

		$di = new DirectoryIterator($targetDir);

		/** @var DirectoryIterator $item */
		foreach ($di as $item)
		{
			if (!$item->isDir() || $item->isDot())
			{
				continue;
			}

			self::moveDir($item->getPathname(), $targetDir);

			break;
		}
	}

	private static function downloadDbXslSource(Event $event): string
	{
		$workDir   = realpath(__DIR__ . '/../..');
		$extras   = $event->getComposer()->getPackage()->getExtra();
		$source   = $extras['dbxsl']['source'];
		$baseName = basename($source);

		$cacheDir = $workDir . '/cache';
		$target   = $cacheDir . '/' . $baseName;

		if (is_file($target))
		{
			return $target;
		}

		if (!@is_dir($cacheDir))
		{
			mkdir($cacheDir, 0755, true);
		}

		$contents = file_get_contents($source);

		if ($contents === false || file_put_contents($target, $contents) === false)
		{
			throw new \RuntimeException(sprintf('Cannot download the DocBook XSL stylesheets from %s', $source));
		}

		return $target;
	}

	private static function moveDir(string $sourceDir, string $targetDir): void
	{
		$di = new DirectoryIterator($sourceDir);

		/** @var DirectoryIterator $item */
		foreach ($di as $item)
		{
			if ($item->isDot())
			{
				continue;
			}

			if ($item->isFile() || $item->isLink())
			{
				@rename($item->getPathname(), $targetDir . '/' . $item->getBasename());

				continue;
			}

			mkdir($targetDir . '/' . $item->getBasename(), 0755, true);

			self::moveDir($item->getPathname(), $targetDir . '/' . $item->getBasename());
		}

		rmdir($sourceDir);
	}

}