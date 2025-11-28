/**
 * Translation Service
 * Handles all translation-related API calls
 */

import { apiClient } from './api-client';
import type {
  TranslateRequest,
  BatchTranslateRequest,
  ValidateRequest,
} from './types/requests';
import type {
  TranslateResponse,
  BatchTranslateResponse,
  JobStatusResponse,
  ValidateResponse,
  FrameworksResponse,
  StatusResponse,
} from './types/responses';

class TranslationService {
  /**
   * Translate code from one framework to another
   */
  async translate(request: TranslateRequest): Promise<TranslateResponse> {
    return apiClient.post<TranslateResponse>('translate', request, {
      requestId: 'translate',
      timeout: 60000, // 1 minute for complex translations
    });
  }

  /**
   * Batch translate to multiple frameworks
   * Returns immediately if async, otherwise waits for results
   */
  async batchTranslate(request: BatchTranslateRequest): Promise<BatchTranslateResponse | JobStatusResponse> {
    const response = await apiClient.post<BatchTranslateResponse | { job_id: string }>(
      'batch-translate',
      request,
      { timeout: 120000 } // 2 minutes for batch
    );

    // If async mode, the response contains a job_id
    if ('job_id' in response && typeof response.job_id === 'string') {
      return this.getJobStatus(response.job_id);
    }

    return response as BatchTranslateResponse;
  }

  /**
   * Get async job status
   */
  async getJobStatus(jobId: string): Promise<JobStatusResponse> {
    return apiClient.get<JobStatusResponse>(`job/${jobId}`);
  }

  /**
   * Poll job status until complete or failed
   */
  async pollJobStatus(
    jobId: string,
    options: {
      interval?: number;
      maxAttempts?: number;
      onProgress?: (status: JobStatusResponse) => void;
    } = {}
  ): Promise<JobStatusResponse> {
    const { interval = 2000, maxAttempts = 60, onProgress } = options;
    let attempts = 0;

    while (attempts < maxAttempts) {
      const status = await this.getJobStatus(jobId);

      if (onProgress) {
        onProgress(status);
      }

      if (status.status === 'completed' || status.status === 'failed') {
        return status;
      }

      attempts++;
      await new Promise((resolve) => setTimeout(resolve, interval));
    }

    throw new Error('Job polling timeout exceeded');
  }

  /**
   * Validate code for a specific framework
   */
  async validate(request: ValidateRequest): Promise<ValidateResponse> {
    return apiClient.post<ValidateResponse>('validate', request);
  }

  /**
   * Get list of all supported frameworks
   */
  async getFrameworks(): Promise<FrameworksResponse> {
    return apiClient.get<FrameworksResponse>('frameworks');
  }

  /**
   * Get API status and health check
   */
  async getStatus(): Promise<StatusResponse> {
    return apiClient.get<StatusResponse>('status');
  }
}

// Singleton instance
export const translationService = new TranslationService();

export default translationService;
