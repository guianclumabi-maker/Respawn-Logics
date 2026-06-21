import { useState, useEffect } from "react";
import { useAuth } from "../context/AuthContext";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=leaves`;

export function LeavesDashboard() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState("my_requests");
  
  const [balances, setBalances] = useState<any[]>([]);
  const [myRequests, setMyRequests] = useState<any[]>([]);
  const [approvals, setApprovals] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Form State
  const [showApplyModal, setShowApplyModal] = useState(false);
  const [leaveType, setLeaveType] = useState("Vacation Leave");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [reason, setReason] = useState("");

  const fetchData = async () => {
    setLoading(true);
    try {
      const [balRes, reqRes, appRes] = await Promise.all([
        fetch(`${API}&action=balances`, { credentials: "include" }),
        fetch(`${API}&action=my_requests`, { credentials: "include" }),
        fetch(`${API}&action=pending_approvals`, { credentials: "include" })
      ]);
      const balData = await balRes.json();
      const reqData = await reqRes.json();
      const appData = await appRes.json();
      
      if (balData.success) setBalances(balData.data || []);
      if (reqData.success) setMyRequests(reqData.data || []);
      if (appData.success) setApprovals(appData.data || []);
    } catch (e) {
      console.error("Failed to fetch leaves data", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleApply = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await fetch(`${API}&action=apply`, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ leave_type: leaveType, start_date: startDate, end_date: endDate, reason })
      });
      const data = await res.json();
      if (data.success) {
        setShowApplyModal(false);
        setStartDate("");
        setEndDate("");
        setReason("");
        fetchData();
      } else {
        alert(data.error || "Failed to apply");
      }
    } catch (e) {
      alert("An error occurred");
    }
  };

  const handleApproveReject = async (requestId: number, decision: "Approved" | "Rejected") => {
    try {
      const res = await fetch(`${API}&action=approve_reject`, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ request_id: requestId, decision, comments: "" })
      });
      const data = await res.json();
      if (data.success) {
        fetchData();
      } else {
        alert(data.error || "Failed to process decision");
      }
    } catch (e) {
      alert("An error occurred");
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
    <div className="flex-1 flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-white mb-1" style={{ fontFamily: "'Plus Jakarta Sans', sans-serif" }}>
              Leave Requests
            </h1>
            <p className="text-sm text-gray-400">Manage time off and view balances</p>
          </div>
          <button 
            onClick={() => setShowApplyModal(true)}
            className="px-4 py-2 bg-gradient-to-r from-[#00e07a] to-[#00b8ff] text-black font-bold border-none rounded-lg text-sm hover:opacity-90 transition-opacity shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2"
          >
            Apply for Leave
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-auto p-8 space-y-6">
        {/* Balances Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {balances.map((b, i) => {
            const available = b.total_allowance - b.used_balance;
            return (
              <div key={i} className="bg-[#161922]/70 border border-white/5 rounded-xl p-5 shadow-lg">
                <div className="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-2">{b.leave_type}</div>
                <div className="flex items-end gap-2">
                  <div className="text-3xl font-bold text-white">{available}</div>
                  <div className="text-sm text-gray-500 mb-1">/ {b.total_allowance} days</div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Tabs */}
        <div className="flex gap-4 border-b border-white/5">
          <button 
            onClick={() => setActiveTab("my_requests")}
            className={`pb-3 px-1 text-sm font-medium transition-colors ${activeTab === "my_requests" ? "text-white border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"}`}
          >
            My Requests
          </button>
          {approvals.length > 0 && (
            <button 
              onClick={() => setActiveTab("approvals")}
              className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 ${activeTab === "approvals" ? "text-white border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"}`}
            >
              Pending Approvals
              <span className="bg-[#c084fc]/20 text-[#c084fc] px-1.5 py-0.5 rounded text-[10px] font-bold">{approvals.length}</span>
            </button>
          )}
        </div>

        {/* Content */}
        {loading ? (
          <div className="text-center text-gray-500 py-10">Loading...</div>
        ) : activeTab === "my_requests" ? (
          <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead className="bg-black/20">
                <tr>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Type</th>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Dates</th>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Status</th>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Submitted</th>
                </tr>
              </thead>
              <tbody>
                {myRequests.map((req) => (
                  <tr key={req.id} className="border-b border-white/[0.02] hover:bg-white/[0.02]">
                    <td className="py-4 px-5 text-sm text-white font-medium">{req.leave_type}</td>
                    <td className="py-4 px-5 text-sm text-gray-300">{req.start_date} to {req.end_date}</td>
                    <td className="py-4 px-5">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold border ${getStatusColor(req.status)}`}>
                        {req.status}
                      </span>
                    </td>
                    <td className="py-4 px-5 text-xs text-gray-500">{new Date(req.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
                {myRequests.length === 0 && (
                  <tr><td colSpan={4} className="py-8 text-center text-gray-500 text-sm">No leave requests found.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden">
            <table className="w-full text-left border-collapse">
              <thead className="bg-black/20">
                <tr>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Employee</th>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Type & Dates</th>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase">Reason</th>
                  <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {approvals.map((req) => (
                  <tr key={req.id} className="border-b border-white/[0.02] hover:bg-white/[0.02]">
                    <td className="py-4 px-5">
                      <div className="text-sm font-medium text-white">{req.full_name}</div>
                      <div className="text-xs text-gray-500">{req.department}</div>
                    </td>
                    <td className="py-4 px-5">
                      <div className="text-sm text-gray-300">{req.leave_type}</div>
                      <div className="text-xs text-gray-500">{req.start_date} to {req.end_date}</div>
                    </td>
                    <td className="py-4 px-5 text-sm text-gray-400 max-w-xs truncate">{req.reason || "—"}</td>
                    <td className="py-4 px-5 text-right space-x-2">
                      <button onClick={() => handleApproveReject(req.id, "Approved")} className="px-3 py-1.5 bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/30 rounded text-xs font-bold hover:bg-[#00e07a]/20">Approve</button>
                      <button onClick={() => handleApproveReject(req.id, "Rejected")} className="px-3 py-1.5 bg-red-500/10 text-red-500 border border-red-500/30 rounded text-xs font-bold hover:bg-red-500/20">Reject</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Apply Modal */}
      {showApplyModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-white/5 flex justify-between items-center">
              <h3 className="text-lg font-bold text-white">Apply for Leave</h3>
              <button onClick={() => setShowApplyModal(false)} className="text-gray-400 hover:text-white">&times;</button>
            </div>
            <form onSubmit={handleApply} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-medium text-gray-400 uppercase mb-1">Leave Type</label>
                <select value={leaveType} onChange={(e) => setLeaveType(e.target.value)} className="w-full bg-[#1a1d27] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50">
                  <option value="Vacation Leave">Vacation Leave</option>
                  <option value="Sick Leave">Sick Leave</option>
                  <option value="Maternity Leave">Maternity Leave</option>
                  <option value="Emergency Leave">Emergency Leave</option>
                </select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-400 uppercase mb-1">Start Date</label>
                  <input type="date" required value={startDate} onChange={(e) => setStartDate(e.target.value)} className="w-full bg-[#1a1d27] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 [color-scheme:dark]" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-400 uppercase mb-1">End Date</label>
                  <input type="date" required value={endDate} onChange={(e) => setEndDate(e.target.value)} className="w-full bg-[#1a1d27] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 [color-scheme:dark]" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-400 uppercase mb-1">Reason</label>
                <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} className="w-full bg-[#1a1d27] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none" placeholder="Optional details..."></textarea>
              </div>
              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => setShowApplyModal(false)} className="px-4 py-2 bg-transparent text-gray-400 hover:text-white font-medium text-sm">Cancel</button>
                <button type="submit" className="px-4 py-2 bg-[#00e07a] text-black font-bold rounded-lg text-sm shadow-[0_0_10px_rgba(0,224,122,0.3)]">Submit Request</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
