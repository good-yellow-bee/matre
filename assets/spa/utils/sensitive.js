const SENSITIVE_NAME = /(password|secret|token|key|auth|pass)/i;

export function isSensitiveName(name) {
  return SENSITIVE_NAME.test(name || '');
}
