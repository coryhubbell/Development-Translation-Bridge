/**
 * Editor State Management Store
 * Using Zustand for lightweight, performant state management
 */

import { create } from 'zustand';
import { devtools, persist } from 'zustand/middleware';
import type {
  EditorState,
  Framework,
  CorrectionSuggestion,
  TooltipData,
  UserPreferences,
} from '@/types';
import {
  translationService,
  persistenceService,
  correctionService,
  ApiError,
  RateLimitError,
} from '@/services';
import type { TranslationSummary } from '@/services';

// Notification types
export interface Notification {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  message: string;
  duration?: number;
}

interface EditorStoreState {
  // Editor State
  editor: EditorState;

  // Persistence State
  currentTranslationId: number | null;
  saveStatus: 'idle' | 'saving' | 'saved' | 'error';
  lastSavedAt: Date | null;
  translationHistory: TranslationSummary[];
  historyLoading: boolean;

  // Corrections
  corrections: CorrectionSuggestion[];
  activeCorrections: string[];
  correctionsSource: 'none' | 'rules' | 'ai' | 'both';

  // Tooltips
  tooltips: TooltipData[];
  activeTooltip: string | null;

  // User Preferences
  preferences: UserPreferences;

  // Notifications
  notifications: Notification[];

  // Loading States
  isLoading: boolean;
  isTranslating: boolean;
  isFetchingCorrections: boolean;

  // Actions
  setSourceFramework: (framework: Framework) => void;
  setTargetFramework: (framework: Framework) => void;
  setSourceCode: (code: string) => void;
  setTranslatedCode: (code: string) => void;
  setIsDirty: (dirty: boolean) => void;

  // Correction Actions
  addCorrection: (correction: CorrectionSuggestion) => void;
  removeCorrection: (id: string) => void;
  clearCorrections: () => void;
  applyCorrection: (id: string) => void;
  fetchCorrections: (aiEnabled?: boolean) => Promise<void>;
  dismissCorrection: (id: string) => Promise<void>;

  // Tooltip Actions
  showTooltip: (tooltip: TooltipData) => void;
  hideTooltip: (id: string) => void;
  clearTooltips: () => void;

  // Translation Actions
  translateCode: () => Promise<void>;

  // Persistence Actions
  saveToBackend: (name?: string) => Promise<void>;
  loadFromBackend: (id: number) => Promise<void>;
  loadHistory: () => Promise<void>;

  // Notification Actions
  showNotification: (type: Notification['type'], message: string, duration?: number) => void;
  dismissNotification: (id: string) => void;

  // Preferences Actions
  updatePreferences: (preferences: Partial<UserPreferences>) => void;

  // Reset
  reset: () => void;
}

const defaultEditorState: EditorState = {
  sourceFramework: 'bootstrap',
  targetFramework: 'elementor',
  sourceCode: '',
  translatedCode: '',
  isDirty: false,
  isTranslating: false,
  lastSaved: null,
};

const defaultPreferences: UserPreferences = {
  theme: 'light',
  editorFontSize: 14,
  editorFontFamily: 'Fira Code, monospace',
  showMinimap: true,
  enableAI: true,
  enableRealTimeCorrections: true,
  autoSave: true,
  notifications: {
    corrections: true,
    translations: true,
    errors: true,
  },
};

