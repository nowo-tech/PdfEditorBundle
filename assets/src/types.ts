export type EditorOp =
  | { op: 'insert_text'; page: number; x: number; y: number; text: string; fontSize?: number }
  | { op: 'add_watermark'; text: string; opacity?: number }
  | { op: 'rotate_page'; page: number; degrees: number }
  | { op: 'remove_detected_watermarks' }
  | { op: 'remove_xobject'; page: number; rect: [number, number, number, number] }
  | { op: 'add_acroform_field'; page: number; name: string; type: string; bbox: [number, number, number, number] };

export interface WorkspaceConfig {
  workspaceId: string;
  pdfUrl: string;
  applyUrl: string;
  downloadUrl: string;
  csrfToken: string;
  engineMode: 'client' | 'python';
  i18n: Record<string, string>;
}

export interface EditorRegistry {
  getPlugin(name: string): { provides?: () => unknown } | undefined;
}

export interface ExportScope {
  saveAsCopy(): { toPromise(): Promise<Uint8Array> };
}

export interface ExportPlugin {
  forDocument(id: string): ExportScope;
}
