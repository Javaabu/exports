/**
 * Live progress for the Blade exports pages.
 *
 * Rows marked with data-export-row update themselves: over Laravel Echo when the page
 * says broadcasting is on and window.Echo exists, by polling the JSON show route otherwise.
 * When an export finishes, the page reloads to show its actions. Forms with data-confirm
 * ask before they submit.
 */
import type { ExportProgressPayload } from "./types";

interface EchoLike {
  private(channel: string): {
    listen(event: string, callback: (payload: ExportProgressPayload) => void): unknown;
  };
}

declare global {
  interface Window {
    Echo?: EchoLike;
  }
}

const EVENTS = [".export.started", ".export.progressed", ".export.completed", ".export.failed", ".export.cancelled"];

export function updateRow(row: HTMLElement, data: ExportProgressPayload): void {
  const bar = row.querySelector<HTMLElement>("[data-export-progress-bar]");
  const text = row.querySelector<HTMLElement>("[data-export-progress-text]");
  const rows = row.querySelector<HTMLElement>("[data-export-rows]");
  const status = row.querySelector<HTMLElement>("[data-export-status] [data-slot='status-badge']");
  const meter = row.querySelector<HTMLElement>("[role='progressbar']");

  if (bar && data.progress !== null) {
    bar.style.inlineSize = `${data.progress}%`;
    bar.classList.remove("animate-pulse");
  }

  if (text) {
    text.textContent = data.progress === null ? "" : `${data.progress}%`;
  }

  if (meter && data.progress !== null) {
    meter.setAttribute("aria-valuenow", String(data.progress));
  }

  if (rows && data.total_rows !== null) {
    rows.textContent = `${data.processed_rows.toLocaleString()} / ${data.total_rows.toLocaleString()}`;
  }

  if (status) {
    status.textContent = data.status_label;
  }

  if (data.is_finished) {
    row.dataset.exportFinished = "true";
  }
}

function follow(container: HTMLElement): void {
  const rows = Array.from(container.querySelectorAll<HTMLElement>("[data-export-row]")).filter(
    (row) => row.dataset.exportFinished !== "true",
  );

  if (rows.length === 0) {
    return;
  }

  const onFinished = () => window.location.reload();
  const broadcasting = container.dataset.broadcasting === "true" && window.Echo !== undefined;

  if (broadcasting) {
    for (const row of rows) {
      const channel = row.dataset.exportChannel;

      if (!channel) {
        continue;
      }

      const subscription = window.Echo!.private(channel);

      for (const event of EVENTS) {
        subscription.listen(event, (payload) => {
          updateRow(row, payload);

          if (payload.is_finished) {
            onFinished();
          }
        });
      }
    }

    return;
  }

  const interval = Number(container.dataset.pollInterval) || 3000;

  const poll = async () => {
    const pending = rows.filter((row) => row.dataset.exportFinished !== "true");

    if (pending.length === 0) {
      return;
    }

    let finished = false;

    await Promise.all(
      pending.map(async (row) => {
        const url = row.dataset.exportUrl;

        if (!url) {
          return;
        }

        const response = await fetch(url, {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
        });

        if (!response.ok) {
          return;
        }

        const { data } = (await response.json()) as { data: ExportProgressPayload };

        updateRow(row, data);
        finished ||= data.is_finished;
      }),
    );

    if (finished) {
      onFinished();

      return;
    }

    window.setTimeout(poll, interval);
  };

  window.setTimeout(poll, interval);
}

function confirmForms(): void {
  document.addEventListener("submit", (event) => {
    const form = event.target as HTMLFormElement | null;
    const message = form?.dataset?.confirm;

    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });
}

export function init(): void {
  document.querySelectorAll<HTMLElement>("[data-exports]").forEach(follow);
  confirmForms();
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
}
