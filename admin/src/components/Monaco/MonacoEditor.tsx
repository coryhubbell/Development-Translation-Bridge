/**
 * Monaco Editor Component
 * Wraps @monaco-editor/react with custom configuration
 * @refresh reset
 */

import React, { useRef, useEffect } from 'react';
import Editor, { type Monaco } from '@monaco-editor/react';
import type { editor } from 'monaco-editor';
import type { Framework } from '@/types';
import { useEditorStore } from '@/store/editorStore';

interface MonacoEditorProps {
  value: string;
  framework: Framework;
  onChange: (value: string | undefined) => void;
  isReadOnly?: boolean;
}

function MonacoEditor({
  value,
  framework,
  onChange,
  isReadOnly = false,
}: MonacoEditorProps) {
  const { preferences, corrections } = useEditorStore();
  const editorRef = useRef<editor.IStandaloneCodeEditor | null>(null);
  const monacoRef = useRef<Monaco | null>(null);

  const getLanguage = (fw: Framework): string => {
    const languageMap: Record<Framework, string> = {
      bootstrap: 'html',
      elementor: 'json',
      bricks: 'json',
      oxygen: 'json',
      wpbakery: 'html',
      divi: 'json',
      'beaver-builder': 'html',
      gutenberg: 'html',
      avada: 'html',
      claude: 'html',
    };

    return languageMap[fw] || 'html';
  };

  const handleEditorDidMount = (
    editor: editor.IStandaloneCodeEditor,
    monaco: Monaco
  ) => {
    editorRef.current = editor;
    monacoRef.current = monaco;

    // Configure editor options
    editor.updateOptions({
      fontSize: preferences.editorFontSize,
      fontFamily: preferences.editorFontFamily,
      minimap: { enabled: preferences.showMinimap },
      wordWrap: 'on',
      lineNumbers: 'on',
      renderWhitespace: 'boundary',
      scrollBeyondLastLine: false,
      automaticLayout: true,
      readOnly: isReadOnly,
      theme: 'vs-light',
    });

    // Add custom commands
    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
      // Trigger save
      console.log('Save triggered');
    });
  };

  // Update decorations for corrections
  useEffect(() => {
    if (!editorRef.current || !monacoRef.current) return;

    const editor = editorRef.current;
    const monaco = monacoRef.current;

    const decorations = corrections.map((correction) => ({
      range: new monaco.Range(
        correction.line,
        correction.column,
        correction.endLine,
        correction.endColumn
      ),
      options: {
        isWholeLine: false,
        className:
          correction.type === 'error'
            ? 'squiggly-error'
            : correction.type === 'warning'
            ? 'squiggly-warning'
            : 'squiggly-info',
        hoverMessage: {
          value: `**${correction.type.toUpperCase()}**: ${correction.message}`,
        },
        glyphMarginClassName:
          correction.type === 'error'
            ? 'error-glyph'
            : correction.type === 'warning'
            ? 'warning-glyph'
            : 'info-glyph',
      },
    }));

    const decorationIds = editor.deltaDecorations([], decorations);

    return () => {
      editor.deltaDecorations(decorationIds, []);
    };
  }, [corrections]);

  return (
    <div className="h-full w-full monaco-editor-container">
      <Editor
        height="100%"
        language={getLanguage(framework)}
        value={value}
        onChange={onChange}
        onMount={handleEditorDidMount}
        theme="vs-light"
        options={{
          fontSize: preferences.editorFontSize,
          fontFamily: preferences.editorFontFamily,
          minimap: { enabled: preferences.showMinimap },
          wordWrap: 'on',
          lineNumbers: 'on',
          readOnly: isReadOnly,
          scrollBeyondLastLine: false,
          automaticLayout: true,
        }}
      />
    </div>
  );
}

export default MonacoEditor;
