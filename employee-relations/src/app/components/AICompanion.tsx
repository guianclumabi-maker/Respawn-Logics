import { useState, useRef, useEffect } from "react";
import { Send, Bot, User, Loader2, Info } from "lucide-react";

type Message = {
  id: string;
  sender: "user" | "bot";
  text: string;
  isStreaming?: boolean;
};

const SUGGESTIONS = [
  "What is the company policy on repeated tardiness?",
  "Draft an incident report for an altercation.",
  "Suggest a resolution for Case #12.",
  "How do I handle an employee dispute fairly?"
];

export function AICompanion() {
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "welcome",
      sender: "bot",
      text: "Hello! I am your HR Employee Relations Assistant. How can I help you today?"
    }
  ]);
  const [input, setInput] = useState("");
  const [isTyping, setIsTyping] = useState(false);
  const endOfMessagesRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    endOfMessagesRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isTyping]);

  const handleSend = async (textToProcess: string) => {
    if (!textToProcess.trim()) return;
    
    const userMsg: Message = { id: Date.now().toString(), sender: "user", text: textToProcess };
    setMessages(prev => [...prev, userMsg]);
    setInput("");
    setIsTyping(true);

    try {
      const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';
      const response = await fetch(`${basePath}/ai_companion_api.php?action=chat`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: textToProcess })
      });
      const data = await response.json();
      
      const botMsg: Message = { id: (Date.now() + 1).toString(), sender: "bot", text: data.reply || "I encountered an error processing that." };
      setMessages(prev => [...prev, botMsg]);
    } catch (err) {
      setMessages(prev => [...prev, { id: Date.now().toString(), sender: "bot", text: "Error connecting to AI backend." }]);
    } finally {
      setIsTyping(false);
    }
  };

  return (
    <div className="flex-1 flex flex-col bg-[#06070a] text-white p-6 h-full overflow-hidden">
      <div className="flex items-center gap-3 mb-6">
        <div className="w-10 h-10 rounded-full bg-cyan-500/20 flex items-center justify-center">
          <Bot className="text-cyan-400" />
        </div>
        <div>
          <h1 className="text-xl font-bold font-['Outfit']">HR AI Companion</h1>
          <p className="text-sm text-gray-400">Intelligent assistance for employee relations</p>
        </div>
      </div>

      <div className="flex-1 flex flex-col bg-[#0d0f19] border border-white/5 rounded-2xl overflow-hidden shadow-2xl relative">
        <div className="flex-1 p-6 overflow-y-auto space-y-6 scrollbar-thin">
          {messages.map((msg) => (
            <div key={msg.id} className={`flex gap-4 max-w-[80%] ${msg.sender === "user" ? "ml-auto flex-row-reverse" : ""}`}>
              <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${msg.sender === "user" ? "bg-purple-500/20 text-purple-400" : "bg-cyan-500/20 text-cyan-400"}`}>
                {msg.sender === "user" ? <User size={16} /> : <Bot size={16} />}
              </div>
              <div className={`p-4 rounded-2xl text-[0.95rem] leading-relaxed shadow-md ${
                msg.sender === "user" 
                  ? "bg-purple-500/10 border border-purple-500/20 rounded-tr-sm text-white" 
                  : "bg-white/5 border border-white/10 rounded-tl-sm text-gray-200"
              }`}>
                {msg.text.split('\n').map((line, i) => <p key={i} className="mb-2 last:mb-0">{line}</p>)}
              </div>
            </div>
          ))}
          {isTyping && (
            <div className="flex gap-4 max-w-[80%]">
              <div className="w-8 h-8 rounded-full bg-cyan-500/20 flex items-center justify-center flex-shrink-0 text-cyan-400">
                <Bot size={16} />
              </div>
              <div className="p-4 rounded-2xl bg-white/5 border border-white/10 rounded-tl-sm text-gray-200 flex items-center gap-2">
                <Loader2 size={16} className="animate-spin text-cyan-400" /> Thinking...
              </div>
            </div>
          )}
          <div ref={endOfMessagesRef} />
        </div>

        <div className="p-4 bg-[#0d0f19] border-t border-white/5">
          {messages.length === 1 && (
            <div className="flex gap-2 mb-4 overflow-x-auto pb-2 scrollbar-thin">
              {SUGGESTIONS.map((sug, i) => (
                <button
                  key={i}
                  onClick={() => handleSend(sug)}
                  className="whitespace-nowrap px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-full text-sm text-gray-300 transition-colors cursor-pointer"
                >
                  {sug}
                </button>
              ))}
            </div>
          )}
          
          <form 
            onSubmit={(e) => { e.preventDefault(); handleSend(input); }}
            className="flex gap-3 relative"
          >
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Ask me anything about HR policies or cases..."
              className="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 focus:bg-white/10 transition-all"
            />
            <button
              type="submit"
              disabled={!input.trim() || isTyping}
              className="px-5 bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-400 hover:to-blue-400 text-white rounded-xl flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-cyan-500/20"
            >
              <Send size={18} />
            </button>
          </form>
          <p className="text-center text-xs text-gray-500 mt-3 flex justify-center items-center gap-1">
             <Info size={12}/> AI can make mistakes. Always verify HR policies with your handbook.
          </p>
        </div>
      </div>
    </div>
  );
}
