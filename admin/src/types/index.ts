/**
 * Core TypeScript Type Definitions
 * DevelopmentTranslation Bridge - Visual Interface
 */

// Framework Types — must stay in sync with DEVTB_Converter_Factory (14 entries).
export type Framework =
  | 'bootstrap'
  | 'divi'
  | 'divi-5'
  | 'elementor'
  | 'elementor-4'
  | 'avada'
  | 'bricks'
  | 'wpbakery'
  | 'beaver-builder'
  | 'gutenberg'
  | 'oxygen'
  | 'oxygen-6'
  | 'kadence'
  | 'thrive';

export interface FrameworkInfo {
  id: Framework;
  name: string;
  version: string;
  fileExtension: string;
  syntax: 'xml' | 'json' | 'html' | 'php';
}

// Editor State Types
export interface EditorState {
  sourceFramework: Framework;
  targetFramework: Framework;
  sourceCode: string;
  translatedCode: string;
  isDirty: boolean;
  isTranslating: boolean;
  lastSaved: Date | null;
}

// Correction Types
export interface CorrectionSuggestion {
  id: string;
  type: 'error' | 'warning' | 'info' | 'enhancement';
  severity: 'critical' | 'high' | 'medium' | 'low';
  line: number;
  column: number;
  endLine: number;
  endColumn: number;
  message: string;
  suggestion: string;
  autoFix?: {
    description: string;
    replacement: string;
  };
  aiGenerated: boolean;
  confidence: number; // 0-100
}

export interface CorrectionContext {
  framework: Framework;
  codeSnippet: string;
  surroundingContext: string;
  userIntent?: string;
}

// Tooltip Types
export interface TooltipData {
  id: string;
  position: { x: number; y: number };
  content: string;
  type: 'suggestion' | 'documentation' | 'error' | 'help';
  actions?: TooltipAction[];
  persistent: boolean;
}

export interface TooltipAction {
  label: string;
  type: 'primary' | 'secondary' | 'danger';
  onClick: () => void | Promise<void>;
}

// AI Integration Types
export interface AIRequest {
  framework: Framework;
  code: string;
  context: string;
  requestType: 'correction' | 'translation' | 'enhancement' | 'documentation';
}

export interface AIResponse {
  success: boolean;
  data?: {
    suggestions: CorrectionSuggestion[];
    translatedCode?: string;
    explanation?: string;
  };
  error?: string;
  tokensUsed?: number;
  processingTime?: number;
}

// Project Types
export interface Project {
  id: string;
  name: string;
  description: string;
  sourceFramework: Framework;
  targetFramework: Framework;
  createdAt: Date;
  updatedAt: Date;
  files: ProjectFile[];
  settings: ProjectSettings;
}

export interface ProjectFile {
  id: string;
  name: string;
  path: string;
  content: string;
  translatedContent?: string;
  status: 'pending' | 'translating' | 'completed' | 'error';
  corrections: CorrectionSuggestion[];
}

export interface ProjectSettings {
  autoSave: boolean;
  autoSaveInterval: number; // seconds
  aiAssistance: boolean;
  realTimeCorrections: boolean;
  showLineNumbers: boolean;
  wordWrap: boolean;
  theme: 'light' | 'dark' | 'auto';
}

// Translation Bridge Types
export interface TranslationRequest {
  sourceFramework: Framework;
  targetFramework: Framework;
  code: string;
  options?: {
    preserveComments?: boolean;
    optimizeOutput?: boolean;
    includeMetadata?: boolean;
  };
}

export interface TranslationResponse {
  success: boolean;
  translatedCode?: string;
  warnings?: string[];
  errors?: string[];
  metadata?: {
    elementsTranslated: number;
    elementsSkipped: number;
    processingTime: number;
  };
}

// API Types
export interface APIError {
  code: string;
  message: string;
  status: number;
  details?: Record<string, unknown>;
}

export interface APIKey {
  key: string;
  name: string;
  permissions: string[];
  createdAt: string;
  lastUsed: string | null;
  status: 'active' | 'revoked';
}

// User Preferences
export interface UserPreferences {
  theme: 'light' | 'dark' | 'auto';
  editorFontSize: number;
  editorFontFamily: string;
  showMinimap: boolean;
  enableAI: boolean;
  enableRealTimeCorrections: boolean;
  autoSave: boolean;
  notifications: {
    corrections: boolean;
    translations: boolean;
    errors: boolean;
  };
}

// WebSocket Types (for real-time collaboration)
export interface WebSocketMessage {
  type: 'connection' | 'edit' | 'cursor' | 'selection' | 'correction';
  payload: unknown;
  timestamp: number;
  userId: string;
}

export interface CollaborationState {
  connected: boolean;
  users: CollaborationUser[];
  activeEditors: Map<string, EditorState>;
}

export interface CollaborationUser {
  id: string;
  name: string;
  color: string;
  cursorPosition: { line: number; column: number };
  isActive: boolean;
}
