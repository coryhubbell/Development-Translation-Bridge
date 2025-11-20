/**
 * Toolbar Component
 * Top application toolbar with actions and settings
 */

import React, { useState } from 'react';
import { useEditorStore } from '@/store/editorStore';

function Toolbar() {
  const { editor, translateCode, isTranslating, reset, preferences, updatePreferences } =
    useEditorStore();
  const [showSettings, setShowSettings] = useState(false);

  const handleSave = () => {
    // TODO: Implement save functionality
    console.log('Save triggered');
  };

  const handleExport = () => {
    // Create downloadable file
    const blob = new Blob([editor.translatedCode], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `translated_${editor.targetFramework}_${Date.now()}.txt`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="px-6 py-3 flex items-center justify-between">
      {/* Left Side - Logo & Title */}
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2">
          <svg
            className="w-8 h-8 text-primary-600"
            fill="currentColor"
            viewBox="0 0 24 24"
          >
            <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
          </svg>
          <h1 className="text-lg font-bold text-gray-900 dark:text-gray-100">
            WordPress Bootstrap Claude
          </h1>
        </div>

        <span className="text-xs px-2 py-1 bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 rounded-full font-medium">
          v3.2.1
        </span>
      </div>

      {/* Right Side - Actions */}
      <div className="flex items-center gap-2">
        {/* Translate Button */}
        <button
          onClick={() => translateCode()}
          disabled={isTranslating || !editor.sourceCode}
          className="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          {isTranslating ? (
            <>
              <div className="spinner border-white" />
              <span>Translating...</span>
            </>
          ) : (
            <>
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                />
              </svg>
              <span>Translate</span>
            </>
          )}
        </button>

        {/* Save Button */}
        <button
          onClick={handleSave}
          disabled={!editor.isDirty}
          className="btn btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"
            />
          </svg>
        </button>

        {/* Export Button */}
        <button
          onClick={handleExport}
          disabled={!editor.translatedCode}
          className="btn btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
            />
          </svg>
        </button>

        {/* Settings */}
        <button
          onClick={() => setShowSettings(!showSettings)}
          className="btn btn-secondary"
          title="Settings"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
            />
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            />
          </svg>
        </button>

        {/* New Project */}
        <button
          onClick={reset}
          className="btn btn-secondary"
          title="New project"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 4v16m8-8H4"
            />
          </svg>
        </button>
      </div>
    </div>
  );
}

export default Toolbar;
