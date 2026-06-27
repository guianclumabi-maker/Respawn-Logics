import { useState, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { useAuth } from "../context/AuthContext";
import { 
  Users, Calendar, Activity, X
} from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=performance`;

export function PerformanceAdmin() {
  const { hasPermission } = useAuth();
  const isManager = hasPermission("performance.manage"); 
  const [activeTab, setActiveTab] = useState("team");

  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-y-auto bg-[#0b0f1a] text-slate-200 p-8">
        <div className="max-w-6xl mx-auto space-y-8">
          
          <div>
            <h1 className="text-3xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>Performance & Talent Management</h1>
            <p className="text-slate-400">Evaluate your team and calibrate talent.</p>
          </div>

          <div className="flex border-b border-white/10 mb-6">
            <button 
              className={`px-6 py-3 text-sm font-semibold border-b-2 transition-colors ${activeTab === 'team' ? 'text-[#00e07a] border-[#00e07a]' : 'text-slate-400 border-transparent hover:text-white'}`}
              onClick={() => setActiveTab('team')}
            >
              <div className="flex items-center gap-2"><Users size={18} /> Team Reviews</div>
            </button>
            {isManager && (
              <>
                <button 
                  className={`px-6 py-3 text-sm font-semibold border-b-2 transition-colors ${activeTab === 'cycles' ? 'text-[#00e07a] border-[#00e07a]' : 'text-slate-400 border-transparent hover:text-white'}`}
                  onClick={() => setActiveTab('cycles')}
                >
                  <div className="flex items-center gap-2"><Calendar size={18} /> Review Cycles</div>
                </button>
                <button 
                  className={`px-6 py-3 text-sm font-semibold border-b-2 transition-colors ${activeTab === 'ninebox' ? 'text-[#00e07a] border-[#00e07a]' : 'text-slate-400 border-transparent hover:text-white'}`}
                  onClick={() => setActiveTab('ninebox')}
                >
                  <div className="flex items-center gap-2"><Activity size={18} /> 9-Box Calibration</div>
                </button>
              </>
            )}
          </div>

          <div className="bg-[#141929] border border-white/5 rounded-xl p-6">
            {activeTab === 'team' && <TeamReviews />}
            {isManager && activeTab === 'cycles' && <ReviewCycles />}
            {isManager && activeTab === 'ninebox' && <NineBoxCalibration />}
          </div>

        </div>
      </div>
    </ThemeProvider>
  );
}

function TeamReviews() {
  const [reviews, setReviews] = useState<any[]>([]);
  const [evalModal, setEvalModal] = useState<any>(null);

  const fetchReviews = async () => {
    try {
      const res = await fetch(`${API}&action=team_reviews`, { credentials: "include" });
      const data = await res.json();
      if (data.success) setReviews(data.data);
    } catch (err) { console.error(err); }
  };

  useEffect(() => { fetchReviews(); }, []);

  const handleSubmitEval = async (e: React.FormEvent, payload: any) => {
    e.preventDefault();
    try {
      const res = await fetch(`${API}&action=submit_manager_eval`, {
        method: "POST", body: JSON.stringify(payload), credentials: "include"
      });
      const data = await res.json();
      if(data.success) {
        setEvalModal(null);
        fetchReviews();
      } else { alert(data.error); }
    } catch(err) { console.error(err); }
  };

  return (
    <div>
      <h3 className="text-lg font-semibold text-white mb-6">My Team's Evaluations</h3>
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5">Employee</th>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5">Cycle</th>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5">Status</th>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {reviews.map(r => (
              <tr key={r.id} className="border-b border-white/5 hover:bg-white/5">
                <td className="p-4">
                  <div className="font-semibold text-white">{r.employee_name}</div>
                  <div className="text-xs text-slate-400">{r.job_title || 'N/A'}</div>
                </td>
                <td className="p-4 text-sm text-slate-300">{r.cycle_name}</td>
                <td className="p-4 text-sm">
                  <span className={`px-3 py-1 rounded-full text-xs font-bold ${r.status.includes('Pending') ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'}`}>
                    {r.status}
                  </span>
                </td>
                <td className="p-4 text-right">
                  {r.status === 'Pending Manager' ? (
                    <button onClick={() => setEvalModal(r)} className="bg-[#00e07a] text-black px-4 py-2 rounded-md font-bold text-xs hover:bg-white transition-colors">Evaluate</button>
                  ) : (
                    <span className="text-slate-500 text-xs font-medium">Done ({r.overall_score_1_to_5}/5)</span>
                  )}
                </td>
              </tr>
            ))}
            {reviews.length === 0 && <tr><td colSpan={4} className="p-4 text-center text-slate-500 text-sm">No team reviews found.</td></tr>}
          </tbody>
        </table>
      </div>
      {evalModal && <EvalModal review={evalModal} onClose={() => setEvalModal(null)} onSubmit={handleSubmitEval} />}
    </div>
  );
}

