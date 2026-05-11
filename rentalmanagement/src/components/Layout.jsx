import Sidebar from './Sidebar';

export default function Layout({ children }) {
  return (
    <div className="flex h-screen bg-slate-900">
      {/* Sidebar */}
      <Sidebar />

      {/* Main Content Area */}
      <main className="flex-1 ml-0 md:ml-64 overflow-hidden">
        {/* Content */}
        <div className="h-full overflow-y-auto">
          {children}
        </div>
      </main>
    </div>
  );
}
