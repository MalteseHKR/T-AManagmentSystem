import Head from 'next/head';

export default function EmployeeDashboard() {
  return (
    <div className="flex flex-col items-center justify-center min-h-screen py-2 bg-gray-100">
      <Head>
        <title>Employee Dashboard | Web Portal</title>
      </Head>
      <header className="w-full py-4 bg-blue-600 text-white text-center">
        <h1 className="text-4xl font-bold">Employee Dashboard</h1>
      </header>
      <main className="flex flex-col items-center justify-center w-full flex-1 px-20 text-center">
        <h2 className="text-3xl font-bold mb-8">Welcome to the Employee Dashboard</h2>
        {/* Add Employee-specific content here */}
      </main>
      <footer className="w-full py-4 bg-blue-600 text-white text-center">
        <p>&copy; 2025 Garrison. All rights reserved.</p>
      </footer>
    </div>
  );
}