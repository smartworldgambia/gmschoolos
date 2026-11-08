export default function DataTable({
    columns = [],
    rows = [],
    emptyMessage = 'No records found.',
    keyField = 'id',
    className = '',
    embedded = false,
    headClassName = 'border-b border-surface-muted bg-slate-50/80',
}) {
    return (
        <div className={embedded ? className : `overflow-hidden rounded-2xl border border-surface-muted bg-white shadow-soft ${className}`}>
            <div className="overflow-x-auto">
                <table className="min-w-full text-start text-sm">
                    <thead>
                        <tr className={headClassName}>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className={`px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 ${column.headerClassName ?? ''}`}
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length || 1} className="px-4 py-12 text-center text-sm text-slate-500">
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row, index) => {
                                const rowKey = row[keyField] ?? row.key ?? `row-${index}`;

                                return (
                                    <tr key={rowKey} className="border-b border-surface-muted/80 last:border-0 hover:bg-slate-50/70">
                                        {columns.map((column) => {
                                            const value = column.render ? column.render(row, index) : row[column.key];

                                            return (
                                                <td
                                                    key={column.key}
                                                    className={`whitespace-nowrap px-4 py-3.5 text-[13px] text-brand-ink ${column.cellClassName ?? ''}`}
                                                >
                                                    {value ?? '—'}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
