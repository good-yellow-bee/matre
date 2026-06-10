const SENSITIVE_NAME = /(password|secret|token|key|auth|pass)/i;

export function isSensitiveName(name) {
  return SENSITIVE_NAME.test(name || '');
}

/** baseUrl may embed basic-auth credentials — never render the raw value. */
export function stripCredentials(url) {
  if (!url) return '';
  try {
    const parsed = new URL(url);
    return parsed.origin + parsed.pathname;
  } catch {
    return url.replace(/^([a-z][a-z0-9+.-]*:\/\/)[^@/]*@/i, '$1');
  }
}

export function formatDateTime(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}
