import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ExportProgress, rowsText } from "../../resources/js/components/ExportProgress";
import { makeExport, strings } from "./fixtures";

describe("ExportProgress", () => {
  it("shows the progress and the rows", () => {
    render(<ExportProgress export={makeExport({ processed_rows: 1500, total_rows: 2000, progress: 75 })} strings={strings} />);

    const bar = screen.getByRole("progressbar");

    expect(bar.getAttribute("aria-valuenow")).toBe("75");
    expect(screen.getByText("1,500 of 2,000 rows")).toBeTruthy();
  });

  it("is indeterminate while the row count is unknown", () => {
    render(<ExportProgress export={makeExport({ progress: null, total_rows: null })} strings={strings} />);

    expect(screen.getByRole("progressbar").getAttribute("aria-valuenow")).toBeNull();
  });

  it("formats the rows text", () => {
    expect(rowsText(":processed / :total", 10, 20)).toBe("10 / 20");
    expect(rowsText(":processed / :total", 10, null)).toBe("");
  });
});
