/**
 * API Response Type Definitions
 * WordPress Bootstrap Claude - Service Layer
 */

import type { Framework, CorrectionSuggestion } from '@/types';

// Base response wrapper
export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  code?: string;
}

// Translation responses
export interface TranslateResponse {
  success: boolean;
  source: Framework;
  target: Framework;
  result: string;
  elapsed_time: number;
  stats: TranslationStats;
  timestamp: string;
}

export interface TranslationStats {
  elements_processed: number;
  elements_translated: number;
  elements_skipped: number;
  warnings: string[];
}

export interface BatchTranslateResponse {
  success: boolean;
  source: Framework;
  total: number;
  successful: number;
  failed: number;
  results: Record<Framework, BatchResult>;
  elapsed_time: number;
  timestamp: string;
}

export interface BatchResult {
  success: boolean;
  result?: string;
  error?: string;
  stats?: TranslationStats;
}

// Job polling responses
export interface JobStatusResponse {
  job_id: string;
  status: 'queued' | 'processing' | 'completed' | 'failed';
  source: Framework;
  targets: Framework[];
  progress: number;
  results: Record<Framework, BatchResult>;
  created_at: string;
  updated_at: string;
}

// Validation responses
export interface ValidateResponse {
  success: boolean;
  valid: boolean;
  framework: Framework;
  component_count: number;
  component_types: Record<string, number>;
  timestamp: string;
}

// Framework responses
export interface FrameworksResponse {
  success: boolean;
  total_frameworks: number;
  translation_pairs: number;
  frameworks: Record<Framework, FrameworkInfo>;
}

export interface FrameworkInfo {
  name: string;
  type: string;
  extension: string;
  description: string;
}

// Status responses
export interface StatusResponse {
  success: boolean;
  version: string;
  status: 'operational' | 'degraded' | 'down';
  features: Record<string, boolean>;
  timestamp: string;
}

// Persistence responses
export interface SaveTranslationResponse {
  success: boolean;
  translation_id: number;
  version: number;
  saved_at: string;
}

export interface TranslationRecord {
  id: number;
  user_id: number;
  project_id: string | null;
  source_framework: Framework;
  target_framework: Framework;
  source_code: string;
  translated_code: string;
  metadata: Record<string, unknown>;
  version: number;
  parent_id: number | null;
  status: 'draft' | 'saved' | 'archived';
  created_at: string;
  updated_at: string;
}

export interface TranslationHistoryResponse {
  success: boolean;
  translations: TranslationSummary[];
  total: number;
  page: number;
  per_page: number;
}

export interface TranslationSummary {
  id: number;
  source_framework: Framework;
  target_framework: Framework;
  name: string;
  version: number;
  status: 'draft' | 'saved' | 'archived';
  created_at: string;
  updated_at: string;
}

// Correction responses
export interface CorrectionsResponse {
  success: boolean;
  corrections: CorrectionSuggestion[];
  summary: CorrectionSummary;
  processing_time: number;
}

export interface CorrectionSummary {
  total: number;
  errors: number;
  warnings: number;
  info: number;
  enhancements: number;
}

export interface ApplyCorrectionResponse {
  success: boolean;
  applied: boolean;
  updated_code?: string;
}

// User preferences responses
export interface PreferencesResponse {
  success: boolean;
  preferences: Record<string, unknown>;
}

// Rate limit info (parsed from headers)
export interface RateLimitInfo {
  limit: number;
  remaining: number;
  resetAt: number;
  retryAfter: number | null;
}
