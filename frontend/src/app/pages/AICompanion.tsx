import React, { useState, useEffect, useRef } from 'react';
import { 
  Send, 
  Bot, 
  User, 
  MessageSquare, 
  Plus, 
  Settings, 
  Menu,
  Sparkles,
  Search,
  MoreVertical,
  Paperclip,
  Mic,
  Cpu
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';

interface Message {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  timestamp: Date;
}

interface ChatSession {
  id: string;
  title: string;
  date: string;
}

const mockHistory: ChatSession[] = [
  { id: '1', title: 'Termination Policy Clarification', date: 'Today' },
  { id: '2', title: 'Performance Review Draft', date: 'Yesterday' },
  { id: '3', title: 'Case #12 Resolution', date: 'Previous 7 Days' },
  { id: '4', title: 'Onboarding Process Updates', date: 'Previous 7 Days' },
];

export function AICompanion() {
  const { user } = useAuth();
  const [messages, setMessages] = useState<Message[]>([
    {
      id: 'welcome',
      role: 'assistant',
      content: `Hello ${user?.name || 'there'}. I am your Enterprise AI Copilot. How can I assist you today with HR intelligence, policy analysis, or case resolution?`,
      timestamp: new Date()
    }
  ]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isLoading]);

  const handleSend = async () => {
    if (!input.trim()) return;

    const userMessage: Message = {
      id: Date.now().toString(),
      role: 'user',
      content: input.trim(),
      timestamp: new Date()
    };

    setMessages(prev => [...prev, userMessage]);
    setInput('');
    setIsLoading(true);

    try {
      const API_BASE = (import.meta as any).env?.VITE_API_BASE || '';
      const response = await fetch(`${API_BASE}/api/ai-companion?action=chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: userMessage.content })
      });

      let data;
      if (response.ok) {
        data = await response.json();
      } else {
        throw new Error('API Error');
      }

      const aiMessage: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: data.reply || "I'm sorry, I couldn't process that request.",
        timestamp: new Date()
      };
      
      setMessages(prev => [...prev, aiMessage]);
    } catch (error) {
      setTimeout(() => {
        const aiMessage: Message = {
          id: (Date.now() + 1).toString(),
          role: 'assistant',
          content: "I am operating in offline mock mode. This is a simulated response indicating that the backend endpoint was unreachable. You asked: *" + userMessage.content + "*",
          timestamp: new Date()
        };
        setMessages(prev => [...prev, aiMessage]);
        setIsLoading(false);
      }, 1500);
      return;
    }

    setIsLoading(false);
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  return (
    <div className="flex h-screen bg-[#0f1115] text-slate-200 overflow-hidden font-sans">
      
      <div 
        className={`${sidebarOpen ? 'w-72' : 'w-0'} transition-all duration-300 ease-in-out flex flex-col bg-[#16181d] border-r border-white/5 flex-shrink-0 z-20`}
      >
        <div className="p-4">
          <button className="flex items-center gap-2 w-full px-4 py-3 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 rounded-xl transition-all border border-indigo-500/20 shadow-[0_0_15px_rgba(99,102,241,0.1)] group">
            <Plus className="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
            <span className="font-medium">New Chat</span>
            <Sparkles className="w-4 h-4 ml-auto opacity-50" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-3 py-2 custom-scrollbar">
          {['Today', 'Yesterday', 'Previous 7 Days'].map((group) => (
            <div key={group} className="mb-6">
              <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-3">
                {group}
              </h3>
              <div className="space-y-1">
                {mockHistory.filter(h => h.date === group).map((chat) => (
                  <button 
                    key={chat.id}
                    className="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors group"
                  >
                    <MessageSquare className="w-4 h-4 text-slate-500 group-hover:text-indigo-400" />
                    <span className="truncate">{chat.title}</span>
                  </button>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="p-4 border-t border-white/5 bg-[#16181d]/80 backdrop-blur-md">
          <button className="w-full flex items-center gap-3 px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors rounded-lg hover:bg-white/5">
            <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold">
              {user?.name?.[0] || 'U'}
            </div>
            <span className="flex-1 text-left font-medium truncate">{user?.name || 'User'}</span>
            <Settings className="w-4 h-4" />
          </button>
        </div>
      </div>

      <div className="flex-1 flex flex-col relative min-w-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-[#1f222d] via-[#0f1115] to-[#0f1115]">
        
        <header className="h-16 flex items-center justify-between px-4 border-b border-white/5 backdrop-blur-sm bg-[#0f1115]/50 sticky top-0 z-10">
          <div className="flex items-center gap-3">
            <button 
              onClick={() => setSidebarOpen(!sidebarOpen)}
              className="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors"
            >
              <Menu className="w-5 h-5" />
            </button>
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center">
                <Cpu className="w-5 h-5 text-indigo-400" />
              </div>
              <h1 className="font-semibold text-lg tracking-tight bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                Respawn Copilot
              </h1>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button className="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors">
              <Search className="w-5 h-5" />
            </button>
            <button className="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors">
              <MoreVertical className="w-5 h-5" />
            </button>
          </div>
        </header>

        <div className="flex-1 overflow-y-auto px-4 py-8 scroll-smooth">
          <div className="max-w-3xl mx-auto space-y-8">
            {messages.map((message) => (
              <div 
                key={message.id} 
                className={`flex gap-4 ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
              >
                {message.role === 'assistant' && (
                  <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <Bot className="w-5 h-5 text-white" />
                  </div>
                )}
                
                <div 
                  className={`max-w-[80%] rounded-2xl px-5 py-3.5 shadow-sm ${
                    message.role === 'user' 
                      ? 'bg-indigo-600 text-white shadow-indigo-600/10 rounded-tr-sm' 
                      : 'bg-white/5 border border-white/10 text-slate-200 rounded-tl-sm backdrop-blur-md'
                  }`}
                >
                  <div className="prose prose-invert max-w-none text-[15px] leading-relaxed whitespace-pre-wrap">
                    {message.content}
                  </div>
                </div>

                {message.role === 'user' && (
                  <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-slate-800 flex items-center justify-center border border-white/10">
                    <User className="w-5 h-5 text-slate-400" />
                  </div>
                )}
              </div>
            ))}
            
            {isLoading && (
              <div className="flex gap-4 justify-start">
                <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                  <Bot className="w-5 h-5 text-white" />
                </div>
                <div className="bg-white/5 border border-white/10 rounded-2xl rounded-tl-sm px-5 py-4 backdrop-blur-md flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: '0ms' }} />
                  <div className="w-2 h-2 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: '150ms' }} />
                  <div className="w-2 h-2 rounded-full bg-indigo-400 animate-bounce" style={{ animationDelay: '300ms' }} />
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>
        </div>

        <div className="p-4 bg-gradient-to-t from-[#0f1115] via-[#0f1115] to-transparent">
          <div className="max-w-3xl mx-auto relative">
            <div className="absolute inset-0 bg-gradient-to-r from-indigo-500/20 to-purple-500/20 blur-xl rounded-full" />
            <div className="relative bg-[#1c1e26] border border-white/10 rounded-2xl shadow-2xl flex items-end p-2 transition-all focus-within:border-indigo-500/50 focus-within:ring-1 focus-within:ring-indigo-500/50">
              <button className="p-3 text-slate-400 hover:text-white transition-colors mb-0.5">
                <Paperclip className="w-5 h-5" />
              </button>
              
              <textarea
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyPress}
                placeholder="Message Copilot..."
                className="flex-1 max-h-48 min-h-[44px] bg-transparent text-white placeholder-slate-500 resize-none outline-none py-3 px-2 custom-scrollbar text-[15px]"
                rows={1}
                style={{ height: 'auto', minHeight: '44px' }}
                onInput={(e) => {
                  const target = e.target as HTMLTextAreaElement;
                  target.style.height = 'auto';
                  target.style.height = `${Math.min(target.scrollHeight, 200)}px`;
                }}
              />
              
              <button className="p-3 text-slate-400 hover:text-white transition-colors mb-0.5">
                <Mic className="w-5 h-5" />
              </button>
              
              <button 
                onClick={handleSend}
                disabled={!input.trim() || isLoading}
                className="p-3 bg-indigo-600 hover:bg-indigo-500 disabled:bg-slate-800 disabled:text-slate-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-600/20 disabled:shadow-none mb-0.5 ml-1"
              >
                <Send className="w-5 h-5" />
              </button>
            </div>
            <div className="text-center mt-3 mb-1">
              <span className="text-[11px] text-slate-500">
                Copilot can make mistakes. Consider verifying important information.
              </span>
            </div>
          </div>
        </div>
        
      </div>
    </div>
  );
}
