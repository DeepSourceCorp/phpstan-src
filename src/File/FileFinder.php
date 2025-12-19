<?php declare(strict_types = 1);

namespace PHPStan\File;

use Symfony\Component\Finder\Finder;
use function array_filter;
use function array_unique;
use function array_values;
use function file_exists;
use function implode;
use function is_file;

final class FileFinder
{

	/**
	 * @param string[] $fileExtensions
	 */
	public function __construct(
		private FileExcluder $fileExcluder,
		private FileHelper $fileHelper,
		private array $fileExtensions,
	)
	{
	}

	/**
	 * @param string[] $paths
	 */
	public function findFiles(array $paths): FileFinderResult
	{
		error_log('[PHPSTAN-DEBUG] FileFinder.findFiles() called with paths: ' . implode(', ', $paths));
		$onlyFiles = true;
		$files = [];
		foreach ($paths as $path) {
			if (is_file($path)) {
				$files[] = $this->fileHelper->normalizePath($path);
			} elseif (!file_exists($path)) {
				throw new PathNotFoundException($path);
			} else {
				$finder = new Finder();
				$finder->followLinks();
				foreach ($finder->files()->name('*.{' . implode(',', $this->fileExtensions) . '}')->in($path) as $fileInfo) {
					$files[] = $this->fileHelper->normalizePath($fileInfo->getPathname());
					$onlyFiles = false;
				}
			}
		}

		$filesBeforeExclusion = $files;
		$files = array_values(array_unique(array_filter($files, fn (string $file): bool => !$this->fileExcluder->isExcludedFromAnalysing($file))));

		error_log('[PHPSTAN-DEBUG] FileFinder found ' . count($filesBeforeExclusion) . ' files before exclusion');
		error_log('[PHPSTAN-DEBUG] FileFinder returning ' . count($files) . ' files after exclusion');
		error_log('[PHPSTAN-DEBUG] Final files: ' . implode(', ', array_slice($files, 0, 10)) . (count($files) > 10 ? ' ... (and ' . (count($files) - 10) . ' more)' : ''));

		return new FileFinderResult($files, $onlyFiles);
	}

}
