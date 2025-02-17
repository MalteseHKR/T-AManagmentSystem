"use client";

import { useState, useEffect } from 'react';
import { useRouter } from 'next/router';
import Head from 'next/head';
import api, { isAxiosError } from '../lib/axios';

// Type definitions
interface LoginResponse {
  status: string;
  token: string;
  user: {
    id: number;
    email: string;
    role: 'HR' | 'Payroll' | 'Employee';
  };
}

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const router = useRouter();

  // Clear any existing auth state on mount
  useEffect(() => {
    localStorage.removeItem('token');
    api.defaults.headers.common['Authorization'] = '';
  }, []);

  const handleLogin = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (loading) return;

    setLoading(true);
    setError('');

    try {
      // Get CSRF cookie from Laravel Sanctum
      await api.get('/sanctum/csrf-cookie');

      // Attempt login
      const response = await api.post<LoginResponse>('/api/login', {
        email,
        password
      });

      console.log('Login successful');

      // Store auth token
      const token = response.data.token;
      localStorage.setItem('token', token);
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`;

      // Redirect based on role
      const roleRoutes = {
        'HR': '/hr-dashboard',
        'Payroll': '/payroll-dashboard',
        'Employee': '/employee-dashboard'
      };

      const redirectPath = roleRoutes[response.data.user.role] || '/';
      await router.push(redirectPath);

    } catch (error) {
      console.error('Login failed:', error);
      
      if (isAxiosError(error)) {
        // Handle specific error cases
        if (!error.response) {
          setError('Unable to reach the server. Please check your connection.');
        } else if (error.response.status === 422) {
          setError('Invalid email or password.');
        } else if (error.response.status === 429) {
          setError('Too many login attempts. Please try again later.');
        } else {
          setError(error.response.data?.message || 'Login failed. Please try again.');
        }
      } else {
        setError('An unexpected error occurred.');
      }

      // Clear any partial auth state
      localStorage.removeItem('token');
      api.defaults.headers.common['Authorization'] = '';
    } finally {
      setLoading(false);
    }
  };

  const testConnection = async () => {
    try {
      const response = await api.get('/api/test-connection');
      alert('Successfully connected to backend server');
    } catch (error) {
      console.error('Connection test failed:', error);
      if (isAxiosError(error)) {
        alert(error.response?.data?.message || 'Failed to connect to server');
      } else {
        alert('Unable to reach the server');
      }
    }
  };

  return (
    <div className="min-h-screen flex flex-col bg-gray-100">
      <Head>
        <title>Login | Garrison Portal</title>
      </Head>

      <header className="bg-blue-600 text-white py-6">
        <div className="container mx-auto text-center">
          <h1 className="text-4xl font-bold">Garrison</h1>
          <p className="text-lg mt-2">Time and Attendance Web Portal</p>
        </div>
      </header>

      <main className="flex-grow container mx-auto px-4 py-8">
        <div className="max-w-md mx-auto">
          <div className="bg-white rounded-lg shadow-md p-8">
            <h2 className="text-2xl font-bold text-center mb-6">Login</h2>
            
            <form onSubmit={handleLogin} className="space-y-4">
              <div>
                <input
                  type="email"
                  placeholder="Email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value.trim())}
                  className="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                  disabled={loading}
                />
              </div>

              <div>
                <input
                  type="password"
                  placeholder="Password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full p-3 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                  disabled={loading}
                />
              </div>

              <button
                type="submit"
                className={`w-full py-3 px-4 bg-blue-600 text-white rounded font-medium
                  ${loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'}
                  transition duration-200`}
                disabled={loading}
              >
                {loading ? 'Logging in...' : 'Login'}
              </button>

              <button
                type="button"
                onClick={testConnection}
                className="w-full py-3 px-4 bg-gray-500 text-white rounded font-medium
                  hover:bg-gray-600 transition duration-200"
                disabled={loading}
              >
                Test Connection
              </button>

              {error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded">
                  <p className="text-red-600 text-sm text-center">{error}</p>
                </div>
              )}
            </form>
          </div>
        </div>
      </main>

      <footer className="bg-blue-600 text-white py-4">
        <div className="container mx-auto text-center">
          <p>&copy; {new Date().getFullYear()} Garrison. All rights reserved.</p>
        </div>
      </footer>
    </div>
  );
}