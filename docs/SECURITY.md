# Security

- Editor routes require the access checker (roles or `allow_unauthenticated` for demos only).
- Uploads must be PDF; size capped by `max_upload_bytes`.
- Workspace ids are 32 hex chars; path traversal is rejected.
- Apply mutations require CSRF (`pdf_editor` token).
- The Python process is isolated; never pass unsanitized shell strings (argv list only).
- Do not expose `workspace_dir` on a public file server.
