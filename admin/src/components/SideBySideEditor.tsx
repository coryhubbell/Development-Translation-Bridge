/**
 * Side-by-Side Editor Component
 * Main component for the visual interface
 */

import React, { useEffect } from 'react';
import { Allotment } from 'allotment';
import 'allotment/dist/style.css';
import MonacoEditor from './Monaco/MonacoEditor';
import PreviewPane from './Preview/PreviewPane';
import CorrectionPanel from './Corrections/CorrectionPanel';
import Toolbar from './Layout/Toolbar';
import FrameworkSelector from './Layout/FrameworkSelector';
import { useEditorStore } from '@/store/editorStore';

function SideBySideEditor() {
  const { editor, translateCode, isTranslating } = useEditorStore();

  // Auto-translate on framework change or code change (debounced)
  useEffect(() => {
    if (editor.sourceCode && !isTranslating) {
      const timer = setTimeout(() => {
        translateCode();
      }, 1000); // 1 second debounce

      return () => clearTimeout(timer);
    }
  }, [editor.sourceCode, editor.sourceFramework, editor.targetFramework]);

  return (
    <div className="h-screen flex flex-col bg-gray-50 dark:bg-gray-900">
      {/* Top Toolbar */}
      <div className="flex-shrink-0 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <Toolbar />
      </div>

      {/* Framework Selectors */}
      <div className="flex-shrink-0 px-6 py-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center gap-6">
          <FrameworkSelector
            label="Source Framework"
            value={editor.sourceFramework}
            onChange={(framework) =>
              useEditorStore.getState().setSourceFramework(framework)
            }
          />

          <div className="flex-shrink-0">
            <svg
              className="w-6 h-6 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M13 7l5 5m0 0l-5 5m5-5H6"
              />
            </svg>
          </div>

          <FrameworkSelector
            label="Target Framework"
            value={editor.targetFramework}
            onChange={(framework) =>
              useEditorStore.getState().setTargetFramework(framework)
            }
          />

          {isTranslating && (
            <div className="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400">
              <div className="spinner" />
              <span>Translating...</span>
            </div>
          )}
        </div>
      </div>

      {/* Main Content Area */}
      <div className="flex-1 overflow-hidden">
        <Allotment defaultSizes={[50, 50]}>
          {/* Left Pane - Source Code Editor */}
          <Allotment.Pane minSize={300}>
            <div className="h-full flex flex-col bg-white dark:bg-gray-800">
              <div className="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                  Source Code ({editor.sourceFramework})
                </h2>
              </div>
              <div className="flex-1 overflow-hidden">
                <MonacoEditor
                  value={editor.sourceCode}
                  framework={editor.sourceFramework}
                  onChange={(value) =>
                    useEditorStore.getState().setSourceCode(value || '')
                  }
                  isReadOnly={false}
                />
              </div>
            </div>
          </Allotment.Pane>

          {/* Right Pane - Translated Code / Preview */}
          <Allotment.Pane minSize={300}>
            <div className="h-full flex flex-col bg-white dark:bg-gray-800">
              <div className="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                  Translated Code ({editor.targetFramework})
                </h2>
              </div>
              <div className="flex-1 overflow-hidden">
                <Allotment vertical defaultSizes={[70, 30]}>
                  {/* Translated Code Editor */}
                  <Allotment.Pane minSize={200}>
                    <MonacoEditor
                      value={editor.translatedCode}
                      framework={editor.targetFramework}
                      onChange={(value) =>
                        useEditorStore.getState().setTranslatedCode(value || '')
                      }
                      isReadOnly={false}
                    />
                  </Allotment.Pane>

                  {/* Live Preview */}
                  <Allotment.Pane minSize={100}>
                    <PreviewPane code={editor.translatedCode} />
                  </Allotment.Pane>
                </Allotment>
              </div>
            </div>
          </Allotment.Pane>
        </Allotment>
      </div>

      {/* Bottom Panel - Corrections */}
      <CorrectionPanel />
    </div>
  );
}

export default SideBySideEditor;