function EvalModal({ review, onClose, onSubmit }: { review: any, onClose: () => void, onSubmit: (e:any, payload:any) => void }) {
  const [score, setScore] = useState(review.overall_score_1_to_5 || "");
  const [perf, setPerf] = useState(review.nine_box_performance || "2");
  const [pot, setPot] = useState(review.nine_box_potential || "2");
  const [comments, setComments] = useState(review.manager_comments || "");

  const submit = (e: React.FormEvent) => {
    onSubmit(e, {
      review_id: review.id,
      overall_score_1_to_5: score,
      nine_box_performance: perf,
      nine_box_potential: pot,
      manager_comments: comments
    });
  };

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-lg flex flex-col max-h-[90vh]">
        <div className="p-6 border-b border-white/10 flex justify-between items-center">
          <h3 className="text-lg font-bold text-white">Evaluate Employee</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-white"><X size={20}/></button>
        </div>
        <div className="p-6 overflow-y-auto">
          <div className="bg-white/5 p-4 rounded-lg mb-6">
            <h4 className="text-sm font-semibold text-white mb-2">Employee's Self Evaluation</h4>
            <p className="text-sm text-slate-400 italic">{review.self_comments || 'No self evaluation submitted.'}</p>
          </div>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1">Overall Score (1.0 to 5.0)</label>
              <input type="number" step="0.1" min="1" max="5" value={score} onChange={e=>setScore(e.target.value)} required className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">9-Box: Performance</label>
                <select value={perf} onChange={e=>setPerf(e.target.value)} required className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50">
                  <option value="1">1 - Below</option>
                  <option value="2">2 - Meets</option>
                  <option value="3">3 - Exceeds</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">9-Box: Potential</label>
                <select value={pot} onChange={e=>setPot(e.target.value)} required className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50">
                  <option value="1">1 - Low</option>
                  <option value="2">2 - Moderate</option>
                  <option value="3">3 - High</option>
                </select>
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1">Manager Comments (Final)</label>
              <textarea rows={4} value={comments} onChange={e=>setComments(e.target.value)} required placeholder="Constructive feedback..." className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"></textarea>
            </div>
            <button type="submit" className="w-full bg-[#00e07a] text-black font-bold py-3 rounded-md hover:bg-white transition-colors">Finalize Review</button>
          </form>
        </div>
      </div>
    </div>
  );
}

