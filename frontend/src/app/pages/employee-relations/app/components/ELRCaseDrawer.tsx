import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  X, 
  FileText, 
  Printer, 
  Clock, 
  AlertCircle, 
  CheckCircle,
  Calendar,
  CheckSquare,
  MapPin,
  Send,
  ThumbsUp,
  ThumbsDown
} from "lucide-react";

interface GeneratedDoc {
  id: number;
  card_id: number;
  title: string;
  doc_type: string;
  content: string;
  generated_at: string;
  served_at: string | null;
  acknowledged_at: string | null;
}

interface Transition {
  id: number;
  from_stage_name: string | null;
  to_stage_name: string;
  transitioned_at: string;
  actor: string;
}

interface Hearing {
  id: number;
  case_card_id: number;
  scheduled_at: string;
  location: string;
  notes: string;
  outcome: string | null;
  status: string;
}

interface Approval {
  id: number;
  case_card_id: number;
  subject: string;
  stage_id: number | null;
  status: string;
  decision_note: string | null;
  created_at: string;
}

interface Card {
  id: number;
  full_name: string;
  employee_id: string;
  department: string;
  created_at: string;
  entered_via: string;
}

interface CardDetails {
  card: Card;
  documents: GeneratedDoc[];
  transitions: Transition[];
}

interface ELRCaseDrawerProps {
  cardId: number | null;
  onClose: () => void;
  onUpdate?: () => void; // call when docs or stuff change if needed
}

