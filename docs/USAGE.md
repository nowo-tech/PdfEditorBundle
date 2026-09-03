# Usage

The bundle is a Symfony PDF workspace with a **React editor** (embedpdf + pdf-lib). The browser collects a **draft**; **Save to PDF** sends the exported bytes to Symfony.

1. Grant `ROLE_ADMIN` (or set `security.allow_unauthenticated: true` in a demo).
2. Build frontend assets once: `make -C vendor/nowo-tech/pdf-editor-bundle assets` (or from the bundle repo: `make assets`).
3. Open `/pdf-editor` and upload a PDF.
4. Edit in the workspace:
   - Use the **shell toolbar** and embedpdf viewport (see [ADR 0001](adr/0001-unified-editor-ux-ui.md) for the target unified UX).
   - Queued changes appear in the **Draft** panel until you save.
5. Click **Save to PDF**. Symfony `POST /pdf-editor/{id}/apply` accepts `Content-Type: application/pdf` (client engine) or legacy `{"ops":[...]}` when `engine_mode: python`.
6. **Download** returns the last saved PDF (not the unsaved draft).

Inline PDF for the viewer: `GET /pdf-editor/{id}/view`.

JSON apply (Python engine only): `POST /pdf-editor/{id}/apply` with header `X-CSRF-TOKEN` and body `{"ops":[{"op":"replace_text",...}]}`.
