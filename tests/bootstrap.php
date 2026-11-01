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
		$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
		if (is_file($file)) {
			require_once $file;
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
