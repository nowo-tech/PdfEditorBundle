import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { PdfEditorApp } from './pdf-editor-app';
import type { WorkspaceConfig } from './types';
import { createBundleLogger } from './logger';

declare const __PDF_EDITOR_BUILD_TIME__: string;

createBundleLogger('pdf-editor', {
  buildTime: typeof __PDF_EDITOR_BUILD_TIME__ !== 'undefined' ? __PDF_EDITOR_BUILD_TIME__ : undefined,
}).scriptLoaded();

const mount = document.getElementById('nowo-pdf-editor-react-root');
if (mount) {
  const raw = mount.getAttribute('data-config');
  if (raw) {
    const config = JSON.parse(raw) as WorkspaceConfig;
    createRoot(mount).render(
      <StrictMode>
        <PdfEditorApp config={config} />
      </StrictMode>,
    );
  }
}
