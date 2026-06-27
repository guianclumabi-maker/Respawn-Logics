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
  Cpu,
  Zap,
  ShieldCheck,
  BookOpen,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const API_BASE =
  (import.meta as any).env?.VITE_API_BASE_URL ||
  (window.location.origin +
    (window.location.hostname === 'localhost' ? '/respawn-logics' : ''));

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

const SUGGESTIONS = [
  { icon: <ShieldCheck size={16} />, text: 'What is our policy on repeated tardiness?' },
  { icon: <BookOpen size={16} />, text: 'Draft an incident report for a workplace dispute.' },
  { icon: <Zap size={16} />, text: 'Summarise legal compliance for 13th month pay.' },
  { icon: <MessageSquare size={16} />, text: 'How do I fairly handle an employee grievance?' },
];

export function AICompanion() {
  const { user } = useAuth();
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isLoading]);

  const handleSend = async (text?: string) => {
    const content = (text ?? input).trim();
    if (!content) return;

    const userMessage: Message = {
      id: Date.now().toString(),
      role: 'user',
      content,
      timestamp: new Date(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
    }
    setIsLoading(true);

    try {
      const response = await fetch(
        `${API_BASE}/api/index.php?route=ai_companion&action=chat`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ message: content }),
        }
      );

      const data = await response.json();
      const aiMessage: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: data.reply || data.message || "I couldn't process that request.",
        timestamp: new Date(),
      };
      setMessages((prev) => [...prev, aiMessage]);
    } catch {
      const aiMessage: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content:
          "I'm having trouble reaching the backend right now. Please try again in a moment.",
        timestamp: new Date(),
      };
      setMessages((prev) => [...prev, aiMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const isEmpty = messages.length === 0;

  return (
    /* Use h-full so we fill the parent shell pane, not force viewport height */
    <div className="flex h-full bg-[#0f1115] text-slate-200 overflow-hidden font-sans">

      {/* ── Sidebar ── */}
      <div
        className={`${
          sidebarOpen ? 'w-72' : 'w-0'
        } transition-all duration-300 ease-in-out flex flex-col bg-[#16181d] border-r border-white/5 flex-shrink-0 overflow-hidden`}
      >
        {/* New Chat */}
        <div className="p-4 flex-shrink-0">
          <button
            onClick={() => setMessages([])}
            className="flex items-center gap-2 w-full px-4 py-3 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 rounded-xl transition-all border border-indigo-500/20 shadow-[0_0_15px_rgba(99,102,241,0.1)] group"
          >
            <Plus className="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
            <span className="font-medium">New Chat</span>
            <Sparkles className="w-4 h-4 ml-auto opacity-50" />
          </button>
        </div>

        {/* History */}
        <div className="flex-1 overflow-y-auto px-3 py-2 min-h-0">
          {['Today', 'Yesterday', 'Previous 7 Days'].map((group) => (
            <div key={group} className="mb-6">
              <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-3">
                {group}
              </h3>
              <div className="space-y-1">
                {mockHistory
                  .filter((h) => h.date === group)
                  .map((chat) => (
                    <button
                      key={chat.id}
                      className="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors group"
                    >
                      <MessageSquare className="w-4 h-4 text-slate-500 group-hover:text-indigo-400 flex-shrink-0" />
                      <span className="truncate">{chat.title}</span>
                    </button>
                  ))}
              </div>
            </div>
          ))}
        </div>

        {/* User footer */}
        <div className="p-4 border-t border-white/5 flex-shrink-0">
          <button className="w-full flex items-center gap-3 px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors rounded-lg hover:bg-white/5">
            <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
              {user?.name?.[0]?.toUpperCase() || 'U'}
            </div>
            <span className="flex-1 text-left font-medium truncate">
              {user?.name || 'User'}
            </span>
            <Settings className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* ── Main ── */}
      <div className="flex-1 flex flex-col min-w-0 relative">
        {/* Subtle background glow */}
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(99,102,241,0.06)_0%,transparent_60%)] pointer-events-none" />

        {/* Header */}
        <header className="h-14 flex items-center justify-between px-4 border-b border-white/5 bg-[#0f1115]/80 backdrop-blur-sm flex-shrink-0 relative z-10">
          <div className="flex items-center gap-3">
            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              className="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors"
              title="Toggle sidebar"
            >
              <Menu className="w-5 h-5" />
            </button>
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center">
                <Cpu className="w-4 h-4 text-indigo-400" />
              </div>
              <h1 className="font-semibold text-base tracking-tight bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                Respawn Copilot
              </h1>
            </div>
          </div>
          <div className="flex items-center gap-1">
            <button className="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors">
              <Search className="w-4 h-4" />
            </button>
            <button className="p-2 hover:bg-white/10 rounded-lg text-slate-400 hover:text-white transition-colors">
              <MoreVertical className="w-4 h-4" />
            </button>
          </div>
        </header>

        {/* Messages / Empty state */}
        <div className="flex-1 overflow-y-auto min-h-0 relative">
          {isEmpty ? (
            /* Empty state */
            <div className="h-full flex flex-col items-center justify-center px-6 text-center">
              <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-2xl shadow-indigo-500/30 mb-6">
                <Bot className="w-8 h-8 text-white" />
              </div>
              <h2 className="text-2xl font-bold text-white mb-2">
                How can I help,{' '}
                <span className="bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                  {user?.name?.split(' ')[0] || 'there'}
                </span>
                ?
              </h2>
              <p className="text-slate-400 mb-10 max-w-sm text-sm leading-relaxed">
                I'm your Enterprise HR AI Copilot — ask me anything about HR
                policy, case resolution, compliance, or documentation.
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-xl">
                {SUGGESTIONS.map((s, i) => (
                  <button
                    key={i}
                    onClick={() => handleSend(s.text)}
                    className="flex items-start gap-3 p-4 bg-white/[0.03] hover:bg-white/[0.07] border border-white/[0.08] hover:border-indigo-500/30 rounded-xl text-left text-sm text-slate-300 hover:text-white transition-all group"
                  >
                    <span className="text-indigo-400 mt-0.5 flex-shrink-0 group-hover:scale-110 transition-transform">
                      {s.icon}
                    </span>
                    <span className="leading-snug">{s.text}</span>
                  </button>
                ))}
              </div>
            </div>
          ) : (
            <div className="px-4 py-8">
              <div className="max-w-3xl mx-auto space-y-6">
                {messages.map((message) => (
                  <div
                    key={message.id}
                    className={`flex gap-3 ${
                      message.role === 'user' ? 'justify-end' : 'justify-start'
                    }`}
                  >
                    {message.role === 'assistant' && (
                      <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20 mt-0.5">
                        <Bot className="w-4 h-4 text-white" />
                      </div>
                    )}

                    <div
                      className={`max-w-[78%] rounded-2xl px-5 py-3.5 shadow-sm text-[14.5px] leading-relaxed whitespace-pre-wrap ${
                        message.role === 'user'
                          ? 'bg-indigo-600 text-white shadow-indigo-600/20 rounded-tr-sm'
                          : 'bg-white/[0.05] border border-white/[0.08] text-slate-200 rounded-tl-sm'
                      }`}
                    >
                      {message.content}
                      <div className="text-[10px] mt-2 opacity-40">
                        {message.timestamp.toLocaleTimeString([], {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </div>
                    </div>

                    {message.role === 'user' && (
                      <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-slate-700 border border-white/10 flex items-center justify-center mt-0.5 text-sm font-bold text-slate-300">
                        {user?.name?.[0]?.toUpperCase() || <User size={14} />}
                      </div>
                    )}
                  </div>
                ))}

                {isLoading && (
                  <div className="flex gap-3 justify-start">
                    <div className="w-8 h-8 flex-shrink-0 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20 mt-0.5">
                      <Bot className="w-4 h-4 text-white" />
                    </div>
                    <div className="bg-white/[0.05] border border-white/[0.08] rounded-2xl rounded-tl-sm px-5 py-4 flex items-center gap-1.5">
                      {[0, 150, 300].map((delay) => (
                        <div
                          key={delay}
                          className="w-2 h-2 rounded-full bg-indigo-400 animate-bounce"
                          style={{ animationDelay: `${delay}ms` }}
                        />
                      ))}
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>
            </div>
          )}
        </div>

        {/* Input area */}
        <div className="flex-shrink-0 px-4 pb-4 pt-2 bg-gradient-to-t from-[#0f1115] to-transparent relative z-10">
          <div className="max-w-3xl mx-auto">
            {/* Glow ring behind input */}
            <div className="relative">
              <div className="absolute -inset-px bg-gradient-to-r from-indigo-500/20 to-purple-500/20 rounded-2xl blur-md pointer-events-none" />
              <div className="relative bg-[#1c1e26] border border-white/10 rounded-2xl shadow-2xl flex items-end p-2 transition-all focus-within:border-indigo-500/40 focus-within:shadow-indigo-500/10">
                <button className="p-2.5 text-slate-500 hover:text-slate-300 transition-colors mb-0.5 flex-shrink-0">
                  <Paperclip className="w-4 h-4" />
                </button>

                <textarea
                  ref={textareaRef}
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Message Copilot…"
                  rows={1}
                  className="flex-1 bg-transparent text-white placeholder-slate-500 resize-none outline-none py-2.5 px-2 text-[14.5px] min-h-[40px] max-h-52 overflow-y-auto"
                  onInput={(e) => {
                    const t = e.target as HTMLTextAreaElement;
                    t.style.height = 'auto';
                    t.style.height = `${Math.min(t.scrollHeight, 200)}px`;
                  }}
                />

                <button className="p-2.5 text-slate-500 hover:text-slate-300 transition-colors mb-0.5 flex-shrink-0">
                  <Mic className="w-4 h-4" />
                </button>

                <button
                  onClick={() => handleSend()}
                  disabled={!input.trim() || isLoading}
                  className="p-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:bg-slate-800 disabled:text-slate-600 text-white rounded-xl transition-all shadow-lg shadow-indigo-600/20 disabled:shadow-none mb-0.5 ml-1 flex-shrink-0"
                >
                  <Send className="w-4 h-4" />
                </button>
              </div>
            </div>

            <p className="text-center mt-2 text-[11px] text-slate-600">
              Copilot can make mistakes. Consider verifying important information.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
