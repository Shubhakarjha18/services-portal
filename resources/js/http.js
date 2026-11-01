/**
 * Thin fetch wrapper for the stateless /api/v1 surface. The public tracker uses
 * it anonymously; a token obtained from POST /api/v1/auth/login is sent as a
 * bearer header when present.
 */
export async function api(path, { method = 'GET', body = null, token = null } = {}) {
    const headers = { Accept: 'application/json' };

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`/api/v1/${path.replace(/^\//, '')}`, {
        method,
        headers,
        body: body === null ? undefined : JSON.stringify(body),
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));

    return { ok: response.ok, status: response.status, ...payload };
}
