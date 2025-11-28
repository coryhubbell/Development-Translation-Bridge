/**
 * API Client
 * Base HTTP client with authentication, error handling, and rate limiting
 */

import type { RateLimitInfo } from './types/responses';

// WordPress data injected by PHP
interface WpbcData {
  restUrl: string;
  nonce: string;
  userId: number;
  siteUrl: string;
  adminUrl: string;
  version: string;
}

declare global {
  interface Window {
    wpbcData?: WpbcData;
  }
}

// Custom Error Classes
export class ApiError extends Error {
  code: string;
  status: number;
  details?: Record<string, unknown>;

  constructor(
    code: string,
    message: string,
    status: number,
    details?: Record<string, unknown>
  ) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
    this.status = status;
    this.details = details;
  }
}

export class RateLimitError extends ApiError {
  retryAfter: number;

  constructor(retryAfter: number, message: string = 'Rate limit exceeded') {
    super('wpbc_rate_limit_exceeded', message, 429);
    this.name = 'RateLimitError';
    this.retryAfter = retryAfter;
  }
}

export class AuthenticationError extends ApiError {
  constructor(message: string = 'Authentication required') {
    super('wpbc_auth_required', message, 401);
    this.name = 'AuthenticationError';
  }
}

export class ValidationError extends ApiError {
  validationErrors: Record<string, string>;

  constructor(
    message: string,
    validationErrors: Record<string, string> = {}
  ) {
    super('wpbc_validation_error', message, 400);
    this.name = 'ValidationError';
    this.validationErrors = validationErrors;
  }
}

// Request options
interface RequestOptions {
  requestId?: string;
  timeout?: number;
  retries?: number;
  retryDelay?: number;
}

// API Client class
class ApiClient {
  private baseUrl: string;
  private nonce: string;
  private abortControllers: Map<string, AbortController> = new Map();
  private rateLimitInfo: RateLimitInfo | null = null;

  constructor() {
    this.baseUrl = window.wpbcData?.restUrl || '/wp-json/wpbc/v2/';
    this.nonce = window.wpbcData?.nonce || '';
  }

  /**
   * Get current rate limit info
   */
  getRateLimitInfo(): RateLimitInfo | null {
    return this.rateLimitInfo;
  }

  /**
   * Check if rate limited
   */
  isRateLimited(): boolean {
    if (!this.rateLimitInfo) return false;
    return this.rateLimitInfo.remaining <= 0 && Date.now() < this.rateLimitInfo.resetAt;
  }

  /**
   * Build request headers
   */
  private getHeaders(): HeadersInit {
    return {
      'Content-Type': 'application/json',
      'X-WP-Nonce': this.nonce,
    };
  }

  /**
   * Parse rate limit headers from response
   */
  private parseRateLimitHeaders(response: Response): void {
    const limit = response.headers.get('X-RateLimit-Limit');
    const remaining = response.headers.get('X-RateLimit-Remaining');
    const reset = response.headers.get('X-RateLimit-Reset');
    const retryAfter = response.headers.get('Retry-After');

    if (limit && remaining && reset) {
      this.rateLimitInfo = {
        limit: parseInt(limit, 10),
        remaining: parseInt(remaining, 10),
        resetAt: parseInt(reset, 10) * 1000, // Convert to ms
        retryAfter: retryAfter ? parseInt(retryAfter, 10) : null,
      };
    }
  }

  /**
   * Handle error responses
   */
  private async handleErrorResponse(response: Response): Promise<never> {
    let errorData: { code?: string; message?: string; data?: Record<string, unknown> };

    try {
      errorData = await response.json();
    } catch {
      errorData = { message: response.statusText };
    }

    const message = errorData.message || `HTTP ${response.status}: ${response.statusText}`;
    const code = errorData.code || 'unknown_error';

    switch (response.status) {
      case 401:
        throw new AuthenticationError(message);
      case 429:
        const retryAfter = parseInt(response.headers.get('Retry-After') || '60', 10);
        throw new RateLimitError(retryAfter, message);
      case 400:
        throw new ValidationError(message, errorData.data as Record<string, string>);
      default:
        throw new ApiError(code, message, response.status, errorData.data);
    }
  }

  /**
   * Sleep helper for retries
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Execute request with retry logic
   */
  private async executeWithRetry<T>(
    method: string,
    endpoint: string,
    data?: unknown,
    options: RequestOptions = {}
  ): Promise<T> {
    const { requestId, timeout = 30000, retries = 3, retryDelay = 1000 } = options;

    // Cancel previous request with same ID
    if (requestId) {
      this.abortControllers.get(requestId)?.abort();
      const controller = new AbortController();
      this.abortControllers.set(requestId, controller);
    }

    const controller = requestId
      ? this.abortControllers.get(requestId)
      : new AbortController();

    // Set timeout
    const timeoutId = setTimeout(() => controller?.abort(), timeout);

    let lastError: Error | null = null;

    for (let attempt = 0; attempt <= retries; attempt++) {
      try {
        const url = `${this.baseUrl}${endpoint}`;
        const fetchOptions: RequestInit = {
          method,
          headers: this.getHeaders(),
          signal: controller?.signal,
        };

        if (data && method !== 'GET') {
          fetchOptions.body = JSON.stringify(data);
        }

        const response = await fetch(url, fetchOptions);

        // Parse rate limit headers
        this.parseRateLimitHeaders(response);

        if (!response.ok) {
          await this.handleErrorResponse(response);
        }

        clearTimeout(timeoutId);

        // Clean up abort controller
        if (requestId) {
          this.abortControllers.delete(requestId);
        }

        return await response.json();
      } catch (error) {
        lastError = error as Error;

        // Don't retry on certain errors
        if (
          error instanceof AuthenticationError ||
          error instanceof ValidationError ||
          (error as Error).name === 'AbortError'
        ) {
          throw error;
        }

        // Retry on rate limit with proper delay
        if (error instanceof RateLimitError) {
          if (attempt < retries) {
            await this.sleep(error.retryAfter * 1000);
            continue;
          }
          throw error;
        }

        // Exponential backoff for other errors
        if (attempt < retries) {
          await this.sleep(retryDelay * Math.pow(2, attempt));
          continue;
        }
      }
    }

    clearTimeout(timeoutId);
    throw lastError || new Error('Request failed after retries');
  }

  /**
   * GET request
   */
  async get<T>(endpoint: string, options?: RequestOptions): Promise<T> {
    return this.executeWithRetry<T>('GET', endpoint, undefined, options);
  }

  /**
   * POST request
   */
  async post<T>(endpoint: string, data?: unknown, options?: RequestOptions): Promise<T> {
    return this.executeWithRetry<T>('POST', endpoint, data, options);
  }

  /**
   * PUT request
   */
  async put<T>(endpoint: string, data?: unknown, options?: RequestOptions): Promise<T> {
    return this.executeWithRetry<T>('PUT', endpoint, data, options);
  }

  /**
   * DELETE request
   */
  async delete<T>(endpoint: string, options?: RequestOptions): Promise<T> {
    return this.executeWithRetry<T>('DELETE', endpoint, undefined, options);
  }

  /**
   * Cancel a pending request
   */
  cancelRequest(requestId: string): void {
    this.abortControllers.get(requestId)?.abort();
    this.abortControllers.delete(requestId);
  }
}

// Singleton instance
export const apiClient = new ApiClient();

export default apiClient;
