import type { ReactNode } from 'react';

interface Props {
  height?: string;
  minHeight?: string;
  mode?: 'standalone' | 'embed';
  busy?: boolean;
  toolbar: ReactNode;
  pageRail: ReactNode;
  viewport: ReactNode;
  sidebar: ReactNode;
  toast?: ReactNode;
}

/**
 * CKEditor / Tiptap-style shell: toolbar → [page rail | canvas | properties].
 */
export function EditorShell({
  height,
  minHeight,
  mode = 'standalone',
  busy,
  toolbar,
  pageRail,
  viewport,
  sidebar,
  toast,
}: Props) {
  const shellStyle = {
    height: height ?? '100%',
    minHeight: minHeight ?? '480px',
  };

  return (
    <div
      className={`nowo-pdf-editor-shell nowo-pdf-editor-shell--${mode}`}
      id="nowo-pdf-editor-root"
      style={shellStyle}
      aria-busy={busy}
    >
      {toolbar}
      <div className="nowo-pdf-editor-body">
        {pageRail}
        <main className="nowo-pdf-editor-viewport">{viewport}</main>
        {sidebar}
      </div>
      {toast}
    </div>
  );
}
