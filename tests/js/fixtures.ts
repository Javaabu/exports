import type { ExportData, ExportsStrings } from "../../resources/js/types";

export const strings = {
  progress: "ޕްރޮގްރެސް",
  rows_of: ":processed of :total rows",
  download: "ޑައުންލޯޑު",
  cancel: "ކެންސަލް",
  delete: "ފުހެލާ",
  confirm_cancel: "ކެންސަލް ކުރަންވީތޯ؟",
  confirm_delete: "ފުހެލަންވީތޯ؟",
} as unknown as ExportsStrings;

export function makeExport(overrides: Partial<ExportData> = {}): ExportData {
  return {
    id: "9d3c",
    type: "users",
    name: "ބޭނުންތެރިން",
    format: "xlsx",
    format_label: "Excel (XLSX)",
    status: "processing",
    status_label: "ތައްޔާރުކުރަނީ",
    status_color: "info",
    progress: 25,
    total_rows: 200,
    processed_rows: 50,
    filename: "ބޭނުންތެރިން.xlsx",
    size: null,
    error: null,
    is_finished: false,
    is_downloadable: false,
    is_cancellable: true,
    user: null,
    can: { download: true, cancel: true, delete: true },
    links: { show: "/exports/9d3c", download: null, cancel: "/exports/9d3c/cancel", destroy: "/exports/9d3c" },
    broadcast_channel: "exports.9d3c",
    started_at: null,
    completed_at: null,
    failed_at: null,
    cancelled_at: null,
    expires_at: null,
    created_at: "2026-11-04T09:30:00+05:00",
    updated_at: null,
    ...overrides,
  };
}
