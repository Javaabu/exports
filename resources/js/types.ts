export type ExportStatus = "pending" | "processing" | "completed" | "failed" | "cancelled";

export type ExportStatusColor = "secondary" | "info" | "success" | "danger" | "warning";

/** What the progress events broadcast, and the subset of an export the pages update */
export interface ExportProgressPayload {
  id: string;
  status: ExportStatus;
  status_label: string;
  status_color: ExportStatusColor;
  progress: number | null;
  total_rows: number | null;
  processed_rows: number;
  is_finished: boolean;
  is_downloadable: boolean;
  error: string | null;
}

/** An export as ExportResource serializes it */
export interface ExportData extends ExportProgressPayload {
  type: string | null;
  name: string;
  format: "csv" | "xlsx" | "ods";
  format_label: string;
  filename: string | null;
  size: number | null;
  is_cancellable: boolean;
  user?: { type: string; id: string | number; name: string | null } | null;
  can: { download: boolean; cancel: boolean; delete: boolean };
  links: { show: string | null; download: string | null; cancel: string | null; destroy: string | null };
  broadcast_channel: string | null;
  started_at: string | null;
  completed_at: string | null;
  failed_at: string | null;
  cancelled_at: string | null;
  expires_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ExportRoutes {
  index: string;
  store: string;
  show: string;
  download: string;
  cancel: string;
  destroy: string;
}

export interface Option<T extends string = string> {
  value: T;
  label: string;
}

export interface ExportsStrings {
  title: string;
  new_export: string;
  export: string;
  format: string;
  start: string;
  status: string;
  all_statuses: string;
  only_mine: string;
  filter: string;
  name: string;
  progress: string;
  rows: string;
  started_by: string;
  created: string;
  expires: string;
  actions: string;
  download: string;
  cancel: string;
  delete: string;
  confirm_cancel: string;
  confirm_delete: string;
  no_exports: string;
  no_exports_message: string;
  rows_of: string;
  rows_written: string;
  error: string;
  back: string;
  file: string;
  size: string;
}

export interface Paginated<T> {
  data: T[];
  links: Record<string, string | null>;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    path: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
}

/** Props shared by the pages */
export interface ExportsPageProps {
  title: string;
  broadcasting: boolean;
  poll_interval: number;
  routes: ExportRoutes;
  urls: { index: string; store: string };
  strings: ExportsStrings;
}

export interface ExportsIndexProps extends ExportsPageProps {
  exports: Paginated<ExportData>;
  available: Array<{ type: string; label: string }>;
  formats: Option[];
  statuses: Option<ExportStatus>[];
  sees_others: boolean;
  filters: { status: ExportStatus | null; type: string | null; mine: boolean };
}

export interface ExportsShowProps extends ExportsPageProps {
  export: ExportData;
}
