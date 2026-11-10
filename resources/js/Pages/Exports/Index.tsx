import { Card, Checkbox, Form, Pagination, Select, Status, Submit, Table, TableCell, TableEmptyRow, TableHeading, TableRow } from "@javaabu/forms";
import { router } from "@inertiajs/react";
import { ExportActions } from "../../components/ExportActions";
import { ExportProgress } from "../../components/ExportProgress";
import { useExportProgress } from "../../hooks/useExportProgress";
import type { ExportsIndexProps } from "../../types";

/** exports::Exports/Index — the user's exports, a form to start one, and live progress */
export default function Index(props: ExportsIndexProps) {
  const { strings, urls, sees_others: seesOthers } = props;

  const exports = useExportProgress(props.exports.data, {
    broadcasting: props.broadcasting,
    pollInterval: props.poll_interval,
    onFinished: () => router.reload({ only: ["exports"] }),
  });

  const columns = seesOthers ? 7 : 6;

  return (
    <div className="flex flex-col gap-6" data-exports>
      <h1 className="text-2xl font-semibold">{props.title}</h1>

      {props.available.length > 0 ? (
        <Card title={strings.new_export}>
          <Form method="POST" action={urls.store} className="flex flex-wrap items-end gap-4">
            <Select
              name="type"
              label={strings.export}
              options={Object.fromEntries(props.available.map((option) => [option.type, option.label]))}
              required
            />
            <Select
              name="format"
              label={strings.format}
              options={Object.fromEntries(props.formats.map((option) => [option.value, option.label]))}
              default={props.formats[0]?.value}
            />
            <Submit>{strings.start}</Submit>
          </Form>
        </Card>
      ) : null}

      <Form method="GET" action={urls.index} model={props.filters} className="flex flex-wrap items-end gap-4">
        <Select
          name="status"
          label={strings.status}
          placeholder={strings.all_statuses}
          showPlaceholder
          options={Object.fromEntries(props.statuses.map((option) => [option.value, option.label]))}
        />
        {seesOthers ? <Checkbox name="mine" value="1" label={strings.only_mine} /> : null}
        <Submit color="secondary" animate={false}>
          {strings.filter}
        </Submit>
      </Form>

      <Table
        model="exports"
        noCheckbox
        noBulk
        headers={
          <>
            <TableHeading label={strings.name} />
            <TableHeading label={strings.format} />
            <TableHeading label={strings.status} />
            <TableHeading label={strings.progress} />
            {seesOthers ? <TableHeading label={strings.started_by} /> : null}
            <TableHeading label={strings.created} />
            <TableHeading label={strings.actions} />
          </>
        }
        pagination={props.exports.meta.last_page > 1 ? <Pagination paginator={props.exports} /> : null}
      >
        {exports.length ? (
          exports.map((item) => (
            <TableRow key={item.id} modelId={item.id} noCheckbox data-export-row={item.id}>
              <TableCell name="name" label={strings.name}>
                {item.links.show ? (
                  <a href={item.links.show} className="font-medium hover:underline">
                    {item.name}
                  </a>
                ) : (
                  item.name
                )}
                <div className="text-muted-foreground text-sm" dir="auto">
                  {item.filename}
                </div>
              </TableCell>
              <TableCell name="format" label={strings.format}>
                {item.format_label}
              </TableCell>
              <TableCell name="status" label={strings.status}>
                <Status color={item.status_color} label={item.status_label} />
              </TableCell>
              <TableCell name="progress" label={strings.progress}>
                <ExportProgress export={item} strings={strings} />
              </TableCell>
              {seesOthers ? (
                <TableCell name="user" label={strings.started_by}>
                  {item.user?.name ?? ""}
                </TableCell>
              ) : null}
              <TableCell name="created_at" label={strings.created}>
                {item.created_at ? <time dateTime={item.created_at}>{new Date(item.created_at).toLocaleString()}</time> : null}
              </TableCell>
              <TableCell name="actions" label={strings.actions}>
                <ExportActions export={item} strings={strings} />
              </TableCell>
            </TableRow>
          ))
        ) : (
          <TableEmptyRow columns={columns} noCheckbox>
            <p className="font-medium">{strings.no_exports}</p>
            <p className="text-muted-foreground text-sm">{strings.no_exports_message}</p>
          </TableEmptyRow>
        )}
      </Table>
    </div>
  );
}
