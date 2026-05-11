import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, LineChart, Line, PieChart, Pie, Cell } from 'recharts';
import { TrendingUp, Users, Building2, DollarSign, AlertCircle, Calendar } from 'lucide-react';

export default function Dashboard() {
  // Sample data for charts
  const revenueData = [
    { month: 'Jan', revenue: 12000, expenses: 3000 },
    { month: 'Feb', revenue: 15000, expenses: 3500 },
    { month: 'Mar', revenue: 13000, expenses: 3200 },
    { month: 'Apr', revenue: 18000, expenses: 4000 },
    { month: 'May', revenue: 22000, expenses: 4500 },
    { month: 'Jun', revenue: 20000, expenses: 4200 },
  ];

  const occupancyData = [
    { name: 'Occupied', value: 45, fill: '#3b82f6' },
    { name: 'Vacant', value: 15, fill: '#94a3b8' },
  ];

  const stats = [
    {
      title: 'Total Properties',
      value: '12',
      icon: Building2,
      color: 'from-blue-500 to-blue-600',
      bg: 'bg-blue-50',
    },
    {
      title: 'Active Tenants',
      value: '45',
      icon: Users,
      color: 'from-purple-500 to-purple-600',
      bg: 'bg-purple-50',
    },
    {
      title: 'Monthly Revenue',
      value: 'AED 98K',
      icon: DollarSign,
      color: 'from-green-500 to-green-600',
      bg: 'bg-green-50',
    },
    {
      title: 'Pending Issues',
      value: '8',
      icon: AlertCircle,
      color: 'from-red-500 to-red-600',
      bg: 'bg-red-50',
    },
  ];

  return (
    <div className="p-6 md:p-8 space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-4xl font-bold text-white mb-2">Dashboard</h1>
          <p className="text-slate-400 flex items-center gap-2">
            <Calendar size={18} />
            Welcome back! Here's your property management overview.
          </p>
        </div>
        <button className="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold py-2 px-6 rounded-lg transition-all duration-200 hover:shadow-lg">
          Generate Report
        </button>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat) => (
          <div
            key={stat.title}
            className="bg-slate-800 bg-opacity-50 backdrop-blur-sm border border-slate-700 border-opacity-50 rounded-xl p-6 hover:border-slate-600 transition-all duration-200 group"
          >
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-slate-400 text-sm font-medium">{stat.title}</h3>
              <div className={`bg-gradient-to-br ${stat.color} p-3 rounded-lg shadow-lg group-hover:scale-110 transition-transform`}>
                <stat.icon size={20} className="text-white" />
              </div>
            </div>
            <p className="text-3xl font-bold text-white mb-2">{stat.value}</p>
            <p className="text-xs text-slate-500">
              <TrendingUp size={14} className="inline mr-1 text-green-400" />
              +12% from last month
            </p>
          </div>
        ))}
      </div>

      {/* Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Revenue Chart */}
        <div className="lg:col-span-2 bg-slate-800 bg-opacity-50 backdrop-blur-sm border border-slate-700 border-opacity-50 rounded-xl p-6">
          <h2 className="text-white text-lg font-semibold mb-6">Revenue vs Expenses</h2>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={revenueData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#334155" opacity={0.3} />
              <XAxis stroke="#94a3b8" />
              <YAxis stroke="#94a3b8" />
              <Tooltip
                contentStyle={{
                  backgroundColor: '#1e293b',
                  border: '1px solid #475569',
                  borderRadius: '0.5rem',
                }}
                labelStyle={{ color: '#e2e8f0' }}
              />
              <Legend wrapperStyle={{ paddingTop: '1rem' }} />
              <Bar dataKey="revenue" fill="#3b82f6" radius={[8, 8, 0, 0]} />
              <Bar dataKey="expenses" fill="#f43f5e" radius={[8, 8, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Occupancy Chart */}
        <div className="bg-slate-800 bg-opacity-50 backdrop-blur-sm border border-slate-700 border-opacity-50 rounded-xl p-6 flex flex-col">
          <h2 className="text-white text-lg font-semibold mb-6">Occupancy Rate</h2>
          <div className="flex-1 flex items-center justify-center">
            <ResponsiveContainer width="100%" height={250}>
              <PieChart>
                <Pie
                  data={occupancyData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={100}
                  paddingAngle={5}
                  dataKey="value"
                >
                  {occupancyData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.fill} />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    backgroundColor: '#1e293b',
                    border: '1px solid #475569',
                    borderRadius: '0.5rem',
                  }}
                  labelStyle={{ color: '#e2e8f0' }}
                />
              </PieChart>
            </ResponsiveContainer>
          </div>
          <div className="mt-4 space-y-2 text-sm">
            <div className="flex items-center justify-between">
              <span className="text-slate-300 flex items-center gap-2">
                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                Occupied
              </span>
              <span className="text-white font-semibold">75%</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-300 flex items-center gap-2">
                <div className="w-2 h-2 bg-slate-500 rounded-full"></div>
                Vacant
              </span>
              <span className="text-white font-semibold">25%</span>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Transactions */}
        <div className="bg-slate-800 bg-opacity-50 backdrop-blur-sm border border-slate-700 border-opacity-50 rounded-xl p-6">
          <h2 className="text-white text-lg font-semibold mb-6">Recent Transactions</h2>
          <div className="space-y-4">
            {[
              { type: 'Rent Payment', amount: '+AED 3,500', status: 'completed', user: 'Ahmed Hassan' },
              { type: 'Maintenance', amount: '-AED 800', status: 'completed', user: 'Property A' },
              { type: 'Penalty Fee', amount: '+AED 250', status: 'pending', user: 'Muhammad Ali' },
              { type: 'Deposit Return', amount: '-AED 1,200', status: 'processing', user: 'Fatima Khan' },
            ].map((transaction, idx) => (
              <div key={idx} className="flex items-center justify-between p-3 bg-slate-700 bg-opacity-30 rounded-lg hover:bg-opacity-50 transition-all">
                <div className="flex-1">
                  <p className="text-white font-medium text-sm">{transaction.type}</p>
                  <p className="text-slate-400 text-xs">{transaction.user}</p>
                </div>
                <div className="text-right">
                  <p className={`font-semibold text-sm ${transaction.amount.startsWith('+') ? 'text-green-400' : 'text-red-400'}`}>
                    {transaction.amount}
                  </p>
                  <span className={`text-xs px-2 py-1 rounded-full ${
                    transaction.status === 'completed' ? 'bg-green-500 bg-opacity-20 text-green-400' :
                    transaction.status === 'pending' ? 'bg-yellow-500 bg-opacity-20 text-yellow-400' :
                    'bg-blue-500 bg-opacity-20 text-blue-400'
                  }`}>
                    {transaction.status}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Quick Actions */}
        <div className="bg-slate-800 bg-opacity-50 backdrop-blur-sm border border-slate-700 border-opacity-50 rounded-xl p-6">
          <h2 className="text-white text-lg font-semibold mb-6">Quick Actions</h2>
          <div className="grid grid-cols-2 gap-3">
            {[
              { icon: '➕', label: 'Add Property', color: 'from-blue-500 to-blue-600' },
              { icon: '👤', label: 'Add Tenant', color: 'from-purple-500 to-purple-600' },
              { icon: '💰', label: 'Record Rent', color: 'from-green-500 to-green-600' },
              { icon: '⚙️', label: 'Settings', color: 'from-slate-500 to-slate-600' },
              { icon: '📊', label: 'View Reports', color: 'from-indigo-500 to-indigo-600' },
              { icon: '📬', label: 'Messages', color: 'from-pink-500 to-pink-600' },
            ].map((action, idx) => (
              <button
                key={idx}
                className={`bg-gradient-to-br ${action.color} hover:shadow-lg transform hover:scale-105 transition-all duration-200 text-white font-semibold py-4 px-4 rounded-lg flex flex-col items-center justify-center gap-2`}
              >
                <span className="text-2xl">{action.icon}</span>
                <span className="text-xs text-center">{action.label}</span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
