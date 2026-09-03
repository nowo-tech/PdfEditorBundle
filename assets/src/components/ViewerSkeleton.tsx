interface Props {
  message: string;
}

export function ViewerSkeleton({ message }: Props) {
  return (
    <div className="nowo-pdf-editor-skeleton" aria-hidden="true">
      <div className="nowo-pdf-editor-skeleton-page" />
      <p className="nowo-pdf-editor-skeleton-label">{message}</p>
    </div>
  );
}
