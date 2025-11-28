/**
 * Persistence Service
 * Handles save/load operations for translations
 */

import { apiClient } from './api-client';
import type {
  SaveTranslationRequest,
  UpdateTranslationRequest,
  ListTranslationsRequest,
} from './types/requests';
import type {
  SaveTranslationResponse,
  TranslationRecord,
  TranslationHistoryResponse,
  PreferencesResponse,
} from './types/responses';
import type { UserPreferences } from '@/types';

class PersistenceService {
  /**
   * Save a new translation
   */
  async saveTranslation(request: SaveTranslationRequest): Promise<SaveTranslationResponse> {
    return apiClient.post<SaveTranslationResponse>('save', request);
  }

  /**
   * Load a specific translation by ID
   */
  async loadTranslation(id: number): Promise<TranslationRecord> {
    return apiClient.get<TranslationRecord>(`translations/${id}`);
  }

  /**
   * Update an existing translation
   */
  async updateTranslation(id: number, request: UpdateTranslationRequest): Promise<SaveTranslationResponse> {
    return apiClient.put<SaveTranslationResponse>(`translations/${id}`, request);
  }

  /**
   * Delete a translation
   */
  async deleteTranslation(id: number): Promise<{ success: boolean }> {
    return apiClient.delete<{ success: boolean }>(`translations/${id}`);
  }

  /**
   * Get translation history for current user
   */
  async getHistory(request: ListTranslationsRequest = {}): Promise<TranslationHistoryResponse> {
    const params = new URLSearchParams();

    if (request.page) params.append('page', request.page.toString());
    if (request.per_page) params.append('per_page', request.per_page.toString());
    if (request.status) params.append('status', request.status);
    if (request.source_framework) params.append('source_framework', request.source_framework);
    if (request.target_framework) params.append('target_framework', request.target_framework);

    const queryString = params.toString();
    const endpoint = queryString ? `translations/history?${queryString}` : 'translations/history';

    return apiClient.get<TranslationHistoryResponse>(endpoint);
  }

  /**
   * Get version history for a specific translation
   */
  async getVersions(translationId: number): Promise<TranslationRecord[]> {
    return apiClient.get<TranslationRecord[]>(`translations/${translationId}/versions`);
  }

  /**
   * Restore a previous version
   */
  async restoreVersion(translationId: number, versionId: number): Promise<SaveTranslationResponse> {
    return apiClient.post<SaveTranslationResponse>(`translations/${translationId}/restore`, {
      version_id: versionId,
    });
  }

  /**
   * Save user preferences
   */
  async savePreferences(preferences: Partial<UserPreferences>): Promise<PreferencesResponse> {
    return apiClient.post<PreferencesResponse>('preferences', { preferences });
  }

  /**
   * Load user preferences
   */
  async loadPreferences(): Promise<PreferencesResponse> {
    return apiClient.get<PreferencesResponse>('preferences');
  }

  /**
   * Auto-save helper with debounce tracking
   */
  private autoSaveTimeoutId: ReturnType<typeof setTimeout> | null = null;
  private lastAutoSaveData: string | null = null;

  /**
   * Debounced auto-save to backend
   * Only saves if data has changed
   */
  autoSave(
    request: SaveTranslationRequest,
    options: { delay?: number; onSave?: (result: SaveTranslationResponse) => void } = {}
  ): void {
    const { delay = 30000, onSave } = options;
    const dataHash = JSON.stringify(request);

    // Skip if data hasn't changed
    if (dataHash === this.lastAutoSaveData) {
      return;
    }

    // Clear existing timeout
    if (this.autoSaveTimeoutId) {
      clearTimeout(this.autoSaveTimeoutId);
    }

    // Set new timeout
    this.autoSaveTimeoutId = setTimeout(async () => {
      try {
        const result = await this.saveTranslation({
          ...request,
          metadata: { ...request.metadata, autoSave: true },
        });
        this.lastAutoSaveData = dataHash;
        if (onSave) {
          onSave(result);
        }
      } catch (error) {
        console.error('Auto-save failed:', error);
      }
    }, delay);
  }

  /**
   * Cancel pending auto-save
   */
  cancelAutoSave(): void {
    if (this.autoSaveTimeoutId) {
      clearTimeout(this.autoSaveTimeoutId);
      this.autoSaveTimeoutId = null;
    }
  }
}

// Singleton instance
export const persistenceService = new PersistenceService();

export default persistenceService;
