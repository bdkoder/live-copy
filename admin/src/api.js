const { restUrl, nonce } = window.liveCopyAdmin

async function apiFetch(path, options = {}) {
  const res = await fetch(restUrl + 'live-copy/v1' + path, {
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    },
    ...options,
  })

  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err?.message || `HTTP ${res.status}`)
  }

  return res.json()
}

export const getSettings = ()       => apiFetch('/settings')
export const saveSettings = (data)  => apiFetch('/settings', { method: 'POST', body: JSON.stringify(data) })
export const getStats = (days = 30) => apiFetch(`/stats?days=${days}`)
export const clearStats = ()        => apiFetch('/stats/clear', { method: 'POST' })
export const exportRows = ()        => apiFetch('/export')
