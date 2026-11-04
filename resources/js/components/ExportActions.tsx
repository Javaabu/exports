import { Form, LinkButton, Submit } from "@javaabu/forms";
import type { MouseEvent } from "react";
import type { ExportData, ExportsStrings } from "../types";

export interface ExportActionsProps {
  export: ExportData;
  strings: Pick<ExportsStrings, "download" | "cancel" | "delete" | "confirm_cancel" | "confirm_delete">;
}

function confirmFirst(message: string) {
  return (event: MouseEvent<HTMLButtonElement>) => {
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  };
}

/** Download, cancel and delete buttons, shown when the user may use them */
export function ExportActions({ export: item, strings }: ExportActionsProps) {
  return (
    <div className="flex flex-wrap items-center gap-2" data-export-actions>
      {item.is_downloadable && item.can.download && item.links.download ? (
        <LinkButton url={item.links.download} download size="sm" startIcon="download">
          {strings.download}
        </LinkButton>
      ) : null}

      {item.is_cancellable && item.can.cancel && item.links.cancel ? (
        <Form method="POST" action={item.links.cancel}>
          <Submit color="secondary" size="sm" animate={false} onClick={confirmFirst(strings.confirm_cancel)}>
            {strings.cancel}
          </Submit>
        </Form>
      ) : null}

      {item.can.delete && item.links.destroy ? (
        <Form method="DELETE" action={item.links.destroy}>
          <Submit color="danger" size="sm" animate={false} onClick={confirmFirst(strings.confirm_delete)}>
            {strings.delete}
          </Submit>
        </Form>
      ) : null}
    </div>
  );
}
