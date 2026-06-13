import React, { useState, useEffect, useCallback } from 'react'
import { getStats, clearStats, exportRows } from '../api'

const CSV_COLUMNS = ['id', 'page_id', 'page_slug', 'section_id', 'action_type', 'user_id', 'ip_address', 'created_at']

function toCsv(rows) {
  const esc = (v) => {
    const s = v == null ? '' : String(v)
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s
  }
  const head = CSV_COLUMNS.join(',')
  const body = rows.map(r => CSV_COLUMNS.map(c => esc(r[c])).join(',')).join('\n')
  return head + '\n' + body
}

// value 0 = all time
const DAYS_OPTIONS = [
  { value: 7,  label: 'Last 7 days' },
  { value: 30, label: 'Last 30 days' },
  { value: 90, label: 'Last 90 days' },
  { value: 0,  label: 'All time' },
]

function StatCard({ label, value, sub, color = 'blue' }) {
  const colors = {
    blue  : 'bg-wp-blue-light text-wp-blue border-wp-blue',
    purple: 'bg-purple-50 text-brand-purple border-purple-300',
    green : 'bg-green-50 text-brand-green border-green-300',
    gray  : 'bg-gray-50 text-gray-600 border-gray-200',
  }
  return (
    <div className={`lc-card border-t-4 ${colors[color]}`}>
      <p className="text-3xl font-bold">{value ?? '—'}</p>
      <p className="text-sm font-medium mt-1">{label}</p>
      {sub && <p className="text-xs opacity-70 mt-0.5">{sub}</p>}
    </div>
  )
}

function Bar({ value, max }) {
  const pct = max > 0 ? Math.round((value / max) * 100) : 0
  return (
    <div className="flex items-center gap-2">
      <div className="flex-1 bg-gray-100 rounded h-2">
        <div className="bg-wp-blue h-2 rounded transition-all" style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs text-gray-500 w-8 text-right">{value}</span>
    </div>
  )
}

// Clickable page cell: title (or slug) linked to the live URL, opens new tab.
function PageLink({ row }) {
  const label = row.page_title || row.page_slug || `#${row.page_id}` || '—'
  if (!row.page_url) {
    return <span className="text-gray-500">{label}</span>
  }
  return (
    <a
      href={row.page_url}
      target="_blank"
      rel="noopener noreferrer"
      title={`Open ${row.page_url}`}
      className="text-wp-blue hover:text-wp-blue-dark hover:underline inline-flex items-center gap-1"
    >
      <span className="truncate max-w-[150px]">{label}</span>
      <span aria-hidden className="text-xs opacity-60">↗</span>
    </a>
  )
}

