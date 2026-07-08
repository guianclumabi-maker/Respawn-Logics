import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  CheckSquare, 
  ThumbsUp, 
  ThumbsDown, 
  Clock, 
  AlertCircle,
  CheckCircle,
  FileText
} from "lucide-react";
import { ELRCaseDrawer } from "./ELRCaseDrawer";

interface Approval {
  id: number;
  case_card_id: number;
  subject: string;
  stage_id: number | null;
  status: string;
  decision_note: string | null;
  created_at: string;
  full_name: string;
  department: string;
}

export function ELRApprovals() {
  const [loading, setLoading] = useState(true);
  const [approvals, setApprovals] = useState<Approval[]>([]);
  const [error, setError] = useState<string | null>(null);

  const [selectedCardId, setSelectedCardId] = useState<number | null>(null);
  
  const [decidingId, setDecidingId] = useState<number | null>(null);
  const [decisionNote, setDecisionNote] = useState("");
  const [toast, setToast] = useState<{message: string, isError: boolean} | null>(null);

  useEffect(() => {
    fetchApprovals();
  }, []);

  const showToast = (message: string, isError = false) => {
    setToast({ message, isError });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchApprovals = async () => {
    setLoading(true);
    setError(null);
    try {
      // Fetching all approvals (pending queue logic is handled by API usually, or we filter here)
      // The API `action=approvals` without `case_card_id` returns all pending for the tenant
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=approvals");
      const data = await res.json();
      if (data.success) {
        setApprovals(data.approvals || []);
      } else {
        setError(data.error || "Failed to load approvals");
      }
    } catch (err) {
      setError("Error loading approvals queue");
    } finally {
      setLoading(false);
    }
  };

  const handleDecision = async (id: number, status: 'Approved' | 'Rejected') => {
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=decide_approval", {
        method: "POST",
        body: JSON.stringify({ id, status, decision_note: decisionNote })
      });
      const data = await res.json();
      if (data.success) {
        showToast(`Approval ${status.toLowerCase()} successfully`);
        setDecidingId(null);
        setDecisionNote("");
        fetchApprovals();
      } else {
        showToast(data.error || "Failed to process decision", true);
      }
    } catch (err) {
      showToast("Error processing decision", true);
    }
  };

  if (loading) {
    return (
      <div className="flex-1 flex justify-center items-center h-full bg-[#f4f6f8] dark:bg-[#06070a]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#06070a] text-slate-900 dark:text-white overflow-y-auto">
      <div className="p-8 max-w-6xl mx-auto w-full">
        
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-600 dark:from-white dark:to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Manager Approvals
            </h1>
            <p className="text-slate-500 dark:text-slate-400 text-sm">Review and decide on pending case requests (terminations, suspensions, etc).</p>
          </div>
        </div>

        {error && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-500 dark:text-red-400 text-sm mb-6 flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        )}

        <div className="space-y-4">
          {approvals.length === 0 ? (
            <div className="text-center py-16 bg-white dark:bg-[#0f1422]/50 border border-gray-200 dark:border-[#2a2d36] rounded-2xl border-dashed">
              <CheckSquare className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
              <h3 className="text-lg font-bold text-gray-700 dark:text-gray-300">All caught up!</h3>
              <p className="text-sm text-gray-500 mt-1">There are no pending approvals requiring your attention.</p>
            </div>
          ) : (
            approvals.map(approval => (
              <div key={approval.id} className="bg-white dark:bg-[#161922] border border-gray-200 dark:border-[#2a2d36] rounded-xl p-5 shadow-sm">
                <div className="flex justify-between items-start">
                  <div>
                    <div className="flex items-center gap-3 mb-2">
                      <h3 className="text-lg font-bold text-slate-900 dark:text-white">{approval.subject}</h3>
                      <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded flex items-center gap-1 ${
                        approval.status === 'Pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-500' :
                        approval.status === 'Approved' ? 'bg-[#00e07a]/10 text-[#00e07a]' :
                        'bg-red-500/10 text-red-500'
                      }`}>
                        {approval.status === 'Pending' && <Clock size={12}/>}
                        {approval.status === 'Approved' && <ThumbsUp size={12}/>}
                        {approval.status === 'Rejected' && <ThumbsDown size={12}/>}
                        {approval.status}
                      </span>
                    </div>
                    <div className="text-sm text-slate-600 dark:text-gray-400 mb-3">
                      Employee: <span className="font-bold text-slate-800 dark:text-gray-300">{approval.full_name}</span> ({approval.department})
                    </div>
                    <div className="flex items-center gap-4">
                      <button 
                        onClick={() => setSelectedCardId(approval.case_card_id)}
                        className="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                      >
                        <FileText size={14}/> View Case Details
                      </button>
                      <span className="text-xs text-muted-foreground">Requested on {new Date(approval.created_at).toLocaleDateString()}</span>
                    </div>
                  </div>

                  {approval.status === 'Pending' && (
                    <div className="flex flex-col items-end gap-2">
                      {decidingId === approval.id ? (
                        <div className="bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] p-3 rounded-xl w-72">
                          <textarea 
                            value={decisionNote}
                            onChange={(e) => setDecisionNote(e.target.value)}
                            placeholder="Add a note (optional)"
                            className="w-full bg-white dark:bg-[#161922] border border-gray-200 dark:border-white/5 rounded p-2 text-xs mb-2 focus:outline-none focus:border-[#00e07a]"
                            rows={2}
                          ></textarea>
                          <div className="flex justify-between items-center">
                            <button onClick={() => setDecidingId(null)} className="text-[10px] text-gray-500 font-bold uppercase hover:text-slate-700 dark:hover:text-white">Cancel</button>
                            <div className="flex gap-2">
                              <button onClick={() => handleDecision(approval.id, 'Rejected')} className="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-[11px] font-bold rounded flex items-center gap-1 transition-colors"><ThumbsDown size={12}/> Reject</button>
                              <button onClick={() => handleDecision(approval.id, 'Approved')} className="px-3 py-1.5 bg-[#00e07a] hover:bg-[#00c96d] text-black text-[11px] font-bold rounded flex items-center gap-1 transition-colors"><ThumbsUp size={12}/> Approve</button>
                            </div>
                          </div>
                        </div>
                      ) : (
                        <div className="flex gap-2">
                          <button onClick={() => setDecidingId(approval.id)} className="px-4 py-2 border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 rounded-lg text-sm font-bold transition-colors">Make Decision</button>
                        </div>
                      )}
                    </div>
                  )}

                  {approval.status !== 'Pending' && approval.decision_note && (
                    <div className="max-w-xs text-xs text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-[#0b0f1a] p-3 rounded-lg border border-gray-200 dark:border-white/5">
                      <strong>Note:</strong> {approval.decision_note}
                    </div>
                  )}
                </div>
              </div>
            ))
          )}
        </div>

      </div>

      {/* Case Drawer */}
      {selectedCardId && (
        <ELRCaseDrawer 
          cardId={selectedCardId} 
          onClose={() => setSelectedCardId(null)} 
        />
      )}

      {/* Global Toast */}
      {toast && (
        <div className={`absolute bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-sm font-bold shadow-lg z-50 animate-in slide-in-from-bottom flex items-center gap-2 ${
          toast.isError ? "bg-red-500 text-foreground" : "bg-[#00e07a] text-black"
        }`}>
          {toast.isError ? <AlertCircle size={16} /> : <CheckCircle size={16} />}
          {toast.message}
        </div>
      )}
    </div>
  );
}