// Generate unique notification ID
const generateNotificationId = () => `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

export const useEditorStore = create<EditorStoreState>()(
  devtools(
    persist(
      (set, get) => ({
        // Initial State
        editor: defaultEditorState,
        currentTranslationId: null,
        saveStatus: 'idle',
        lastSavedAt: null,
        translationHistory: [],
        historyLoading: false,
        corrections: [],
        activeCorrections: [],
        correctionsSource: 'none',
        tooltips: [],
        activeTooltip: null,
        preferences: defaultPreferences,
        notifications: [],
        isLoading: false,
        isTranslating: false,
        isFetchingCorrections: false,

        // Editor Actions
        setSourceFramework: (framework) =>
          set((state) => ({
            editor: { ...state.editor, sourceFramework: framework, isDirty: true },
          })),

        setTargetFramework: (framework) =>
          set((state) => ({
            editor: { ...state.editor, targetFramework: framework, isDirty: true },
          })),

        setSourceCode: (code) =>
          set((state) => ({
            editor: { ...state.editor, sourceCode: code, isDirty: true },
          })),

        setTranslatedCode: (code) =>
          set((state) => ({
            editor: { ...state.editor, translatedCode: code },
          })),

        setIsDirty: (dirty) =>
          set((state) => ({
            editor: { ...state.editor, isDirty: dirty },
          })),

        // Correction Actions
        addCorrection: (correction) =>
          set((state) => ({
            corrections: [...state.corrections, correction],
          })),

        removeCorrection: (id) =>
          set((state) => ({
            corrections: state.corrections.filter((c) => c.id !== id),
            activeCorrections: state.activeCorrections.filter((cid) => cid !== id),
          })),

        clearCorrections: () =>
          set({
            corrections: [],
            activeCorrections: [],
            correctionsSource: 'none',
          }),

        applyCorrection: (id) => {
          const correction = get().corrections.find((c) => c.id === id);
          if (!correction || !correction.autoFix) return;

          const { sourceCode } = get().editor;
          const lines = sourceCode.split('\n');

          // Apply the fix
          const line = lines[correction.line - 1];
          if (line) {
            const before = line.substring(0, correction.column);
            const after = line.substring(correction.endColumn);
            lines[correction.line - 1] = before + correction.autoFix.replacement + after;
          }

          const updatedCode = lines.join('\n');

          set((state) => ({
            editor: { ...state.editor, sourceCode: updatedCode, isDirty: true },
          }));

          // Remove the applied correction
          get().removeCorrection(id);
          get().showNotification('success', 'Correction applied');
        },

        fetchCorrections: async (aiEnabled = false) => {
          const { translatedCode, targetFramework } = get().editor;

          if (!translatedCode.trim()) {
            get().showNotification('warning', 'No code to analyze');
            return;
          }

          set({ isFetchingCorrections: true });

          try {
            const result = aiEnabled
              ? await correctionService.aiAnalyze(translatedCode, targetFramework)
              : await correctionService.quickCheck(translatedCode, targetFramework);

            set({
              corrections: result.corrections,
              correctionsSource: aiEnabled ? 'both' : 'rules',
              isFetchingCorrections: false,
            });

            const { total, errors, warnings } = result.summary;
            if (total > 0) {
              get().showNotification(
                errors > 0 ? 'warning' : 'info',
                `Found ${total} issue${total !== 1 ? 's' : ''}: ${errors} error${errors !== 1 ? 's' : ''}, ${warnings} warning${warnings !== 1 ? 's' : ''}`
              );
            } else {
              get().showNotification('success', 'No issues found');
            }
          } catch (error) {
            set({ isFetchingCorrections: false });
            console.error('Correction fetch failed:', error);
            get().showNotification('error', 'Failed to analyze code');
          }
        },

        dismissCorrection: async (id) => {
          try {
            await correctionService.dismissCorrection({ correction_id: id });
            get().removeCorrection(id);
          } catch (error) {
            console.error('Dismiss correction failed:', error);
          }
        },

        // Tooltip Actions
        showTooltip: (tooltip) =>
          set((state) => ({
            tooltips: [...state.tooltips, tooltip],
            activeTooltip: tooltip.id,
          })),

        hideTooltip: (id) =>
          set((state) => ({
            tooltips: state.tooltips.filter((t) => t.id !== id),
            activeTooltip: state.activeTooltip === id ? null : state.activeTooltip,
          })),

        clearTooltips: () =>
          set({
            tooltips: [],
            activeTooltip: null,
          }),

        // Translation Actions
        translateCode: async () => {
          const { sourceCode, sourceFramework, targetFramework } = get().editor;

          if (!sourceCode.trim()) {
            get().showNotification('warning', 'Please enter some code to translate');
            return;
          }

          set({ isTranslating: true });

          try {
            const response = await translationService.translate({
              source: sourceFramework,
              target: targetFramework,
              content: sourceCode,
            });

            set((state) => ({
              editor: {
                ...state.editor,
                translatedCode: response.result,
                lastSaved: new Date(),
                isDirty: true,
              },
              isTranslating: false,
            }));

            get().showNotification('success', 'Translation successful!');

            // Optionally trigger auto-analysis after translation
            if (get().preferences.enableRealTimeCorrections) {
              get().fetchCorrections(false);
            }
          } catch (error) {
            console.error('Translation error:', error);
            set({ isTranslating: false });

            if (error instanceof RateLimitError) {
              get().showNotification(
                'warning',
                `Rate limit exceeded. Please wait ${error.retryAfter} seconds.`
              );
            } else if (error instanceof ApiError) {
              get().showNotification('error', `Translation failed: ${error.message}`);
            } else {
              get().showNotification(
                'error',
                `Translation failed: ${error instanceof Error ? error.message : 'Unknown error'}`
              );
            }
          }
        },

        // Persistence Actions
        saveToBackend: async (name) => {
          const { editor } = get();

          if (!editor.sourceCode.trim() && !editor.translatedCode.trim()) {
            get().showNotification('warning', 'Nothing to save');
            return;
          }

          set({ saveStatus: 'saving' });

          try {
            const result = await persistenceService.saveTranslation({
              source_framework: editor.sourceFramework,
              target_framework: editor.targetFramework,
              source_code: editor.sourceCode,
              translated_code: editor.translatedCode,
              name: name || `Translation ${new Date().toLocaleDateString()}`,
            });

            set({
              saveStatus: 'saved',
              lastSavedAt: new Date(),
              currentTranslationId: result.translation_id,
              editor: { ...editor, isDirty: false },
            });

            get().showNotification('success', 'Saved successfully');
          } catch (error) {
            console.error('Save failed:', error);
            set({ saveStatus: 'error' });
            get().showNotification('error', 'Failed to save');
          }
        },

        loadFromBackend: async (id) => {
          set({ isLoading: true });

          try {
            const translation = await persistenceService.loadTranslation(id);

            set((state) => ({
              editor: {
                ...state.editor,
                sourceFramework: translation.source_framework,
                targetFramework: translation.target_framework,
                sourceCode: translation.source_code,
                translatedCode: translation.translated_code,
                isDirty: false,
              },
              currentTranslationId: translation.id,
              isLoading: false,
            }));

            get().showNotification('success', 'Translation loaded');
          } catch (error) {
            console.error('Load failed:', error);
            set({ isLoading: false });
            get().showNotification('error', 'Failed to load translation');
          }
        },

        loadHistory: async () => {
          set({ historyLoading: true });

          try {
            const result = await persistenceService.getHistory({ per_page: 50 });

            set({
              translationHistory: result.translations,
              historyLoading: false,
            });
          } catch (error) {
            console.error('Load history failed:', error);
            set({ historyLoading: false });
            get().showNotification('error', 'Failed to load history');
          }
        },

        // Notification Actions
        showNotification: (type, message, duration = 5000) => {
          const id = generateNotificationId();
          const notification: Notification = { id, type, message, duration };

          set((state) => ({
            notifications: [...state.notifications, notification],
          }));

          // Auto-dismiss after duration
          if (duration > 0) {
            setTimeout(() => {
              get().dismissNotification(id);
            }, duration);
          }
        },

        dismissNotification: (id) =>
          set((state) => ({
            notifications: state.notifications.filter((n) => n.id !== id),
          })),

        // Preferences Actions
        updatePreferences: (preferences) =>
          set((state) => ({
            preferences: { ...state.preferences, ...preferences },
          })),

        // Reset
        reset: () =>
          set({
            editor: defaultEditorState,
            currentTranslationId: null,
            saveStatus: 'idle',
            lastSavedAt: null,
            corrections: [],
            activeCorrections: [],
            correctionsSource: 'none',
            tooltips: [],
            activeTooltip: null,
            notifications: [],
            isLoading: false,
            isTranslating: false,
            isFetchingCorrections: false,
          }),
      }),
      {
        name: 'wpbc-editor-storage',
        partialize: (state) => ({
          preferences: state.preferences,
          editor: {
            sourceFramework: state.editor.sourceFramework,
            targetFramework: state.editor.targetFramework,
          },
        }),
      }
    )
  )
);