// mode: 'pages' (Section column omitted) | 'sections' (Section column shown)
function Table({ title, rows, mode }) {
  const max = rows.length > 0 ? Math.max(...rows.map(r => Number(r.total))) : 1
  const showSection = mode === 'sections'
  return (
    <div className="lc-card">
      <h3 className="text-sm font-semibold text-gray-700 mb-4">{title}</h3>
      {rows.length === 0 ? (
        <p className="text-sm text-gray-400">No data for this period.</p>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-gray-400 border-b border-gray-100">
              <th className="pb-2 font-medium">#</th>
              {showSection && <th className="pb-2 font-medium">Section</th>}
              <th className="pb-2 font-medium">Page</th>
              <th className="pb-2 font-medium">Count</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, i) => (
              <tr key={i} className="border-b border-gray-50 last:border-0">
                <td className="py-2 text-gray-400 w-6">{i + 1}</td>
                {showSection && (
                  <td className="py-2 pr-4 text-xs font-mono text-gray-500">{row.section_id || '—'}</td>
                )}
                <td className="py-2 pr-4"><PageLink row={row} /></td>
                <td className="py-2 w-36"><Bar value={Number(row.total)} max={max} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

// Fixed last-30-days window, zero-filled by the backend. Bars flex to fill the
// card width so it never scrolls horizontally regardless of the period filter.
function DailyChart({ daily }) {
  const rows = daily || []
  const hasAny = rows.some(d => (Number(d.copy) + Number(d.download)) > 0)
  const maxVal = Math.max(...rows.map(d => Number(d.copy) + Number(d.download)), 1)

  const fmt = (iso) => {
    const [, m, d] = iso.split('-')
    return `${m}/${d}`
  }

  return (
    <div className="lc-card">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-sm font-semibold text-gray-700">Daily Activity</h3>
        <span className="text-xs text-gray-400">Last 30 days</span>
      </div>

      {!hasAny ? (
        <p className="text-sm text-gray-400">No activity in the last 30 days.</p>
      ) : (
        <>
          <div className="flex items-end gap-[2px] h-28">
            {rows.map(({ date, copy, download }) => {
              const c = Number(copy)
              const d = Number(download)
              const total = c + d
              const barH  = total > 0 ? Math.max(Math.round((total / maxVal) * 100), 3) : 0
              const copyH = total > 0 ? Math.round((c / total) * barH) : 0
              const dlH   = barH - copyH
              return (
                <div
                  key={date}
                  className="flex-1 min-w-0 h-full flex flex-col justify-end group relative"
                  title={`${date} — ${c} copies, ${d} downloads`}
                >
                  <div className="w-full flex flex-col justify-end rounded-sm overflow-hidden" style={{ height: `${barH}px` }}>
                    {dlH > 0   && <div style={{ height: `${dlH}px` }}   className="w-full bg-brand-green" />}
                    {copyH > 0 && <div style={{ height: `${copyH}px` }} className="w-full bg-wp-blue" />}
                  </div>
                </div>
              )
            })}
          </div>

          {/* Sparse date axis: start / middle / end — no rotation, no clipping */}
          <div className="flex justify-between text-[10px] text-gray-400 mt-1.5">
            <span>{rows.length ? fmt(rows[0].date) : ''}</span>
            <span>{rows.length ? fmt(rows[Math.floor(rows.length / 2)].date) : ''}</span>
            <span>{rows.length ? fmt(rows[rows.length - 1].date) : ''}</span>
          </div>
        </>
      )}

      <div className="flex gap-4 mt-4 text-xs text-gray-500">
        <span className="flex items-center gap-1.5"><span className="w-3 h-3 rounded-sm bg-wp-blue inline-block" />Copies</span>
        <span className="flex items-center gap-1.5"><span className="w-3 h-3 rounded-sm bg-brand-green inline-block" />Downloads</span>
      </div>
    </div>
  )
}

export default function Reports() {
  const [days, setDays]     = useState(30)
  const [data, setData]     = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]   = useState(null)

  const [busy, setBusy] = useState(false)

  const load = useCallback(async (d) => {
    setLoading(true)
    setError(null)
    try {
      const stats = await getStats(d)
      setData(stats)
    } catch (e) {
      setError(e.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load(days) }, [days, load])

  const handleExport = async () => {
    setBusy(true)
    setError(null)
    try {
      const { rows } = await exportRows()
      if (!rows || !rows.length) { setError('No data to export.'); return }
      const blob = new Blob([toCsv(rows)], { type: 'text/csv' })
      const url  = URL.createObjectURL(blob)
      const a    = document.createElement('a')
      a.href = url
      a.download = 'live-copy-history.csv'
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  const handleClear = async () => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Delete all copy/download history? This cannot be undone.')) return
    setBusy(true)
    setError(null)
    try {
      await clearStats()
      await load(days)
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }

  const totalsMap = Object.fromEntries((data?.totals || []).map(r => [r.action_type, Number(r.total)]))
  const totalCopies    = totalsMap.copy     || 0
  const totalDownloads = totalsMap.download || 0

  return (
    <div className="space-y-6">
      {/* Day filter + actions */}
      <div className="flex items-center gap-2 flex-wrap">
        <span className="text-sm text-gray-500">Period:</span>
        {DAYS_OPTIONS.map(d => (
          <button
            key={d.value}
            onClick={() => setDays(d.value)}
            className={`lc-btn text-xs ${days === d.value ? 'lc-btn-primary' : 'lc-btn-secondary'}`}
          >
            {d.label}
          </button>
        ))}
        {loading && <span className="text-xs text-gray-400 ml-2">Loading…</span>}

        <span className="flex-1" />
        <button onClick={handleExport} disabled={busy} className="lc-btn-secondary text-xs">Export CSV</button>
        {window.liveCopyAdmin.canClear ? (
          <button
            onClick={handleClear}
            disabled={busy}
            className="lc-btn text-xs bg-red-50 text-red-600 hover:bg-red-100"
          >
            Clear data
          </button>
        ) : (
          <span
            className="text-xs text-gray-400"
            title="Add define( 'LIVE_COPY_ALLOW_CLEAR', true ); to wp-config.php to enable clearing."
          >
            Clear data 🔒
          </span>
        )}
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-600 text-sm rounded p-3">{error}</div>
      )}

      {/* Stat cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total Copies"    value={totalCopies}           color="blue"   sub="Clipboard copies" />
        <StatCard label="Total Downloads" value={totalDownloads}        color="green"  sub="JSON file downloads" />
        <StatCard label="Unique Pages"    value={data?.unique_pages}    color="purple" sub="Pages with activity" />
        <StatCard label="Unique Sections" value={data?.unique_sections} color="gray"   sub="Sections copied/downloaded" />
      </div>

      {/* Daily chart */}
      <DailyChart daily={data?.daily} />

      {/* Tables */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Table title="Top Pages"    rows={data?.top_pages || []}    mode="pages" />
        <Table title="Top Sections" rows={data?.top_sections || []} mode="sections" />
      </div>

      <p className="text-xs text-gray-400">
        {days > 0 ? `Stats based on the last ${days} days.` : 'Stats across all recorded history.'}
        {' '}Counts include both copy and download actions. Page links resolve to the current permalink;
        history is retained for 180 days.
      </p>
    </div>
  )
}
