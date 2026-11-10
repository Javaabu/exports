import { useEffect, useRef, useState } from "react";
import type { ExportData, ExportProgressPayload } from "../types";

interface EchoLike {
  private(channel: string): {
    listen(event: string, callback: (payload: ExportProgressPayload) => void): unknown;
  };
  leave(channel: string): void;
}

export interface UseExportProgressOptions {
  /** Listen with window.Echo when true and Echo is there; poll otherwise */
  broadcasting: boolean;
  /** Milliseconds between polls */
  pollInterval: number;
  /** Called when an export finishes */
  onFinished?: (exportData: ExportData) => void;
}

const EVENTS = [".export.started", ".export.progressed", ".export.completed", ".export.failed", ".export.cancelled"];

function echo(): EchoLike | undefined {
  return typeof window === "undefined" ? undefined : (window as unknown as { Echo?: EchoLike }).Echo;
}

/**
 * Keeps a list of exports up to date while they run: over Laravel Echo when the page
 * broadcasts, by polling each unfinished export's show route otherwise.
 */
export function useExportProgress(initial: ExportData[], options: UseExportProgressOptions): ExportData[] {
  const [exports, setExports] = useState(initial);
  const options_ref = useRef(options);
  options_ref.current = options;

  useEffect(() => setExports(initial), [initial]);

  const unfinished = exports.filter((item) => !item.is_finished).map((item) => item.id).join(",");

  useEffect(() => {
    if (unfinished === "") {
      return;
    }

    const merge = (payload: ExportProgressPayload) => {
      setExports((current) =>
        current.map((item) => {
          if (item.id !== payload.id) {
            return item;
          }

          const updated = { ...item, ...payload };

          if (payload.is_finished && !item.is_finished) {
            options_ref.current.onFinished?.(updated);
          }

          return updated;
        }),
      );
    };

    const pending = exports.filter((item) => !item.is_finished);
    const client = echo();

    if (options.broadcasting && client) {
      const channels = pending.map((item) => item.broadcast_channel).filter((channel): channel is string => !!channel);

      for (const channel of channels) {
        const subscription = client.private(channel);

        for (const event of EVENTS) {
          subscription.listen(event, merge);
        }
      }

      return () => channels.forEach((channel) => client.leave(channel));
    }

    let stopped = false;

    const timer = window.setInterval(async () => {
      await Promise.all(
        pending.map(async (item) => {
          if (!item.links.show) {
            return;
          }

          const response = await fetch(item.links.show, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
          });

          if (!response.ok || stopped) {
            return;
          }

          const { data } = (await response.json()) as { data: ExportData };

          merge(data);
        }),
      );
    }, options.pollInterval);

    return () => {
      stopped = true;
      window.clearInterval(timer);
    };
    // Re-subscribe only when the set of unfinished exports changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unfinished, options.broadcasting, options.pollInterval]);

  return exports;
}
