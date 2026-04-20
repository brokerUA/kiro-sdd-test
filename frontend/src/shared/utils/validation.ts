/**
 * Validates an email address format.
 */
export function isValidEmail(s: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
}

/**
 * Validates a password against the policy (mirrors backend PasswordPolicy).
 * Returns an array of violation messages; empty array means valid.
 */
export function validatePassword(s: string): string[] {
  const violations: string[] = [];

  if (s.length < 8) {
    violations.push('Password must be at least 8 characters');
  }
  if (!/[A-Z]/.test(s)) {
    violations.push('Password must contain at least one uppercase letter');
  }
  if (!/[a-z]/.test(s)) {
    violations.push('Password must contain at least one lowercase letter');
  }
  if (!/[0-9]/.test(s)) {
    violations.push('Password must contain at least one digit');
  }
  if (!/[^A-Za-z0-9]/.test(s)) {
    violations.push('Password must contain at least one special character');
  }

  return violations;
}
