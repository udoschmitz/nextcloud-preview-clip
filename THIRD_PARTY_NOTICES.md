# Third-Party Notices

## Format reference: dobrokot/clip_to_psd

| Field | Value |
|-------|-------|
| Project | `dobrokot/clip_to_psd` |
| URL | https://github.com/dobrokot/clip_to_psd/ |
| License | MIT |
| Author | dobrokot |

`clip_to_psd` was used as a reference for understanding the Clip Studio Paint `.clip`
container format — specifically the **CSFCHUNK** / **CHNK** block structure, the
**`SQLi`** chunk carrying an embedded SQLite database, and the
**`CanvasPreview.ImageData`** table that holds the canvas preview PNG.

No source code from `clip_to_psd` is included in this repository.
The implementation in `ClipStudio.php` was written independently.
