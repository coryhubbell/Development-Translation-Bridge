/**
 * API Request Type Definitions
 * WordPress Bootstrap Claude - Service Layer
 */

import type { Framework, UserPreferences } from '@/types';

// Translation requests
export interface TranslateRequest {
  source: Framework;
  target: Framework;
  content: string;
  options?: TranslateOptions;
}

export interface TranslateOptions {
  preserveComments?: boolean;
  optimizeOutput?: boolean;
  includeMetadata?: boolean;
}

export interface BatchTranslateRequest {
  source: Framework;
  targets: Framework[];
  content: string;
  async?: boolean;
}

// Validation requests
export interface ValidateRequest {
  framework: Framework;
  content: string;
}

// Persistence requests
export interface SaveTranslationRequest {
  source_framework: Framework;
  target_framework: Framework;
  source_code: string;
  translated_code: string;
  project_id?: string;
  name?: string;
  metadata?: Record<string, unknown>;
}

export interface UpdateTranslationRequest {
  source_code?: string;
  translated_code?: string;
  name?: string;
  metadata?: Record<string, unknown>;
  create_version?: boolean;
}

export interface ListTranslationsRequest {
  page?: number;
  per_page?: number;
  status?: 'draft' | 'saved' | 'archived';
  source_framework?: Framework;
  target_framework?: Framework;
}

// Correction requests
export interface AnalyzeCorrectionsRequest {
  code: string;
  framework: Framework;
  context?: 'source' | 'translated';
  options?: CorrectionOptions;
}

export interface CorrectionOptions {
  aiEnabled?: boolean;
  checkAccessibility?: boolean;
  checkBestPractices?: boolean;
  maxSuggestions?: number;
}

export interface ApplyCorrectionRequest {
  translation_id?: number;
  correction_id: string;
}

export interface DismissCorrectionRequest {
  correction_id: string;
  feedback?: string;
}

// Preferences requests
export interface SavePreferencesRequest {
  preferences: Partial<UserPreferences>;
}
