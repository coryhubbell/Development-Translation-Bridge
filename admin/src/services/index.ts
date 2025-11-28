/**
 * Services Layer
 * Centralized API communication for the Visual Interface
 */

// Core client and error classes
export {
  apiClient,
  ApiError,
  RateLimitError,
  AuthenticationError,
  ValidationError,
} from './api-client';

// Domain services
export { translationService } from './translation.service';
export { persistenceService } from './persistence.service';
export { correctionService } from './correction.service';

// Type exports
export type * from './types/requests';
export type * from './types/responses';
