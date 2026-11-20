import axios from 'axios';
import type { AxiosRequestConfig, AxiosResponse } from 'axios';

const csrfCookieEndpoint = '/sanctum/csrf-cookie';
let csrfCookiePromise: Promise<void> | null = null;

const shouldEnsureCsrfCookie = (url?: string): boolean => {
    if (!url) {
        return false;
    }

    if (url === csrfCookieEndpoint) {
        return false;
    }

    return url.startsWith('/api');
};

export const ensureSanctumCsrfCookie = async (): Promise<void> => {
    if (csrfCookiePromise) {
        await csrfCookiePromise;
        return;
    }

    csrfCookiePromise = axios.get(csrfCookieEndpoint).then(() => undefined);
    await csrfCookiePromise;
};

export type ApiRequestConfig = AxiosRequestConfig;

export const apiClient = {
    get: <T = unknown>(url: string, config?: ApiRequestConfig): Promise<AxiosResponse<T>> =>
        axios.get<T>(url, config),
    post: <T = unknown>(
        url: string,
        data?: unknown,
        config?: ApiRequestConfig
    ): Promise<AxiosResponse<T>> => axios.post<T>(url, data, config),
    put: <T = unknown>(
        url: string,
        data?: unknown,
        config?: ApiRequestConfig
    ): Promise<AxiosResponse<T>> => axios.put<T>(url, data, config),
    patch: <T = unknown>(
        url: string,
        data?: unknown,
        config?: ApiRequestConfig
    ): Promise<AxiosResponse<T>> => axios.patch<T>(url, data, config),
    delete: <T = unknown>(url: string, config?: ApiRequestConfig): Promise<AxiosResponse<T>> =>
        axios.delete<T>(url, config),
};

// Configure axios to include CSRF token from cookie
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

axios.interceptors.request.use(async (config) => {
    if (shouldEnsureCsrfCookie(config.url)) {
        await ensureSanctumCsrfCookie();
    }
    return config;
});

export default axios;
