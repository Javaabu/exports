import { describe, expect, it } from "vitest";
import { updateRow } from "../../resources/js/progress";
import { makeExport } from "./fixtures";

describe("Blade progress script", () => {
  it("updates a row from a progress payload", () => {
    document.body.innerHTML = `
      <table><tbody>
        <tr data-export-row="9d3c" data-export-finished="false">
          <td><span data-export-status><span data-slot="status-badge">ތައްޔާރުކުރަނީ</span></span></td>
          <td>
            <div role="progressbar" aria-valuenow="25"><div data-export-progress-bar class="animate-pulse" style="inline-size: 25%"></div></div>
            <span data-export-progress-text>25%</span>
          </td>
          <td><span data-export-rows></span></td>
        </tr>
      </tbody></table>`;

    const row = document.querySelector<HTMLElement>("[data-export-row]")!;

    updateRow(row, makeExport({ progress: 100, processed_rows: 200, status_label: "ނިމިއްޖެ", is_finished: true }));

    expect(row.querySelector<HTMLElement>("[data-export-progress-bar]")!.style.inlineSize).toBe("100%");
    expect(row.querySelector("[data-export-progress-text]")!.textContent).toBe("100%");
    expect(row.querySelector("[role=progressbar]")!.getAttribute("aria-valuenow")).toBe("100");
    expect(row.querySelector("[data-export-rows]")!.textContent).toBe("200 / 200");
    expect(row.querySelector("[data-slot=status-badge]")!.textContent).toBe("ނިމިއްޖެ");
    expect(row.dataset.exportFinished).toBe("true");
  });
});
