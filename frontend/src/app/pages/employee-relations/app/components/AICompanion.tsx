import { useState } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Bot, 
  Send, 
  BookOpen, 
  Scale, 
  AlertTriangle, 
  Info, 
  Loader2, 
  FileText, 
  ExternalLink,
  ShieldCheck,
  Compass
} from "lucide-react";

interface Source {
  type: "reference" | "precedent";
  title: string;
  reference: string;
  url?: string;
  risk_level?: string;
}

interface CopilotResponse {
  success: boolean;
  answer: string;
  sources: Source[];
  grounded: boolean;
}

const SUGGESTIONS = [
  "What is the due process for terminating an employee?",
  "How many hours of overtime can an employee work per day?",
  "What are the statutory requirements for maternity leave under PH law?",
  "How is 13th month pay computed?"
];

export function AICompanion() {
  const [question, setQuestion] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<CopilotResponse | null>(null);

  const handleAsk = async (textToSubmit: string) => {
    const query = textToSubmit.trim();
    if (!query) return;

    setQuestion(query);
    setLoading(true);
    setError(null);
    setResult(null);

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=copilot", {
        method: "POST",
        body: JSON.stringify({ question: query })
      });

      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      
      if (data.success) {
        setResult(data);
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
            className="ml-5 list-disc text-gray-300 my-1 font-sans text-sm" 
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
          className="mb-3 leading-relaxed text-gray-300 text-sm font-sans" 
          dangerouslySetInnerHTML={{ __html: content }} 
        />
      );
    });
  };

  return (
    <div className="flex-1 flex flex-col bg-[#06070a] text-white p-6 h-full overflow-hidden">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 rounded-full bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center shadow-[0_0_15px_rgba(6,182,212,0.15)]">
          <Bot className="text-cyan-400" />
        </div>
        <div>
          <h1 className="text-xl font-bold font-['Space_Grotesk']">ELR Labor Relations Copilot</h1>
          <p className="text-sm text-gray-400">Grounded statutory legal reasoning and Supreme Court jurisprudence Q&A</p>
        </div>
      </div>

      {/* Main Workspace */}
      <div className="flex-1 flex flex-col gap-6 overflow-hidden">
        
        {/* Input panel */}
        <div className="bg-[#0f121d]/40 border border-white/5 p-6 rounded-2xl space-y-4">
          <form 
            onSubmit={(e) => { e.preventDefault(); handleAsk(question); }}
            className="flex gap-3"
          >
            <input
              type="text"
              value={question}
              onChange={(e) => setQuestion(e.target.value)}
              placeholder="Ask a labor relations question (e.g., 'What are the steps for Twin Notice Rule?')"
              className="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 focus:bg-white/10 transition-all text-sm font-sans"
            />
            <button
              type="submit"
              disabled={!question.trim() || loading}
              className="px-5 bg-gradient-to-r from-cyan-500 to-blue-500 hover:opacity-90 text-black font-extrabold rounded-xl flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-cyan-500/20"
            >
              <Send size={18} />
            </button>
          </form>

          {/* Suggestions */}
          <div className="space-y-2">
            <span className="block text-xs font-semibold text-gray-500 uppercase tracking-wider">Suggested Queries</span>
            <div className="flex flex-wrap gap-2">
              {SUGGESTIONS.map((sug, i) => (
                <button
                  key={i}
                  onClick={() => handleAsk(sug)}
                  disabled={loading}
                  className="px-3.5 py-1.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-full text-xs text-gray-300 transition-colors disabled:opacity-50"
                >
                  {sug}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Output Panel / Workspace */}
        <div className="flex-1 bg-[#0d0f19] border border-white/5 rounded-2xl overflow-hidden flex flex-col relative shadow-2xl">
          
          {loading ? (
            <div className="flex-1 flex flex-col items-center justify-center gap-3 text-gray-400">
              <Loader2 className="w-8 h-8 animate-spin text-cyan-400" />
              <p className="text-sm font-medium">Scanning Statutory Corpus & SC Decisions...</p>
            </div>
          ) : error ? (
            <div className="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-3">
              <AlertTriangle className="w-10 h-10 text-red-500" />
              <h3 className="text-lg font-bold text-white">Copilot Exception</h3>
              <p className="text-sm text-gray-400 max-w-md">{error}</p>
            </div>
          ) : result ? (
            <div className="flex-1 flex flex-col lg:flex-row overflow-hidden divide-y lg:divide-y-0 lg:divide-x divide-white/5">
              
              {/* Answer Column */}
              <div className="flex-1 p-6 overflow-y-auto space-y-4">
                <div className="border-b border-white/5 pb-2">
                  <span className="text-xs font-bold text-cyan-400 uppercase tracking-wider block">Copilot Response</span>
                </div>

                <div className="space-y-1">
                  {parseMarkdown(result.answer)}
                </div>

                {/* Grounding Alert */}
                {!result.grounded && (
                  <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-amber-500 text-xs flex items-start gap-2.5 mt-4">
                    <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                    <div>
                      <span className="font-bold block">General Guidance Only</span>
                      <span>No matching sources were found in the database. The answer provided above is not grounded in tenant-specific corpus metadata.</span>
                    </div>
                  </div>
                )}
              </div>

              {/* Sources Column */}
              <div className="w-full lg:w-80 p-6 overflow-y-auto space-y-4 bg-black/10 flex-shrink-0">
                <div className="border-b border-white/5 pb-2">
                  <span className="text-xs font-bold text-gray-400 uppercase tracking-wider block">Grounded Sources</span>
                </div>

                <div className="space-y-3">
                  {result.sources.map((src, i) => (
                    <div key={i} className="bg-white/[0.02] border border-white/5 p-3.5 rounded-xl space-y-2 hover:border-cyan-500/20 transition-all">
                      <div className="flex items-center gap-2">
                        {src.type === "reference" ? (
                          <BookOpen className="w-4 h-4 text-cyan-400 flex-shrink-0" />
                        ) : (
                          <Scale className="w-4 h-4 text-purple-400 flex-shrink-0" />
                        )}
                        <span className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                          {src.type}
                        </span>
                      </div>
                      
                      <div className="text-xs font-bold text-white line-clamp-2">
                        {src.title}
                      </div>

                      <div className="text-[11px] text-gray-400">
                        {src.reference}
                      </div>

                      {src.type === "reference" && src.url && (
                        <a 
                          href={src.url} 
                          target="_blank" 
                          rel="noreferrer" 
                          className="inline-flex items-center gap-1 text-[10px] text-cyan-400 hover:text-cyan-300 font-semibold"
                        >
                          Official URL <ExternalLink size={10} />
                        </a>
                      )}

                      {src.type === "precedent" && src.risk_level && (
                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold border ${getRiskColor(src.risk_level)}`}>
                          Risk: {src.risk_level}
                        </span>
                      )}
                    </div>
                  ))}

                  {result.sources.length === 0 && (
                    <div className="text-center text-gray-600 text-xs py-8">
                      No document sources referenced.
                    </div>
                  )}
                </div>
              </div>

            </div>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-8 text-center text-gray-500 space-y-3">
              <Compass className="w-12 h-12 text-gray-700" />
              <h3 className="text-base font-bold text-white">Ask a labor relations query</h3>
              <p className="text-sm text-gray-500 max-w-sm">The Copilot will answer based on DOLE handbook references and Supreme Court rulings.</p>
            </div>
          )}

          {/* Disclaimer / Bottom bar */}
          <div className="p-4 bg-[#0d0f19] border-t border-white/5 flex items-center justify-center gap-1.5 text-center text-xs text-gray-500">
            <Info size={14} className="text-[#00e07a] flex-shrink-0" />
            <span>Guidance only, not legal advice. Avoid pasting sensitive employee details.</span>
          </div>

        </div>

      </div>
    </div>
  );
}
