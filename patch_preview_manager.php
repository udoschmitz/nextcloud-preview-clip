<?php

declare(strict_types=1);

/**
 * Idempotently inject ClipStudio preview provider into PreviewManager.
 */

$targets = [
	'/usr/src/nextcloud/lib/private/PreviewManager.php',
	'/var/www/html/lib/private/PreviewManager.php',
];

$anchor = "\t\t\$this->registerCoreProvider(Preview\\Krita::class, '/application\\/x-krita/');";
$insertion = $anchor . "\n"
	. "\t\t\$this->registerCoreProvider(Preview\\ClipStudio::class, '/application\\/x-clip-studio/');";

$alreadyPatched = true;

foreach ($targets as $target) {
	if (!file_exists($target)) {
		continue;
	}

	$source = @file_get_contents($target);
	if ($source === false) {
		fwrite(STDERR, "Failed to read {$target}\n");
		exit(1);
	}

	$hasProvider = strpos($source, "Preview\\ClipStudio::class") !== false;
	if ($hasProvider) {
		continue;
	}

	$alreadyPatched = false;
	if (strpos($source, $anchor) === false) {
		fwrite(STDERR, "Patch anchor not found in {$target}\n");
		exit(1);
	}

	$patched = str_replace($anchor, $insertion, $source);
	if ($patched === $source) {
		fwrite(STDERR, "Patch did not modify {$target}\n");
		exit(1);
	}

	if (@file_put_contents($target, $patched) === false) {
		fwrite(STDERR, "Failed to write patched {$target}\n");
		exit(1);
	}
}

echo $alreadyPatched
	? "PreviewManager already patched, skipping.\n"
	: "PreviewManager patched successfully.\n";

