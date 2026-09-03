# ADR 0001: Unified editor shell — UX & UI

- **Status:** Accepted  
- **Date:** 2026-08-24  
- **Deciders:** PdfEditorBundle maintainers  
- **Related:** Client engine (`engine_mode: client`), `assets/` React app, embedpdf + pdf-lib + vensas XObject helpers  

## Context

The bundle moved from a vanilla-JS canvas (PNG previews via Python) to a **browser-first** stack:

| Layer | Role |
|-------|------|
| `@embedpdf/react-pdf-viewer` | Rendering, selection, AcroForms, redaction, export |
| `pdf-lib` | Low-level draw, forms, page ops |
| vensas-inspired `content-stream` | Real XObject removal |

The first React integration exposes **two competing UIs**:

1. **Symfony toolbar** — emoji icons, draft list, tools that do not always drive embedpdf.  
2. **embedpdf built-in chrome** — its own toolbar, sidebar, and tool modes.

Users see duplicated controls, unclear which toolbar “owns” an action, and a draft panel that speaks in engine terms (`remove_detected_watermarks · p1`) instead of human language. Tools such as **Text** and **Form** change local state but do not consistently activate the corresponding embedpdf plugins or queue canvas interactions.

We need a **single, predictable editing surface** comparable to a document editor (toolbar → canvas → properties), without fighting embedpdf’s strengths.

### Constraints

- **REQ-UI-001 / REQ-UI-002:** CKEditor-like shell (toolbar, canvas, properties).  
- **Draft-then-save:** edits stay client-side until **Save to PDF**; Symfony only persists bytes or legacy ops.  
- **Free stack only:** no paid PDF SDK.  
- **i18n:** seven locales via Symfony translations passed into React.  
- **Accessibility:** keyboard paths and screen-reader labels for tools.  
- **FrankenPHP dev:** hot reload must preserve `#nowo-pdf-editor-react-root`.  

## Decision

Adopt a **Unified Editor Shell** pattern:

```text
┌─────────────────────────────────────────────────────────────┐
│  Nowo shell (single toolbar + status + save affordance)    │
├──────────┬──────────────────────────────────┬───────────────┤
│  Pages   │  embedpdf viewport (chrome hidden  │  Properties   │
│  rail    │  or minimal — tools driven by      │  + draft      │
│          │  shell via plugin registry)        │  summary      │
└──────────┴──────────────────────────────────┴───────────────┘
```

### 1. One toolbar, one source of truth

- **Hide or disable** embedpdf’s default toolbar when the shell toolbar is present (`PDFViewer` config / theme hooks).  
- Symfony/React toolbar becomes the **only** primary control surface.  
- Each button **maps 1:1** to an embedpdf plugin action *or* a pdf-lib/vensas draft op — never both silently.

| Shell tool | Runtime behaviour |
|------------|-------------------|
| Select | embedpdf selection plugin active; properties show text field values when applicable |
| Text | embedpdf insert/annotation mode **or** click-to-queue `insert_text` with ghost preview |
| Form | embedpdf form authoring mode |
| Redact | `@embedpdf/plugin-redaction` unified mode |
| Watermark | Queue `add_watermark` with live diagonal ghost on all pages |
| Remove watermarks | Queue `remove_detected_watermarks`; show “N overlays detected” when inspect available |
| Rotate | Queue `rotate_page` for **current** page (read page index from embedpdf scroll state) |
| Save | Export via embedpdf → apply pdf-lib/vensas ops → `POST apply` (`application/pdf`) |
| Discard | Clear draft + reload viewer from `/view` |
| Download | Unchanged — attachment of last **saved** PDF |

### 2. Visual design system

Replace emoji icons with **inline SVG** (16–20 px, `currentColor`) in `assets/src/components/icons/`. Reuse existing CSS tokens from `pdf-editor.css`:

- Toolbar: `#2b2118` background, `#0b6e4f` active/primary, `#7dffc3` focus ring.  
- Canvas gutter: `#cfc4b0`; panels: `#fff` / `#e7dfd1`.  
- **No third-party emoji** in production UI — breaks cross-platform rendering.

Tooltips: keep `title` + CSS `data-tooltip` (already in bundle CSS); max-width 240 px, localized via `i18n` payload from Twig.

### 3. Properties panel (right rail)

Split the right column into two collapsible sections:

1. **Selection** — context-sensitive fields (text content, font size, field name, redaction note). Empty state: “Select an object on the page.”  
2. **Draft** — human-readable lines (“Rotate page 2 clockwise”, “Add watermark CONFIDENTIAL”) with remove per item; badge count on Save button.

Draft labels must come from **`opLabel(i18n, op)`**, not raw `op` strings.

### 4. Page rail (left)

