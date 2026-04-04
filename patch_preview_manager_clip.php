<?php

declare(strict_types=1);

/**
 * Idempotently register the ClipStudio preview provider in Nextcloud core PreviewManager.
 *
 * Patches both /usr/src/nextcloud (image build) and /var/www/html (runtime volume),
 * skipping whichever path does not exist.
 *
 * Idempotency check matches the full insertion string — a changed regex will
 * trigger a re-patch rather than being silently kept.
 *
 * @license AGPL-3.0-or-later
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

	// Check for the exact insertion string — prevents stale regex from being kept silently.
	if (strpos($source, $insertion) !== false) {
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

