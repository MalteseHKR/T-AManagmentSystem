"use client";

import { useEffect, useState } from 'react';
import axios from 'axios';
import Image from "next/image";
import { useRouter } from 'next/navigation';

export default function Welcome() {
  interface User {
    role: 'HR' | 'Employee' | 'Payroll';
    // Add other user properties if needed
  }

  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  useEffect(() => {
    // Fetch user data from the backend
    const fetchUserData = async () => {
      try {
        const response = await axios.get('http://localhost:8000/api/user', {
          withCredentials: true, // Include cookies for authentication
        });
        setUser(response.data as User);
      } catch (error) {
        console.error('Error fetching user data:', error);
      }
    };

    fetchUserData();
  }, []);

  const handleRedirect = (path: string) => {
    setLoading(true);
    setTimeout(() => {
      router.push(path);
    }, 1500); // 1.5-second delay
  };

  const renderContent = () => {
    if (!user) {
      return null;
    }

    switch (user.role) {
      case 'HR':
        return (
          <div>
            <h2 className="text-3xl font-bold mt-8">HR Dashboard</h2>
            <p className="mt-4 text-xl">View and manage all employees' statistics.</p>
          </div>
        );
      case 'Employee':
        return (
          <div>
            <h2 className="text-3xl font-bold mt-8">Employee Dashboard</h2>
            <p className="mt-4 text-xl">View your own attendance and statistics.</p>
          </div>
        );
      case 'Payroll':
        return (
          <div>
            <h2 className="text-3xl font-bold mt-8">Payroll Dashboard</h2>
            <p className="mt-4 text-xl">Calculate and manage wages.</p>
          </div>
        );
      default:
        return <p>Unauthorized access</p>;
    }
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-screen py-2 bg-gray-100">
      <header className="w-full py-4 bg-blue-600 text-white text-center">
        <Image
          src="/image/garrison.svg"
          alt="Garrison Logo"
          width={100}
          height={100}
        />
        <h1 className="text-4xl font-bold">Garrison</h1>
        <p className="text-lg">Time and Attendance Web Portal</p>
      </header>
      <main className="flex flex-col items-center justify-center w-full flex-1 px-20 text-center">
        {loading && (
          <div className="loading">
            Loading<span className="dot">.</span><span className="dot">.</span><span className="dot">.</span>
          </div>
        )}
        {renderContent()}
        <div className="flex flex-wrap items-center justify-around max-w-4xl mt-6 sm:w-full">
          <button
            onClick={() => handleRedirect('/login')}
            className="p-6 mt-6 text-left border w-96 rounded-xl hover:text-blue-600 focus:text-blue-600"
          >
            <h3 className="text-2xl font-bold">Login &rarr;</h3>
            <p className="mt-4 text-xl">
              Access your account and manage your attendance.
            </p>
          </button>

          <button
            onClick={() => handleRedirect('/register')}
            className="p-6 mt-6 text-left border w-96 rounded-xl hover:text-blue-600 focus:text-blue-600"
          >
            <h3 className="text-2xl font-bold">Register &rarr;</h3>
            <p className="mt-4 text-xl">
              Create a new account to start using Garrison.
            </p>
          </button>
        </div>
      </main>
      <footer className="w-full py-4 bg-blue-600 text-white text-center">
        <p>&copy; 2025 Garrison. All rights reserved.</p>
      </footer>
      <style jsx>{`
        .loading {
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 1.5rem;
          margin-top: 1rem;
        }
        .dot {
          animation: jump 1s infinite;
        }
        .dot:nth-child(2) {
          animation-delay: 0.2s;
        }
        .dot:nth-child(3) {
          animation-delay: 0.4s;
        }
        @keyframes jump {
          0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
          }
          40% {
            transform: translateY(-10px);
          }
          60% {
            transform: translateY(-5px);
          }
        }
      `}</style>
    </div>
  );
}
