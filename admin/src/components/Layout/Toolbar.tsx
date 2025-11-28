/**
 * Toolbar Component
 * Top application toolbar with actions and settings
 */

import { useState } from 'react';
import { useEditorStore } from '@/store/editorStore';

function Toolbar() {
  const {
    editor,
    translateCode,
    isTranslating,
    reset,
    saveToBackend,
    saveStatus,
    loadHistory,
    historyLoading,
    fetchCorrections,
    isFetchingCorrections,
  } = useEditorStore();

  const [showSettings, setShowSettings] = useState(false);
  const [showHistory, setShowHistory] = useState(false);

  const handleSaveLocal = () => {
    // Save current state to localStorage as backup
    try {
      localStorage.setItem('wpbc_editor_state', JSON.stringify({
        sourceCode: editor.sourceCode,
        translatedCode: editor.translatedCode,
        sourceFramework: editor.sourceFramework,
        targetFramework: editor.targetFramework,
        savedAt: new Date().toISOString(),
      }));
    } catch (e) {
      console.error('Failed to save locally:', e);
    }
  };

  const handleSave = async () => {
    // Save to both localStorage and backend
    handleSaveLocal();
    await saveToBackend();
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

  const handleCheckIssues = () => {
    fetchCorrections(false);
  };

  const handleAICheck = () => {
    fetchCorrections(true);
  };

  const getSaveButtonContent = () => {
    switch (saveStatus) {
      case 'saving':
        return (
          <>
            <div className="spinner border-current w-4 h-4" />
            <span className="sr-only">Saving...</span>
          </>
        );
      case 'saved':
        return (
          <svg className="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
          </svg>
        );
      case 'error':
        return (
          <svg className="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
          </svg>
        );
      default:
        return (
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"
            />
          </svg>
        );
    }
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
          v{(window as any).wpbcData?.version || '3.3.0'}
        </span>

        {/* Dirty indicator */}
        {editor.isDirty && (
          <span className="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 rounded-full font-medium">
            Unsaved
          </span>
        )}
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

        {/* Check Issues Button */}
        <button
          onClick={handleCheckIssues}
          disabled={isFetchingCorrections || !editor.translatedCode}
          className="btn btn-secondary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          title="Check for issues"
        >
          {isFetchingCorrections ? (
            <div className="spinner border-current w-4 h-4" />
          ) : (
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          )}
          <span className="hidden sm:inline">Check</span>
        </button>

        {/* AI Analysis Button */}
        <button
          onClick={handleAICheck}
          disabled={isFetchingCorrections || !editor.translatedCode}
          className="btn btn-secondary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          title="AI-powered analysis"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
            />
          </svg>
          <span className="hidden sm:inline">AI</span>
        </button>

        <div className="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" />

        {/* Save Button */}
        <button
          onClick={handleSave}
          disabled={saveStatus === 'saving' || (!editor.isDirty && saveStatus !== 'error')}
          className="btn btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
          title={saveStatus === 'saved' ? 'Saved' : saveStatus === 'error' ? 'Save failed - click to retry' : 'Save to server'}
        >
          {getSaveButtonContent()}
        </button>

        {/* History Button */}
        <button
          onClick={() => {
            loadHistory();
            setShowHistory(!showHistory);
          }}
          disabled={historyLoading}
          className="btn btn-secondary disabled:opacity-50"
          title="View history"
        >
          {historyLoading ? (
            <div className="spinner border-current w-4 h-4" />
          ) : (
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          )}
        </button>

        {/* Export Button */}
        <button
          onClick={handleExport}
          disabled={!editor.translatedCode}
          className="btn btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
          title="Export translated code"
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

        <div className="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1" />

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
