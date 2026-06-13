import React, { useState, useEffect } from 'react'
import { getSettings, saveSettings } from '../api'

function Toggle({ checked, onChange, id }) {
  return (
    <button
      id={id}
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={`lc-toggle ${checked ? 'bg-wp-blue' : 'bg-gray-300'}`}
    >
      <span className={`lc-toggle-thumb ${checked ? 'translate-x-5' : 'translate-x-1'}`} />
    </button>
  )
}

function Row({ label, hint, children }) {
  return (
    <div className="flex items-start justify-between py-4 border-b border-gray-100 last:border-0">
      <div className="flex-1 pr-6">
        <p className="text-sm font-medium text-gray-800">{label}</p>
        {hint && <p className="text-xs text-gray-400 mt-0.5">{hint}</p>}
      </div>
      <div className="shrink-0">{children}</div>
    </div>
  )
}

export default function Settings() {
  const [form, setForm]     = useState(window.liveCopyAdmin.settings)
  const [status, setStatus] = useState(null) // 'saving' | 'saved' | 'error'

  useEffect(() => {
    getSettings().then(setForm).catch(() => {})
  }, [])

  const set = (key, val) => setForm(prev => ({ ...prev, [key]: val }))

  const handleSave = async () => {
    setStatus('saving')
    try {
      const updated = await saveSettings(form)
      setForm(updated)
      setStatus('saved')
      setTimeout(() => setStatus(null), 2500)
    } catch {
      setStatus('error')
      setTimeout(() => setStatus(null), 3000)
    }
  }

  return (
    <div className="space-y-6">
      <div className="lc-card">
        <h2 className="text-base font-semibold text-gray-700 mb-1">General</h2>
        <p className="text-xs text-gray-400 mb-4">Control whether the plugin is active and who can see the buttons.</p>

        <Row label="Enable Live Copy" hint="Master switch — disables all frontend buttons when off.">
          <Toggle checked={!!form.enable} onChange={v => set('enable', v)} />
        </Row>

        <Row label="Show Copy Button" hint="Adds the 'Live Copy' clipboard button on hover.">
          <Toggle checked={!!form.show_copy_btn} onChange={v => set('show_copy_btn', v)} />
        </Row>

        <Row label="Show Download Button" hint="Adds the 'Download' JSON button on hover.">
          <Toggle checked={!!form.show_download_btn} onChange={v => set('show_download_btn', v)} />
        </Row>

        <Row label="Disable on Mobile" hint="Hides buttons on touch/small-screen devices (prevents accidents).">
          <Toggle checked={!!form.disable_on_mobile} onChange={v => set('disable_on_mobile', v)} />
        </Row>
      </div>

      <div className="lc-card">
        <h2 className="text-base font-semibold text-gray-700 mb-1">Access Control</h2>
        <p className="text-xs text-gray-400 mb-4">Choose which visitors see the copy/download buttons.</p>

        <Row label="Button Visibility">
          <select
            value={form.visibility}
            onChange={e => set('visibility', e.target.value)}
            className="border border-gray-200 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-wp-blue"
          >
            <option value="everyone">Everyone (including visitors)</option>
            <option value="logged_in">Logged-in users only</option>
            <option value="editors">Editors & Admins only</option>
          </select>
        </Row>
      </div>

      <div className="lc-card">
        <h2 className="text-base font-semibold text-gray-700 mb-1">Advanced</h2>
        <p className="text-xs text-gray-400 mb-4">Per-section targeting mode.</p>

        <Row
          label="Specific Section Mode"
          hint="When on, buttons only appear on sections you explicitly enable via the Elementor Advanced tab."
        >
          <Toggle checked={!!form.specific_section_mode} onChange={v => set('specific_section_mode', v)} />
        </Row>

        <Row
          label="Help / Video URL"
          hint="Linked from the ℹ️ info icon on the frontend panel (e.g. a 'how it works' video). Cached in the visitor's browser after first load."
        >
          <input
            type="url"
            value={form.help_url || ''}
            onChange={e => set('help_url', e.target.value)}
            placeholder="https://…"
            className="border border-gray-200 rounded px-3 py-1.5 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-wp-blue"
          />
        </Row>
      </div>

      <div className="flex items-center gap-3">
        <button onClick={handleSave} disabled={status === 'saving'} className="lc-btn-primary">
          {status === 'saving' ? 'Saving…' : 'Save Settings'}
        </button>

        {status === 'saved' && (
          <span className="text-sm text-green-600 font-medium">Settings saved.</span>
        )}
        {status === 'error' && (
          <span className="text-sm text-red-500 font-medium">Save failed. Try again.</span>
        )}
      </div>
    </div>
  )
}
