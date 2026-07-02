import { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { useAuth } from "../../../../context/AuthContext";
import { 
  ArrowLeft, 
  Clock, 
  User, 
  AlertCircle, 
  FileText, 
  Lock,
  Loader2,
  CheckCircle,
  Save
} from "lucide-react";

interface CaseDetailProps {
  caseId: number;
  onBack: () => void;
}

export function CaseDetail({ caseId, onBack }: CaseDetailProps) {
  const { hasPermission } = useAuth();
  const canInvestigate = hasPermission("elr.investigate");

  const [caseData, setCaseData] = useState<any>(null);
  const [timeline, setTimeline] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // Edit States
  const [updating, setUpdating] = useState(false);
  const [updateError, setUpdateError] = useState<string | null>(null);
  const [updateSuccess, setUpdateSuccess] = useState<string | null>(null);

  // Edit fields
  const [status, setStatus] = useState("");
  const [severity, setSeverity] = useState("");
  const [investigatorId, setInvestigatorId] = useState("");

  const fetchCase = async () => {
    setLoading(true);
    setError("");
    try {
      const res = await apiFetch(`/api/index.php?route=elr&action=case&id=${caseId}`);
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      if (data.success) {
        setCaseData(data.case);
        setTimeline(data.timeline || []);
        setStatus(data.case.status);
        setSeverity(data.case.severity);
        setInvestigatorId(data.case.investigator_id || "");
      } else {
        setError(data.error || "Failed to load case details.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading case details.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCase();
  }, [caseId]);

  const handleUpdate = async (e: React.FormEvent) => {
    e.preventDefault();
    setUpdating(true);
    setUpdateError(null);
    setUpdateSuccess(null);

    const payload = {
      id: caseId,
      status,
      severity,
      investigator_id: investigatorId
    };

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=update_case", {
        method: "POST",
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        if (res.status === 400) {
          const errData = await res.json();
          throw new Error(errData.error || "Illegal parameters or status transition.");
        }
        if (res.status === 403) throw new Error("Permission Denied: You do not have permissions to modify this case.");
        throw new Error(`HTTP error ${res.status}`);
      }

      const data = await res.json();
      if (data.success) {
        setUpdateSuccess("Case details adjusted successfully.");
        fetchCase();
      } else {
        setUpdateError(data.error || "Failed to update case.");
      }
    } catch (err: any) {
      setUpdateError(err.message || "Unable to update case details.");
    } finally {
      setUpdating(false);
    }
  };

  if (loading && !caseData) {
    return (
      <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
        <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
        <p className="text-sm font-medium">Resolving investigation file...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
        <AlertCircle className="w-10 h-10 text-red-500" />
        <h3 className="text-lg font-bold text-white font-['Space_Grotesk']">Case Locked</h3>
        <p className="text-sm text-gray-400">{error}</p>
        <button 
          onClick={onBack}
          className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs transition-colors border border-white/10"
        >
          Return to Registry
        </button>
      </div>
    );
  }

  if (!caseData) return null;

  // Legal transitions helper
  const getAllowedStatusOptions = () => {
    const current = caseData.status;
    const options = [current];
    
    if (current === 'Open') options.push('Under Review');
    if (current === 'Under Review') {
      options.push('Investigating');
      options.push('Closed');
    }
    if (current === 'Investigating') {
      options.push('Pending Approval');
      options.push('Resolved');
      options.push('Closed');
    }
    if (current === 'Pending Approval') {
      options.push('Resolved');
      options.push('Investigating');
    }
    if (current === 'Resolved') {
      options.push('Closed');
    }
    
    // De-duplicate
    return Array.from(new Set(options));
  };

  return (
    <div className="flex-1 flex flex-col h-full bg-[#06070a] text-[#c8d0e0] overflow-hidden">
      
      {/* Header */}
      <div className="p-8 border-b border-white/[0.04] flex items-center gap-4 shrink-0 bg-[#161922]/50 backdrop-blur-md">
        <button onClick={onBack} className="p-2 hover:bg-white/5 rounded-lg transition-colors cursor-pointer text-white">
          <ArrowLeft size={20} />
        </button>
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight text-white font-['Space_Grotesk']">{caseData.case_number}</h1>
            {caseData.is_confidential === 1 && (
              <span className="flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 bg-red-500/10 text-red-400 border border-red-500/20 rounded">
                <Lock size={12} /> CONFIDENTIAL
              </span>
            )}
            <span className="text-[10px] font-bold px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded">
              {caseData.status}
            </span>
          </div>
          <p className="text-gray-400 text-sm mt-1">{caseData.case_type_name || "General"} • {caseData.department}</p>
        </div>
      </div>

      <div className="flex-1 overflow-auto flex flex-col lg:flex-row gap-6 p-8">
        
        {/* Main Content Area */}
        <div className="flex-1 flex flex-col gap-6">
          <div className="bg-[#161922]/70 border border-white/5 p-6 rounded-2xl space-y-4">
            <h2 className="text-base font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Investigation Narrative</h2>
            <p className="text-gray-300 whitespace-pre-wrap text-sm leading-relaxed font-sans">
              {caseData.description || "No description provided."}
            </p>
          </div>

          {/* Timeline details */}
          <div className="bg-[#161922]/70 border border-white/5 p-6 rounded-2xl flex-1 overflow-auto">
            <h3 className="text-base font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2 mb-4 flex items-center gap-2">
              <Clock size={16} className="text-[#00e07a]" /> Investigation Activity Log
            </h3>
            
            <div className="space-y-6 pl-4 border-l border-white/5">
              {timeline.map((event) => (
                <div key={event.id} className="relative pl-6 last:border-transparent">
                  <div className="absolute -left-[29px] top-1.5 w-2 h-2 rounded-full bg-[#00e07a] shadow-[0_0_10px_rgba(0,224,122,0.5)]"></div>
                  <p className="text-sm font-semibold text-white">{event.event_type}</p>
                  <p className="text-xs text-gray-400 mt-1 mb-2 font-sans">{event.description}</p>
                  <p className="text-[10px] text-gray-500 font-mono">
                    {new Date(event.created_at).toLocaleString()} • by {event.actor || 'System'}
                  </p>
                </div>
              ))}
              {timeline.length === 0 && (
                <div className="text-center text-gray-500 py-8 text-sm">No activity recorded.</div>
              )}
            </div>
          </div>
        </div>

        {/* Right Sidebar - Edit Controls */}
        <div className="w-full lg:w-[350px] flex flex-col gap-6 shrink-0">
          
          {/* Edit Form */}
          {canInvestigate && (
            <div className="bg-[#161922]/70 border border-white/5 p-6 rounded-2xl space-y-4">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Investigation Controls</h3>
              
              {updateSuccess && (
                <div className="p-3 bg-[#00e07a]/15 border border-[#00e07a]/25 rounded-xl text-[#00e07a] text-xs flex items-start gap-2">
                  <CheckCircle size={14} className="flex-shrink-0 mt-0.5" />
                  <span>{updateSuccess}</span>
                </div>
              )}
              {updateError && (
                <div className="p-3 bg-red-500/15 border border-red-500/25 rounded-xl text-red-400 text-xs flex items-start gap-2">
                  <AlertCircle size={14} className="flex-shrink-0 mt-0.5" />
                  <span>{updateError}</span>
                </div>
              )}

              <form onSubmit={handleUpdate} className="space-y-4">
                <div>
                  <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Case Status</label>
                  <select
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    disabled={caseData.status === 'Closed'}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-xs focus:outline-none focus:border-[#00e07a]/50 disabled:opacity-50"
                  >
                    {getAllowedStatusOptions().map(opt => (
                      <option key={opt} value={opt}>{opt}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Severity</label>
                  <select
                    value={severity}
                    onChange={(e) => setSeverity(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-xs focus:outline-none focus:border-[#00e07a]/50"
                  >
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                  </select>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Investigator ID</label>
                  <input 
                    type="text" 
                    value={investigatorId}
                    onChange={(e) => setInvestigatorId(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-xs focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="e.g. EMP-010"
                  />
                </div>

                <button 
                  type="submit" 
                  disabled={updating}
                  className="w-full py-2.5 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                >
                  {updating ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Save size={13} />}
                  Save Adjustments
                </button>
              </form>
            </div>
          )}

          {/* Details Card */}
          <div className="bg-[#161922]/70 border border-white/5 p-6 rounded-2xl text-xs space-y-4">
            <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2">Case Metadata</h3>
            
            <div className="space-y-4 font-sans text-gray-300">
              <div>
                <span className="text-gray-500 block mb-1 uppercase font-bold text-[9px] tracking-wider">Subject Employee ID</span>
                <div className="flex items-center gap-2">
                  <User size={14} className="text-gray-400" />
                  <span>{caseData.employee_id}</span>
                </div>
              </div>
              <div>
                <span className="text-gray-500 block mb-1 uppercase font-bold text-[9px] tracking-wider">Severity Tier</span>
                <span className="text-orange-400 font-medium">{caseData.severity}</span>
              </div>
              <div>
                <span className="text-gray-500 block mb-1 uppercase font-bold text-[9px] tracking-wider">Reported By</span>
                <span>{caseData.anonymous_report === 1 ? "Anonymous" : (caseData.reported_by_employee_id || "Direct File")}</span>
              </div>
              <div>
                <span className="text-gray-500 block mb-1 uppercase font-bold text-[9px] tracking-wider">Created At</span>
                <span className="font-mono text-gray-400">{new Date(caseData.created_at).toLocaleString()}</span>
              </div>
              {caseData.date_closed && (
                <div>
                  <span className="text-gray-500 block mb-1 uppercase font-bold text-[9px] tracking-wider">Date Closed</span>
                  <span className="font-mono text-gray-400">{new Date(caseData.date_closed).toLocaleString()}</span>
                </div>
              )}
            </div>
          </div>

        </div>

      </div>
    </div>
  );
}
