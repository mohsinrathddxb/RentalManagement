import { useState } from 'react';
import { Menu, X, Home, Building2, Grid3x3, Users, DollarSign, AlertCircle, TrendingUp, FileText, LogOut } from 'lucide-react';

export default function Sidebar() {
  const [isOpen, setIsOpen] = useState(false);

  const menuItems = [
    { icon: Home, label: 'Dashboard', href: '#' },
    { icon: Building2, label: 'Properties', href: '#' },
    { icon: Grid3x3, label: 'Partitions', href: '#' },
    { icon: Users, label: 'Tenants', href: '#' },
    { icon: DollarSign, label: 'Rent', href: '#' },
    { icon: AlertCircle, label: 'Complaints', href: '#' },
    { icon: TrendingUp, label: 'Expenses', href: '#' },
    { icon: FileText, label: 'Reports', href: '#' },
  ];

  return (
    <>
      {/* Mobile Menu Button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="fixed top-4 left-4 z-50 md:hidden bg-slate-800 text-white p-2 rounded-lg border border-slate-700 hover:bg-slate-700 transition-colors"
      >
        {isOpen ? <X size={24} /> : <Menu size={24} />}
      </button>

      {/* Mobile Overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden"
          onClick={() => setIsOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed left-0 top-0 h-screen w-64 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 border-r border-slate-700 border-opacity-50 transition-transform duration-300 ease-in-out z-40 md:translate-x-0 ${
          isOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Sidebar Header */}
        <div className="flex items-center justify-between h-20 px-6 border-b border-slate-700 border-opacity-50">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-lg">PM</span>
            </div>
            <div className="hidden sm:block">
              <h1 className="text-white font-bold text-sm">Partition Mgmt</h1>
              <p className="text-slate-400 text-xs">Dubai Rentals</p>
            </div>
          </div>
        </div>

        {/* Navigation Menu */}
        <nav className="flex-1 overflow-y-auto px-3 py-6">
          <ul className="space-y-2">
            {menuItems.map((item) => (
              <li key={item.label}>
                <a
                  href={item.href}
                  className="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 hover:bg-opacity-50 transition-all duration-200 group"
                >
                  <item.icon
                    size={20}
                    className="text-slate-400 group-hover:text-blue-400 transition-colors"
                  />
                  <span className="font-medium text-sm">{item.label}</span>
                </a>
              </li>
            ))}
          </ul>
        </nav>

        {/* Sidebar Footer */}
        <div className="border-t border-slate-700 border-opacity-50 p-4">
          {/* User Profile Card */}
          <div className="bg-slate-700 bg-opacity-30 border border-slate-600 border-opacity-50 rounded-lg p-4 mb-4">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-10 h-10 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-xs">A</span>
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-white text-sm font-semibold truncate">Ahmed Khan</p>
                <p className="text-slate-400 text-xs truncate">admin@partitions.ae</p>
              </div>
            </div>
            <div className="w-full h-1 bg-slate-600 rounded-full overflow-hidden">
              <div className="h-full w-3/4 bg-gradient-to-r from-blue-400 to-indigo-600"></div>
            </div>
            <p className="text-slate-400 text-xs mt-2">Account Status: Active</p>
          </div>

          {/* Logout Button */}
          <button className="w-full flex items-center gap-3 px-4 py-3 rounded-lg bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 text-red-400 hover:bg-red-500 hover:bg-opacity-20 hover:border-red-500 transition-all duration-200 font-medium text-sm group">
            <LogOut size={18} className="group-hover:scale-110 transition-transform" />
            <span>Logout</span>
          </button>
        </div>
      </aside>

      {/* Mobile Sidebar Close on md and up - handled by translate-x-0 */}
      {isOpen && (
        <div
          className="fixed inset-0 z-20 md:hidden"
          onClick={() => setIsOpen(false)}
        />
      )}
    </>
  );
}
