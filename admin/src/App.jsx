import React, { useState } from 'react'
import Settings from './tabs/Settings'
import Reports  from './tabs/Reports'

const TABS = [
  { id: 'settings', label: 'Settings' },
  { id: 'reports',  label: 'Reports' },
]

export default function App() {
  const [active, setActive] = useState('settings')

  return (
    <div className="wrap max-w-5xl py-6">
      <h1 className="text-2xl font-semibold text-gray-800 mb-6">
        Live Copy Paste
        <span className="ml-3 text-sm font-normal text-gray-400">Settings & Analytics</span>
      </h1>

      {/* Tab nav */}
      <div className="flex gap-1 border-b border-gray-200 mb-6">
        {TABS.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActive(tab.id)}
            className={[
              'px-5 py-2.5 text-sm font-medium rounded-t border-b-2 transition-colors',
              active === tab.id
                ? 'border-wp-blue text-wp-blue bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-700',
            ].join(' ')}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {active === 'settings' && <Settings />}
      {active === 'reports'  && <Reports />}
    </div>
  )
}
