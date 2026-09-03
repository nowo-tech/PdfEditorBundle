import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { PdfEditorApp } from './PdfEditorApp';
import type { WorkspaceConfig } from './types';

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
