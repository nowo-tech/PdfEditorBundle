export type EditorOp =
  | { op: 'insert_text'; page: number; x: number; y: number; text: string; fontSize?: number }
  | { op: 'add_watermark'; text: string; opacity?: number }
  | { op: 'rotate_page'; page: number; degrees: number }
  | { op: 'remove_detected_watermarks' }
  | { op: 'remove_xobject'; page: number; rect: [number, number, number, number] }
  | { op: 'redact_text'; page: number }
  | {
      op: 'delete_text';
      page: number;
      /** embedpdf PDF user-space rects (origin bottom-left). */
      rects: Array<{ x: number; y: number; width: number; height: number }>;
    }
  | { op: 'add_acroform_field'; page: number; name: string; type: string; bbox: [number, number, number, number] }
  | { op: 'delete_page'; page: number }
  | { op: 'insert_blank_page'; afterPage: number }
  | { op: 'duplicate_page'; page: number };

export interface DemoScenarioConfig {
  id: string;
  title: string;
  lead: string;
  hubUrl?: string;
}

export interface WorkspaceConfig {
  workspaceId: string;
  pdfUrl: string;
  applyUrl: string;
  downloadUrl: string;
  csrfToken: string;
  engineMode: 'client' | 'python';
  i18n: Record<string, string>;
  scenario?: DemoScenarioConfig | null;
  /** Host-controlled shell sizing (dashboard embed). */
  layout?: {
    height?: string;
    minHeight?: string;
    /** standalone = full page; embed = inside host chrome (demo/dashboard). */
    mode?: 'standalone' | 'embed';
  };
}

export interface EditorRegistry {
  getPlugin(name: string): { provides?: () => unknown } | null | undefined;
}

export interface ExportScope {
  saveAsCopy(): { toPromise(): Promise<Uint8Array> };
}

export interface ExportPlugin {
  forDocument(id: string): ExportScope;
}
