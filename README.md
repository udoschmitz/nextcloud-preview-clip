# nextcloud-preview-clip

Eigenständiges Projekt für Nextcloud-Previews von Clip Studio Paint `.clip` Dateien.

Kompatibel mit Nextcloud 33 (Docker-Setup auf Basis `nextcloud:33-apache`).

## Inhalt

- `ClipStudio.php`: Preview-Provider (CSFCHUNK -> SQLi -> `CanvasPreview.ImageData`)
- `patch_preview_manager.php`: registriert `OC\Preview\ClipStudio` idempotent
- `patch_files_dist_haspreview.py`: UI-Patch für `has-preview`/`fileid`-Fallback
- `entrypoint-apply-preview-customizations.sh`: Runtime-Sync für gemountetes `/var/www/html`
- `Dockerfile`: Beispiel-Build für Nextcloud 33

## Idee

Bei vielen Docker-Setups ist `/var/www/html` ein Volume. Dann reichen Build-Patches allein nicht.
Deshalb werden die Provider-/UI-Patches beim Container-Start erneut (idempotent) angewendet.

## Voraussetzungen

- Docker Compose Setup für Nextcloud
- Preview-App in Nextcloud aktiviert (`core` Preview-System)
- MIME-Zuordnung für `.clip` auf `application/x-clip-studio`

## Deployment (Kurzfassung)

1. Dateien aus diesem Repo in den Build-Kontext kopieren.
2. `Dockerfile` so verwenden, dass `ClipStudio.php` + Patches ins Image kommen.
3. Image neu bauen und Container neu starten (`docker compose build && docker compose up -d`).
4. Optional/empfohlen: `previewgenerator` aktiv lassen und per Cron vorwärmen.
5. Smoke-Test:
   - `php occ preview:generate -n <fileid>`
   - danach im Files-UI hart neu laden.

## Warum SQLi-Extraktion?

`.clip` enthält den verwertbaren Vorschaudatenstrom nicht zuverlässig als sofort dekodierbares Standard-PNG im Dateikörper.
Der robuste Weg ist:

- `CSFCHUNK` parsen
- `CHNK "SQLi"` extrahieren
- aus `CanvasPreview.ImageData` das PNG lesen

Das folgt dem bewährten Ansatz aus `clip_to_psd` (als Inspiration für das Formatverständnis).

