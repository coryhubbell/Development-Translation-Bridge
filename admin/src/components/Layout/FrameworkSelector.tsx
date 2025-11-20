/**
 * Framework Selector Component
 * Dropdown for selecting source/target framework
 */

import React from 'react';
import type { Framework } from '@/types';

interface FrameworkSelectorProps {
  label: string;
  value: Framework;
  onChange: (framework: Framework) => void;
}

const frameworks: Array<{ id: Framework; name: string; icon: string }> = [
  { id: 'bootstrap', name: 'Bootstrap', icon: '🅱️' },
  { id: 'elementor', name: 'Elementor', icon: '🎨' },
  { id: 'bricks', name: 'Bricks Builder', icon: '🧱' },
  { id: 'oxygen', name: 'Oxygen Builder', icon: '⚛️' },
  { id: 'wpbakery', name: 'WPBakery', icon: '🏗️' },
  { id: 'divi', name: 'Divi Builder', icon: '💫' },
  { id: 'beaver-builder', name: 'Beaver Builder', icon: '🦫' },
  { id: 'gutenberg', name: 'Gutenberg', icon: '📝' },
  { id: 'avada', name: 'Avada Fusion', icon: '🔥' },
  { id: 'claude', name: 'Claude AI', icon: '🤖' },
];

function FrameworkSelector({
  label,
  value,
  onChange,
}: FrameworkSelectorProps) {

  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs font-medium text-gray-600 dark:text-gray-400">
        {label}
      </label>
      <div className="relative">
        <select
          value={value}
          onChange={(e) => onChange(e.target.value as Framework)}
          className="input pr-10 appearance-none cursor-pointer text-sm"
        >
          {frameworks.map((framework) => (
            <option key={framework.id} value={framework.id}>
              {framework.icon} {framework.name}
            </option>
          ))}
        </select>

        <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
          <svg
            className="w-4 h-4 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M19 9l-7 7-7-7"
            />
          </svg>
        </div>
      </div>
    </div>
  );
}

export default FrameworkSelector;
