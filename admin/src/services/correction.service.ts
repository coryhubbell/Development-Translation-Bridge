/**
 * Correction Service
 * Handles code analysis and correction suggestions
 */

import { apiClient } from './api-client';
import type {
  AnalyzeCorrectionsRequest,
  ApplyCorrectionRequest,
  DismissCorrectionRequest,
} from './types/requests';
import type {
  CorrectionsResponse,
  ApplyCorrectionResponse,
} from './types/responses';

class CorrectionService {
  /**
   * Analyze code and get correction suggestions
   */
  async analyze(request: AnalyzeCorrectionsRequest): Promise<CorrectionsResponse> {
    return apiClient.post<CorrectionsResponse>('corrections/analyze', request, {
      requestId: 'corrections-analyze', // Cancel previous analysis if new one starts
      timeout: request.options?.aiEnabled ? 60000 : 15000, // Longer timeout for AI
    });
  }

  /**
   * Quick rule-based analysis (no AI)
   */
  async quickCheck(code: string, framework: string): Promise<CorrectionsResponse> {
    return this.analyze({
      code,
      framework: framework as AnalyzeCorrectionsRequest['framework'],
      options: {
        aiEnabled: false,
        checkAccessibility: true,
        checkBestPractices: true,
      },
    });
  }

  /**
   * Full AI-powered analysis
   */
  async aiAnalyze(code: string, framework: string): Promise<CorrectionsResponse> {
    return this.analyze({
      code,
      framework: framework as AnalyzeCorrectionsRequest['framework'],
      options: {
        aiEnabled: true,
        checkAccessibility: true,
        checkBestPractices: true,
      },
    });
  }

  /**
   * Apply a correction to the code
   */
  async applyCorrection(request: ApplyCorrectionRequest): Promise<ApplyCorrectionResponse> {
    return apiClient.post<ApplyCorrectionResponse>('corrections/apply', request);
  }

  /**
   * Dismiss a correction (won't be suggested again for this session)
   */
  async dismissCorrection(request: DismissCorrectionRequest): Promise<{ success: boolean }> {
    return apiClient.post<{ success: boolean }>('corrections/dismiss', request);
  }

  /**
   * Debounced analysis for real-time checking
   */
  private analysisTimeoutId: ReturnType<typeof setTimeout> | null = null;

  /**
   * Debounced real-time correction checking
   */
  realtimeCheck(
    code: string,
    framework: string,
    options: {
      delay?: number;
      onResult?: (result: CorrectionsResponse) => void;
      onError?: (error: Error) => void;
    } = {}
  ): void {
    const { delay = 500, onResult, onError } = options;

    // Clear existing timeout
    if (this.analysisTimeoutId) {
      clearTimeout(this.analysisTimeoutId);
    }

    // Skip empty code
    if (!code.trim()) {
      return;
    }

    // Set new timeout
    this.analysisTimeoutId = setTimeout(async () => {
      try {
        const result = await this.quickCheck(code, framework);
        if (onResult) {
          onResult(result);
        }
      } catch (error) {
        if (onError) {
          onError(error as Error);
        }
      }
    }, delay);
  }

  /**
   * Cancel pending real-time check
   */
  cancelRealtimeCheck(): void {
    if (this.analysisTimeoutId) {
      clearTimeout(this.analysisTimeoutId);
      this.analysisTimeoutId = null;
    }
    // Also cancel any in-flight request
    apiClient.cancelRequest('corrections-analyze');
  }
}

// Singleton instance
export const correctionService = new CorrectionService();

export default correctionService;
