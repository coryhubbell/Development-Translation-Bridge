/**
 * Correction Item Component
 * Individual correction/suggestion display with action buttons
 */

import React from 'react';
import type { CorrectionSuggestion } from '@/types';
import { useEditorStore } from '@/store/editorStore';

interface CorrectionItemProps {
  correction: CorrectionSuggestion;
}

function CorrectionItem({ correction }: CorrectionItemProps) {
  const { applyCorrection, removeCorrection } = useEditorStore();

  const getIcon = () => {
    switch (correction.type) {
      case 'error':
        return (
          <svg className="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
              clipRule="evenodd"
            />
          </svg>
        );
      case 'warning':
        return (
          <svg className="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clipRule="evenodd"
            />
          </svg>
        );
      case 'info':
        return (
          <svg className="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
              clipRule="evenodd"
            />
          </svg>
        );
      case 'enhancement':
        return (
          <svg className="w-5 h-5 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        );
    }
  };

  const getSeverityColor = () => {
    switch (correction.severity) {
      case 'critical':
        return 'text-red-700 dark:text-red-300';
      case 'high':
        return 'text-orange-700 dark:text-orange-300';
      case 'medium':
        return 'text-yellow-700 dark:text-yellow-300';
      case 'low':
        return 'text-gray-700 dark:text-gray-300';
    }
  };

  return (
    <div className="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
      <div className="flex items-start gap-3">
        {/* Icon */}
        <div className="flex-shrink-0 mt-0.5">{getIcon()}</div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <span className={`text-sm font-medium ${getSeverityColor()}`}>
              {correction.type.charAt(0).toUpperCase() + correction.type.slice(1)}
            </span>
            <span className="text-xs text-gray-500 dark:text-gray-400">
              Line {correction.line}:{correction.column}
            </span>
            {correction.aiGenerated && (
              <span className="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 rounded">
                AI
              </span>
            )}
            {correction.confidence && (
              <span className="text-xs text-gray-500 dark:text-gray-400">
                {correction.confidence}% confidence
              </span>
            )}
          </div>

          <p className="text-sm text-gray-700 dark:text-gray-300 mb-2">
            {correction.message}
          </p>

          {correction.suggestion && (
            <p className="text-sm text-gray-600 dark:text-gray-400 italic mb-3">
              Suggestion: {correction.suggestion}
            </p>
          )}

          {/* Actions */}
          <div className="flex items-center gap-2">
            {correction.autoFix && (
              <button
                onClick={() => applyCorrection(correction.id)}
                className="text-xs px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded transition-colors"
              >
                Apply Fix
              </button>
            )}

            <button
              onClick={() => removeCorrection(correction.id)}
              className="text-xs px-3 py-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded transition-colors"
            >
              Dismiss
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default CorrectionItem;
