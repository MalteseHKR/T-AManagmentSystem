import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from 'axios';

// Define API error interface
interface ApiError {
    message?: string;
    error?: string;
}

// Create axios instance with proper configuration
const api: AxiosInstance = axios.create({
    baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true // Required for CSRF/Session cookies
});

// Request interceptor
api.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        // Log request for debugging
        console.log('Making request to:', config.url);
        
        // Get token from localStorage
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        
        return config;
    },
    (error) => {
        console.error('Request error:', error);
        return Promise.reject(error);
    }
);

// Response interceptor
api.interceptors.response.use(
    response => response,
    (error: unknown) => {
        if (axios.isAxiosError(error)) {
            const axiosError = error as AxiosError<ApiError>;
            
            // Handle authentication errors
            if (axiosError.response?.status === 401) {
                localStorage.removeItem('token');
                window.location.href = '/login';
            }
            
            console.error('API Error:', {
                status: axiosError.response?.status,
                data: axiosError.response?.data,
                message: axiosError.message
            });
        }
        return Promise.reject(error);
    }
);

// Helper function to get CSRF token
export const getCsrfToken = async () => {
    try {
        await api.get('/sanctum/csrf-cookie');
        console.log('CSRF token fetched');
    } catch (error) {
        console.error('CSRF token fetch failed:', error);
        throw error;
    }
};

// Helper function to set auth token
export const setAuthToken = (token: string | null) => {
    if (token) {
        localStorage.setItem('token', token);
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        localStorage.removeItem('token');
        delete api.defaults.headers.common['Authorization'];
    }
};

export { isAxiosError } from 'axios';
export type { ApiError };
export default api;