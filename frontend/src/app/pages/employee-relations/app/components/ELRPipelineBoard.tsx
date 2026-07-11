import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Kanban, Plus, Settings, Trash2, Save, AlertCircle, Clock,
  CheckCircle, FileText, AlignLeft, Type, ListOrdered, ArrowLeft,
  Eye, X, FileDown, Printer, ChevronDown, ChevronUp, Edit2, Check, Search, Filter,
  ArrowUp, ArrowDown, Move, LayoutList, Columns3
} from "lucide-react";
import { ELRCaseDrawer } from "./ELRCaseDrawer";

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
  order_index: number;
  sla_days: number;
  is_terminal: number | boolean;
  template_id: number | null;
}

interface Card {
  id: number;
  employee_id: string;
  full_name: string;
  department: string;
  current_stage_id: number;
  doc_count: number;
  created_at: string;
}

export function ELRPipelineBoard() {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [currentPipelineId, setCurrentPipelineId] = useState<number | null>(null);
  
  const [stages, setStages] = useState<Stage[]>([]);
  const [cards, setCards] = useState<Card[]>([]);
  
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters
  const [searchQuery, setSearchQuery] = useState("");
  const [filterDepartment, setFilterDepartment] = useState("");
  const [filterStatus, setFilterStatus] = useState("");

  // Add Card Modal
  const [showAddCard, setShowAddCard] = useState(false);
  const [addCardEmployeeId, setAddCardEmployeeId] = useState("");
  const [addCardStageId, setAddCardStageId] = useState<number | "">("");
  
  // Card Detail Drawer
  const [selectedCardId, setSelectedCardId] = useState<number | null>(null);

  // Transition Fields Modal
  const [pendingMove, setPendingMove] = useState<{cardId: number, toStageId: number} | null>(null);
  const [transitionFields, setTransitionFields] = useState<{awol_start_date?: string, deadline_days?: string}>({});
  const [showTransitionModal, setShowTransitionModal] = useState(false);

  // Manage Phases Modal
  const [showManagePhases, setShowManagePhases] = useState(false);
  const [manageStages, setManageStages] = useState<Stage[]>([]);
  const [newStageName, setNewStageName] = useState("");
  const [editingStageId, setEditingStageId] = useState<number | null>(null);
  const [editStageName, setEditStageName] = useState("");

  // Toast
  const [toast, setToast] = useState<{message: string, isError: boolean} | null>(null);

  // HUD states
  const [viewMode, setViewMode] = useState<"table" | "kanban">(() => {
    try {
      const saved = localStorage.getItem("elr_view_mode");
      return (saved === "table" || saved === "kanban") ? saved : "kanban";
    } catch {
      return "kanban";
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem("elr_view_mode", viewMode);
    } catch {}
  }, [viewMode]);

  const [activeStage, setActiveStage] = useState<number | "All">("All");
  
  type SortKey = "full_name" | "employee_id" | "stage" | "department" | "doc_count" | "created_at";
  const [sortKey, setSortKey] = useState<SortKey>("full_name");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("asc");

  useEffect(() => {
    fetchPipelines();
  }, []);

  useEffect(() => {
    if (currentPipelineId) {
      fetchBoard(currentPipelineId);
    }
  }, [currentPipelineId]);

  useEffect(() => {
    if (showManagePhases) {
      setManageStages([...stages].sort((a, b) => a.order_index - b.order_index));
    }
  }, [showManagePhases, stages]);

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
        if (data.pipelines && data.pipelines.length > 0 && !currentPipelineId) {
          setCurrentPipelineId(data.pipelines[0].id);
        }
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
        showToast("Case created in pipeline");
        setShowAddCard(false);
        setAddCardEmployeeId("");
        setAddCardStageId("");
        fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to create case", true);
      }
    } catch (err) {
      showToast("Error creating case", true);
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
    
    const card = cards.find(c => c.id === cardId);
    if (card && card.current_stage_id === toStageId) return;

    const toStage = stages.find(s => s.id === toStageId);
    if (toStage && toStage.template_id) {
      setPendingMove({ cardId, toStageId });
      setTransitionFields({});
      setShowTransitionModal(true);
    } else {
      executeMove(cardId, toStageId, {});
    }
  };

  const executeMove = async (cardId: number, toStageId: number, fields: any) => {
    const toStage = stages.find(s => s.id === toStageId);
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
        if (data.generated_document) {
          showToast(`Moved to ${toStage?.name}. Document generated: ${data.generated_document.title}`);
        } else {
          showToast("Case moved successfully");
        }
        if (currentPipelineId) fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to move case", true);
      }
    } catch (err) {
      showToast("Error moving case", true);
    } finally {
      setShowTransitionModal(false);
      setPendingMove(null);
    }
  };

  const handleSaveStage = async (stage: Partial<Stage>) => {
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=save_stage", {
        method: "POST",
        body: JSON.stringify({
          id: stage.id,
          pipeline_id: currentPipelineId,
          name: stage.name,
          stage_order: stage.order_index,
          is_terminal: stage.is_terminal ? 1 : 0
        })
      });
      const data = await res.json();
      if (data.success) {
        if (currentPipelineId) fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to save stage", true);
      }
    } catch (err) {
      showToast("Error saving stage", true);
    }
  };

  const handleDeleteStage = async (id: number) => {
    if (!confirm("Are you sure you want to delete this stage? All cases in it might be orphaned.")) return;
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=delete_stage", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      const data = await res.json();
      if (data.success) {
        if (currentPipelineId) fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to delete stage", true);
      }
    } catch (err) {
      showToast("Error deleting stage", true);
    }
  };

  const handleAddNewStage = async () => {
    if (!newStageName.trim() || !currentPipelineId) return;
    const order_index = manageStages.length > 0 ? Math.max(...manageStages.map(s => s.order_index)) + 1 : 1;
    await handleSaveStage({
      pipeline_id: currentPipelineId,
      name: newStageName.trim(),
      order_index,
      is_terminal: 0
    });
    setNewStageName("");
  };

  const handleStageDragStart = (e: React.DragEvent, index: number) => {
    e.dataTransfer.setData("stageIndex", index.toString());
  };

  const handleStageDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleStageDrop = (e: React.DragEvent, toIndex: number) => {
    e.preventDefault();
    const fromIndex = parseInt(e.dataTransfer.getData("stageIndex"));
    if (isNaN(fromIndex) || fromIndex === toIndex) return;

    const newStages = [...manageStages];
    const [moved] = newStages.splice(fromIndex, 1);
    newStages.splice(toIndex, 0, moved);
    
    // Update order_index locally so they sort properly
    newStages.forEach((s, idx) => {
      s.order_index = idx;
    });
    setManageStages(newStages);
  };

  const handleSaveOrder = async () => {
    if (!currentPipelineId) return;
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=reorder_stages", {
        method: "POST",
        body: JSON.stringify({
          pipeline_id: currentPipelineId,
          order: manageStages.map(s => s.id)
        })
      });
      const data = await res.json();
      if (data.success) {
        showToast("Phase order saved");
        fetchBoard(currentPipelineId);
      } else {
        showToast(data.error || "Failed to save order", true);
      }
    } catch (err) {
      showToast("Error saving order", true);
    }
  };

  // ------------------------------------------------------------------
  // RENDER: BOARD VIEW
  // ------------------------------------------------------------------
  const currentPipeline = pipelines.find(p => p.id === currentPipelineId);
  const grouped: Record<number, Card[]> = {};

  const departments = Array.from(new Set(cards.map(c => c.department))).filter(Boolean);
  
  const filteredCards = cards.filter(card => {
    const stage = stages.find(s => s.id === card.current_stage_id);
    const isTerminal = stage?.is_terminal === 1 || stage?.is_terminal === true;
    
    if (activeStage !== "All" && card.current_stage_id !== activeStage) return false;
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      if (!card.full_name.toLowerCase().includes(q) && !card.employee_id.toLowerCase().includes(q)) return false;
    }
    if (filterDepartment && card.department !== filterDepartment) return false;
    if (filterStatus) {
      if (filterStatus === "active" && isTerminal) return false;
      if (filterStatus === "closed" && !isTerminal) return false;
    }
    return true;
  });

  const sortedCards = [...filteredCards].sort((a, b) => {
    let cmp = 0;
    if (sortKey === "stage") {
      const stageA = stages.find(s => s.id === a.current_stage_id)?.name || "";
      const stageB = stages.find(s => s.id === b.current_stage_id)?.name || "";
      cmp = stageA.localeCompare(stageB);
    } else {
      const av = a[sortKey];
      const bv = b[sortKey];
      if (typeof av === "number" && typeof bv === "number") {
        cmp = av - bv;
      } else {
        cmp = String(av || "").localeCompare(String(bv || ""));
      }
    }
    return sortDir === "asc" ? cmp : -cmp;
  });

  const toggleSort = (key: SortKey) => {
    if (sortKey === key) setSortDir(d => d === "asc" ? "desc" : "asc");
    else { setSortKey(key); setSortDir("asc"); }
  };

  const SortIcon = ({ col }: { col: SortKey }) => {
    if (sortKey !== col) return <ChevronDown size={11} className="text-muted-foreground" />;
    return sortDir === "asc" ? <ChevronUp size={11} className="text-[#00e07a]" /> : <ChevronDown size={11} className="text-[#00e07a]" />;
  };

  const handleStageSelectChange = (cardId: number, toStageId: number) => {
    const card = cards.find(c => c.id === cardId);
    if (card && card.current_stage_id === toStageId) return;

    const toStage = stages.find(s => s.id === toStageId);
    if (toStage && toStage.template_id) {
      setPendingMove({ cardId, toStageId });
      setTransitionFields({});
      setShowTransitionModal(true);
    } else {
      executeMove(cardId, toStageId, {});
    }
  };

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-background text-foreground overflow-hidden transition-colors duration-300 relative font-mono">
      
      {/* Header */}
      <div className="flex-none px-6 py-4 border-b border-border bg-card/50 backdrop-blur-md flex items-center justify-between">
        <div className="flex items-center gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground font-['Space_Grotesk'] flex items-center gap-1.5">
              {pipelines.length > 1 ? (
                <select
                  value={currentPipelineId || ""}
                  onChange={(e) => setCurrentPipelineId(parseInt(e.target.value))}
                  className="bg-transparent font-bold border-none outline-none cursor-pointer focus:ring-0 p-0 text-2xl text-foreground font-['Space_Grotesk']"
                >
                  {pipelines.map(p => (
                    <option key={p.id} value={p.id} className="text-black dark:text-white bg-card">{p.name}</option>
                  ))}
                </select>
              ) : (
                currentPipeline?.name || "Pipeline Board"
              )}
              <span className="inline-block w-2 h-4 bg-[#00e07a] blink"></span>
            </h1>
          </div>
        </div>
        <div className="flex items-center gap-3">
          {/* View toggle */}
          <div className="flex items-center rounded-xl border border-border overflow-hidden bg-card/[0.01]">
            <button onClick={() => setViewMode("table")}
              className={`flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold transition-all cursor-pointer border-0 ${viewMode === "table" ? "bg-[#00e07a]/10 border-r border-[#00e07a]/20 text-[#00e07a]" : "text-muted-foreground hover:text-foreground border-r border-border bg-transparent"}`}>
              <LayoutList size={14} /> [ HUD TABLE ]
            </button>
            <button onClick={() => setViewMode("kanban")}
              className={`flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold transition-all cursor-pointer border-0 ${viewMode === "kanban" ? "bg-[#00e07a]/10 text-[#00e07a]" : "text-muted-foreground hover:text-foreground bg-transparent"}`}>
              <Columns3 size={14} /> [ HUD KANBAN ]
            </button>
          </div>
          <button 
            onClick={() => setShowManagePhases(true)}
            className="px-4 py-2.5 bg-transparent border border-border text-muted-foreground hover:text-foreground hover:bg-accent font-bold rounded-xl text-xs transition-all flex items-center gap-2 cursor-pointer"
          >
            <Settings size={14} /> [ MANAGE PHASES ]
          </button>
          <button 
            onClick={() => setShowAddCard(true)}
            className="px-4 py-2.5 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-xl text-xs transition-all flex items-center gap-2 cursor-pointer shadow-lg shadow-green-500/10 border-0"
          >
            <Plus size={14} className="text-black" /> [ CREATE CASE ]
          </button>
        </div>
      </div>

      {/* Stage-count HUD header row */}
      <div className="flex-none flex gap-2 overflow-x-auto pb-2 pt-2 items-center scrollbar-thin px-6 border-b border-border bg-card/25 backdrop-blur-md">
        <button
          onClick={() => setActiveStage("All")}
          className={`px-3 py-1.5 rounded-lg border text-xs font-bold transition-all cursor-pointer ${
            activeStage === "All"
              ? "bg-[#00e07a]/10 border-[#00e07a] text-[#00e07a]"
              : "border-border text-muted-foreground hover:text-foreground bg-transparent"
          }`}
        >
          {`[ ALL CASES: ${cards.length} ]`}
        </button>
        {stages.map(stage => {
          const count = cards.filter(c => c.current_stage_id === stage.id).length;
          const isActive = activeStage === stage.id;
          return (
            <button
              key={stage.id}
              onClick={() => setActiveStage(stage.id)}
              className={`px-3 py-1.5 rounded-lg border text-xs font-bold transition-all cursor-pointer whitespace-nowrap ${
                isActive
                  ? "bg-[#00e07a]/10 border-[#00e07a] text-[#00e07a]"
                  : "border-border text-muted-foreground hover:text-foreground bg-transparent"
              }`}
            >
              {`[ ${stage.name.toUpperCase()}: ${count} ]`}
            </button>
          );
        })}
      </div>

      {/* Filter Bar */}
      <div className="flex-none px-6 py-3 border-b border-border bg-muted/20 flex flex-wrap gap-4 items-center">
        <div className="relative">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <input 
            type="text" 
            placeholder="Search by name or ID..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9 pr-4 py-1.5 text-xs bg-background border border-border rounded-full focus:outline-none focus:border-[#00e07a] transition-colors w-64 text-foreground placeholder-gray-600 font-mono"
          />
        </div>
        <div className="flex items-center gap-2 text-xs">
          <span className="text-muted-foreground">// DEPT:</span>
          <select
            value={filterDepartment}
            onChange={(e) => setFilterDepartment(e.target.value)}
            className="bg-background border border-border rounded-full px-3 py-1.5 text-xs focus:outline-none focus:border-[#00e07a] text-foreground font-mono"
          >
            <option value="">ALL DEPARTMENTS</option>
            {departments.map(d => <option key={d} value={d}>{d.toUpperCase()}</option>)}
          </select>
        </div>
        <div className="flex items-center gap-2 text-xs">
          <span className="text-muted-foreground">// STATUS:</span>
          <select
            value={filterStatus}
            onChange={(e) => setFilterStatus(e.target.value)}
            className="bg-background border border-border rounded-full px-3 py-1.5 text-xs focus:outline-none focus:border-[#00e07a] text-foreground font-mono"
          >
            <option value="">ALL CASES</option>
            <option value="active">ACTIVE CASES</option>
            <option value="closed">CLOSED CASES</option>
          </select>
        </div>
        <div className="ml-auto text-[10px] text-muted-foreground flex-shrink-0">
          <span className="font-bold text-foreground">{filteredCards.length}</span> CASES FOUND
        </div>
      </div>

      {/* Main Content Area */}
      <div className="flex-1 overflow-auto p-6 relative z-10 scrollbar-thin">
        {loading && stages.length === 0 ? (
          <div className="flex-1 flex items-center justify-center py-20">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div>
          </div>
        ) : error ? (
          <div className="flex-1 flex items-center justify-center flex-col gap-4 text-red-400 py-20">
            <AlertCircle size={32} />
            <p>{error}</p>
          </div>
        ) : cards.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center w-full">
            <div className="w-16 h-16 rounded-2xl bg-[#00e07a]/10 border border-[#00e07a]/20 flex items-center justify-center mb-4">
              <FileText size={28} className="text-[#00e07a]" />
            </div>
            <h3 className="text-sm font-bold text-foreground mb-1 font-['Space_Grotesk']">NO CASES ADDED</h3>
            <p className="text-xs text-muted-foreground max-w-xs mb-4">Add a case to begin.</p>
            <button onClick={() => setShowAddCard(true)} className="px-4 py-2.5 rounded-xl text-xs font-bold bg-[#00e07a] hover:bg-[#00c96a] text-black cursor-pointer border-0">
              <Plus size={12} className="inline mr-1 text-black" />[ CREATE CASE ]
            </button>
          </div>
        ) : viewMode === "table" ? (
          /* ─── Table View ─── */
          <div className="rounded-xl border border-border overflow-hidden bg-card backdrop-blur-md shadow-2xl">
            <table className="w-full">
              <thead>
                <tr className="text-[9px] font-mono font-bold uppercase tracking-wider border-b"
                  style={{ backgroundColor: "var(--card)", color: "#8b95a8", borderColor: "var(--border)" }}>
                  {([["full_name", "// NAME"], ["stage", "// PHASE"], ["department", "// DEPARTMENT"], ["doc_count", "// DOCUMENTS"], ["created_at", "// SUMMONED"]] as [SortKey, string][]).map(([key, label]) => (
                    <th key={key} className="px-4 py-3 text-left cursor-pointer hover:text-[#00e07a] transition-colors" onClick={() => toggleSort(key)}>
                      <div className="flex items-center gap-1">{label} <SortIcon col={key} /></div>
                    </th>
                  ))}
                  <th className="px-4 py-3 text-left">// ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {sortedCards.length === 0 ? (
                  <tr><td colSpan={6} className="px-4 py-12 text-center text-xs text-muted-foreground font-mono">No cases match the target filters.</td></tr>
                ) : sortedCards.map(card => {
                  const stage = stages.find(s => s.id === card.current_stage_id);
                  return (
                    <tr key={card.id} className="border-b border-border transition-colors hover:bg-muted font-mono"
                      style={{ borderColor: "var(--border)" }}>
                      <td className="px-4 py-3">
                        <button onClick={() => setSelectedCardId(card.id)}
                          className="text-xs font-bold text-cyan-400 hover:text-cyan-300 hover:underline decoration-dotted underline-offset-2 cursor-pointer text-left border-0 bg-transparent p-0">{card.full_name}</button>
                        <div className="text-[10px] text-muted-foreground mt-0.5">{card.employee_id}</div>
                      </td>
                      <td className="px-4 py-3">
                        <select value={card.current_stage_id || ""} onChange={e => handleStageSelectChange(card.id, parseInt(e.target.value))}
                          className="text-[9px] font-mono font-bold rounded px-2.5 py-1 bg-[#00e07a]/10 border border-[#00e07a]/20 text-[#00e07a] outline-none cursor-pointer">
                          {stages.map(s => <option key={s.id} value={s.id} className="bg-card text-foreground">{s.name}</option>)}
                        </select>
                      </td>
                      <td className="px-4 py-3 text-xs text-muted-foreground">{card.department || "—"}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                          <FileText size={12} />
                          <span>{card.doc_count || 0} Docs</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-xs text-muted-foreground">{new Date(card.created_at).toLocaleDateString()}</td>
                      <td className="px-4 py-3">
                        <button onClick={() => setSelectedCardId(card.id)}
                          className="text-[9px] text-muted-foreground hover:text-foreground px-2.5 py-1 rounded bg-muted border border-border hover:bg-accent cursor-pointer transition-colors">[ VIEW ]</button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            <div className="px-4 py-3 border-t border-border text-[10px] text-muted-foreground font-mono">
              COMPILING {sortedCards.length} OF {cards.length} CASES
            </div>
          </div>
        ) : (
          /* ─── Kanban View ─── */
          <div className="flex gap-6 overflow-x-auto pb-4 scrollbar-thin select-none h-full items-start">
            {stages
              .filter(s => activeStage === "All" || s.id === activeStage)
              .map(stage => {
                grouped[stage.id] = filteredCards.filter((c: any) => c.current_stage_id === stage.id);
                return (
                  <div 
                    key={stage.id} 
                    className="w-[320px] flex-shrink-0 flex flex-col max-h-[70vh] bg-muted rounded-xl border border-border"
                    onDragOver={handleDragOver}
                    onDrop={(e) => handleDrop(e, stage.id)}
                  >
                    {/* Column Header */}
                    <div className="p-3 border-b border-border flex justify-between items-center bg-card/50 rounded-t-xl">
                      <div className="flex items-center gap-2">
                        <h3 className="font-bold text-sm tracking-tight text-foreground uppercase">{stage.name}</h3>
                        <span className="bg-accent text-xs font-mono px-2 py-0.5 rounded-full text-muted-foreground">
                          {grouped[stage.id].length}
                        </span>
                      </div>
                      {stage.is_terminal === 1 && (
                        <span className="w-2 h-2 rounded-full bg-red-500" title="Terminal Stage" />
                      )}
                    </div>
                    
                    {/* Column Body */}
                    <div className="flex-1 p-3 overflow-y-auto space-y-3 scrollbar-thin max-h-[60vh]">
                      {grouped[stage.id].map(card => (
                        <div 
                          key={card.id}
                          draggable
                          onDragStart={(e) => handleDragStart(e, card.id)}
                          onClick={() => setSelectedCardId(card.id)}
                          className="bg-card border border-border hover:border-[#00e07a]/50 rounded-lg p-4 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-all group"
                        >
                          <div className="flex justify-between items-start mb-2">
                            <span className="font-bold text-sm text-foreground group-hover:text-[#00e07a] transition-colors">{card.full_name}</span>
                          </div>
                          <div className="text-xs text-muted-foreground font-mono mb-3">
                            {card.employee_id} • {card.department}
                          </div>
                          <div className="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-white/5">
                            <div className="flex items-center gap-1.5 text-[10px] uppercase font-bold text-muted-foreground">
                              <FileText size={12} /> {card.doc_count || 0} Docs
                            </div>
                            <span className="text-[10px] text-muted-foreground">
                              {new Date(card.created_at).toLocaleDateString()}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })}
          </div>
        )}
      </div>

      {/* MODALS AND OVERLAYS */}

      {/* Manage Phases Modal */}
      {showManagePhases && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col max-h-[80vh] font-mono">
            <div className="p-4 border-b border-border flex justify-between items-center bg-muted">
              <h3 className="font-bold text-foreground flex items-center gap-2"><Settings size={16} className="text-[#00e07a]" /> [ MANAGE PHASES ]</h3>
              <button onClick={() => setShowManagePhases(false)} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
            </div>
            
            <div className="p-5 overflow-y-auto flex-1 space-y-3">
              {manageStages.map((stage, index) => (
                <div 
                  key={stage.id} 
                  draggable
                  onDragStart={(e) => handleStageDragStart(e, index)}
                  onDragOver={handleStageDragOver}
                  onDrop={(e) => handleStageDrop(e, index)}
                  className="flex items-center justify-between p-3 bg-muted border border-border rounded-lg cursor-grab active:cursor-grabbing"
                >
                  <div className="flex items-center gap-3">
                    <div className="text-muted-foreground hover:text-foreground cursor-grab">
                      <Move size={14} />
                    </div>
                    {editingStageId === stage.id ? (
                      <div className="flex items-center gap-2">
                        <input 
                          type="text" 
                          value={editStageName}
                          onChange={(e) => setEditStageName(e.target.value)}
                          className="bg-card border border-border rounded px-2 py-1 text-sm focus:outline-none focus:border-[#00e07a]"
                        />
                        <button 
                          onClick={() => {
                            handleSaveStage({...stage, name: editStageName});
                            setEditingStageId(null);
                            setManageStages(manageStages.map(s => s.id === stage.id ? {...s, name: editStageName} : s));
                          }} 
                          className="text-[#00e07a] hover:text-[#00c96a]"
                        >
                          <Check size={16} />
                        </button>
                        <button onClick={() => setEditingStageId(null)} className="text-red-400 hover:text-red-300">
                          <X size={16} />
                        </button>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-sm">{stage.name}</span>
                        {stage.is_terminal === 1 && <span className="text-[10px] bg-red-500/20 text-red-400 px-1.5 py-0.5 rounded font-bold uppercase">Terminal</span>}
                        <button onClick={() => { setEditingStageId(stage.id); setEditStageName(stage.name); }} className="text-muted-foreground hover:text-foreground ml-2">
                          <Edit2 size={14} />
                        </button>
                      </div>
                    )}
                  </div>
                  <button onClick={() => handleDeleteStage(stage.id)} className="text-muted-foreground hover:text-red-500">
                    <Trash2 size={16} />
                  </button>
                </div>
              ))}
            </div>

            <div className="p-4 border-t border-border bg-muted flex flex-col gap-3">
              <div className="flex justify-between items-center w-full mb-2">
                <span className="text-xs text-muted-foreground">Drag to reorder, then click Save.</span>
                <button 
                  onClick={handleSaveOrder}
                  className="px-4 py-1.5 bg-accent hover:bg-accent text-slate-800 dark:text-white font-semibold rounded-lg text-sm transition-colors whitespace-nowrap"
                >
                  Save Order
                </button>
              </div>
              <div className="flex items-center gap-3 w-full">
                <input 
                  type="text" 
                  placeholder="New phase name..."
                  value={newStageName}
                  onChange={(e) => setNewStageName(e.target.value)}
                  onKeyDown={(e) => e.key === "Enter" && handleAddNewStage()}
                  className="flex-1 bg-background border border-border rounded-lg py-2 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                />
                <button 
                  onClick={handleAddNewStage}
                  className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-colors whitespace-nowrap border-0 cursor-pointer"
                >
                  Add Phase
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Add Card Modal */}
      {showAddCard && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl w-full max-w-sm shadow-2xl overflow-hidden font-mono">
            <div className="p-4 border-b border-border flex justify-between items-center bg-muted">
              <h3 className="font-bold text-foreground">[ CREATE CASE ]</h3>
              <button onClick={() => setShowAddCard(false)} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
            </div>
            <form onSubmit={handleAddCard} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Employee ID</label>
                <input 
                  type="text" 
                  required
                  value={addCardEmployeeId}
                  onChange={(e) => setAddCardEmployeeId(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                  placeholder="e.g. EMP-001"
                />
              </div>
              {stages.length > 0 && (
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Initial Stage (Optional)</label>
                  <select 
                    value={addCardStageId}
                    onChange={(e) => setAddCardStageId(parseInt(e.target.value) || "")}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a] text-foreground font-mono" 
                  >
                    <option value="">-- Default (First Stage) --</option>
                    {stages.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </div>
              )}
              <div className="pt-2 flex justify-end gap-3 font-mono">
                <button type="button" onClick={() => setShowAddCard(false)} className="px-3 py-1.5 text-muted-foreground hover:text-foreground text-sm font-semibold border-0 bg-transparent cursor-pointer">[ CANCEL ]</button>
                <button type="submit" className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-colors border-0 cursor-pointer">[ CREATE CASE ]</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Transition Modal (Collect extra fields) */}
      {showTransitionModal && pendingMove && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border rounded-xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 font-mono">
            <div className="p-4 border-b border-border flex justify-between items-center bg-muted">
              <h3 className="font-bold text-foreground flex items-center gap-2"><FileText size={16} className="text-[#00e07a]" /> Stage Requires Details</h3>
              <button onClick={() => { setShowTransitionModal(false); setPendingMove(null); }} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
            </div>
            <div className="p-5 space-y-4">
              <p className="text-xs text-muted-foreground mb-2">This stage auto-generates a document. Please provide any required dynamic fields:</p>
              
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">AWOL Start Date</label>
                <input 
                  type="date" 
                  value={transitionFields.awol_start_date || ""}
                  onChange={(e) => setTransitionFields({...transitionFields, awol_start_date: e.target.value})}
                  className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                />
              </div>
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Deadline Days</label>
                <input 
                  type="number" 
                  placeholder="e.g. 5"
                  value={transitionFields.deadline_days || ""}
                  onChange={(e) => setTransitionFields({...transitionFields, deadline_days: e.target.value})}
                  className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-sm focus:outline-none focus:border-[#00e07a]" 
                />
              </div>
              <div className="pt-2 flex justify-end gap-3">
                <button type="button" onClick={() => executeMove(pendingMove.cardId, pendingMove.toStageId, transitionFields)} className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-colors w-full border-0 cursor-pointer">[ PROCEED & GENERATE ]</button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Card Detail Drawer */}
      {selectedCardId && (
        <ELRCaseDrawer 
          cardId={selectedCardId} 
          onClose={() => setSelectedCardId(null)} 
          onUpdate={() => {
            if (currentPipelineId) fetchBoard(currentPipelineId);
          }}
        />
      )}

      {/* Global Toast Notification */}
      {toast && (
        <div className={`absolute bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-sm font-bold shadow-lg z-50 animate-in slide-in-from-bottom flex items-center gap-2 ${
          toast.isError ? "bg-red-500 text-foreground" : "bg-[#00e07a] text-black"
        }`}>
          {toast.isError ? <AlertCircle size={16} /> : <CheckCircle size={16} />}
          {toast.message}
        </div>
      )}

    </main>
  );
}
