import { ProgressBar } from "@javaabu/onu";
import type { ExportData, ExportProgressPayload, ExportsStrings } from "../types";

export interface ExportProgressProps {
  export: Pick<ExportProgressPayload, "progress" | "status" | "is_finished" | "processed_rows" | "total_rows">;
  strings: Pick<ExportsStrings, "progress" | "rows_of">;
}

const COLORS: Record<ExportData["status"], "primary" | "success" | "destructive" | "muted" | "info"> = {
  pending: "muted",
  processing: "info",
  completed: "success",
  failed: "destructive",
  cancelled: "muted",
};

export function rowsText(template: string, processed: number, total: number | null): string {
  if (total === null) {
    return "";
  }

  return template.replace(":processed", processed.toLocaleString()).replace(":total", total.toLocaleString());
}

/** A progress bar for an export; indeterminate while the row count is unknown */
export function ExportProgress({ export: item, strings }: ExportProgressProps) {
  const value = item.progress ?? (item.is_finished ? 0 : null);

  return (
    <div className="flex min-w-32 flex-col gap-1" data-export-progress>
      <ProgressBar value={value} label={strings.progress} showValue={item.progress !== null} size="sm" color={COLORS[item.status]} />
      <span className="text-muted-foreground text-xs tabular-nums">{rowsText(strings.rows_of, item.processed_rows, item.total_rows)}</span>
    </div>
  );
}
