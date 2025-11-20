/**
 * Correction Panel Component
 * Displays AI-generated corrections and suggestions
 */

import React, { useState } from 'react';
import { useEditorStore } from '@/store/editorStore';
import CorrectionItem from './CorrectionItem';

function CorrectionPanel() {
  const { corrections, clearCorrections } = useEditorStore();
  const [isCollapsed, setIsCollapsed] = useState(false);

  if (corrections.length === 0) {
    return null;
  }

  return (
    <div className="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
      {/* Panel Header */}
      <div className="flex items-center justify-between px-6 py-3 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center gap-3">
          <button
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          >
            <svg
              className={`w-5 h-5 transition-transform ${
                isCollapsed ? '-rotate-90' : ''
              }`}
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
          </button>

          <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Corrections & Suggestions
          </h3>

          <span className="px-2 py-1 text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 rounded-full">
            {corrections.length}
          </span>
        </div>

        <button
          onClick={clearCorrections}
          className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          Clear All
        </button>
      </div>

      {/* Panel Content */}
      {!isCollapsed && (
        <div className="max-h-64 overflow-y-auto scrollbar-thin">
          <div className="divide-y divide-gray-200 dark:divide-gray-700">
            {corrections.map((correction) => (
              <CorrectionItem key={correction.id} correction={correction} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default CorrectionPanel;
