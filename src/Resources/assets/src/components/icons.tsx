import type { ReactElement, SVGProps } from 'react';

type IconProps = SVGProps<SVGSVGElement>;

function IconBase({ children, ...props }: IconProps & { children: ReactElement | ReactElement[] }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" {...props}>
      {children}
    </svg>
  );
}

export function IconSelect(props: IconProps) {
  return (
    <IconBase {...props}>
      <path d="M4 4l7 17 2.5-7.5L21 11 4 4z" />
    </IconBase>
  );
}

export function IconText(props: IconProps) {
  return (
    <IconBase {...props}>
      <path d="M5 4h14v3h-5v13h-4V7H5V4z" />
    </IconBase>
  );
}

export function IconForm(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="M7 10h10M7 14h6" />
    </IconBase>
  );
}

export function IconWatermark(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z" />
      <path d="M8 14c1.5-2 3-2 4 0s2.5 2 4 0" />
    </IconBase>
  );
}

export function IconRedact(props: IconProps) {
  return (
    <IconBase {...props}>
      <rect x="4" y="8" width="16" height="8" rx="1" />
    </IconBase>
  );
}

export function IconAnnotate(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M4 4h16v12H4z" />
      <path d="M8 8h8M8 12h5" />
    </IconBase>
  );
}

export function IconDeletePage(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M6 6h12M9 6V4h6v2M8 10v6M12 10v6M16 10v6" />
    </IconBase>
  );
}

export function IconInsertPage(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M12 5v14M5 12h14" />
    </IconBase>
  );
}

export function IconDuplicatePage(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <rect x="8" y="6" width="10" height="12" rx="1" />
      <rect x="6" y="8" width="10" height="12" rx="1" />
    </IconBase>
  );
}

export function IconTrash(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z" />
    </IconBase>
  );
}

export function IconRemoveWatermark(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M15 6l-9 9M9 6l9 9" />
      <path d="M4 20h16" />
    </IconBase>
  );
}

export function IconRotate(props: IconProps) {
  return (
    <IconBase {...props}>
      <path d="M12 4V1l4 4-4 4V6a6 6 0 1 0 6 6h2a8 8 0 1 1-8-8z" />
    </IconBase>
  );
}

export function IconSave(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M5 4h10l4 4v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" />
      <path d="M15 4v4h4M9 13l2 2 4-4" />
    </IconBase>
  );
}

export function IconDiscard(props: IconProps) {
  return (
    <IconBase {...props}>
      <path d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z" />
    </IconBase>
  );
}

export function IconDownload(props: IconProps) {
  return (
    <IconBase {...props} fill="none" stroke="currentColor" strokeWidth={2}>
      <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16" />
    </IconBase>
  );
}

export const TOOL_ICONS = {
  select: IconSelect,
  text: IconText,
  form: IconForm,
  annotate: IconAnnotate,
  watermark: IconWatermark,
  redact: IconRedact,
} as const;

export const ACTION_ICONS = {
  'remove-watermarks': IconRemoveWatermark,
  rotate: IconRotate,
  'delete-selection': IconTrash,
  'delete-page': IconDeletePage,
  'insert-page': IconInsertPage,
  'duplicate-page': IconDuplicatePage,
  commit: IconSave,
  discard: IconDiscard,
  download: IconDownload,
} as const;
