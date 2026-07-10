import { useState, useRef, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { apiFetch } from "../lib/apiClient";
import { 
  Bot, 
  Send, 
  BookOpen, 
  Scale, 
  AlertTriangle, 
  Info, 
  Loader2, 
  ExternalLink,
  Sparkles,
  User,
  Trash2
} from "lucide-react";

interface Source {
  type: "reference" | "precedent";
  title: string;
  reference: string;
  url?: string;
  risk_level?: string;
}

interface QAPair {
  question: string;
  answer: string;
  grounded: boolean;
  sources: Source[];
  web_fallback?: boolean;
}

const SUGGESTIONS = [
  "What is the due process for terminating an employee?",
  "How many hours of overtime can an employee work per day?",
  "What are the statutory requirements for maternity leave under PH law?",
  "How is 13th month pay computed?"
];

export function ElrCopilotContent() {
  const [question, setQuestion] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [transcript, setTranscript] = useState<QAPair[]>([]);
  
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [transcript, loading]);

  const handleAsk = async (textToSubmit: string) => {
    const query = textToSubmit.trim();
    if (!query) return;

    setLoading(true);
    setError(null);

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=copilot", {
        method: "POST",
        body: JSON.stringify({ question: query })
      });

      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      
      if (data.success) {
        const newQA: QAPair = {
          question: query,
          answer: data.answer,
          grounded: data.grounded,
          sources: data.sources || [],
          web_fallback: data.web_fallback || false
        };
        setTranscript(prev => [...prev, newQA]);
        setQuestion("");
      } else {
        setError(data.error || "Failed to process query.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while contacting the Copilot API.");
    } finally {
      setLoading(false);
    }
  };

  const getRiskColor = (risk?: string) => {
    switch (risk) {
      case "Critical": return "bg-red-500/20 text-red-400 border-red-500/30";
      case "High": return "bg-orange-500/20 text-orange-400 border-orange-500/30";
      case "Medium": return "bg-yellow-500/20 text-yellow-400 border-yellow-500/30";
      default: return "bg-blue-500/20 text-blue-400 border-blue-500/30";
    }
  };

  const parseMarkdown = (text: string) => {
    if (!text) return null;
    const lines = text.split("\n");
    return lines.map((line, idx) => {
      let content = line;
      // Basic bold formatting **text**
      content = content.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
      
      if (line.startsWith("- ") || line.startsWith("* ")) {
        return (
          <li 
            key={idx} 
            className="ml-5 list-disc text-foreground my-1 font-sans text-sm" 
            dangerouslySetInnerHTML={{ __html: content.substring(2) }} 
          />
        );
      }
      
      if (line.trim() === "") {
        return <div key={idx} className="h-3" />;
      }
      
      return (
        <p 
          key={idx} 
          className="mb-3 leading-relaxed text-foreground text-sm font-sans" 
          dangerouslySetInnerHTML={{ __html: content }} 
        />
      );
    });
  };

  return (
    <div className="flex-1 flex flex-col bg-background text-foreground p-6 h-full overflow-hidden">
      {/* Top Header */}
      <div className="flex items-center justify-between mb-6 flex-none">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center shadow-[0_0_15px_rgba(6,182,212,0.15)]">
            <Bot className="text-cyan-400" />
          </div>
          <div>
            <h1 className="text-xl font-bold font-['Space_Grotesk']">ELR Labor Relations Copilot</h1>
            <p className="text-sm text-muted-foreground">Philippine labor law Q&A grounded on DOLE references and SC jurisprudence</p>
          </div>
        </div>
        {transcript.length > 0 && (
          <button 
            onClick={() => setTranscript([])}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-card/50 hover:bg-red-500/15 hover:text-red-400 border border-border hover:border-red-500/20 rounded-lg text-xs text-muted-foreground transition-all cursor-pointer font-sans"
          >
            <Trash2 size={13} /> Clear Session
          </button>
        )}
      </div>

      {/* Main Workspace (Split / Scrollable view) */}
      <div className="flex-1 flex flex-col overflow-hidden bg-card border border-border rounded-2xl relative shadow-2xl">
        
        {/* Transcript History */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6 scrollbar-thin">
          {transcript.map((qa, index) => (
            <div key={index} className="space-y-4">
              
              {/* Question bubble */}
              <div className="flex gap-4 max-w-[85%] ml-auto flex-row-reverse">
                <div className="w-8 h-8 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center flex-shrink-0">
                  <User size={15} />
                </div>
                <div className="p-4 rounded-2xl bg-purple-500/5 border border-purple-500/10 rounded-tr-sm text-gray-100 text-sm font-sans shadow-md">
                  {qa.question}
                </div>
              </div>

              {/* Answer bubble */}
              <div className="flex gap-4 max-w-[90%] mr-auto">
                <div className="w-8 h-8 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center flex-shrink-0">
                  <Bot size={15} />
                </div>
                <div className="flex-1 p-5 rounded-2xl bg-card/[0.02] border border-border rounded-tl-sm shadow-md space-y-4">
                  
                  {/* Markdown Answer */}
                  <div className="space-y-1">
                    {parseMarkdown(qa.answer)}
                  </div>

                  {/* Amber grounding warning */}
                  {!qa.grounded && (
                    <div className="p-3.5 bg-amber-500/10 border border-amber-500/25 rounded-xl text-amber-500 text-xs flex items-start gap-2">
                      <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                      <div>
                        <span className="font-bold block">General Guidance Only</span>
                        <span>No matching sources in the knowledge base — general guidance only.</span>
                      </div>
                    </div>
                  )}

                  {/* Sources cited */}
                  {qa.sources.length > 0 && (
                    <div className="border-t border-border pt-3 mt-3">
                      {qa.web_fallback && (
                        <div className="mb-3 p-3 bg-amber-500/10 border border-amber-500/20 text-amber-500 text-[10px] font-bold rounded-lg uppercase tracking-wide inline-flex items-center gap-1.5 font-sans">
                          <AlertTriangle size={12} className="flex-shrink-0" />
                          Live web result — not yet in the reviewed knowledge base.
                        </div>
                      )}
                      <span className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-2">Sources Cited:</span>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {qa.sources.map((src, srcIdx) => (
                          <div key={srcIdx} className="bg-input border-border border border-border p-3 rounded-lg flex flex-col justify-between gap-1 hover:border-cyan-500/20 transition-all">
                            <div>
                              <div className="flex items-center gap-1.5">
                                {src.type === "reference" ? (
                                  <BookOpen className="w-3.5 h-3.5 text-cyan-400 flex-shrink-0" />
                                ) : (
                                  <Scale className="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />
                                )}
                                <span className="text-[9px] font-bold text-muted-foreground uppercase tracking-wider">{src.type}</span>
                              </div>
                              <div className="text-xs font-bold text-foreground leading-tight mt-1 line-clamp-2">{src.title}</div>
                              <div className="text-[10px] text-muted-foreground mt-0.5 line-clamp-1">{src.reference}</div>
                            </div>

                            {src.type === "reference" && src.url && (
                              <a 
                                href={src.url} 
                                target="_blank" 
                                rel="noreferrer" 
                                className="inline-flex items-center gap-1 text-[10px] text-cyan-400 hover:text-cyan-300 font-semibold mt-1"
                              >
                                Link <ExternalLink size={9} />
                              </a>
                            )}

                            {src.type === "precedent" && src.risk_level && (
                              <div className="mt-1">
                                <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold border ${getRiskColor(src.risk_level)}`}>
                                  Risk: {src.risk_level}
                                </span>
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                </div>
              </div>

            </div>
          ))}

          {loading && (
            <div className="flex gap-4 max-w-[80%]">
              <div className="w-8 h-8 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center flex-shrink-0">
                <Bot size={15} />
              </div>
              <div className="p-4 rounded-2xl bg-card/[0.02] border border-border rounded-tl-sm text-muted-foreground flex items-center gap-2 text-sm font-sans shadow-md">
                <Loader2 size={16} className="animate-spin text-cyan-400" /> Scanning labor law references...
              </div>
            </div>
          )}

          {error && (
            <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-xs flex items-center gap-2.5 max-w-lg">
              <AlertTriangle className="w-4 h-4 flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {transcript.length === 0 && !loading && (
            <div className="h-full flex flex-col items-center justify-center text-center text-muted-foreground py-12 px-6">
              <Sparkles className="w-12 h-12 text-foreground mb-3" />
              <h3 className="text-base font-bold text-foreground mb-1">Ask a labor relations query</h3>
              <p className="text-sm text-muted-foreground max-w-sm font-sans">Type a query below. The Copilot will analyze your input against registered DOLE advisories and SC decisions.</p>
            </div>
          )}

          <div ref={endRef} />
        </div>

        {/* Persistent bottom input workspace */}
        <div className="p-4 bg-card border-t border-border space-y-3 flex-none">
          {/* Suggestions (only when empty) */}
          {transcript.length === 0 && (
            <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-thin">
              {SUGGESTIONS.map((sug, i) => (
                <button
                  key={i}
                  onClick={() => {
                    setQuestion(sug);
                    handleAsk(sug);
                  }}
                  disabled={loading}
                  className="whitespace-nowrap px-3 py-1.5 bg-card/50 hover:bg-accent border border-border rounded-full text-xs text-foreground transition-all cursor-pointer disabled:opacity-50"
                >
                  {sug}
                </button>
              ))}
            </div>
          )}

          {/* Form Area */}
          <form 
            onSubmit={(e) => { e.preventDefault(); handleAsk(question); }}
            className="flex gap-3 items-end"
          >
            <textarea
              value={question}
              onChange={(e) => setQuestion(e.target.value)}
              placeholder="Type your labor relations question..."
              rows={2}
              className="flex-1 bg-card/50 border border-border rounded-xl px-4 py-2.5 text-foreground placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 focus:bg-card/10 transition-all text-sm font-sans resize-none"
              onKeyDown={(e) => {
                if (e.key === "Enter" && !e.shiftKey) {
                  e.preventDefault();
                  handleAsk(question);
                }
              }}
            />
            <button
              type="submit"
              disabled={!question.trim() || loading}
              className="h-11 px-5 bg-gradient-to-r from-cyan-500 to-blue-500 hover:opacity-90 text-black font-extrabold rounded-xl flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-cyan-500/20"
            >
              <Send size={16} />
            </button>
          </form>

          {/* Persistent Disclaimer */}
          <p className="text-center text-[10px] text-muted-foreground flex justify-center items-center gap-1.5 select-none leading-none">
            <Info size={12} className="text-[#00e07a]" />
            <span>Guidance only, not legal advice. Avoid pasting sensitive employee names or details.</span>
          </p>
        </div>

      </div>
    </div>
  );
}

export function ElrCopilot() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="system">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <ElrCopilotContent />
      </div>
    </ThemeProvider>
  );
}
