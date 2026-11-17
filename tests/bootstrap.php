<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
	$prefixes = [
		'Totoglu\\Console\\Boost\\Tests\\' => __DIR__ . '/',
		'Totoglu\\Console\\Boost\\' => __DIR__ . '/../src/',
	];

	foreach ($prefixes as $prefix => $baseDir) {
		if (!str_starts_with($class, $prefix)) {
			continue;
		}

		$relativeClass = substr($class, strlen($prefix));
		if ($relativeClass === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $relativeClass) !== 1) {
			return;
		}

		$realBase = realpath($baseDir);
		$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
		$realFile = realpath($file);
		if (
			$realBase !== false
			&& $realFile !== false
			&& str_starts_with($realFile, rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
			&& is_file($realFile)
		) {
			require_once $realFile;
			return;
		}
	}
}, true, true);

$autoloadCandidates = [
	__DIR__ . '/../vendor/autoload.php',
	__DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadFile) {
	if (is_file($autoloadFile)) {
		require_once $autoloadFile;
		break;
	}
}

require_once __DIR__ . '/TestCase.php';
