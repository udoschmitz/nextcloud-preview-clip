# nextcloud-preview-clip

Eigenständiges Projekt für Nextcloud-Previews von Clip Studio Paint `.clip` Dateien.

## Inhalt

- `ClipStudio.php`: Preview-Provider (CSFCHUNK -> SQLi -> `CanvasPreview.ImageData`)
- `patch_preview_manager.php`: registriert `OC\Preview\ClipStudio` idempotent
- `patch_files_dist_haspreview.py`: UI-Patch für `has-preview`/`fileid`-Fallback
- `entrypoint-apply-preview-customizations.sh`: Runtime-Sync für gemountetes `/var/www/html`
- `Dockerfile`: Beispiel-Build für Nextcloud 33

## Idee

Bei vielen Docker-Setups ist `/var/www/html` ein Volume. Dann reichen Build-Patches allein nicht.
Deshalb werden die Provider-/UI-Patches beim Container-Start erneut (idempotent) angewendet.

