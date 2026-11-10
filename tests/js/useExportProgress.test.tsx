import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useExportProgress } from "../../resources/js/hooks/useExportProgress";
import { makeExport } from "./fixtures";

describe("useExportProgress", () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    delete (window as { Echo?: unknown }).Echo;
  });

  it("polls unfinished exports and stops when they finish", async () => {
    const done = makeExport({ status: "completed", status_label: "ނިމިއްޖެ", progress: 100, processed_rows: 200, is_finished: true, is_downloadable: true });
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(JSON.stringify({ data: done })));
    const onFinished = vi.fn();
    const initial = [makeExport()];

    const { result } = renderHook(() => useExportProgress(initial, { broadcasting: false, pollInterval: 1000, onFinished }));

    expect(result.current[0]?.progress).toBe(25);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1000);
    });

    expect(fetchMock).toHaveBeenCalledWith("/exports/9d3c", expect.objectContaining({ headers: expect.objectContaining({ Accept: "application/json" }) }));
    expect(result.current[0]?.status_label).toBe("ނިމިއްޖެ");
    expect(onFinished).toHaveBeenCalledOnce();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(5000);
    });

    expect(fetchMock).toHaveBeenCalledOnce();
  });

  it("does nothing when every export has finished", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    const initial = [makeExport({ is_finished: true, status: "failed" })];

    renderHook(() => useExportProgress(initial, { broadcasting: false, pollInterval: 1000 }));

    await act(async () => {
      await vi.advanceTimersByTimeAsync(3000);
    });

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("listens on the export's channel when broadcasting", () => {
    const listeners: Record<string, (payload: unknown) => void> = {};
    const leave = vi.fn();

    (window as { Echo?: unknown }).Echo = {
      private: vi.fn(() => ({
        listen: (event: string, callback: (payload: unknown) => void) => {
          listeners[event] = callback;
        },
      })),
      leave,
    };

    const fetchMock = vi.spyOn(globalThis, "fetch");
    const initial = [makeExport()];

    const { result, unmount } = renderHook(() => useExportProgress(initial, { broadcasting: true, pollInterval: 1000 }));

    act(() => {
      listeners[".export.progressed"]?.({ id: "9d3c", progress: 80, processed_rows: 160, is_finished: false });
    });

    expect(result.current[0]?.progress).toBe(80);
    expect(fetchMock).not.toHaveBeenCalled();

    unmount();

    expect(leave).toHaveBeenCalledWith("exports.9d3c");
  });
});
