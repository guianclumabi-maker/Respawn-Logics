import React, { useState, useEffect, useRef } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  FileText, 
  Plus, 
  Edit3, 
  Trash2, 
  X, 
  Save, 
  AlertCircle,
  Tag,
  AlignLeft,
  Type
} from "lucide-react";

interface Template {
  id: number;
  name: string;
  doc_type: string;
  description: string;
  body: string;
  merge_fields?: string[];
  created_at?: string;
}

const MERGE_FIELDS = [
  "{{employee_name}}",
  "{{employee_id}}",
  "{{department}}",
  "{{job_title}}",
  "{{date}}",
  "{{awol_start_date}}",
  "{{deadline_days}}"
];

export function ELRTemplates() {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editingTemplate, setEditingTemplate] = useState<Template | 'new' | null>(null);
  
  const [formData, setFormData] = useState<Partial<Template>>({});
  const [saveLoading, setSaveLoading] = useState(false);
  const [detectedFields, setDetectedFields] = useState<string[]>([]);
  
  const bodyRef = useRef<HTMLTextAreaElement>(null);

  const fetchTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=templates");
      const data = await res.json();
      if (data.success) {
        setTemplates(data.templates || []);
      } else {
        setError(data.error || "Failed to fetch templates");
      }
    } catch (err: any) {
      console.error(err);
      setError("Unable to load templates");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTemplates();
  }, []);

  const handleEdit = async (tmpl: Template) => {
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=template&id=${tmpl.id}`);
      const data = await res.json();
      if (data.success && data.template) {
        setFormData({ ...data.template });
        setDetectedFields(data.template.merge_fields || []);
        setEditingTemplate(data.template);
      } else {
        setError(data.error || "Failed to fetch template details");
      }
    } catch (err) {
      setError("Error fetching template details");
    }
  };

  const handleCreate = () => {
    setFormData({ name: "", doc_type: "", description: "", body: "" });
    setDetectedFields([]);
    setEditingTemplate('new');
  };

  const handleClose = () => {
    setEditingTemplate(null);
    setFormData({});
  };

  const insertMergeField = (field: string) => {
    const textarea = bodyRef.current;
    if (!textarea) return;

    const startPos = textarea.selectionStart;
    const endPos = textarea.selectionEnd;
    const currentBody = formData.body || "";
    
    const newBody = 
      currentBody.substring(0, startPos) + 
      field + 
      currentBody.substring(endPos);
      
    setFormData({ ...formData, body: newBody });
    
    // Update selection position after state update
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(startPos + field.length, startPos + field.length);
    }, 0);
  };

  const handleSave = async () => {
    if (!formData.name || !formData.doc_type) {
      setError("Name and Doc Type are required");
      return;
    }
    
    setSaveLoading(true);
    setError(null);
    try {
      const payload: any = { ...formData };
      if (editingTemplate !== 'new' && editingTemplate?.id) {
        payload.id = editingTemplate.id;
      }
      
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=save_template", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      
      if (data.success) {
        setDetectedFields(data.template?.merge_fields || []);
        await fetchTemplates();
        handleClose();
      } else {
        setError(data.error || "Failed to save template");
      }
    } catch (err) {
      console.error(err);
      setError("Error saving template");
    } finally {
      setSaveLoading(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this template?")) return;
    
    setSaveLoading(true);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=delete_template", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      const data = await res.json();
      
      if (data.success) {
        await fetchTemplates();
      } else {
        setError(data.error || "Failed to delete template");
      }
    } catch (err) {
      setError("Error deleting template");
    } finally {
      setSaveLoading(false);
    }
  };

  if (editingTemplate) {
    return (
      <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-background text-foreground overflow-y-auto transition-colors duration-300">
        <div className="p-8 max-w-5xl mx-auto w-full">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                {editingTemplate === 'new' ? 'Create Template' : 'Edit Template'}
              </h1>
              <p className="text-slate-500 dark:text-muted-foreground text-sm">Design document templates with dynamic merge fields.</p>
            </div>
            <div className="flex gap-3">
              <button 
                onClick={handleClose}
                className="px-4 py-2 bg-accent hover:bg-accent rounded-lg text-sm font-medium transition-colors"
              >
                Cancel
              </button>
              <button 
                onClick={handleSave}
                disabled={saveLoading}
                className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <Save size={16} />
                {saveLoading ? 'Saving...' : 'Save Template'}
              </button>
            </div>
          </div>

          {error && (
            <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
              <AlertCircle className="w-5 h-5 flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-6">
              <div className="bg-card/80 border border-border rounded-2xl p-6">
                <div className="space-y-4">
                  <div>
                    <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                      <Type size={14} /> Template Name
                    </label>
                    <input 
                      type="text" 
                      value={formData.name || ""} 
                      onChange={e => setFormData({ ...formData, name: e.target.value })}
                      className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 text-foreground"
                      placeholder="e.g. Return to Work Notice (Standard)"
                    />
                  </div>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                        <Tag size={14} /> Doc Type
                      </label>
                      <input 
                        type="text" 
                        value={formData.doc_type || ""} 
                        onChange={e => setFormData({ ...formData, doc_type: e.target.value })}
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 text-foreground"
                        placeholder="e.g. RTWN, NTE, NOD"
                      />
                    </div>
                    <div>
                      <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                        <AlignLeft size={14} /> Description
                      </label>
                      <input 
                        type="text" 
                        value={formData.description || ""} 
                        onChange={e => setFormData({ ...formData, description: e.target.value })}
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 text-foreground"
                        placeholder="Brief internal description"
                      />
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <FileText size={14} /> Document Body
                      </label>
                    </div>
                    
                    {/* Toolbar */}
                    <div className="flex flex-wrap gap-2 mb-3 p-3 bg-muted border border-border rounded-xl">
                      {MERGE_FIELDS.map(field => (
                        <button
                          key={field}
                          onClick={() => insertMergeField(field)}
                          type="button"
                          className="px-2.5 py-1 text-xs font-mono bg-primary/10 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 rounded hover:bg-blue-200 dark:hover:bg-blue-500/20 transition-colors"
                        >
                          {field}
                        </button>
                      ))}
                    </div>

                    <textarea 
                      ref={bodyRef}
                      value={formData.body || ""} 
                      onChange={e => setFormData({ ...formData, body: e.target.value })}
                      className="w-full h-[400px] bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 text-foreground font-mono leading-relaxed resize-none scrollbar-thin"
                      placeholder="Type your template here... click fields above to insert dynamic values."
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="space-y-6">
              <div className="bg-card/80 border border-border rounded-2xl p-6">
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-4">Detected Fields</h3>
                {detectedFields.length === 0 ? (
                  <p className="text-xs text-muted-foreground italic">No merge fields detected yet. Save the template to analyze.</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {detectedFields.map(field => (
                      <span key={field} className="px-2 py-1 text-xs font-mono bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 rounded">
                        {field}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-background text-foreground overflow-y-auto transition-colors duration-300">
      <div className="p-8">
        
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Document Templates
            </h1>
            <p className="text-slate-500 dark:text-muted-foreground text-sm">Manage dynamic templates for ELR cases.</p>
          </div>
          <button 
            onClick={handleCreate}
            className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors"
          >
            <Plus size={16} />
            Create Template
          </button>
        </div>

        {error && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div>
          </div>
        ) : templates.length === 0 ? (
          <div className="bg-card/80 border border-border rounded-2xl p-12 text-center">
            <FileText className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
            <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-2">No templates found</h3>
            <p className="text-sm text-muted-foreground mb-6">Create your first document template to standardize ELR case communications.</p>
            <button 
              onClick={handleCreate}
              className="px-4 py-2 bg-card border border-border hover:bg-accent rounded-lg text-sm font-medium transition-colors"
            >
              Create Template
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            {templates.map(tmpl => (
              <div 
                key={tmpl.id}
                className="bg-card border border-border hover:border-[#00e07a]/50 dark:hover:border-[#00e07a]/50 rounded-2xl p-6 transition-all group flex flex-col"
              >
                <div className="flex justify-between items-start mb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-100 dark:border-blue-500/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                      <FileText size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-slate-800 dark:text-white group-hover:text-[#00e07a] transition-colors">{tmpl.name}</h3>
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-accent text-muted-foreground dark:text-foreground border-border mt-1 inline-block">
                        {tmpl.doc_type}
                      </span>
                    </div>
                  </div>
                </div>
                
                <p className="text-sm text-muted-foreground mb-6 flex-1 line-clamp-2">
                  {tmpl.description || "No description provided."}
                </p>
                
                <div className="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/[0.05]">
                  <div className="text-xs text-muted-foreground">
                    {tmpl.merge_fields && tmpl.merge_fields.length > 0 ? (
                      <span>{tmpl.merge_fields.length} dynamic fields</span>
                    ) : (
                      <span>Static document</span>
                    )}
                  </div>
                  <div className="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button 
                      onClick={() => handleEdit(tmpl)}
                      className="p-1.5 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-muted-foreground hover:text-blue-500 rounded"
                    >
                      <Edit3 size={16} />
                    </button>
                    <button 
                      onClick={() => handleDelete(tmpl.id)}
                      className="p-1.5 hover:bg-red-50 dark:hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded"
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
