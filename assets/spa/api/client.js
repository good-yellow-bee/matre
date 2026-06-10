export class ApiError extends Error {
  constructor(message, status, payload) {
    super(message);
    this.status = status;
    this.payload = payload;
  }
}

let csrfToken = null;
let onUnauthorized = null;

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler;
}

function getCsrfToken() {
  if (!csrfToken) {
    // crypto.randomUUID is unavailable in non-HTTPS contexts; getRandomValues always works
    csrfToken = Array.from(crypto.getRandomValues(new Uint8Array(24)), (b) => b.toString(16).padStart(2, '0')).join('');
  }
  return csrfToken;
}

async function request(method, url, { json, params, headers: extraHeaders } = {}) {
  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
    ...extraHeaders,
  };
  if (json !== undefined) headers['Content-Type'] = 'application/json';
  if (method !== 'GET') headers['X-CSRF-Token'] = getCsrfToken();

  let fullUrl = url;
  if (params) {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null && value !== '') search.set(key, value);
    }
    const qs = search.toString();
    if (qs) fullUrl += (url.includes('?') ? '&' : '?') + qs;
  }

  const response = await fetch(fullUrl, {
    method,
    headers,
    credentials: 'same-origin',
    body: json !== undefined ? JSON.stringify(json) : undefined,
  });

  const isJson = (response.headers.get('Content-Type') || '').includes('application/json');
  const payload = isJson ? await response.json() : null;

  if (response.status === 401 && !url.startsWith('/api/me') && !url.startsWith('/api/login') && !url.startsWith('/2fa_check')) {
    onUnauthorized?.(payload);
  }

  if (!response.ok) {
    const message = payload?.error || payload?.message || `Request failed (${response.status})`;
    throw new ApiError(message, response.status, payload);
  }

  return payload;
}

export const api = {
  get: (url, options) => request('GET', url, options),
  post: (url, json, options) => request('POST', url, { ...options, json }),
  put: (url, json, options) => request('PUT', url, { ...options, json }),
  patch: (url, json, options) => request('PATCH', url, { ...options, json }),
  delete: (url, options) => request('DELETE', url, options),
};
