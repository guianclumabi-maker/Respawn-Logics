import { useState, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { useAuth } from "../context/AuthContext";
import { Plus, X, Rocket, Edit, Check, Wand2, Trash2, Ghost } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=surveys`;

export function Surveys() {
  const { hasPermission } = useAuth();
  const isAdmin = hasPermission("surveys.manage");
  const [surveys, setSurveys] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Modals
  const [showBuilder, setShowBuilder] = useState(false);
  const [takeSurveyId, setTakeSurveyId] = useState<number | null>(null);

  const fetchSurveys = async () => {
    setLoading(true);
    try {
      const endpoint = isAdmin ? 'fetch_admin_surveys' : 'fetch_my_surveys';
      const res = await fetch(`${API}&action=${endpoint}`, { credentials: "include" });
      const data = await res.json();
      if (data.success) setSurveys(data.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchSurveys(); }, []);

  const launchSurvey = async (id: number) => {
    if (!confirm('This will blast a push notification to ALL employees telling them to take the survey. Proceed?')) return;
    try {
      const res = await fetch(`${API}&action=launch_survey`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }), credentials: 'include'
      });
      const data = await res.json();
      if(data.success) {
        alert('Survey launched globally!');
        fetchSurveys();
      }
    } catch(err) {}
  };

  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-y-auto bg-[#0b0f1a] text-slate-200 p-8">
        <div className="max-w-7xl mx-auto space-y-8">
          
          <div className="flex justify-between items-center border-b border-white/10 pb-6">
            <div>
              <h1 className="text-3xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>Engagement Surveys</h1>
              <p className="text-slate-400 text-sm">Company-wide pulse surveys and eNPS tracking.</p>
            </div>
            {isAdmin && (
              <button onClick={() => setShowBuilder(true)} className="bg-[#00e07a] text-black px-4 py-2 rounded-lg font-bold text-sm flex items-center gap-2 hover:bg-white transition-colors">
                <Plus size={18} /> Create New Survey
              </button>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {loading ? (
              <div className="col-span-full text-center text-slate-500 py-10">Loading surveys...</div>
            ) : surveys.length === 0 ? (
              <div className="col-span-full text-center text-slate-500 py-10 bg-white/5 rounded-xl border border-white/10">No surveys available.</div>
            ) : (
              surveys.map(s => (
                <div key={s.id} className="bg-white/5 border border-white/10 rounded-2xl p-6 flex flex-col hover:-translate-y-1 hover:border-white/20 transition-all shadow-lg backdrop-blur-md">
                  {isAdmin ? (
                    <>
                      <span className={`self-start px-2 py-1 rounded-md text-xs font-bold mb-3 ${s.status === 'Draft' ? 'bg-slate-500/10 text-slate-400' : s.status === 'Active' ? 'bg-[#00e07a]/10 text-[#00e07a]' : 'bg-red-500/10 text-red-500'}`}>
                        {s.status}
                      </span>
                      <h3 className="text-xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{s.title}</h3>
                      
                      <div className="flex justify-between border-t border-white/10 pt-4 mt-auto mb-4">
                        <div className="text-center">
                          <div className={`text-2xl font-bold font-mono ${s.enps !== null && s.enps < 0 ? 'text-red-500' : 'text-[#00e07a]'}`}>{s.enps !== null ? s.enps : '--'}</div>
                          <div className="text-[10px] text-slate-500 uppercase tracking-widest">eNPS Score</div>
                        </div>
                        <div className="text-center">
                          <div className="text-2xl font-bold font-mono text-white">{s.completion_rate}%</div>
                          <div className="text-[10px] text-slate-500 uppercase tracking-widest">Completion</div>
                        </div>
                        <div className="text-center">
                          <div className="text-2xl font-bold font-mono text-white">{s.responses}</div>
                          <div className="text-[10px] text-slate-500 uppercase tracking-widest">Responses</div>
                        </div>
                      </div>

                      {s.status === 'Draft' ? (
                        <button onClick={() => launchSurvey(s.id)} className="w-full bg-[#00b8ff] text-black font-bold py-2 rounded-lg flex justify-center items-center gap-2 hover:bg-white transition-colors">
                          <Rocket size={16} /> Launch to Company
                        </button>
                      ) : (
                        <button disabled className="w-full bg-transparent border border-white/10 text-slate-500 font-bold py-2 rounded-lg">
                          Live / Tracking
                        </button>
                      )}
                    </>
                  ) : (
                    <>
                      <span className="self-start px-2 py-1 rounded-md text-xs font-bold mb-3 bg-[#00e07a]/10 text-[#00e07a]">Pending</span>
                      <h3 className="text-xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{s.title}</h3>
                      <p className="text-sm text-slate-400 mb-6 flex-grow">{s.description || 'Please take a moment to fill out this pulse survey.'}</p>
                      
                      {s.has_completed === 0 ? (
                        <button onClick={() => setTakeSurveyId(s.id)} className="w-full bg-[#00e07a] text-black font-bold py-2 rounded-lg flex justify-center items-center gap-2 hover:bg-white transition-colors">
                          <Edit size={16} /> Take Survey
                        </button>
                      ) : (
                        <button disabled className="w-full bg-white/5 border border-white/10 text-slate-400 font-bold py-2 rounded-lg flex justify-center items-center gap-2">
                          <Check size={16} /> Completed
                        </button>
                      )}
                    </>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {showBuilder && <BuilderModal onClose={() => setShowBuilder(false)} refresh={fetchSurveys} />}
      {takeSurveyId && <TakeModal surveyId={takeSurveyId} onClose={() => setTakeSurveyId(null)} refresh={fetchSurveys} />}
    </ThemeProvider>
  );
}

function BuilderModal({ onClose, refresh }: { onClose: () => void, refresh: () => void }) {
  const [title, setTitle] = useState("");
  const [desc, setDesc] = useState("");
  const [questions, setQuestions] = useState<{text: string}[]>([]);

  const save = async () => {
    if(!title) return alert('Title required');
    try {
      const res = await fetch(`${API}&action=create_survey`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, description: desc, questions }), credentials: 'include'
      });
      const data = await res.json();
      if(data.success) {
        onClose();
        refresh();
      } else alert('Error: ' + data.error);
    } catch(err) { console.error(err); }
  };

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-2xl flex flex-col max-h-[90vh]">
        <div className="p-6 border-b border-white/10 flex justify-between items-center">
          <h3 className="text-xl font-bold text-white" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>Survey Builder</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-white"><X size={20}/></button>
        </div>
        <div className="p-6 overflow-y-auto space-y-6">
          
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1">Survey Title</label>
            <input type="text" value={title} onChange={e=>setTitle(e.target.value)} placeholder="e.g. Q3 Company Pulse" className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50" />
          </div>
          <div>
            <label className="block text-xs font-medium text-slate-400 mb-1">Description (Optional)</label>
            <textarea value={desc} onChange={e=>setDesc(e.target.value)} rows={3} className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"></textarea>
          </div>

          <div className="bg-[#00e07a]/10 border border-[#00e07a]/30 rounded-lg p-4">
            <div className="flex items-center gap-2 text-[#00e07a] font-bold text-sm mb-1"><Wand2 size={16}/> Auto-Injected</div>
            <div className="text-slate-300 text-sm">The standard 0-10 eNPS question will automatically be added to this survey to calculate your score.</div>
          </div>

          <div>
            <h4 className="text-white font-bold mb-3">Custom Questions</h4>
            {questions.map((q, i) => (
              <div key={i} className="flex gap-2 mb-2">
                <input type="text" value={q.text} onChange={e => {
                  const newQ = [...questions]; newQ[i].text = e.target.value; setQuestions(newQ);
                }} placeholder="Type your question..." className="flex-1 bg-black/20 border border-white/10 rounded-md p-2 text-sm text-white focus:outline-none focus:border-[#00e07a]/50" />
                <button onClick={() => setQuestions(questions.filter((_, idx)=>idx!==i))} className="bg-transparent border border-white/10 text-slate-400 p-2 rounded-md hover:bg-red-500/20 hover:text-red-500 transition-colors"><Trash2 size={18}/></button>
              </div>
            ))}
            <button onClick={() => setQuestions([...questions, {text: ''}])} className="w-full bg-white/5 border border-white/10 text-white font-semibold py-2 rounded-md hover:bg-white/10 flex items-center justify-center gap-2 text-sm mt-2 transition-colors"><Plus size={16}/> Add Text Question</button>
          </div>

        </div>
        <div className="p-6 border-t border-white/10 flex justify-end gap-3 bg-black/20 rounded-b-xl">
          <button onClick={onClose} className="px-5 py-2 rounded-md text-sm font-semibold text-white bg-transparent border border-white/20 hover:bg-white/10">Cancel</button>
          <button onClick={save} className="px-5 py-2 rounded-md text-sm font-bold text-black bg-[#00e07a] hover:bg-white">Draft Survey</button>
        </div>
      </div>
    </div>
  );
}

function TakeModal({ surveyId, onClose, refresh }: { surveyId: number, onClose: () => void, refresh: () => void }) {
  const [data, setData] = useState<any>(null);
  const [answers, setAnswers] = useState<Record<string, string>>({});

  useEffect(() => {
    fetch(`${API}&action=fetch_survey&id=${surveyId}`, { credentials: "include" })
      .then(res => res.json())
      .then(d => { if(d.success) setData(d.data); });
  }, [surveyId]);

  const submit = async () => {
    if(!data) return;
    const ansArray = Object.keys(answers).map(qid => ({ question_id: qid, value: answers[qid] }));
    if (ansArray.length < data.questions.length || Object.values(answers).some(v => !v.trim())) {
      return alert('Please answer all questions before submitting.');
    }
    
    try {
      const res = await fetch(`${API}&action=submit_survey`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ survey_id: surveyId, answers: ansArray }), credentials: 'include'
      });
      const d = await res.json();
      if(d.success) {
        alert('Thank you! Your anonymous responses have been recorded.');
        onClose();
        refresh();
      } else alert('Error: ' + d.error);
    } catch(err) { console.error(err); }
  };

  if(!data) return null;

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-3xl flex flex-col max-h-[90vh]">
        <div className="p-6 border-b border-white/10 flex justify-between items-center">
          <h3 className="text-xl font-bold text-white" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{data.survey.title}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-white"><X size={20}/></button>
        </div>
        
        <div className="p-6 overflow-y-auto space-y-8 bg-[#0b0f1a]">
          {data.questions.map((q: any, idx: number) => (
            <div key={q.id} className="bg-white/5 border border-white/10 rounded-xl p-6">
              <div className="text-lg font-semibold text-white mb-4">{idx+1}. {q.question_text}</div>
              
              {q.question_type === 'eNPS' ? (
                <div>
                  <div className="flex flex-wrap gap-2">
                    {[0,1,2,3,4,5,6,7,8,9,10].map(val => (
                      <button key={val} onClick={() => setAnswers({...answers, [q.id]: val.toString()})} className={`w-10 h-10 rounded-md font-bold transition-colors ${answers[q.id] === val.toString() ? 'bg-[#00b8ff] text-black border-transparent' : 'bg-[#161922] text-white border border-white/20 hover:border-[#00b8ff]'}`}>
                        {val}
                      </button>
                    ))}
                  </div>
                  <div className="flex justify-between text-xs text-slate-500 mt-2 font-medium uppercase tracking-wider">
                    <span>Not likely</span><span>Extremely likely</span>
                  </div>
                </div>
              ) : (
                <textarea rows={3} value={answers[q.id] || ''} onChange={e => setAnswers({...answers, [q.id]: e.target.value})} placeholder="Type your answer here..." className="w-full bg-black/20 border border-white/10 rounded-md p-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"></textarea>
              )}
            </div>
          ))}
        </div>

        <div className="p-6 border-t border-white/10 flex items-center justify-between bg-black/20 rounded-b-xl">
          <div className="flex items-center gap-2 text-slate-400 text-sm font-medium"><EyeOff size={18}/> Your responses are 100% anonymous.</div>
          <div className="flex gap-3">
            <button onClick={onClose} className="px-5 py-2 rounded-md text-sm font-semibold text-white bg-transparent border border-white/20 hover:bg-white/10">Cancel</button>
            <button onClick={submit} className="px-5 py-2 rounded-md text-sm font-bold text-black bg-[#00e07a] hover:bg-white">Submit Anonymous Answers</button>
          </div>
        </div>
      </div>
    </div>
  );
}