function ReviewCycles() {
  const [cycles, setCycles] = useState<any[]>([]);
  const [showModal, setShowModal] = useState(false);

  const fetchCycles = async () => {
    try {
      const res = await fetch(`${API}&action=cycles`, { credentials: "include" });
      const data = await res.json();
      if (data.success) setCycles(data.data);
    } catch (err) { console.error(err); }
  };

  useEffect(() => { fetchCycles(); }, []);

  const createCycle = async (e: React.FormEvent, payload: any) => {
    e.preventDefault();
    try {
      const res = await fetch(`${API}&action=create_cycle`, {
        method: "POST", body: JSON.stringify(payload), credentials: "include"
      });
      if((await res.json()).success) { setShowModal(false); fetchCycles(); }
    } catch(err) {}
  };

  const deploy = async (id: number) => {
    if(!confirm("Deploy review shells to all active employees and managers?")) return;
    try {
      const res = await fetch(`${API}&action=initialize_reviews`, {
        method: "POST", body: JSON.stringify({cycle_id: id}), credentials: "include"
      });
      if((await res.json()).success) { alert("Successfully deployed."); fetchCycles(); }
    } catch(err) {}
  };

  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-lg font-semibold text-white">Company Review Cycles</h3>
        <button onClick={()=>setShowModal(true)} className="bg-[#00e07a] text-black px-4 py-2 rounded-md font-bold text-sm hover:bg-white transition-colors">Create Cycle</button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5">Name</th>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5">Period</th>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5">Status</th>
              <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {cycles.map(c => (
              <tr key={c.id} className="border-b border-white/5 hover:bg-white/5">
                <td className="p-4 font-semibold text-white">{c.name}</td>
                <td className="p-4 text-sm text-slate-300">{c.start_date} to {c.end_date}</td>
                <td className="p-4 text-sm">
                  <span className="px-3 py-1 rounded-full text-xs font-bold bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/20">{c.status}</span>
                </td>
                <td className="p-4 text-right">
                  <button onClick={() => deploy(c.id)} className="bg-transparent border border-white/20 text-white px-3 py-1.5 rounded text-xs hover:bg-white/10 transition-colors">Deploy Reviews to Company</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {showModal && <CreateCycleModal onClose={() => setShowModal(false)} onSubmit={createCycle} />}
    </div>
  );
}

function CreateCycleModal({ onClose, onSubmit }: { onClose: () => void, onSubmit: (e:any, p:any) => void }) {
  const [name, setName] = useState("");
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  
  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-sm flex flex-col">
        <div className="p-6 border-b border-white/10 flex justify-between items-center">
          <h3 className="text-lg font-bold text-white">Create Cycle</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-white"><X size={20}/></button>
        </div>
        <div className="p-6">
          <form onSubmit={e => onSubmit(e, { name, start_date: start, end_date: end })} className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1">Cycle Name</label>
              <input type="text" value={name} onChange={e=>setName(e.target.value)} required placeholder="e.g. Q3 2026 Annual" className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50" />
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1">Start Date</label>
              <input type="date" value={start} onChange={e=>setStart(e.target.value)} required className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50" />
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1">End Date</label>
              <input type="date" value={end} onChange={e=>setEnd(e.target.value)} required className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50" />
            </div>
            <button type="submit" className="w-full bg-[#00e07a] text-black font-bold py-3 rounded-md hover:bg-white transition-colors">Create</button>
          </form>
        </div>
      </div>
    </div>
  );
}

function NineBoxCalibration() {
  const [cycles, setCycles] = useState<any[]>([]);
  const [selectedCycle, setSelectedCycle] = useState("");
  const [data, setData] = useState<any[]>([]);

  useEffect(() => {
    fetch(`${API}&action=cycles`, { credentials: "include" })
      .then(res => res.json())
      .then(d => {
        if(d.success) {
          setCycles(d.data);
          if(d.data.length > 0) setSelectedCycle(d.data[0].id);
        }
      });
  }, []);

  useEffect(() => {
    if(!selectedCycle) return;
    fetch(`${API}&action=nine_box_data&cycle_id=${selectedCycle}`, { credentials: "include" })
      .then(res => res.json())
      .then(d => {
        if(d.success) setData(d.data);
      });
  }, [selectedCycle]);

  const getBoxEmps = (perf: number, pot: number) => {
    return data.filter(d => parseInt(d.perf) === perf && parseInt(d.pot) === pot);
  };

  const getInitials = (name: string) => {
    return name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
  };

  const Box = ({ p, pt, title, color }: { p:number, pt:number, title:string, color:string }) => (
    <div className={`rounded-xl p-3 flex flex-wrap gap-2 content-start min-h-[140px] border ${color}`}>
      <div className="w-full text-center text-[0.65rem] font-bold uppercase tracking-wider text-white/50 mb-2">{title}</div>
      {getBoxEmps(p, pt).map((emp, i) => (
        <div key={i} title={`${emp.full_name} - Score: ${emp.overall_score_1_to_5}`} className="w-8 h-8 rounded-full bg-[#222] text-white border-2 border-white flex items-center justify-center text-xs font-bold shadow-lg hover:scale-110 hover:z-10 transition-transform cursor-pointer">
          {getInitials(emp.full_name)}
        </div>
      ))}
    </div>
  );

  return (
    <div>
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-lg font-semibold text-white">Talent Grid (9-Box)</h3>
        <select value={selectedCycle} onChange={e=>setSelectedCycle(e.target.value)} className="bg-black/20 border border-white/10 rounded-md p-2 text-sm text-white focus:outline-none focus:border-[#00e07a]/50">
          {cycles.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>

      <div className="relative p-8 bg-black/20 rounded-xl border border-white/5 overflow-hidden">
        <div className="grid grid-cols-[50px_1fr_1fr_1fr] grid-rows-[1fr_1fr_1fr_50px] gap-3 h-[600px]">
          <div className="col-start-1 row-start-1 row-end-4 flex items-center justify-center">
            <div className="text-slate-500 font-bold uppercase tracking-widest text-xs -rotate-90 whitespace-nowrap">Potential (Future Leader)</div>
          </div>
          
          <Box p={1} pt={3} title="Enigma" color="bg-amber-500/10 border-amber-500/20" />
          <Box p={2} pt={3} title="Growth Employee" color="bg-[#00e07a]/10 border-[#00e07a]/20" />
          <Box p={3} pt={3} title="Future Leader" color="bg-[#00e07a]/20 border-[#00e07a]/40" />

          <Box p={1} pt={2} title="Dilemma" color="bg-red-500/10 border-red-500/20" />
          <Box p={2} pt={2} title="Core Employee" color="bg-blue-500/10 border-blue-500/20" />
          <Box p={3} pt={2} title="High Impact" color="bg-[#00e07a]/10 border-[#00e07a]/20" />

          <Box p={1} pt={1} title="Risk" color="bg-red-500/20 border-red-500/40" />
          <Box p={2} pt={1} title="Solid Professional" color="bg-amber-500/10 border-amber-500/20" />
          <Box p={3} pt={1} title="Trusted Pro" color="bg-blue-500/10 border-blue-500/20" />

          <div className="col-start-2 col-end-5 row-start-4 flex items-center justify-center">
            <div className="text-slate-500 font-bold uppercase tracking-widest text-xs">Performance (Current Output)</div>
          </div>
        </div>
      </div>
    </div>
  );
}