export function ELRCaseDrawer({ cardId, onClose, onUpdate }: ELRCaseDrawerProps) {
  const [detailsLoading, setDetailsLoading] = useState(false);
  const [cardDetails, setCardDetails] = useState<CardDetails | null>(null);
  
  // Hearings
  const [hearings, setHearings] = useState<Hearing[]>([]);
  const [showHearingForm, setShowHearingForm] = useState(false);
  const [hearingForm, setHearingForm] = useState<{ id?: number, scheduled_at: string, location: string, notes: string, outcome: string, status: string }>({
    scheduled_at: "", location: "", notes: "", outcome: "", status: "Scheduled"
  });

  // Approvals
  const [approvals, setApprovals] = useState<Approval[]>([]);
  const [showApprovalForm, setShowApprovalForm] = useState(false);
  const [approvalSubject, setApprovalSubject] = useState("");

  const [toast, setToast] = useState<{message: string, isError: boolean} | null>(null);

  useEffect(() => {
    if (cardId) {
      fetchCardData(cardId);
    }
  }, [cardId]);

  const showToast = (message: string, isError = false) => {
    setToast({ message, isError });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchCardData = async (id: number) => {
    setDetailsLoading(true);
    setCardDetails(null);
    try {
      // 1. Details (Card + Docs + Transitions)
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=card&id=${id}`);
      const data = await res.json();
      if (data.success) {
        setCardDetails(data);
      } else {
        showToast(data.error || "Failed to fetch card details", true);
        onClose();
        return;
      }

      // 2. Hearings
      const hRes = await apiFetch(`/api/index.php?route=elr_pipeline&action=hearings&case_card_id=${id}`);
      const hData = await hRes.json();
      if (hData.success) {
        setHearings(hData.hearings || []);
      }

      // 3. Approvals
      const aRes = await apiFetch(`/api/index.php?route=elr_pipeline&action=approvals&case_card_id=${id}`);
      const aData = await aRes.json();
      if (aData.success) {
        setApprovals(aData.approvals || []);
      }

    } catch (err) {
      showToast("Unable to load card details", true);
      onClose();
    } finally {
      setDetailsLoading(false);
    }
  };

  const handlePrint = (body: string, title: string) => {
    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(`
        <html>
          <head>
            <title>${title}</title>
            <style>
              body { font-family: Arial, sans-serif; padding: 40px; line-height: 1.6; white-space: pre-wrap; }
            </style>
          </head>
          <body>
            ${body}
            <script>window.onload = function() { window.print(); window.close(); }</script>
          </body>
        </html>
      `);
      printWindow.document.close();
    }
  };

  const markDoc = async (docId: number, action: 'serve_document' | 'acknowledge_document') => {
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=${action}`, {
        method: "POST",
        body: JSON.stringify({ id: docId })
      });
      const data = await res.json();
      if (data.success) {
        showToast(`Document marked as ${action === 'serve_document' ? 'served' : 'acknowledged'}`);
        if (cardId) fetchCardData(cardId);
      } else {
        showToast(data.error || "Failed to update document", true);
      }
    } catch (err) {
      showToast("Error updating document", true);
    }
  };

  const saveHearing = async () => {
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=save_hearing`, {
        method: "POST",
        body: JSON.stringify({
          case_card_id: cardId,
          id: hearingForm.id,
          scheduled_at: hearingForm.scheduled_at,
          location: hearingForm.location,
          notes: hearingForm.notes,
          outcome: hearingForm.outcome,
          status: hearingForm.status
        })
      });
      const data = await res.json();
      if (data.success) {
        showToast("Hearing saved");
        setShowHearingForm(false);
        setHearingForm({ scheduled_at: "", location: "", notes: "", outcome: "", status: "Scheduled" });
        if (cardId) fetchCardData(cardId);
      } else {
        showToast(data.error || "Failed to save hearing", true);
      }
    } catch (err) {
      showToast("Error saving hearing", true);
    }
  };

  const requestApproval = async () => {
    if (!approvalSubject.trim()) {
      showToast("Please enter a subject", true);
      return;
    }
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=request_approval`, {
        method: "POST",
        body: JSON.stringify({
          case_card_id: cardId,
          subject: approvalSubject
        })
      });
      const data = await res.json();
      if (data.success) {
        showToast("Approval requested");
        setShowApprovalForm(false);
        setApprovalSubject("");
        if (cardId) fetchCardData(cardId);
      } else {
        showToast(data.error || "Failed to request approval", true);
      }
    } catch (err) {
      showToast("Error requesting approval", true);
    }
  };

  if (!cardId) return null;

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex justify-end font-sans">
      <div className="w-[500px] bg-card h-full shadow-2xl border-l border-border flex flex-col animate-in slide-in-from-right">
        
        <div className="p-5 border-b border-border flex justify-between items-center bg-muted">
          <h2 className="text-lg font-bold font-['Space_Grotesk'] text-foreground">Case Management</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-foreground p-1 transition-colors"><X size={20} /></button>
        </div>
        
        <div className="flex-1 overflow-y-auto p-6 space-y-8 scrollbar-thin text-slate-800 dark:text-[#c8d0e0]">
          {detailsLoading || !cardDetails ? (
            <div className="flex justify-center py-10"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div></div>
          ) : (
            <>
              {/* Employee Info */}
              <div>
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-gray-100 dark:border-white/5 pb-2">Employee Information</h3>
                <div className="flex items-center gap-4 mb-4">
                  <div className="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg">
                    {cardDetails.card.full_name.charAt(0)}
                  </div>
                  <div>
                    <div className="font-bold text-foreground text-lg">{cardDetails.card.full_name}</div>
                    <div className="text-sm font-mono text-gray-500">{cardDetails.card.employee_id} • {cardDetails.card.department}</div>
                  </div>
                </div>
              </div>

              {/* Documents */}
              <div>
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-gray-100 dark:border-white/5 pb-2 flex items-center gap-2"><FileText size={14}/> Documents & Issuances ({cardDetails.documents.length})</h3>
                {cardDetails.documents.length === 0 ? (
                  <p className="text-sm text-muted-foreground italic">No documents generated yet.</p>
                ) : (
                  <div className="space-y-4">
                    {cardDetails.documents.map(doc => (
                      <div key={doc.id} className="bg-muted border border-border rounded-xl overflow-hidden shadow-sm">
                        <div className="px-4 py-3 border-b border-border flex justify-between items-center bg-card">
                          <div>
                            <div className="font-bold text-sm text-foreground">{doc.title}</div>
                            <div className="text-[10px] text-muted-foreground font-mono mt-0.5">{new Date(doc.generated_at).toLocaleString()} • {doc.doc_type}</div>
                          </div>
                          <button 
                            onClick={() => handlePrint(doc.content, doc.title)}
                            className="p-1.5 bg-accent hover:bg-accent rounded text-slate-600 dark:text-gray-300 transition-colors"
                            title="Print / PDF"
                          >
                            <Printer size={16} />
                          </button>
                        </div>
                        
                        <div className="p-3 bg-card border-b border-border flex gap-2">
                          {doc.served_at ? (
                            <span className="text-[10px] font-bold uppercase flex items-center gap-1 text-[#00e07a] bg-[#00e07a]/10 px-2 py-1 rounded">
                              <CheckCircle size={12}/> Served {new Date(doc.served_at).toLocaleDateString()}
                            </span>
                          ) : (
                            <button onClick={() => markDoc(doc.id, 'serve_document')} className="text-[10px] font-bold uppercase flex items-center gap-1 text-blue-500 bg-blue-500/10 hover:bg-blue-500/20 px-2 py-1 rounded transition-colors">
                              <Send size={12}/> Mark Served
                            </button>
                          )}

                          {doc.acknowledged_at ? (
                            <span className="text-[10px] font-bold uppercase flex items-center gap-1 text-purple-500 bg-purple-500/10 px-2 py-1 rounded">
                              <CheckSquare size={12}/> Ack'd {new Date(doc.acknowledged_at).toLocaleDateString()}
                            </span>
                          ) : (
                            <button onClick={() => markDoc(doc.id, 'acknowledge_document')} className="text-[10px] font-bold uppercase flex items-center gap-1 text-purple-500 bg-purple-500/10 hover:bg-purple-500/20 px-2 py-1 rounded transition-colors">
                              <CheckSquare size={12}/> Mark Acknowledged
                            </button>
                          )}
                        </div>

                        <div className="p-4 max-h-[150px] overflow-y-auto scrollbar-thin font-mono text-[11px] text-gray-600 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">
                          {doc.content}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Hearings */}
              <div>
                <div className="flex justify-between items-center mb-3 border-b border-gray-100 dark:border-white/5 pb-2">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2"><Calendar size={14}/> Hearings & Meetings</h3>
                  <button onClick={() => setShowHearingForm(!showHearingForm)} className="text-[10px] text-blue-500 hover:text-blue-600 dark:hover:text-blue-400 font-bold uppercase transition-colors">
                    + Schedule
                  </button>
                </div>

                {showHearingForm && (
                  <div className="bg-blue-50 dark:bg-[#161922] border border-blue-100 dark:border-blue-500/30 rounded-xl p-4 mb-4 space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Date & Time</label>
                        <input type="datetime-local" value={hearingForm.scheduled_at} onChange={e => setHearingForm({...hearingForm, scheduled_at: e.target.value})} className="w-full text-xs p-2 rounded bg-card border border-border focus:border-blue-500 focus:outline-none"/>
                      </div>
                      <div>
                        <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Status</label>
                        <select value={hearingForm.status} onChange={e => setHearingForm({...hearingForm, status: e.target.value})} className="w-full text-xs p-2 rounded bg-card border border-border focus:border-blue-500 focus:outline-none">
                          <option>Scheduled</option>
                          <option>Completed</option>
                          <option>Cancelled</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Location / Link</label>
                      <input type="text" value={hearingForm.location} onChange={e => setHearingForm({...hearingForm, location: e.target.value})} className="w-full text-xs p-2 rounded bg-card border border-border focus:border-blue-500 focus:outline-none"/>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Notes</label>
                      <textarea value={hearingForm.notes} onChange={e => setHearingForm({...hearingForm, notes: e.target.value})} className="w-full text-xs p-2 rounded bg-card border border-border focus:border-blue-500 focus:outline-none" rows={2}></textarea>
                    </div>
                    {hearingForm.status === 'Completed' && (
                      <div>
                        <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Outcome</label>
                        <textarea value={hearingForm.outcome} onChange={e => setHearingForm({...hearingForm, outcome: e.target.value})} className="w-full text-xs p-2 rounded bg-card border border-border focus:border-blue-500 focus:outline-none" rows={2}></textarea>
                      </div>
                    )}
                    <div className="flex justify-end gap-2 pt-2">
                      <button onClick={() => setShowHearingForm(false)} className="px-3 py-1.5 text-xs text-gray-500">Cancel</button>
                      <button onClick={saveHearing} className="px-3 py-1.5 text-xs bg-blue-500 text-white rounded font-bold hover:bg-blue-600 transition-colors">Save Hearing</button>
                    </div>
                  </div>
                )}

                {hearings.length === 0 && !showHearingForm ? (
                  <p className="text-sm text-muted-foreground italic">No hearings scheduled.</p>
                ) : (
                  <div className="space-y-3">
                    {hearings.map(h => (
                      <div key={h.id} className="bg-muted border border-border rounded-xl p-4">
                        <div className="flex justify-between items-start mb-2">
                          <div className="flex items-center gap-2">
                            <Calendar size={14} className="text-blue-500" />
                            <span className="font-bold text-sm text-foreground">{new Date(h.scheduled_at).toLocaleString()}</span>
                          </div>
                          <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded ${h.status === 'Completed' ? 'bg-[#00e07a]/10 text-[#00e07a]' : h.status === 'Cancelled' ? 'bg-red-500/10 text-red-500' : 'bg-blue-500/10 text-blue-500'}`}>
                            {h.status}
                          </span>
                        </div>
                        <div className="flex items-center gap-1.5 text-xs text-gray-500 mb-2">
                          <MapPin size={12}/> {h.location || 'No location specified'}
                        </div>
                        {h.notes && <div className="text-xs text-muted-foreground mt-2 bg-card p-2 rounded border border-border"><strong>Notes:</strong> {h.notes}</div>}
                        {h.outcome && <div className="text-xs text-muted-foreground mt-2 bg-card p-2 rounded border border-border"><strong>Outcome:</strong> {h.outcome}</div>}
                        <div className="mt-3 flex justify-end">
                          <button onClick={() => { setHearingForm(h as any); setShowHearingForm(true); }} className="text-[10px] text-gray-500 hover:text-blue-500 uppercase font-bold transition-colors">Edit</button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Approvals */}
              <div>
                <div className="flex justify-between items-center mb-3 border-b border-gray-100 dark:border-white/5 pb-2">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2"><CheckSquare size={14}/> Manager Approvals</h3>
                  <button onClick={() => setShowApprovalForm(!showApprovalForm)} className="text-[10px] text-purple-500 hover:text-purple-600 dark:hover:text-purple-400 font-bold uppercase transition-colors">
                    + Request
                  </button>
                </div>

                {showApprovalForm && (
                  <div className="bg-purple-50 dark:bg-[#161922] border border-purple-100 dark:border-purple-500/30 rounded-xl p-4 mb-4 space-y-3">
                    <div>
                      <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Subject / Reason for Approval</label>
                      <input type="text" value={approvalSubject} onChange={e => setApprovalSubject(e.target.value)} placeholder="e.g. Approval for termination" className="w-full text-xs p-2 rounded bg-background border border-border focus:border-purple-500 focus:outline-none"/>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                      <button onClick={() => setShowApprovalForm(false)} className="px-3 py-1.5 text-xs text-gray-500">Cancel</button>
                      <button onClick={requestApproval} className="px-3 py-1.5 text-xs bg-purple-500 text-white rounded font-bold hover:bg-purple-600 transition-colors">Send Request</button>
                    </div>
                  </div>
                )}

                {approvals.length === 0 && !showApprovalForm ? (
                  <p className="text-sm text-muted-foreground italic">No approvals requested.</p>
                ) : (
                  <div className="space-y-3">
                    {approvals.map(a => (
                      <div key={a.id} className="bg-muted border border-border rounded-xl p-4 flex justify-between items-center">
                        <div>
                          <div className="font-bold text-sm text-foreground mb-1">{a.subject}</div>
                          <div className="text-[10px] text-gray-500">Requested: {new Date(a.created_at).toLocaleDateString()}</div>
                          {a.decision_note && (
                            <div className="text-[11px] text-muted-foreground mt-2 bg-card p-2 rounded border border-border">
                              {a.decision_note}
                            </div>
                          )}
                        </div>
                        <div className="flex flex-col items-end">
                          <span className={`text-[10px] font-bold uppercase px-2 py-1 rounded flex items-center gap-1 ${
                            a.status === 'Approved' ? 'bg-[#00e07a]/10 text-[#00e07a]' : 
                            a.status === 'Rejected' ? 'bg-red-500/10 text-red-500' : 
                            'bg-yellow-500/10 text-yellow-600 dark:text-yellow-500'
                          }`}>
                            {a.status === 'Approved' && <ThumbsUp size={12}/>}
                            {a.status === 'Rejected' && <ThumbsDown size={12}/>}
                            {a.status === 'Pending' && <Clock size={12}/>}
                            {a.status}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Timeline */}
              <div>
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-gray-100 dark:border-white/5 pb-2 flex items-center gap-2"><Clock size={14}/> Transition Timeline</h3>
                <div className="relative pl-3 border-l-2 border-border space-y-4 mt-4 ml-2">
                  {cardDetails.transitions.map((trx, index) => (
                    <div key={trx.id || index} className="relative">
                      <div className="absolute -left-[17px] top-1 w-3 h-3 bg-card border-2 border-[#00e07a] rounded-full"></div>
                      <div className="text-sm font-medium text-foreground">
                        Moved to <span className="text-[#00e07a]">{trx.to_stage_name}</span>
                      </div>
                      <div className="text-[11px] text-gray-500 mt-1">
                        {new Date(trx.transitioned_at).toLocaleString()} • by {trx.actor}
                      </div>
                    </div>
                  ))}
                  <div className="relative">
                    <div className="absolute -left-[17px] top-1 w-3 h-3 bg-card border-2 border-blue-400 rounded-full"></div>
                    <div className="text-sm font-medium text-foreground">Case Created ({cardDetails.card.entered_via === 'auto' ? 'Automated' : 'Manual'})</div>
                    <div className="text-[11px] text-gray-500 mt-1">
                      {new Date(cardDetails.card.created_at).toLocaleString()}
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

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
    </div>
  );
}
