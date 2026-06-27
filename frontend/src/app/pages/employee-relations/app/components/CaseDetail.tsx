import React, { useState, useEffect } from "react";
import { ArrowLeft, Clock, User, AlertCircle, FileText, Lock } from "lucide-react";

export function CaseDetail({ caseId, onBack }: { caseId: number; onBack: () => void }) {
  const [caseData, setCaseData] = useState<any>(null);
  const [timeline, setTimeline] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';
    fetch(`${basePath}/api/index.php?route=elr&action=case&id=${caseId}`, { credentials: 'include' })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          setCaseData(data.case);
          setTimeline(data.timeline);
        } else {
          setError(data.error || "Failed to load case.");
        }
        setLoading(false);
      })
      .catch((err) => {
        setError("Network error loading case.");
        setLoading(false);
      });
  }, [caseId]);

  if (loading) return <div className="p-8 text-white">Loading case details...</div>;
  if (error) return <div className="p-8 text-red-400">{error}</div>;
  if (!caseData) return null;

  return (
    <div className="flex-1 flex flex-col h-full bg-[#06070a] text-white overflow-hidden">
      <div className="p-8 border-b border-white/[0.04] flex items-center gap-4 shrink-0">
        <button onClick={onBack} className="p-2 hover:bg-white/5 rounded-lg transition-colors">
          <ArrowLeft size={20} />
        </button>
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight">{caseData.case_number}</h1>
            {caseData.is_confidential === 1 && (
              <span className="flex items-center gap-1 text-xs font-bold px-2 py-1 bg-red-500/20 text-red-400 border border-red-500/30 rounded">
                <Lock size={12} /> CONFIDENTIAL
              </span>
            )}
            <span className="text-xs font-bold px-2 py-1 bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded">
              {caseData.status}
            </span>
          </div>
          <p className="text-slate-400 text-sm mt-1">{caseData.case_type_name || "General"} • {caseData.department}</p>
        </div>
      </div>

      <div className="flex-1 overflow-auto flex gap-6 p-8">
        
        {/* Main Content Area */}
        <div className="flex-1 flex flex-col gap-6">
          <div className="bg-[#0d0f19] border border-white/[0.04] p-6 rounded-xl">
            <h2 className="text-lg font-semibold mb-4">Description</h2>
            <p className="text-slate-300 whitespace-pre-wrap text-sm leading-relaxed">
              {caseData.description}
            </p>
          </div>
        </div>

        {/* Right Sidebar - Details & Timeline */}
        <div className="w-[350px] flex flex-col gap-6 shrink-0">
          <div className="bg-[#0d0f19] border border-white/[0.04] p-6 rounded-xl text-sm">
            <h3 className="font-semibold mb-4 text-slate-200">Case Details</h3>
            <div className="space-y-4">
              <div>
                <span className="text-slate-500 block mb-1">Subject Employee</span>
                <div className="flex items-center gap-2">
                  <User size={14} className="text-slate-400" />
                  <span>{caseData.employee_id}</span>
                </div>
              </div>
              <div>
                <span className="text-slate-500 block mb-1">Investigator</span>
                <span>{caseData.investigator_id || "Unassigned"}</span>
              </div>
              <div>
                <span className="text-slate-500 block mb-1">Severity</span>
                <span className="text-orange-400 font-medium">{caseData.severity}</span>
              </div>
            </div>
          </div>

          <div className="bg-[#0d0f19] border border-white/[0.04] p-6 rounded-xl text-sm flex-1 overflow-auto">
            <h3 className="font-semibold mb-4 text-slate-200 flex items-center gap-2">
              <Clock size={16} /> Timeline
            </h3>
            <div className="space-y-6">
              {timeline.map((event) => (
                <div key={event.id} className="relative pl-6 border-l border-white/[0.08] last:border-transparent">
                  <div className="absolute -left-[5px] top-1 w-2 h-2 rounded-full bg-[#06b6d4]"></div>
                  <p className="text-slate-200 font-medium">{event.event_type}</p>
                  <p className="text-slate-400 text-xs mt-1 mb-2">{event.description}</p>
                  <p className="text-slate-500 text-xs">
                    {new Date(event.created_at).toLocaleString()} • by {event.actor || 'System'}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
