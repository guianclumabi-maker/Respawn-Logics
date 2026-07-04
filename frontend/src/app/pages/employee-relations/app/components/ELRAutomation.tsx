import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Settings, 
  Bot, 
  Play, 
  CheckCircle, 
  AlertCircle,
  Clock,
  Save,
  Activity
} from "lucide-react";

interface Pipeline {
  id: number;
  name: string;
}

interface Stage {
  id: number;
  name: string;
}

export function ELRAutomation() {
  const [enabled, setEnabled] = useState(false);
  const [consecutiveDays, setConsecutiveDays] = useState(3);
  const [targetPipelineId, setTargetPipelineId] = useState<number | "">("");
  const [targetStageId, setTargetStageId] = useState<number | "">("");
  
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [stages, setStages] = useState<Stage[]>([]);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const [toast, setToast] = useState<{message: string, isError: boolean} | null>(null);

  const [scanResult, setScanResult] = useState<{
    scanned: number;
    awol_detected: number;
    cards_added: number;
    added_employees: string[];
  } | null>(null);

  useEffect(() => {
    fetchInitData();
  }, []);

  useEffect(() => {
    if (targetPipelineId) {
      fetchStages(targetPipelineId as number);
    } else {
      setStages([]);
      setTargetStageId("");
    }
  }, [targetPipelineId]);

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
      if (pipeData.success) {
        setPipelines(pipeData.pipelines || []);
      }
      
      // 2. Fetch Auto Rules
      const rulesRes = await apiFetch("/api/index.php?route=elr_pipeline&action=auto_rules");
      const rulesData = await rulesRes.json();
      if (rulesData.success && rulesData.rules) {
        const awolRule = rulesData.rules.find((r: any) => r.rule_key === "awol");
        if (awolRule) {
          const config = typeof awolRule.config === 'string' ? JSON.parse(awolRule.config) : awolRule.config;
          setEnabled(awolRule.is_active === 1);
          setConsecutiveDays(config.consecutive_days || 3);
          setTargetPipelineId(config.target_pipeline_id || "");
          
          if (config.target_pipeline_id) {
            await fetchStages(config.target_pipeline_id);
            setTargetStageId(config.target_target_id || config.target_stage_id || ""); // config uses target_stage_id ideally
          }
        }
      }
    } catch (err) {
      setError("Failed to load automation rules");
    } finally {
      setLoading(false);
    }
  };

  const fetchStages = async (pipelineId: number) => {
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=pipeline&id=${pipelineId}`);
      const data = await res.json();
      if (data.success && data.pipeline) {
        setStages(data.pipeline.stages || []);
      }
    } catch (err) {
      console.error(err);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    try {
      const payload = {
        rule_key: "awol",
        name: "Auto-AWOL Detection",
        is_active: enabled ? 1 : 0,
        config: {
          consecutive_days: consecutiveDays,
          target_pipeline_id: targetPipelineId,
          target_stage_id: targetStageId
        }
      };

      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=save_auto_rule", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast("Automation rules saved successfully");
      } else {
        setError(data.error || "Failed to save rule");
      }
    } catch (err) {
      setError("Error saving rule");
    } finally {
      setSaving(false);
    }
  };

  const handleRunScan = async () => {
    setScanning(true);
    setScanResult(null);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr_pipeline&action=run_scan", {
        method: "POST"
      });
      const data = await res.json();
      if (data.success) {
        setScanResult({
          scanned: data.scanned || 0,
          awol_detected: data.awol_detected || 0,
          cards_added: data.cards_added || 0,
          added_employees: data.added_employees || []
        });
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
      <main className="flex-1 flex items-center justify-center h-full bg-[#f4f6f8] dark:bg-[#06070a]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div>
      </main>
    );
  }

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#06070a] text-slate-900 dark:text-white overflow-y-auto transition-colors duration-300">
      <div className="p-8 max-w-4xl mx-auto w-full">
        
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Automation & Triggers
            </h1>
            <p className="text-slate-500 dark:text-slate-400 text-sm">Configure system routines to automatically generate cases based on data signals.</p>
          </div>
        </div>

        {error && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        )}

        <div className="bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] rounded-2xl overflow-hidden shadow-sm mb-8">
          
          <div className="p-5 border-b border-gray-200 dark:border-[#2a2d36] bg-gray-50 dark:bg-[#161922]/50 flex justify-between items-center">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-[#00e07a]/10 border border-[#00e07a]/20 flex items-center justify-center text-[#00e07a]">
                <Bot size={20} />
              </div>
              <div>
                <h2 className="text-lg font-bold">Auto-AWOL Detection</h2>
                <p className="text-xs text-gray-500 dark:text-gray-400">Scans attendance logs to automatically flag absent employees.</p>
              </div>
            </div>
            
            <label className="flex items-center gap-2 cursor-pointer relative">
              <span className="text-sm font-bold text-gray-500">{enabled ? 'Enabled' : 'Disabled'}</span>
              <input 
                type="checkbox" 
                className="sr-only peer" 
                checked={enabled} 
                onChange={(e) => setEnabled(e.target.checked)} 
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:right-[22px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-[#00e07a]"></div>
            </label>
          </div>

          <div className="p-6 space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Consecutive Absent Days Threshold</label>
                  <input 
                    type="number" 
                    min="1"
                    value={consecutiveDays}
                    onChange={(e) => setConsecutiveDays(parseInt(e.target.value) || 1)}
                    className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50"
                  />
                  <p className="text-[11px] text-gray-500 mt-1">Number of consecutive days an employee must be absent to trigger this rule.</p>
                </div>
              </div>

              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Target Pipeline</label>
                  <select
                    value={targetPipelineId}
                    onChange={(e) => setTargetPipelineId(parseInt(e.target.value) || "")}
                    className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50"
                  >
                    <option value="">Select Pipeline...</option>
                    {pipelines.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Target Stage</label>
                  <select
                    value={targetStageId}
                    onChange={(e) => setTargetStageId(parseInt(e.target.value) || "")}
                    disabled={!targetPipelineId}
                    className="w-full bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-[#2a2d36] rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#00e07a] focus:ring-1 focus:ring-[#00e07a]/50 disabled:opacity-50"
                  >
                    <option value="">Select Stage...</option>
                    {stages.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </div>
              </div>
            </div>

            <div className="flex justify-between items-center pt-6 border-t border-gray-200 dark:border-[#2a2d36]">
              <div className="flex items-center gap-3">
                <button 
                  onClick={handleRunScan}
                  disabled={scanning || !enabled}
                  className="px-4 py-2 bg-blue-500/10 hover:bg-blue-500/20 text-blue-500 border border-blue-500/20 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {scanning ? <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div> : <Play size={16} />}
                  Run Scan Now
                </button>
                <span className="text-[11px] text-gray-500 flex items-center gap-1">
                  <Clock size={12} /> Requires manual trigger (no cron available on current tier)
                </span>
              </div>

              <button 
                onClick={handleSave}
                disabled={saving}
                className="px-6 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black rounded-lg text-sm font-bold flex items-center gap-2 transition-colors disabled:opacity-50"
              >
                <Save size={16} />
                {saving ? 'Saving...' : 'Save Configuration'}
              </button>
            </div>
          </div>
        </div>

        {/* Scan Results Panel */}
        {scanResult && (
          <div className="bg-white dark:bg-[#0f1422]/80 border border-emerald-500/30 rounded-2xl p-6 shadow-[0_0_20px_rgba(16,185,129,0.05)] animate-in slide-in-from-bottom-4">
            <div className="flex items-center gap-3 mb-6">
              <div className="p-2 bg-emerald-500/10 rounded-lg">
                <Activity className="text-emerald-500 w-5 h-5" />
              </div>
              <h3 className="font-bold text-lg">Scan Results Summary</h3>
            </div>

            <div className="grid grid-cols-3 gap-4 mb-6">
              <div className="p-4 bg-gray-50 dark:bg-[#161922] rounded-xl border border-gray-200 dark:border-[#2a2d36]">
                <div className="text-2xl font-bold font-mono">{scanResult.scanned}</div>
                <div className="text-xs text-gray-500 uppercase tracking-wider font-bold mt-1">Employees Scanned</div>
              </div>
              <div className="p-4 bg-gray-50 dark:bg-[#161922] rounded-xl border border-gray-200 dark:border-[#2a2d36]">
                <div className="text-2xl font-bold font-mono text-orange-400">{scanResult.awol_detected}</div>
                <div className="text-xs text-gray-500 uppercase tracking-wider font-bold mt-1">AWOL Detected</div>
              </div>
              <div className="p-4 bg-gray-50 dark:bg-[#161922] rounded-xl border border-gray-200 dark:border-[#2a2d36]">
                <div className="text-2xl font-bold font-mono text-[#00e07a]">{scanResult.cards_added}</div>
                <div className="text-xs text-gray-500 uppercase tracking-wider font-bold mt-1">Cards Added to Pipeline</div>
              </div>
            </div>

            {scanResult.added_employees.length > 0 ? (
              <div>
                <h4 className="text-sm font-bold mb-3 border-b border-gray-200 dark:border-[#2a2d36] pb-2">Added Employees</h4>
                <ul className="grid grid-cols-2 gap-2">
                  {scanResult.added_employees.map((emp, idx) => (
                    <li key={idx} className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-[#161922]/50 px-3 py-2 rounded-lg">
                      <CheckCircle size={14} className="text-[#00e07a]" /> {emp}
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <div className="text-sm text-gray-500 italic">No new employees were added to the pipeline.</div>
            )}
          </div>
        )}

      </div>

      {/* Global Toast */}
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
