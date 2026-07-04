import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Kanban, 
  Plus, 
  Settings, 
  Trash2, 
  Save, 
  AlertCircle,
  Clock,
  CheckCircle,
  FileText,
  AlignLeft,
  Type,
  ListOrdered,
  ArrowLeft,
  Eye,
  X,
  FileDown,
  Printer
} from "lucide-react";

interface Pipeline {
  id: number;
  name: string;
  description: string;
  stage_count?: number;
  active_cases?: number;
}

interface Stage {
  id: number;
  pipeline_id: number;
  name: string;
  stage_order: number;
  sla_days: number;
  is_terminal: number;
  auto_template_id: number | null;
}

interface Card {
  id: number;
  pipeline_id: number;
  stage_id: number;
  employee_id: string;
  full_name: string;
  department: string;
  doc_count: number;
  created_at: string;
}

interface GeneratedDoc {
  id: number;
  card_id: number;
  template_name: string;
  doc_type: string;
  body: string;
  created_at: string;
}

interface Transition {
  id: number;
  from_stage: string | null;
  to_stage: string;
  created_at: string;
  user_name: string;
}

export function ELRPipelineBoard() {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [currentPipelineId, setCurrentPipelineId] = useState<number | null>(null);
  
  const [stages, setStages] = useState<Stage[]>([]);
  const [cards, setCards] = useState<Card[]>([]);
  
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Add Card Modal
  const [showAddCard, setShowAddCard] = useState(false);
  const [addCardEmployeeId, setAddCardEmployeeId] = useState("");
  const [addCardStageId, setAddCardStageId] = useState<number | "">("");
  
  // Card Detail Modal
  const [selectedCardId, setSelectedCardId] = useState<number | null>(null);
  const [cardDetails, setCardDetails] = useState<{
    card: Card,
    documents: GeneratedDoc[],
    transitions: Transition[]
  } | null>(null);
  const [detailsLoading, setDetailsLoading] = useState(false);

  // Transition Fields Modal
  const [pendingMove, setPendingMove] = useState<{cardId: number, toStageId: number} | null>(null);
  const [transitionFields, setTransitionFields] = useState<{awol_start_date?: string, deadline_days?: string}>({});
  const [showTransitionModal, setShowTransitionModal] = useState(false);

  // Toast
  const [toast, setToast] = useState<{message: string, isError: boolean} | null>(null);

  useEffect(() => {
    fetchPipelines();
  }, []);

  useEffect(() => {
    if (currentPipelineId) {
      fetchBoard(currentPipelineId);
    }
  }, [currentPipelineId]);

  const showToast = (message: string, isError = false) => {
    setToast({ message, isError });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchPipelines = async () => {
    setLoading(true);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=pipelines");
      const data = await res.json();
      if (data.success) {
        setPipelines(data.pipelines || []);
      } else {
        setError(data.error || "Failed to fetch pipelines");
      }
    } catch (err) {
      setError("Unable to load pipelines");
    } finally {
      setLoading(false);
    }
  };

  const fetchBoard = async (pipelineId: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=board&pipeline_id=${pipelineId}`);
      const data = await res.json();
      if (data.success) {
        setStages(data.stages || []);
        setCards(data.cards || []);
      } else {
        setError(data.error || "Failed to fetch board");
      }
    } catch (err) {
      setError("Unable to load board");
    } finally {
      setLoading(false);
    }
  };

  const fetchCardDetails = async (id: number) => {
    setDetailsLoading(true);
    setSelectedCardId(id);
    setCardDetails(null);
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=card&id=${id}`);
      const data = await res.json();
      if (data.success) {
        setCardDetails(data.data);
      } else {
        showToast(data.error || "Failed to fetch card details", true);
        setSelectedCardId(null);
      }
    } catch (err) {
      showToast("Unable to load card details", true);
      setSelectedCardId(null);
    } finally {
      setDetailsLoading(false);
    }
  };

  const handleAddCard = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentPipelineId || !addCardEmployeeId) return;
    
    try {
      const payload: any = {
        pipeline_id: currentPipelineId,
        employee_id: addCardEmployeeId
      };
      if (addCardStageId) payload.stage_id = addCardStageId;

      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=add_card", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast("Employee added to pipeline");
        setShowAddCard(false);
        setAddCardEmployeeId("");
        setAddCardStageId("");
        fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to add employee", true);
      }
    } catch (err) {
      showToast("Error adding employee", true);
    }
  };

  const handleDragStart = (e: React.DragEvent, cardId: number) => {
    e.dataTransfer.setData("cardId", cardId.toString());
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = (e: React.DragEvent, toStageId: number) => {
    e.preventDefault();
    const cardId = parseInt(e.dataTransfer.getData("cardId"));
    if (!cardId) return;
    
    // Check if card is already in this stage
    const card = cards.find(c => c.id === cardId);
    if (card && card.stage_id === toStageId) return;

    // Check if target stage has a template that might need fields
    const toStage = stages.find(s => s.id === toStageId);
    if (toStage && toStage.auto_template_id) {
      // Show modal to collect fields
      setPendingMove({ cardId, toStageId });
      setTransitionFields({});
      setShowTransitionModal(true);
    } else {
      executeMove(cardId, toStageId, {});
    }
  };

  const executeMove = async (cardId: number, toStageId: number, fields: any) => {
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=move_card", {
        method: "POST",
        body: JSON.stringify({
          card_id: cardId,
          to_stage_id: toStageId,
          fields: fields
        })
      });
      const data = await res.json();
      if (data.success) {
        if (data.document_generated) {
          showToast(`Moved to new stage. Document generated: ${data.document_generated.template_name}`);
        } else {
          showToast("Card moved successfully");
        }
        if (currentPipelineId) fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to move card", true);
      }
    } catch (err) {
      showToast("Error moving card", true);
    } finally {
      setShowTransitionModal(false);
      setPendingMove(null);
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

  // ------------------------------------------------------------------
  // RENDER: INITIAL SELECTOR
  // ------------------------------------------------------------------
  if (!currentPipelineId) {
    return (
      <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-y-auto transition-colors duration-300">
        <div className="p-8 max-w-5xl mx-auto w-full">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                Pipeline Boards
              </h1>
              <p className="text-slate-500 dark:text-slate-400 text-sm">Select a pipeline to view its active cases.</p>
            </div>
          </div>

          {loading ? (
            <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div></div>
          ) : pipelines.length === 0 ? (
            <div className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] rounded-2xl p-12 text-center">
              <Kanban className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-bold mb-2">No pipelines found</h3>
              <p className="text-sm text-gray-500 mb-6">Create a pipeline in the Pipelines view first.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {pipelines.map(pipe => (
                <div 
                  key={pipe.id} 
                  className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] hover:border-[#00e07a]/50 dark:hover:border-[#00e07a]/50 rounded-2xl p-6 transition-all group flex flex-col cursor-pointer" 
                  onClick={() => setCurrentPipelineId(pipe.id)}
                >
                  <div className="flex justify-between items-start mb-4">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <Kanban size={20} />
                      </div>
                      <div>
                        <h3 className="font-bold text-slate-800 dark:text-white group-hover:text-[#00e07a] transition-colors">{pipe.name}</h3>
                      </div>
                    </div>
                  </div>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mb-6 flex-1 line-clamp-2">
                    {pipe.description || "No description provided."}
                  </p>
                  
                  <div className="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/[0.05]">
                    <div className="flex gap-4">
                      <div className="flex items-center gap-1.5 text-xs font-mono text-gray-400">
                        <ListOrdered size={14} /> {pipe.stage_count || 0} stages
                      </div>
                      <div className="flex items-center gap-1.5 text-xs font-mono text-gray-400">
                        <CheckCircle size={14} /> {pipe.active_cases || 0} active
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </main>
    );
  }

  // ------------------------------------------------------------------
  // RENDER: BOARD VIEW
  // ------------------------------------------------------------------
  const currentPipeline = pipelines.find(p => p.id === currentPipelineId);

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#06070a] text-slate-900 dark:text-white overflow-hidden transition-colors duration-300 relative">
      
      {/* Header */}
      <div className="flex-none px-6 py-4 border-b border-gray-200 dark:border-white/5 bg-white dark:bg-[#161922]/50 backdrop-blur-md flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button 
            onClick={() => setCurrentPipelineId(null)}
            className="p-2 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.1] rounded-lg text-gray-500 dark:text-gray-400 transition-colors"
          >
            <ArrowLeft size={18} />
          </button>
          <div>
            <h1 className="text-xl font-bold text-slate-900 dark:text-white font-['Space_Grotesk']">
              {currentPipeline?.name}
            </h1>
          </div>
        </div>
        <button 
          onClick={() => setShowAddCard(true)}
          className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-all flex items-center gap-2 cursor-pointer shadow-sm"
        >
          <Plus size={16} /> Add Employee
        </button>
      </div>

      {/* Board Scroll Area */}
      <div className="flex-1 overflow-x-auto overflow-y-hidden p-6 flex gap-6 select-none scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-[#2a2d36]">
        {loading ? (
          <div className="flex-1 flex items-center justify-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div>
          </div>
        ) : error ? (
          <div className="flex-1 flex items-center justify-center flex-col gap-4 text-red-400">
            <AlertCircle size={32} />
            <p>{error}</p>
          </div>
        ) : stages.length === 0 ? (
          <div className="flex-1 flex items-center justify-center text-gray-500">
            No stages configured for this pipeline.
          </div>
        ) : (
          stages.map(stage => {
            const stageCards = cards.filter(c => c.stage_id === stage.id);
            return (
              <div 
                key={stage.id} 
                className="w-[320px] flex-shrink-0 flex flex-col max-h-full bg-gray-50 dark:bg-[#0f1422] rounded-xl border border-gray-200 dark:border-[#2a2d36]"
                onDragOver={handleDragOver}
                onDrop={(e) => handleDrop(e, stage.id)}
              >
                {/* Column Header */}
                <div className="p-3 border-b border-gray-200 dark:border-[#2a2d36] flex justify-between items-center bg-white/50 dark:bg-[#161922]/50 rounded-t-xl">
                  <div className="flex items-center gap-2">
                    <h3 className="font-bold text-sm tracking-tight text-slate-800 dark:text-white uppercase">{stage.name}</h3>
                    <span className="bg-gray-200 dark:bg-white/10 text-xs font-mono px-2 py-0.5 rounded-full text-slate-600 dark:text-gray-400">
                      {stageCards.length}
                    </span>
                  </div>
                  {stage.is_terminal === 1 && (
                    <span className="w-2 h-2 rounded-full bg-red-500" title="Terminal Stage" />
                  )}
                </div>
                
                {/* Column Body */}
                <div className="flex-1 p-3 overflow-y-auto space-y-3 scrollbar-thin">
                  {stageCards.map(card => (
                    <div 
                      key={card.id}
                      draggable
                      onDragStart={(e) => handleDragStart(e, card.id)}
                      onClick={() => fetchCardDetails(card.id)}
                      className="bg-white dark:bg-[#1a1f2e] border border-gray-200 dark:border-[#2a2d36] hover:border-[#00e07a]/50 rounded-lg p-4 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-all group"
                    >
                      <div className="flex justify-between items-start mb-2">
                        <span className="font-bold text-sm text-slate-900 dark:text-white group-hover:text-[#00e07a] transition-colors">{card.full_name}</span>
                      </div>
                      <div className="text-xs text-gray-500 font-mono mb-3">
                        {card.employee_id} • {card.department}
                      </div>
                      <div className="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-white/5">
                        <div className="flex items-center gap-1.5 text-[10px] uppercase font-bold text-gray-400">
                          <FileText size={12} /> {card.doc_count || 0} Docs
                        </div>
                        <span className="text-[10px] text-gray-400">
                          {new Date(card.created_at).toLocaleDateString()}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* MODALS AND OVERLAYS */}

      {/* Add Card Modal */}
      {showAddCard && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-[#161922] border border-gray-200 dark:border-[#2a2d36] rounded-xl w-full max-w-sm shadow-2xl overflow-hidden">
            <div className="p-4 border-b border-gray-200 dark:border-[#2a2d36] flex justify-between items-center bg-gray-50 dark:bg-black/20">
              <h3 className="font-bold text-slate-900 dark:text-white">Add Employee</h3>
              <button onClick={() => setShowAddCard(false)} className="text-gray-400 hover:text-slate-900 dark:hover:text-white"><X size={18} /></button>
            </div>
            <form onSubmit={handleAddCard} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Employee ID</label>
                <input 
                  type="text" 
                  required
                  value={addCardEmployeeId}
                  onChange={(e) => setAddCardEmployeeId(e.target.value)}
                  className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                  placeholder="e.g. EMP-001"
                />
              </div>
              {stages.length > 0 && (
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Initial Stage (Optional)</label>
                  <select 
                    value={addCardStageId}
                    onChange={(e) => setAddCardStageId(parseInt(e.target.value) || "")}
                    className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                  >
                    <option value="">-- Default (First Stage) --</option>
                    {stages.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </div>
              )}
              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => setShowAddCard(false)} className="px-3 py-1.5 text-gray-500 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white text-sm font-semibold">Cancel</button>
                <button type="submit" className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-colors">Add</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Transition Modal (Collect extra fields) */}
      {showTransitionModal && pendingMove && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-[#161922] border border-gray-200 dark:border-[#2a2d36] rounded-xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in-95">
            <div className="p-4 border-b border-gray-200 dark:border-[#2a2d36] flex justify-between items-center bg-gray-50 dark:bg-black/20">
              <h3 className="font-bold text-slate-900 dark:text-white flex items-center gap-2"><FileText size={16} className="text-[#00e07a]" /> Stage Requires Details</h3>
              <button onClick={() => { setShowTransitionModal(false); setPendingMove(null); }} className="text-gray-400 hover:text-slate-900 dark:hover:text-white"><X size={18} /></button>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-xs text-gray-500 mb-2">This stage auto-generates a document. Please provide any required dynamic fields:</p>
              
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">AWOL Start Date</label>
                <input 
                  type="date" 
                  value={transitionFields.awol_start_date || ""}
                  onChange={(e) => setTransitionFields({...transitionFields, awol_start_date: e.target.value})}
                  className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Deadline Days</label>
                <input 
                  type="number" 
                  placeholder="e.g. 5"
                  value={transitionFields.deadline_days || ""}
                  onChange={(e) => setTransitionFields({...transitionFields, deadline_days: e.target.value})}
                  className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                />
              </div>
              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => executeMove(pendingMove.cardId, pendingMove.toStageId, transitionFields)} className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-colors w-full">Proceed & Generate Document</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Card Detail Drawer */}
      {selectedCardId && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-40 flex justify-end">
          <div className="w-[500px] bg-white dark:bg-[#0f1422] h-full shadow-2xl border-l border-gray-200 dark:border-[#2a2d36] flex flex-col animate-in slide-in-from-right">
            <div className="p-5 border-b border-gray-200 dark:border-[#2a2d36] flex justify-between items-center bg-gray-50 dark:bg-[#0b0f1a]">
              <h2 className="text-lg font-bold font-['Space_Grotesk'] text-slate-900 dark:text-white">Case Details</h2>
              <button onClick={() => setSelectedCardId(null)} className="text-gray-400 hover:text-slate-900 dark:hover:text-white p-1"><X size={20} /></button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6 space-y-8 scrollbar-thin">
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
                        <div className="font-bold text-slate-900 dark:text-white text-lg">{cardDetails.card.full_name}</div>
                        <div className="text-sm font-mono text-gray-500">{cardDetails.card.employee_id} • {cardDetails.card.department}</div>
                      </div>
                    </div>
                  </div>

                  {/* Documents */}
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-gray-100 dark:border-white/5 pb-2 flex items-center gap-2"><FileText size={14}/> Generated Documents ({cardDetails.documents.length})</h3>
                    {cardDetails.documents.length === 0 ? (
                      <p className="text-sm text-gray-400 italic">No documents generated yet.</p>
                    ) : (
                      <div className="space-y-3">
                        {cardDetails.documents.map(doc => (
                          <div key={doc.id} className="bg-gray-50 dark:bg-[#161922] border border-gray-200 dark:border-[#2a2d36] rounded-xl overflow-hidden">
                            <div className="px-4 py-3 border-b border-gray-200 dark:border-[#2a2d36] flex justify-between items-center bg-white dark:bg-[#1a1f2e]">
                              <div>
                                <div className="font-bold text-sm text-slate-900 dark:text-white">{doc.template_name}</div>
                                <div className="text-[10px] text-gray-400 font-mono mt-0.5">{new Date(doc.created_at).toLocaleString()} • {doc.doc_type}</div>
                              </div>
                              <button 
                                onClick={() => handlePrint(doc.body, doc.template_name)}
                                className="p-1.5 bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 rounded text-slate-600 dark:text-gray-300 transition-colors"
                                title="Print / PDF"
                              >
                                <Printer size={16} />
                              </button>
                            </div>
                            <div className="p-4 max-h-[200px] overflow-y-auto scrollbar-thin bg-gray-50 dark:bg-[#0b0f1a] font-mono text-[11px] text-gray-600 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">
                              {doc.body}
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>

                  {/* Timeline */}
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-gray-100 dark:border-white/5 pb-2 flex items-center gap-2"><Clock size={14}/> Transition Timeline</h3>
                    <div className="relative pl-3 border-l-2 border-gray-200 dark:border-[#2a2d36] space-y-4 mt-4 ml-2">
                      {cardDetails.transitions.map(trx => (
                        <div key={trx.id} className="relative">
                          <div className="absolute -left-[17px] top-1 w-3 h-3 bg-white dark:bg-[#0f1422] border-2 border-[#00e07a] rounded-full"></div>
                          <div className="text-sm font-medium text-slate-900 dark:text-white">
                            Moved to <span className="text-[#00e07a]">{trx.to_stage}</span>
                          </div>
                          <div className="text-[11px] text-gray-500 mt-1">
                            {new Date(trx.created_at).toLocaleString()} • by {trx.user_name}
                          </div>
                        </div>
                      ))}
                      <div className="relative">
                        <div className="absolute -left-[17px] top-1 w-3 h-3 bg-white dark:bg-[#0f1422] border-2 border-blue-400 rounded-full"></div>
                        <div className="text-sm font-medium text-slate-900 dark:text-white">Case Created</div>
                        <div className="text-[11px] text-gray-500 mt-1">
                          {new Date(cardDetails.card.created_at).toLocaleString()}
                        </div>
                      </div>
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Global Toast Notification */}
      {toast && (
        <div className={`absolute bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-sm font-bold shadow-lg z-50 animate-in slide-in-from-bottom flex items-center gap-2 ${
          toast.isError ? "bg-red-500 text-white" : "bg-[#00e07a] text-black"
        }`}>
          {toast.isError ? <AlertCircle size={16} /> : <CheckCircle size={16} />}
          {toast.message}
        </div>
      )}

    </main>
  );
}
