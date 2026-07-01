import { useState, useEffect } from "react";
import { apiFetch } from "../../lib/apiClient";
import { Scale, TrendingUp, Shield, Calendar, DollarSign, AlertCircle, Loader2 } from "lucide-react";

interface CompRecord {
  id: string | number;
  base: number;
  type: string;
  status: string;
  effective: string;
  author: string;
}

interface CompHistoryData {
  employeeName: string;
  employeeId: string;
  currentBase: number;
  history: CompRecord[];
}

export function MyCompensation() {
  const [data, setData] = useState<CompHistoryData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchCompHistory();
  }, []);

  const fetchCompHistory = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=payroll_engine&action=comp_history");
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const resData = await res.json();
      if (resData.success) {
        setData(resData.data);
      } else {
        setError(resData.error || "Failed to load compensation history.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading compensation history.");
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP"
    }).format(amount);
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#06070a] text-[#c8d0e0]">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div>
          <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk']">
            My Compensation
          </h1>
          <p className="text-sm text-gray-400">View salary history and grade details</p>
        </div>
      </div>

      {/* Main Body */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Securing payroll history link...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-white">Access Denied / Failed</h3>
            <p className="text-sm text-gray-400">{error}</p>
            <button 
              onClick={fetchCompHistory}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs transition-colors border border-white/10"
            >
              Retry Connection
            </button>
          </div>
        ) : !data ? (
          <div className="text-center text-gray-500 py-10">No compensation records found.</div>
        ) : (
          <div className="max-w-4xl mx-auto space-y-6">
            {/* Overview / Card */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-6 shadow-lg relative overflow-hidden">
                <div className="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-[#00e07a]/5 to-[#00b8ff]/5 rounded-bl-full pointer-events-none"></div>
                <div className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Employee Name</div>
                <div className="text-xl font-bold text-white tracking-tight">{data.employeeName}</div>
                <div className="text-xs text-gray-500 mt-1">ID: {data.employeeId}</div>
              </div>

              <div className="bg-[#161922]/70 border border-[#00e07a]/10 rounded-xl p-6 shadow-lg relative overflow-hidden">
                <div className="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-[#00e07a]/5 to-[#00e07a]/5 rounded-bl-full pointer-events-none"></div>
                <div className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Current Base Pay</div>
                <div className="text-3xl font-extrabold text-[#00e07a] font-mono flex items-center">
                  <DollarSign size={22} className="mt-1" />
                  {data.currentBase.toLocaleString("en-PH", { minimumFractionDigits: 2 })}
                </div>
                <div className="text-xs text-gray-500 mt-1">Pay Cycle: Monthly / Semi-Monthly</div>
              </div>

              <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-6 shadow-lg relative overflow-hidden">
                <div className="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-[#00e07a]/5 to-[#00b8ff]/5 rounded-bl-full pointer-events-none"></div>
                <div className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Verification</div>
                <div className="text-sm font-semibold text-white flex items-center gap-2 mt-1">
                  <Shield className="text-blue-400 w-5 h-5" /> Secured Record
                </div>
                <div className="text-xs text-gray-500 mt-1.5">Compliance Scoped & Signed</div>
              </div>
            </div>

            {/* History Table */}
            <div className="border-b border-white/5 pb-2 pt-4">
              <h2 className="text-lg font-bold text-white font-['Space_Grotesk'] flex items-center gap-2">
                <TrendingUp size={20} className="text-[#00e07a]" /> Salary History Log
              </h2>
            </div>

            <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden shadow-2xl">
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                      <th className="py-4 px-6">Effective Date</th>
                      <th className="py-4 px-6">Base Pay Rate</th>
                      <th className="py-4 px-6">Pay Frequency</th>
                      <th className="py-4 px-6">Status</th>
                      <th className="py-4 px-6 text-right">Authorized By</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/[0.03]">
                    {data.history.map((record) => (
                      <tr key={record.id} className="hover:bg-white/[0.02] transition-colors">
                        <td className="py-4 px-6 font-medium text-white flex items-center gap-2">
                          <Calendar size={14} className="text-gray-500" />
                          {record.effective}
                        </td>
                        <td className="py-4 px-6 text-sm text-white font-bold font-mono">
                          {formatCurrency(record.base)}
                        </td>
                        <td className="py-4 px-6 text-sm text-gray-300">
                          {record.type}
                        </td>
                        <td className="py-4 px-6">
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold border bg-[#00e07a]/10 text-[#00e07a] border-[#00e07a]/20">
                            {record.status}
                          </span>
                        </td>
                        <td className="py-4 px-6 text-sm text-gray-500 text-right font-mono">
                          {record.author}
                        </td>
                      </tr>
                    ))}
                    {data.history.length === 0 && (
                      <tr>
                        <td colSpan={5} className="py-8 text-center text-gray-500 text-sm">
                          <Scale className="w-10 h-10 text-gray-600 mx-auto mb-2" />
                          No previous compensation adjustments logged.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
