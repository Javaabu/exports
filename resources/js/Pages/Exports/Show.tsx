import { Card, LinkButton, Status } from "@javaabu/forms";
import { router } from "@inertiajs/react";
import { useMemo, type ReactNode } from "react";
import { ExportActions } from "../../components/ExportActions";
import { ExportProgress } from "../../components/ExportProgress";
import { useExportProgress } from "../../hooks/useExportProgress";
import type { ExportsShowProps } from "../../types";

function fileSize(bytes: number): string {
  const units = ["B", "KB", "MB", "GB"];
  let size = bytes;
  let unit = 0;

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024;
    unit++;
  }

  return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

/** exports::Exports/Show — one export, with live progress */
export default function Show(props: ExportsShowProps) {
  const { strings, urls } = props;
  const initial = useMemo(() => [props.export], [props.export]);

  const item =
    useExportProgress(initial, {
      broadcasting: props.broadcasting,
      pollInterval: props.poll_interval,
      onFinished: () => router.reload({ only: ["export"] }),
    })[0] ?? props.export;

  const entries: Array<[string, ReactNode]> = [
    [strings.status, <Status key="status" color={item.status_color} label={item.status_label} />],
    [strings.progress, <ExportProgress key="progress" export={item} strings={strings} />],
    [strings.format, item.format_label],
    [strings.file, <span key="file" dir="auto">{item.filename}</span>],
  ];

  if (item.size) {
    entries.push([strings.size, fileSize(item.size)]);
  }

  if (item.created_at) {
    entries.push([strings.created, new Date(item.created_at).toLocaleString()]);
  }

  if (item.expires_at) {
    entries.push([strings.expires, new Date(item.expires_at).toLocaleString()]);
  }

  return (
    <div className="flex flex-col gap-6" data-exports>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-semibold">{props.title}</h1>
        <LinkButton url={urls.index} color="secondary" size="sm" startIcon="chevron-left">
          {strings.back}
        </LinkButton>
      </div>

      <Card footer={<ExportActions export={item} strings={strings} />}>
        <dl className="grid gap-4 sm:grid-cols-2" data-export-row={item.id}>
          {entries.map(([term, description]) => (
            <div key={term}>
              <dt className="text-muted-foreground text-sm">{term}</dt>
              <dd>{description}</dd>
            </div>
          ))}
          {item.error ? (
            <div className="sm:col-span-2">
              <dt className="text-muted-foreground text-sm">{strings.error}</dt>
              <dd className="text-destructive">{item.error}</dd>
            </div>
          ) : null}
        </dl>
      </Card>
    </div>
  );
}
