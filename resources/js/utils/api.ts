/**
 * Get CSRF token from meta tag
 */
export function getCsrfToken(): string {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token || '';
}

/**
 * Create headers with CSRF token
 */
export function createApiHeaders(additionalHeaders: Record<string, string> = {}): Record<string, string> {
    return {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Content-Type': 'application/json',
        ...additionalHeaders,
    };
}

/**
 * Fetch wrapper that automatically includes CSRF token
 */
export async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = createApiHeaders(options.headers as Record<string, string>);
    
    return fetch(url, {
        ...options,
        headers,
    });
} 