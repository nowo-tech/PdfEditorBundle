# Configuration

```yaml
nowo_pdf_editor:
    default_profile: default
    profiles:
        default:
            python_binary: python3
            engine_script: '%kernel.project_dir%/vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py'
            workspace_dir: '%kernel.project_dir%/var/pdf-editor'
            timeout: 60
            idle_timeout: 30
            max_upload_bytes: 26214400
            render_dpi: 144
    security:
        allow_unauthenticated: false
        roles: [ROLE_ADMIN]
```

`default_profile` must exist under `profiles` (REQ-CFG-001).

Timeouts apply to every engine process (REQ-RUNTIME-001). Keep PHP `max_execution_time` and the Caddy write timeout **above** `timeout`.