- Prefer embedpdf thumbnail sidebar **styled** to match panel width (~140 px) **or** a thin Nowo wrapper that calls embedpdf’s page API.  
- Active page highlight: `#0b6e4f` border (existing token).  
- Click thumb → sync `currentPage` state used by rotate/insert ops.

### 5. Feedback & states

| State | UX |
|-------|-----|
| Loading PDF | Skeleton in viewport + “Loading preview…” (i18n) |
| Saving | Disable toolbar; spinner on Save; `aria-busy` on shell |
| Save success | Toast 2.4 s + reload viewer with cache-bust query |
| Save / export error | Inline error in properties footer + toast |
| Empty draft | Save disabled; Discard disabled |
| Unsaved navigation | Optional `beforeunload` when `pending.length > 0` |

### 6. embedpdf integration mode

**Phase A (current):** Drop-in `PDFViewer` with both toolbars — acceptable for demo only.  

**Phase B (target):** Configure viewer with **minimal chrome**:

- Disable built-in download/print if Symfony owns download.  
- Wire `onReady(registry)` to store plugin handles in React context.  
- Toolbar clicks call `registry.getPlugin('redaction' | 'form' | 'selection' | …)` instead of local-only state.

**Phase C (optional):** Migrate to embedpdf **headless** packages if we need full layout control; keep the same shell components.

### 7. Theming & host apps

- Shell reads optional `theme: 'light' | 'dark' | 'auto'` from Twig → `WorkspaceConfig`.  
- Demo topbar (`demo.css`) stays **outside** the shell; shell fills remaining viewport (`calc(100vh - topbar)`).  
- Host apps override `@PdfEditor/editor/layout.html.twig` only for branding — not toolbar logic.

## UX principles (normative)

1. **One action, one control** — no duplicate buttons across shell and embedpdf.  
2. **WYSIWYG draft** — ghosts/overlays for queued ops on the active page before save.  
3. **Plain language** — draft list and toasts use translation keys, not engine identifiers.  
4. **Progressive disclosure** — advanced ops (XObject removal) behind clear labels, not debug strings.  
5. **Keyboard** — `V` select, `T` text, `R` redact, `Ctrl/Cmd+S` save draft, `Escape` cancel tool.  
6. **Mobile** — toolbar wraps; properties panel moves to bottom sheet under `768px` (future breakpoint).

## Consequences

### Positive

- Coherent product feel aligned with REQ-UI-001.  
- embedpdf handles hard PDF UX (zoom, scroll, forms, redaction); Nowo shell handles workflow (draft, save, ACL, i18n).  
- Easier demos and screenshots — single toolbar, SVG icons, consistent spacing.

### Negative

- Phase B requires embedpdf plugin API stability and test coverage in `assets/`.  
- Hiding embedpdf chrome may break on major embedpdf upgrades — pin semver and read changelogs.  
- Bundle JS weight (~1.7 MB + WASM) — acceptable for admin editor; document in INSTALLATION.

### Follow-up tasks

| Priority | Task |
|----------|------|
| P0 | Hide embedpdf default toolbar; wire Select/Redact/Form to registry | Done |
| P0 | SVG icon set; remove emoji from `EditorToolbar.tsx` | Done |
| P0 | `currentPage` from embedpdf scroll/thumbnail sync (rotate targets correct page) | Done |
| P1 | Properties panel: selection → editable fields → queue op | Done |
| P1 | Human-readable `opLabel` via i18n for all op types | Done |
| P1 | Loading skeleton + save spinner + `aria-busy` | Done |
| P2 | Keyboard shortcuts hook (`useKeyboardShortcuts`) |
| P2 | Responsive bottom sheet for properties |
| P2 | `beforeunload` guard for unsaved draft |
| P3 | Optional dark theme via embedpdf `setTheme` |

## Implementation notes

- React entry: `assets/src/PdfEditorApp.tsx`  
- Toolbar: `assets/src/components/EditorToolbar.tsx` → split into `EditorShell`, `IconButton`, `DraftPanel`, `PropertiesPanel`  
- Styles: extend `src/Resources/public/css/pdf-editor.css` + `assets/src/styles/editor.css`  
- Translations: add keys under `pdf_editor.draft.op.*` and `pdf_editor.properties.*` in all seven YAML files  
- Tests: PHPUnit unchanged; add Vitest smoke tests for `opLabel` and toolbar state reducers  

## References

- [embedpdf React viewer](https://www.embedpdf.com/docs/react/viewer/getting-started)  
- [embedpdf Form plugin](https://www.embedpdf.com/docs/react/viewer/plugins/plugin-form)  
- [embedpdf Redaction plugin](https://www.embedpdf.com/docs/react/headless/plugins/plugin-redaction)  
- [vensas/pdf-editor](https://github.com/vensas/pdf-editor) — content-stream / XObject removal (MIT)  
- Bundle specs: REQ-UI-001, REQ-UI-002, REQ-CFG-001  
