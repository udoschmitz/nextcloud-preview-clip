<?php

/**
 * SPDX-FileCopyrightText: 2026 Apple-Freiheit
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Preview provider for Clip Studio Paint .clip files.
 * Strategy (inspired by dobrokot/clip_to_psd):
 * 1) Parse Clip Studio's CSFCHUNK container and extract the internal SQLite blob (CHNK 'SQLi')
 * 2) Query CanvasPreview.ImageData from SQLite (PNG bytes)
 * 3) Fallback: try heuristic embedded PNG extraction
 */
namespace OC\Preview;

use OCP\Files\File;
use OCP\IImage;
use OCP\Server;
use Psr\Log\LoggerInterface;

class ClipStudio extends ProviderV2 {
	private const PNG_SIG = "\x89PNG\r\n\x1a\n";
	private const CSFCHUNK_MAGIC = 'CSFCHUNK';
	private const CHUNK_MAGIC = 'CHNK';
	private const SQLI_NAME = 'SQLi';
	private const FILE_HEADER_SIZE = 24;
	private const MAX_CHUNKS = 200000;

	/** @var list<string> */
	private const PRE_IDAT_CHUNK_TYPES = [
		'IHDR', 'PLTE', 'tRNS', 'gAMA', 'cHRM', 'iCCP', 'sRGB', 'sBIT',
		'pHYs', 'tEXt', 'zTXt', 'iTXt', 'bKGD', 'hIST', 'tIME',
		'oFFs', 'pCAL', 'sCAL', 'sTER',
	];

	public function getMimeType(): string {
		return '/application\/x-clip-studio/';
	}

	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		$fileName = $this->getLocalFile($file);
		if ($fileName === false) {
			Server::get(LoggerInterface::class)->error(
				'Failed to get local file for Clip Studio preview: ' . $file->getPath(),
				['app' => 'core']
			);
			return null;
		}

		$handle = fopen($fileName, 'rb');
		if ($handle === false) {
			$this->cleanTmpFiles();
			return null;
		}

		$contents = stream_get_contents($handle);
		fclose($handle);
		$this->cleanTmpFiles();

		if ($contents === false || $contents === '') {
			return null;
		}

		$pngData = $this->extractCanvasPreviewPng($contents);
		if ($pngData === null) {
			$pngData = $this->extractEmbeddedPng($contents);
		}
		if ($pngData === null) {
			return null;
		}

		$image = new \OCP\Image();
		if (method_exists($image, 'loadFromData')) {
			$image->loadFromData($pngData);
		} else {
			$tmpPath = Server::get(\OCP\ITempManager::class)->getTemporaryFile();
			if ($tmpPath === false) {
				return null;
			}
			file_put_contents($tmpPath, $pngData);
			$image->loadFromFile($tmpPath);
			@unlink($tmpPath);
		}

		if (!$image->valid()) {
			return null;
		}

