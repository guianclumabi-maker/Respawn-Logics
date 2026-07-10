import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Bot, 
  Play, 
  CheckCircle, 
  AlertCircle,
  Clock,
  Save,
  Activity,
  Plus,
  Trash2,
  Settings
} from "lucide-react";

interface Pipeline {
  id: number;
  name: string;
}

interface Stage {
  id: number;
  name: string;
}

interface DetectorParam {
  key: string;
  label: string;
  type: string;
  default: any;
}

interface Detector {
  key: string;
  label: string;
  desc: string;
  params: DetectorParam[];
}

interface AutoRule {
  id?: number;
  rule_type: string;
  name: string;
  enabled: number;
  params: Record<string, any>;
  target_pipeline_id: number | "";
  target_stage_id: number | "";
}

export function ELRAutomation() {
  const [rules, setRules] = useState<AutoRule[]>([]);
  const [detectors, setDetectors] = useState<Detector[]>([]);
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [pipelineStages, setPipelineStages] = useState<Record<number, Stage[]>>({});
  
  const [loading, setLoading] = useState(true);
  const [savingId, setSavingId] = useState<number | string | null>(null);
  const [scanning, setScanning] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const [toast, setToast] = useState<{message: string, isError: boolean} | null>(null);

  // Scan result: rule_id => { detected: number, cards_added: number, added_employees: string[] }
  const [scanResults, setScanResults] = useState<any[] | null>(null);

  useEffect(() => {
    fetchInitData();
  }, []);

  const showToast = (message: string, isError = false) => {
    setToast({ message, isError });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchInitData = async () => {
    setLoading(true);
    try {
      // 1. Fetch Pipelines
      const pipeRes = await apiFetch("/api/index.php?route=elr_pipeline&action=pipelines");
      const pipeData = await pipeRes.json();
      const loadedPipelines = pipeData.success ? pipeData.pipelines || [] : [];
      setPipelines(loadedPipelines);
      
      // Load stages for all pipelines
      const stagesMap: Record<number, Stage[]> = {};
      for (const p of loadedPipelines) {
        const stageRes = await apiFetch(`/api/index.php?route=elr_pipeline&action=pipeline&id=${p.id}`);
        const stageData = await stageRes.json();
        if (stageData.success && stageData.pipeline) {
          stagesMap[p.id] = stageData.pipeline.stages || [];
        }
      }
      setPipelineStages(stagesMap);

      // 2. Fetch Auto Rules & Detectors
      const rulesRes = await apiFetch("/api/index.php?route=elr_pipeline&action=auto_rules");
      const rulesData = await rulesRes.json();
      if (rulesData.success) {
        setDetectors(rulesData.detectors || []);
        const loadedRules = (rulesData.rules || []).map((r: any) => ({
          ...r,
          params: typeof r.params === 'string' ? JSON.parse(r.params) : (r.params || {}),
          enabled: r.enabled,
          target_pipeline_id: r.target_pipeline_id || "",
          target_stage_id: r.target_stage_id || ""
        }));
        setRules(loadedRules);
      }
    } catch (err) {
      setError("Failed to load automation rules");
    } finally {
      setLoading(false);
    }
  };

  const handleAddRule = () => {
    if (detectors.length === 0) return;
    const defaultDetector = detectors[0];
    const defaultParams: Record<string, any> = {};
    defaultDetector.params.forEach(p => {
      defaultParams[p.key] = p.default;
    });

    const newRule: AutoRule = {
      id: undefined,
      rule_type: defaultDetector.key,
      name: "New Automation Rule",
      enabled: 1,
      params: defaultParams,
      target_pipeline_id: "",
      target_stage_id: ""
    };
    setRules([newRule, ...rules]);
  };

  const handleDetectorChange = (index: number, detectorKey: string) => {
    const updatedRules = [...rules];
    const detector = detectors.find(d => d.key === detectorKey);
    if (!detector) return;
    
    const newParams: Record<string, any> = {};
    detector.params.forEach(p => {
      newParams[p.key] = p.default;
    });

    updatedRules[index].rule_type = detectorKey;
    updatedRules[index].params = newParams;
    setRules(updatedRules);
  };

  const updateRuleParam = (ruleIndex: number, paramKey: string, value: any) => {
    const updatedRules = [...rules];
    updatedRules[ruleIndex].params[paramKey] = value;
    setRules(updatedRules);
  };

  const handleSaveRule = async (index: number) => {
    const rule = rules[index];
    setSavingId(rule.id || 'new');
    setError(null);
    try {
      const payload = {
        id: rule.id,
        rule_type: rule.rule_type,
        name: rule.name,
        enabled: rule.enabled,
        params: rule.params,
        target_pipeline_id: rule.target_pipeline_id,
        target_stage_id: rule.target_stage_id
      };

      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=save_auto_rule", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast("Rule saved successfully");
        fetchInitData(); // Refresh to get ID if it was new
      } else {
        setError(data.error || "Failed to save rule");
      }
    } catch (err) {
      setError("Error saving rule");
    } finally {
      setSavingId(null);
    }
  };

  const handleDeleteRule = async (index: number) => {
    const rule = rules[index];
    if (!rule.id) {
      // It's a new unsaved rule, just remove it from UI
      const updatedRules = [...rules];
      updatedRules.splice(index, 1);
      setRules(updatedRules);
      return;
    }

    if (!confirm("Are you sure you want to delete this rule?")) return;
    
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=delete_auto_rule", {
        method: "POST",
        body: JSON.stringify({ id: rule.id })
      });
      const data = await res.json();
      if (data.success) {
        showToast("Rule deleted");
        fetchInitData();
      } else {
        setError(data.error || "Failed to delete rule");
      }
    } catch (err) {
      setError("Error deleting rule");
    }
  };

  const handleRunScan = async () => {
    setScanning(true);
    setScanResults(null);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=run_scan", {
        method: "POST"
      });
      const data = await res.json();
      if (data.success) {
        setScanResults(data.results || []);
        showToast("Scan completed successfully");
      } else {
        setError(data.error || "Failed to run scan");
      }
    } catch (err) {
      setError("Error running AWOL scan");
    } finally {
      setScanning(false);
    }
  };

  if (loading) {
    return (
      <main className="flex-1 flex items-center justify-center h-full bg-[#f4f6f8] dark:bg-background">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div>
      </main>
    );
  }

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-background text-foreground overflow-y-auto transition-colors duration-300 scrollbar-thin">
      <div className="p-8 max-w-5xl mx-auto w-full">
        
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Automation & Triggers
            </h1>
            <p className="text-slate-500 dark:text-muted-foreground text-sm">Configure multiple detection rules to automatically flag and process HR incidents.</p>
          </div>
          <div className="flex gap-3">
            <button 
              onClick={handleRunScan}
              disabled={scanning || rules.filter(r => r.enabled === 1).length === 0}
              className="px-4 py-2 bg-blue-500/10 hover:bg-blue-500/20 text-blue-500 border border-blue-500/20 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {scanning ? <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div> : <Play size={16} />}
              Run Scan Now
            </button>
            <button 
              onClick={handleAddRule}
              className="px-4 py-2 bg-[#00e07a]/10 hover:bg-[#00e07a]/20 text-[#00e07a] border border-[#00e07a]/20 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors"
            >
              <Plus size={16} />
              Add Rule
            </button>
          </div>
        </div>

        {error && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        )}

        {/* Scan Results Panel */}
        {scanResults && (
          <div className="bg-card/80 border border-blue-500/30 rounded-2xl p-6 shadow-lg mb-8 animate-in slide-in-from-top-4">
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 bg-blue-500/10 rounded-lg">
                <Activity className="text-blue-500 w-5 h-5" />
              </div>
              <h3 className="font-bold text-lg">Scan Results Summary</h3>
            </div>
            {scanResults.length === 0 ? (
              <p className="text-sm text-muted-foreground">No active rules to scan, or no matches found.</p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {scanResults.map((res: any, idx: number) => (
                  <div key={idx} className="p-4 bg-muted rounded-xl border border-border">
                    <div className="flex justify-between items-center mb-2">
                      <span className="font-bold text-sm text-[#00e07a]">{res.rule_name || "Rule"}</span>
                      <span className="text-[10px] text-muted-foreground font-mono uppercase bg-input border-border px-2 py-0.5 rounded border border-border">{res.rule_type}</span>
                    </div>
                    <div className="flex gap-4">
                      <div>
                        <div className="text-lg font-bold font-mono text-orange-400">{res.detected}</div>
                        <div className="text-[10px] text-muted-foreground uppercase tracking-wider font-bold">Detected</div>
                      </div>
                      <div>
                        <div className="text-lg font-bold font-mono text-blue-400">{res.cards_added}</div>
                        <div className="text-[10px] text-muted-foreground uppercase tracking-wider font-bold">Cards Added</div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        <div className="space-y-6">
          {rules.length === 0 ? (
            <div className="text-center py-16 bg-card/50 border border-border rounded-2xl border-dashed">
              <Bot className="w-12 h-12 text-muted-foreground mx-auto mb-4 opacity-50" />
              <h3 className="text-lg font-bold text-foreground">No automation rules</h3>
              <p className="text-sm text-muted-foreground mt-1">Add a rule to automatically flag incidents like AWOL or Tardiness.</p>
            </div>
          ) : (
            rules.map((rule, idx) => (
              <div key={rule.id || `new-${idx}`} className="bg-card/80 border border-border rounded-2xl overflow-hidden shadow-sm">
                
                {/* Rule Header */}
                <div className="p-5 border-b border-border bg-muted flex justify-between items-center">
                  <div className="flex items-center gap-3 flex-1 mr-4">
                    <div className="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 flex-shrink-0">
                      <Settings size={20} />
                    </div>
                    <div className="flex-1">
                      <input 
                        type="text"
                        value={rule.name}
                        onChange={(e) => {
                          const updated = [...rules];
                          updated[idx].name = e.target.value;
                          setRules(updated);
                        }}
                        placeholder="Rule Name"
                        className="bg-transparent text-lg font-bold text-foreground border-none focus:outline-none focus:ring-0 p-0 w-full placeholder-gray-500"
                      />
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-4">
                    <label className="flex items-center gap-2 cursor-pointer relative">
                      <span className="text-sm font-bold text-muted-foreground">{rule.enabled ? 'Enabled' : 'Disabled'}</span>
                      <input 
                        type="checkbox" 
                        className="sr-only peer" 
                        checked={rule.enabled === 1} 
                        onChange={(e) => {
                          const updated = [...rules];
                          updated[idx].enabled = e.target.checked ? 1 : 0;
                          setRules(updated);
                        }} 
                      />
                      <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[22px] after:bg-card after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#00e07a]"></div>
                    </label>

                    <button onClick={() => handleDeleteRule(idx)} className="p-2 text-muted-foreground hover:text-red-500 transition-colors">
                      <Trash2 size={18} />
                    </button>
                  </div>
                </div>

                {/* Rule Body */}
                <div className="p-6">
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    {/* Detector Config */}
                    <div className="space-y-5">
                      <div>
                        <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Detector Type</label>
                        <select
                          value={rule.rule_type}
                          onChange={(e) => handleDetectorChange(idx, e.target.value)}
                          className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50"
                        >
                          {detectors.map(d => <option key={d.key} value={d.key}>{d.label}</option>)}
                        </select>
                        <p className="text-[11px] text-muted-foreground mt-1.5">{detectors.find(d => d.key === rule.rule_type)?.desc}</p>
                      </div>

                      <div className="bg-muted p-4 rounded-xl border border-border space-y-4">
                        <h4 className="text-xs font-bold uppercase text-muted-foreground mb-2">Detector Parameters</h4>
                        {detectors.find(d => d.key === rule.rule_type)?.params.map(param => (
                          <div key={param.key}>
                            <label className="block text-xs text-muted-foreground mb-1.5">{param.label}</label>
                            <input 
                              type={param.type}
                              value={rule.params[param.key] ?? ''}
                              onChange={(e) => updateRuleParam(idx, param.key, param.type === 'number' ? parseInt(e.target.value) || 0 : e.target.value)}
                              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#00e07a]"
                            />
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Target Pipeline Config */}
                    <div className="space-y-5">
                      <div>
                        <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Target Pipeline</label>
                        <select
                          value={rule.target_pipeline_id}
                          onChange={(e) => {
                            const updated = [...rules];
                            updated[idx].target_pipeline_id = parseInt(e.target.value) || "";
                            updated[idx].target_stage_id = ""; // reset stage
                            setRules(updated);
                          }}
                          className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50"
                        >
                          <option value="">Select Pipeline...</option>
                          {pipelines.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                      </div>

                      <div>
                        <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Target Stage</label>
                        <select
                          value={rule.target_stage_id}
                          onChange={(e) => {
                            const updated = [...rules];
                            updated[idx].target_stage_id = parseInt(e.target.value) || "";
                            setRules(updated);
                          }}
                          disabled={!rule.target_pipeline_id}
                          className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 disabled:opacity-50"
                        >
                          <option value="">Select Stage...</option>
                          {(pipelineStages[rule.target_pipeline_id as number] || []).map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                      </div>

                      <div className="pt-4 flex justify-end">
                        <button 
                          onClick={() => handleSaveRule(idx)}
                          disabled={savingId === (rule.id || 'new')}
                          className="px-6 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50"
                        >
                          <Save size={16} />
                          {savingId === (rule.id || 'new') ? 'Saving...' : 'Save Rule'}
                        </button>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
            ))
          )}
        </div>

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
    </main>
  );
}
