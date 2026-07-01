import { useState, useEffect } from "react";
import { apiFetch } from "../../lib/apiClient";
import { CalendarCheck, Calendar, Info, Clock, AlertCircle, Loader2, Plus, X } from "lucide-react";

interface LeaveBalance {
  leave_type: string;
  total_allowance: number;
  used_balance: number;
}

interface LeaveRequest {
  id: number;
  employee_email: string;
  leave_type: string;
  start_date: string;
  end_date: string;
  reason: string;
  status: string;
  created_at: string;
}

export function MyLeave() {
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [myRequests, setMyRequests] = useState<LeaveRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [showApplyModal, setShowApplyModal] = useState(false);
  const [leaveType, setLeaveType] = useState("Vacation Leave");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const [balRes, reqRes] = await Promise.all([
        apiFetch("/api/index.php?route=leaves&action=balances"),
        apiFetch("/api/index.php?route=leaves&action=my_requests")
      ]);

      if (!balRes.ok || !reqRes.ok) {
        throw new Error("Connection failed while loading leave data.");
      }

      const balData = await balRes.json();
      const reqData = await reqRes.json();

      if (balData.success) setBalances(balData.data || []);
      if (reqData.success) setMyRequests(reqData.data || []);

      if (!balData.success || !reqData.success) {
        setError(balData.error || reqData.error || "Failed to load leave records.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading leaves.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleApply = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setFormError(null);

    try {
      const res = await apiFetch("/api/index.php?route=leaves&action=apply", {
        method: "POST",
        body: JSON.stringify({
          leave_type: leaveType,
          start_date: startDate,
          end_date: endDate,
          reason
        })
      });

      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();

      if (data.success) {
        setShowApplyModal(false);
        setStartDate("");
        setEndDate("");
        setReason("");
        // Reload fresh balances and requests list
        fetchData();
      } else {
        setFormError(data.error || "Failed to submit leave request.");
      }
    } catch (err: any) {
      console.error(err);
      setFormError(err.message || "Connection failure. Please check your network and try again.");
    } finally {
      setSubmitting(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Approved": return "bg-[#00e07a]/10 text-[#00e07a] border-[#00e07a]/20";
      case "Rejected": return "bg-red-500/10 text-red-500 border-red-500/20";
      case "Pending": return "bg-amber-500/10 text-amber-500 border-amber-500/20";
      default: return "bg-gray-500/10 text-gray-400 border-gray-500/20";
    }
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#06070a] text-[#c8d0e0]">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk']">
              My Leave
            </h1>
            <p className="text-sm text-gray-400">View balances, check status, and request time off</p>
          </div>
          <button 
            onClick={() => {
              setFormError(null);
              setShowApplyModal(true);
            }}
            className="px-4 py-2 bg-gradient-to-r from-[#00e07a] to-[#00b8ff] text-black font-bold border-none rounded-lg text-sm hover:opacity-90 transition-opacity shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2"
          >
            <Plus size={16} /> Request Time Off
          </button>
        </div>
      </div>

      {/* Main Body */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Retrieving leave calendar and logs...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-white">Connection Error</h3>
            <p className="text-sm text-gray-400">{error}</p>
            <button 
              onClick={fetchData}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs transition-colors border border-white/10"
            >
              Retry Connection
            </button>
          </div>
        ) : (
          <>
            {/* Balances Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {balances.map((b, i) => {
                const available = b.total_allowance - b.used_balance;
                return (
                  <div key={i} className="bg-[#161922]/70 border border-white/5 rounded-xl p-5 shadow-lg relative overflow-hidden group hover:border-[#00e07a]/20 transition-all duration-300">
                    <div className="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-[#00e07a]/5 to-[#00b8ff]/5 rounded-bl-full pointer-events-none"></div>
                    <div className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">{b.leave_type}</div>
                    <div className="flex items-end gap-2 relative z-10">
                      <div className="text-3xl font-bold text-white">{available}</div>
                      <div className="text-xs text-gray-500 mb-1.5 font-medium">/ {b.total_allowance} days remaining</div>
                    </div>
                  </div>
                );
              })}
              {balances.length === 0 && (
                <div className="col-span-full py-6 px-4 bg-[#161922]/20 border border-white/5 rounded-xl text-center text-gray-500 text-sm flex items-center justify-center gap-2">
                  <Info size={16} /> No leave balances allocated to your account.
                </div>
              )}
            </div>

            {/* History Header */}
            <div className="border-b border-white/5 pb-2">
              <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">Leave History</h2>
            </div>

            {/* History Table */}
            <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden shadow-2xl">
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                      <th className="py-4 px-6">Leave Type</th>
                      <th className="py-4 px-6">Dates Requested</th>
                      <th className="py-4 px-6">Reason</th>
                      <th className="py-4 px-6">Status</th>
                      <th className="py-4 px-6 text-right">Submitted</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/[0.03]">
                    {myRequests.map((req) => (
                      <tr key={req.id} className="hover:bg-white/[0.02] transition-colors">
                        <td className="py-4 px-6 font-medium text-white text-sm">{req.leave_type}</td>
                        <td className="py-4 px-6 text-sm text-gray-300 font-mono">
                          {req.start_date} <span className="text-gray-600">to</span> {req.end_date}
                        </td>
                        <td className="py-4 px-6 text-sm text-gray-400 max-w-xs truncate" title={req.reason}>
                          {req.reason || "—"}
                        </td>
                        <td className="py-4 px-6">
                          <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold border ${getStatusColor(req.status)}`}>
                            {req.status}
                          </span>
                        </td>
                        <td className="py-4 px-6 text-xs text-gray-500 text-right">
                          {new Date(req.created_at).toLocaleDateString()}
                        </td>
                      </tr>
                    ))}
                    {myRequests.length === 0 && (
                      <tr>
                        <td colSpan={5} className="py-12 text-center text-gray-500 text-sm">
                          <Clock className="w-10 h-10 text-gray-600 mx-auto mb-2" />
                          No leave request logs found.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}
      </div>

      {/* Apply Modal */}
      {showApplyModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-white/5 flex justify-between items-center bg-black/10">
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <CalendarCheck className="text-[#00e07a]" size={20} /> Apply for Leave
              </h3>
              <button 
                onClick={() => setShowApplyModal(false)} 
                className="text-gray-400 hover:text-white text-xl leading-none"
              >
                &times;
              </button>
            </div>
            
            <form onSubmit={handleApply} className="p-5 space-y-4">
              {formError && (
                <div className="p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-xs flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                  <span>{formError}</span>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Leave Type</label>
                <select 
                  value={leaveType} 
                  onChange={(e) => setLeaveType(e.target.value)} 
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20"
                >
                  <option value="Vacation Leave">Vacation Leave</option>
                  <option value="Sick Leave">Sick Leave</option>
                  <option value="Emergency Leave">Emergency Leave</option>
                  <option value="Maternity Leave">Maternity Leave</option>
                  <option value="Paternity Leave">Paternity Leave</option>
                  <option value="Bereavement Leave">Bereavement Leave</option>
                </select>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Start Date</label>
                  <input 
                    type="date" 
                    required 
                    value={startDate} 
                    onChange={(e) => setStartDate(e.target.value)} 
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20 [color-scheme:dark]" 
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">End Date</label>
                  <input 
                    type="date" 
                    required 
                    value={endDate} 
                    onChange={(e) => setEndDate(e.target.value)} 
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20 [color-scheme:dark]" 
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Reason</label>
                <textarea 
                  value={reason} 
                  onChange={(e) => setReason(e.target.value)} 
                  rows={3} 
                  required
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20 resize-none" 
                  placeholder="Explain why you are requesting leave..."
                ></textarea>
              </div>

              <div className="pt-2 flex justify-end gap-3">
                <button 
                  type="button" 
                  onClick={() => setShowApplyModal(false)} 
                  className="px-4 py-2 text-gray-400 hover:text-white text-sm font-semibold transition-colors"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  disabled={submitting}
                  className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-sm transition-colors shadow-[0_0_15px_rgba(0,224,122,0.3)] disabled:opacity-50 flex items-center gap-1.5"
                >
                  {submitting && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Submit Request
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