		$image->scaleDownToFit($maxX, $maxY);
		return $image;
	}

	private function extractCanvasPreviewPng(string $contents): ?string {
		$sqliteBlob = $this->extractSqliteBlobFromClip($contents);
		if ($sqliteBlob === null) {
			return null;
		}

		$tmpSqlite = Server::get(\OCP\ITempManager::class)->getTemporaryFile();
		if ($tmpSqlite === false) {
			return null;
		}
		file_put_contents($tmpSqlite, $sqliteBlob);

		try {
			$pdo = new \PDO('sqlite:' . $tmpSqlite, null, null, [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			]);
			$stmt = $pdo->query('SELECT ImageData FROM CanvasPreview LIMIT 1');
			$png = $stmt ? $stmt->fetchColumn() : null;
		} catch (\Throwable) {
			$png = null;
		}

		@unlink($tmpSqlite);

		if (!\is_string($png) || $png === '') {
			return null;
		}
		if (\strncmp($png, self::PNG_SIG, \strlen(self::PNG_SIG)) !== 0) {
			return null;
		}

		return $png;
	}

	private function extractSqliteBlobFromClip(string $contents): ?string {
		if (\strncmp($contents, self::CSFCHUNK_MAGIC, 8) !== 0) {
			return null;
		}
		$len = \strlen($contents);
		if ($len < self::FILE_HEADER_SIZE + 16) {
			return null;
		}

		$offset = self::FILE_HEADER_SIZE;
		$chunks = 0;
		while ($offset + 16 <= $len && $chunks < self::MAX_CHUNKS) {
			if (\substr($contents, $offset, 4) !== self::CHUNK_MAGIC) {
				return null;
			}

			$chunkName = \substr($contents, $offset + 4, 4);
			$sizeBin = \substr($contents, $offset + 12, 4);
			$chunkDataSize = unpack('N', $sizeBin)[1];
			if ($chunkDataSize < 0) {
				return null;
			}

			$dataStart = $offset + 16;
			$dataEnd = $dataStart + $chunkDataSize;
			if ($dataEnd > $len) {
				return null;
			}

			if ($chunkName === self::SQLI_NAME) {
				return \substr($contents, $dataStart, $chunkDataSize);
			}

			$offset = $dataEnd;
			$chunks++;
		}

		return null;
	}

	private function extractEmbeddedPng(string $contents): ?string {
		$pngStart = strpos($contents, self::PNG_SIG);
		if ($pngStart === false) {
			return null;
		}

		$out = substr($contents, $pngStart, \strlen(self::PNG_SIG));
		$pos = $pngStart + \strlen(self::PNG_SIG);
		$seenIdat = false;
		$max = \strlen($contents);

		for ($iter = 0; $iter < 20000; $iter++) {
			if ($pos + 12 > $max) {
				return null;
			}

			$len = unpack('N', substr($contents, $pos, 4))[1];
			$type = substr($contents, $pos + 4, 4);
			$headerOk = $this->isPlausibleChunkHeader($len, $type, $pos, $max);
			if ($headerOk && $type === 'IEND' && $len !== 0) {
				$headerOk = false;
			}

			if (!$headerOk) {
				$want = $seenIdat ? ['IDAT', 'IEND'] : array_merge(self::PRE_IDAT_CHUNK_TYPES, ['IDAT']);
				$next = $this->findNextLooseChunk($contents, $pos, $want, 262144);
				if ($next === null) {
					return null;
				}
				$pos = $next;
				continue;
			}

			$data = substr($contents, $pos + 8, $len);
			$out .= $this->emitPngChunk($type, $data);
			if ($type === 'IEND') {
				return $out;
			}
			if ($type === 'IDAT') {
				$seenIdat = true;
			}
			$pos += 12 + $len;
		}

		return null;
	}

	private function isPlausibleChunkHeader(int $len, string $type, int $pos, int $max): bool {
		if ($len < 0 || $len > 0x800000) {
			return false;
		}
		if (!preg_match('/^[A-Za-z]{4}$/', $type)) {
			return false;
		}
		return $pos + 12 + $len <= $max;
	}

	/**
	 * @param list<string> $types
	 */
	private function findNextLooseChunk(string $contents, int $from, array $types, int $maxScan): ?int {
		$lim = min(\strlen($contents), $from + $maxScan);
		for ($i = $from; $i + 12 <= $lim; $i++) {
			$len = unpack('N', substr($contents, $i, 4))[1];
			$type = substr($contents, $i + 4, 4);
			if ($len < 0 || $len > 0x800000) {
				continue;
			}
			if (!preg_match('/^[A-Za-z]{4}$/', $type)) {
				continue;
			}
			if (!\in_array($type, $types, true)) {
				continue;
			}
			if ($i + 12 + $len > \strlen($contents)) {
				continue;
			}
			if ($type === 'IEND' && $len !== 0) {
				continue;
			}
			return $i;
		}
		return null;
	}

	private function emitPngChunk(string $type, string $data): string {
		return pack('N', \strlen($data)) . $type . $data
			. pack('N', crc32($type . $data) & 0xffffffff);
	}
}

