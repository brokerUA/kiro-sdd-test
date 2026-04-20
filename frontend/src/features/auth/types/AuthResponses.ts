export interface SessionTokenResponse {
  session_token: string;
}

export interface ValidationErrorResponse {
  error: 'VALIDATION_ERROR';
  fields?: string[];
  message?: string;
}

export interface AccountLockedResponse {
  error: 'ACCOUNT_LOCKED';
  retry_after_seconds: number;
}
