# Architecture

PdfEditorBundle is a **presentation + infrastructure** bundle: upload and editor pages, a JSON API, a PDF engine
(Python / PyMuPDF through Symfony Process) and per-user workspaces on disk. It has **no Doctrine entities, no mapped
superclasses, no traits and dispatches no events**: workspaces are directories, so there is nothing to map and the
bundle never touches the host EntityManager (REQ-ARCH-001 rules 2, 4, 6 and 7 do not apply).

## Table of contents

- [Ports and extension points (SPI)](#ports-and-extension-points-spi)
- [Events](#events)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)

## Ports and extension points (SPI)

| Port | Shipped implementation | Replace it to |
|------|------------------------|---------------|
| `Engine\PdfEngineInterface` | `PythonPdfEngine` | use another engine (it works on local file paths) |
| `Engine\ProcessRunnerInterface` | `SymfonyProcessRunner` | run the engine elsewhere or fake it in tests |
| `Document\WorkspaceStorageInterface` | `WorkspaceManager` | keep workspaces on another volume, or mirror them to a local path |
| `Operation\OperationDecoderInterface` | `OperationDecoder` | accept additional operations your engine understands |
| `Security\PdfEditorAccessCheckerInterface` | `AllowAllPdfEditorAccessChecker` / `RolePdfEditorAccessChecker` | decide who may use the editor (`security.access_checker`) |
| `Security\WorkspaceOwnerResolverInterface` | `WorkspaceOwnerResolver` | bind workspaces to your own identity (tenant, account id) |

Every interface is aliased to the shipped class; the class ids stay valid. The controller and the purge command depend
on the interfaces only. Overriding an alias changes the whole bundle:

```yaml
# config/services.yaml
Nowo\PdfEditorBundle\Document\WorkspaceStorageInterface: '@App\Infrastructure\Pdf\SharedVolumeWorkspaces'
```

Value objects: `Document\Workspace` (id, directory, pdf path, original name), `Operation\EditorOperation`,
`Config\EditorProfile`. They carry scalars only. `WorkspaceStorageInterface` takes and returns scalars and `Workspace`:
the upload entry point is `createFromUploadedFile($path, $originalName, $size)` (the `UploadedFile`-typed
`createFromUpload()` stays on `WorkspaceManager` only, deprecated).

Presentation theming stays Twig-based: page templates are configurable (`web_ui.templates`, `web_ui.layout_template`)
and overridable under `templates/bundles/NowoPdfEditorBundle/` (see [TEMPLATES](TEMPLATES.md)).

## Events

The bundle dispatches no events. A host that needs to react to "document uploaded" or "operations applied" wraps its
own port around `WorkspaceStorageInterface` (decorate the alias) and dispatches its own events carrying ids
(`Workspace::$id`), never files or HTTP objects. If the bundle ever dispatches events, they will carry the workspace
id and plain values (REQ-ARCH-001 rule 4).

## Standard Symfony and DDD hosts

**Standard Symfony host (default).** Import the routes, set `nowo_pdf_editor` options, optionally set
`security.access_checker`. Nothing else to wire.

**DDD / hexagonal host (opt-in).**

- There is nothing to map: no entities, no base classes, no traits. The domain never sees the bundle.
- Keep the editor at the HTTP edge. Reference a document in your domain by the **workspace id** (a 32-character hex
  string) and store the finished PDF through your own port; read it with `WorkspaceStorageInterface::get($id)` in an
  infrastructure adapter.
- Implement `WorkspaceOwnerResolverInterface` with your own identity (for example a tenant or account id string) so
  workspaces are bound to it; implement `PdfEditorAccessCheckerInterface` over your authorisation port.
- Replace `WorkspaceStorageInterface` only when workspaces must live elsewhere; keep the contract (local directory per
  workspace, owner-bound access, PDF magic bytes checked on upload).
- Never call the bundle from domain code, and never expose `UploadedFile` to the application layer: pass the local
  path, the client name and the size to `createFromUploadedFile()` from your adapter.
