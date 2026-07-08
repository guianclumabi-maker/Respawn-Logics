import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  GitBranch, 
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
  ArrowLeft
} from "lucide-react";

interface Pipeline {
  id: number;
  name: string;
  description: string;
  stage_count?: number;
  active_cases?: number;
}

interface Template {
  id: number;
  name: string;
}

interface Stage {
  id: number;
  pipeline_id?: number;
  name: string;
  order_index: number;
  sla_days: number | "";
  is_terminal: number;
  template_id: number | "";
}

export function ELRPipelines() {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  // View states: 'list' | 'edit_pipeline' | 'edit_stages'
  const [view, setView] = useState<'list' | 'edit_pipeline' | 'edit_stages'>('list');
  
  const [currentPipeline, setCurrentPipeline] = useState<Partial<Pipeline>>({});
  const [saveLoading, setSaveLoading] = useState(false);
  
  const [stages, setStages] = useState<Stage[]>([]);
  const [templates, setTemplates] = useState<Template[]>([]);
  const [editingStage, setEditingStage] = useState<Partial<Stage> | null>(null);

  const fetchPipelines = async () => {
    setLoading(true);
    setError(null);
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

  const fetchTemplates = async () => {
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=templates");
      const data = await res.json();
      if (data.success) {
        setTemplates(data.templates || []);
      }
    } catch (err) {
      console.error(err);
    }
  };

  const fetchStages = async (pipelineId: number) => {
    setLoading(true);
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=pipeline&id=${pipelineId}`);
      const data = await res.json();
      if (data.success) {
        setStages(data.pipeline?.stages || []);
      } else {
        setError(data.error || "Failed to fetch stages");
      }
    } catch (err) {
      setError("Unable to load stages");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPipelines();
    fetchTemplates();
  }, []);

  const handleCreatePipeline = () => {
    setCurrentPipeline({ name: "", description: "" });
    setView('edit_pipeline');
  };

  const handleEditPipeline = (pipe: Pipeline) => {
    setCurrentPipeline({ ...pipe });
    setView('edit_pipeline');
  };

  const handleOpenStages = async (pipe: Pipeline) => {
    setCurrentPipeline(pipe);
    await fetchStages(pipe.id);
    setView('edit_stages');
  };

  const handleSavePipeline = async () => {
    if (!currentPipeline.name) {
      setError("Pipeline name is required");
      return;
    }
    
    setSaveLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=save_pipeline", {
        method: "POST",
        body: JSON.stringify(currentPipeline)
      });
      const data = await res.json();
      
      if (data.success) {
        await fetchPipelines();
        setView('list');
      } else {
        setError(data.error || "Failed to save pipeline");
      }
    } catch (err) {
      setError("Error saving pipeline");
    } finally {
      setSaveLoading(false);
    }
  };

  const handleDeletePipeline = async (id: number) => {
    if (!confirm("Are you sure you want to delete this pipeline?")) return;
    
    setSaveLoading(true);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=delete_pipeline", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      const data = await res.json();
      
      if (data.success) {
        await fetchPipelines();
      } else {
        setError(data.error || "Failed to delete pipeline");
      }
    } catch (err) {
      setError("Error deleting pipeline");
    } finally {
      setSaveLoading(false);
    }
  };

  const handleAddStage = () => {
    setEditingStage({
      pipeline_id: currentPipeline.id,
      name: "",
      order_index: stages.length, 
      sla_days: "", 
      is_terminal: 0, 
      template_id: "" 
    });
  };

  const handleEditStage = (stage: Stage) => {
    setEditingStage({ ...stage, pipeline_id: currentPipeline.id });
  };

  const handleSaveStage = async () => {
    if (!editingStage?.name) {
      setError("Stage name is required");
      return;
    }
    
    setSaveLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=save_stage", {
        method: "POST",
        body: JSON.stringify(editingStage)
      });
      const data = await res.json();
      
      if (data.success) {
        if (currentPipeline.id) await fetchStages(currentPipeline.id);
        setEditingStage(null);
      } else {
        setError(data.error || "Failed to save stage");
      }
    } catch (err) {
      setError("Error saving stage");
    } finally {
      setSaveLoading(false);
    }
  };

  const handleDeleteStage = async (id: number) => {
    if (!confirm("Are you sure you want to delete this stage?")) return;
    
    setSaveLoading(true);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=delete_stage", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      const data = await res.json();
      
      if (data.success) {
        if (currentPipeline.id) await fetchStages(currentPipeline.id);
      } else {
        setError(data.error || "Failed to delete stage");
      }
    } catch (err) {
      setError("Error deleting stage");
    } finally {
      setSaveLoading(false);
    }
  };

  // ------------------------------------------------------------------
  // RENDER: PIPELINE LIST
  // ------------------------------------------------------------------
  if (view === 'list') {
    return (
      <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-y-auto transition-colors duration-300">
        <div className="p-8">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                Pipelines & Workflows
              </h1>
              <p className="text-slate-500 dark:text-slate-400 text-sm">Manage case progression paths and automated stage logic.</p>
            </div>
            <button 
              onClick={handleCreatePipeline}
              className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors"
            >
              <Plus size={16} />
              New Pipeline
            </button>
          </div>

          {error && (
            <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
              <AlertCircle className="w-5 h-5 flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {loading ? (
            <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div></div>
          ) : pipelines.length === 0 ? (
            <div className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] rounded-2xl p-12 text-center">
              <GitBranch className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
              <h3 className="text-lg font-bold mb-2">No pipelines configured</h3>
              <p className="text-sm text-gray-500 mb-6">Create a pipeline to structure your employee relations cases.</p>
              <button 
                onClick={handleCreatePipeline}
                className="px-4 py-2 bg-white dark:bg-white/[0.05] border border-gray-200 dark:border-white/[0.1] hover:bg-gray-50 dark:hover:bg-white/[0.1] rounded-lg text-sm font-medium transition-colors"
              >
                Create Pipeline
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {pipelines.map(pipe => (
                <div key={pipe.id} className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] hover:border-[#00e07a]/50 dark:hover:border-[#00e07a]/50 rounded-2xl p-6 transition-all group flex flex-col cursor-pointer" onClick={() => handleOpenStages(pipe)}>
                  <div className="flex justify-between items-start mb-4">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-500/10 border border-purple-100 dark:border-purple-500/20 flex items-center justify-center text-purple-600 dark:text-purple-400">
                        <GitBranch size={20} />
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
                      <div className="flex items-center gap-1.5 text-xs font-mono text-muted-foreground">
                        <ListOrdered size={14} /> {pipe.stage_count || 0} stages
                      </div>
                      <div className="flex items-center gap-1.5 text-xs font-mono text-muted-foreground">
                        <CheckCircle size={14} /> {pipe.active_cases || 0} active
                      </div>
                    </div>
                    <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button 
                        onClick={(e) => { e.stopPropagation(); handleEditPipeline(pipe); }}
                        className="p-1.5 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-gray-400 hover:text-blue-500 rounded"
                      >
                        <Settings size={16} />
                      </button>
                      <button 
                        onClick={(e) => { e.stopPropagation(); handleDeletePipeline(pipe.id); }}
                        className="p-1.5 hover:bg-red-50 dark:hover:bg-red-500/10 text-gray-400 hover:text-red-500 rounded"
                      >
                        <Trash2 size={16} />
                      </button>
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
  // RENDER: EDIT PIPELINE DETAILS
  // ------------------------------------------------------------------
  if (view === 'edit_pipeline') {
    return (
      <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-y-auto transition-colors duration-300">
        <div className="p-8 max-w-3xl mx-auto w-full">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                {currentPipeline.id ? 'Edit Pipeline Settings' : 'Create Pipeline'}
              </h1>
            </div>
            <div className="flex gap-3">
              <button 
                onClick={() => setView('list')}
                className="px-4 py-2 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.1] rounded-lg text-sm font-medium transition-colors"
              >
                Cancel
              </button>
              <button 
                onClick={handleSavePipeline}
                disabled={saveLoading}
                className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50"
              >
                <Save size={16} />
                {saveLoading ? 'Saving...' : 'Save'}
              </button>
            </div>
          </div>

          {error && (
            <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
              <AlertCircle className="w-5 h-5 flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}

          <div className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] rounded-2xl p-6 space-y-4">
            <div>
              <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                <Type size={14} /> Pipeline Name
              </label>
              <input 
                type="text" 
                value={currentPipeline.name || ""} 
                onChange={e => setCurrentPipeline({ ...currentPipeline, name: e.target.value })}
                className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 text-slate-900 dark:text-white"
                placeholder="e.g. Standard Disciplinary Flow"
              />
            </div>
            <div>
              <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                <AlignLeft size={14} /> Description
              </label>
              <textarea 
                value={currentPipeline.description || ""} 
                onChange={e => setCurrentPipeline({ ...currentPipeline, description: e.target.value })}
                className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 text-slate-900 dark:text-white resize-none h-32"
                placeholder="Brief internal description"
              />
            </div>
          </div>
        </div>
      </main>
    );
  }

  // ------------------------------------------------------------------
  // RENDER: EDIT STAGES
  // ------------------------------------------------------------------
  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-y-auto transition-colors duration-300 relative">
      <div className="p-8 max-w-5xl mx-auto w-full">
        <div className="flex items-center justify-between mb-8">
          <div className="flex items-center gap-4">
            <button 
              onClick={() => setView('list')}
              className="p-2 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.1] rounded-lg text-gray-500 dark:text-gray-400 transition-colors"
            >
              <ArrowLeft size={20} />
            </button>
            <div>
              <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                Pipeline: {currentPipeline.name}
              </h1>
              <p className="text-slate-500 dark:text-slate-400 text-sm">Configure stages and automated document triggers.</p>
            </div>
          </div>
          <button 
            onClick={handleAddStage}
            className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors"
          >
            <Plus size={16} />
            Add Stage
          </button>
        </div>

        {error && !editingStage && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div></div>
        ) : stages.length === 0 ? (
          <div className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] rounded-2xl p-12 text-center">
            <ListOrdered className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
            <h3 className="text-lg font-bold mb-2">No stages defined</h3>
            <p className="text-sm text-gray-500 mb-6">Build your workflow by adding the first stage.</p>
          </div>
        ) : (
          <div className="space-y-4 relative">
            {/* Connecting line */}
            <div className="absolute left-[23px] top-6 bottom-6 w-[2px] bg-gray-200 dark:bg-[#2a2d36] z-0"></div>
            
            {stages.map((stage, idx) => (
              <div key={stage.id} className="relative z-10 flex gap-4 items-start group">
                <div className="w-12 h-12 rounded-full bg-[#f4f6f8] dark:bg-[#0b0f1a] border-4 border-[#f4f6f8] dark:border-[#0b0f1a] flex items-center justify-center flex-shrink-0">
                  <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ${stage.is_terminal ? 'bg-red-500/20 text-red-500' : 'bg-blue-500/20 text-blue-500'}`}>
                    {idx + 1}
                  </div>
                </div>
                <div className="flex-1 bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] hover:border-[#00e07a]/50 rounded-xl p-5 transition-all">
                  <div className="flex justify-between items-start">
                    <div>
                      <div className="flex items-center gap-3 mb-1">
                        <h3 className="font-bold text-lg">{stage.name}</h3>
                        {stage.is_terminal ? (
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-red-500/10 text-red-500 border-red-500/20">Terminal</span>
                        ) : null}
                      </div>
                      <div className="flex gap-4 text-xs font-mono text-gray-500 mt-2">
                        <span className="flex items-center gap-1"><ListOrdered size={14} /> Order: {stage.order_index}</span>
                        <span className="flex items-center gap-1"><Clock size={14} /> SLA: {stage.sla_days} days</span>
                        {stage.template_id && (
                          <span className="flex items-center gap-1 text-emerald-500"><FileText size={14} /> Auto-Document Trigger</span>
                        )}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <button 
                        onClick={() => handleEditStage(stage)}
                        className="px-3 py-1.5 bg-gray-100 dark:bg-white/[0.05] hover:bg-gray-200 dark:hover:bg-white/[0.1] rounded text-sm transition-colors font-medium"
                      >
                        Edit
                      </button>
                      <button 
                        onClick={() => handleDeleteStage(stage.id)}
                        className="p-1.5 hover:bg-red-50 dark:hover:bg-red-500/10 text-gray-400 hover:text-red-500 rounded transition-colors"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* STAGE EDIT MODAL (Slide Over / Overlay) */}
      {editingStage && (
        <div className="absolute inset-0 bg-black/50 backdrop-blur-sm z-50 flex justify-end">
          <div className="w-[450px] bg-white dark:bg-[#0f1422] h-full shadow-2xl border-l border-gray-200 dark:border-[#2a2d36] flex flex-col transform transition-transform duration-300">
            <div className="p-6 border-b border-gray-200 dark:border-[#2a2d36] flex justify-between items-center bg-gray-50 dark:bg-[#0b0f1a]">
              <h2 className="text-lg font-bold" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                {editingStage.id ? 'Edit Stage' : 'Add Stage'}
              </h2>
            </div>
            
            <div className="p-6 flex-1 overflow-y-auto space-y-5">
              {error && (
                <div className="p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                  <span>{error}</span>
                </div>
              )}
              
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Stage Name</label>
                <input 
                  type="text" 
                  value={editingStage.name || ""} 
                  onChange={e => setEditingStage({ ...editingStage, name: e.target.value })}
                  className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50"
                  placeholder="e.g. Notice to Explain"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Order Index</label>
                  <input 
                    type="number"
                    value={editingStage.order_index}
                    onChange={e => setEditingStage({...editingStage, order_index: parseInt(e.target.value) || 0})}
                    className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#00e07a]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">SLA Days</label>
                  <input 
                    type="number" 
                    value={editingStage.sla_days || 0} 
                    onChange={e => setEditingStage({ ...editingStage, sla_days: parseInt(e.target.value) || 0 })}
                    className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#00e07a]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Auto-Generate Document (Optional)</label>
                <select 
                  value={editingStage.template_id}
                  onChange={e => setEditingStage({...editingStage, template_id: parseInt(e.target.value) || ""})}
                  className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[#00e07a]"
                >
                  <option value="">None (Manual processing)</option>
                  {templates.map(t => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </select>
                <p className="text-[11px] text-gray-500 mt-1">If selected, this document is automatically generated when a case enters this stage.</p>
              </div>

              <div className="pt-2">
                <label className="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-lg">
                  <input 
                    type="checkbox" 
                    checked={!!editingStage.is_terminal} 
                    onChange={e => setEditingStage({ ...editingStage, is_terminal: e.target.checked ? 1 : 0 })}
                    className="w-4 h-4 rounded border-gray-300 text-[#00e07a] focus:ring-[#00e07a]"
                  />
                  <div>
                    <span className="block text-sm font-bold">Terminal Stage</span>
                    <span className="block text-[11px] text-gray-500">Entering this stage will close the case.</span>
                  </div>
                </label>
              </div>

            </div>

            <div className="p-5 border-t border-gray-200 dark:border-[#2a2d36] bg-gray-50 dark:bg-[#0b0f1a] flex justify-end gap-3">
              <button 
                onClick={() => { setEditingStage(null); setError(null); }}
                className="px-4 py-2 bg-gray-200 dark:bg-white/[0.05] hover:bg-gray-300 dark:hover:bg-white/[0.1] rounded-lg text-sm font-medium transition-colors"
              >
                Cancel
              </button>
              <button 
                onClick={handleSaveStage}
                disabled={saveLoading}
                className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50"
              >
                {saveLoading ? 'Saving...' : 'Save Stage'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
