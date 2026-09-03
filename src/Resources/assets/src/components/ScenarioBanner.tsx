interface Props {
  title: string;
  lead: string;
  hubUrl?: string;
  backLabel: string;
}

export function ScenarioBanner({ title, lead, hubUrl, backLabel }: Props) {
  return (
    <div className="nowo-pdf-editor-scenario-banner">
      <div className="nowo-pdf-editor-scenario-banner-text">
        <strong>{title}</strong>
        <span>{lead}</span>
      </div>
      {hubUrl ? (
        <a className="nowo-ui-btn nowo-ui-btn-tiny" href={hubUrl}>
          {backLabel}
        </a>
      ) : null}
    </div>
  );
}
