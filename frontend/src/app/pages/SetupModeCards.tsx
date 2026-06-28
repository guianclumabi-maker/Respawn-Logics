import React from "react";
import { Users, Shield, Globe, User } from "lucide-react";

type SetupMode = "Solo" | "Small" | "Mid" | "Enterprise";

export function SetupModeCards() {
  const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

  const handleModeSelect = (mode: SetupMode) => {
    window.location.href = `${API_BASE}/register.php?setup_mode=${mode}`;
  };

  return (
    <div className="h-screen bg-[#0b0f1a] bg-[linear-gradient(to_right,rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:40px_40px] font-['Space_Grotesk'] selection:bg-[#00e07a]/30 overflow-y-auto relative text-[#c8d0e0]">
      {/* Ambient Background Blobs */}
      <div className="ambient-blob blob-1 absolute top-0 left-0 w-96 h-96 bg-[#00e07a]/20 rounded-full blur-[100px]"></div>
      <div className="ambient-blob blob-2 absolute bottom-0 right-0 w-96 h-96 bg-[#00b8ff]/20 rounded-full blur-[100px]"></div>

      {/* HEADER */}
      <nav className="border-b border-white/[0.07] bg-[#0b0f1a]/90 backdrop-blur-[20px] fixed top-0 left-0 right-0 z-50 h-[62px] flex items-center justify-between px-6 md:px-12">
        <a href="#/" className="flex items-center gap-[10px] no-underline font-['JetBrains_Mono'] text-[0.9375rem] font-bold text-white">
          <div className="w-[40px] h-[40px] rounded-[10px] bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center shadow-[0_8px_20px_rgba(0,224,122,0.25)]">
            <i className="fa-solid fa-gamepad" style={{fontSize: "20px", color: "#000"}}></i>
          </div>
          <span className="hidden sm:flex items-center gap-1">
            Respawn Logics
            <span className="text-[9px] text-[#00e07a] border border-[#00e07a]/20 bg-[#00e07a]/10 px-1 py-0.5 rounded ml-1 tracking-[0.1em] font-bold">v2.0</span>
          </span>
        </a>

        <div className="hidden md:flex items-center gap-[30px] text-[0.9rem] font-medium text-[#8b95a8]" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
          <a href={API_BASE + "/index.php"} className="flex items-center gap-2 hover:text-white transition-colors no-underline text-sm px-3 py-1.5 rounded-md hover:bg-white/5">
            <i className="fa-solid fa-arrow-left"></i> Back to Home
          </a>
          
          <a href={API_BASE + "/login.php"} className="font-['JetBrains_Mono'] text-[0.8rem] font-bold tracking-[0.04em] text-black bg-[#00e07a] px-[20px] py-[9px] rounded-[5px] no-underline transition-all hover:bg-white hover:-translate-y-[1px] hover:shadow-[0_4px_16px_rgba(0,224,122,0.3)] ml-2">
            [ LOGIN ]
          </a>
        </div>
      </nav>

      <main className="max-w-5xl mx-auto px-6 py-12 mt-16 pb-24 relative z-10">
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
          <div className="text-center max-w-2xl mx-auto mb-16 mt-8">
            <div className="inline-block px-3 py-1 mb-4 rounded-full border border-white/10 bg-white/5 text-[#8b95a8] text-sm font-['JetBrains_Mono']">// INIT_SEQUENCE</div>
            <h1 className="text-5xl font-bold text-white mb-6 tracking-tight">Select Setup Mode<span className="text-[#00e07a] animate-pulse">_</span></h1>
            <p className="text-[#8b95a8] text-lg font-['Space_Grotesk']">Choose the onboarding path that best fits your organizational scale.</p>
          </div>
          
          <div className="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            {[
              { title: "Co-op Mode", desc: "1-100 employees. Minimal mapping, automatic suggestions.", icon: Users, mode: "Small", color: "text-[#c8d0e0]", bg: "bg-[#0f1422]", border: "hover:border-[#00e07a]/50" },
              { title: "Multiplayer Guild", desc: "100-500 employees. Department structures and multiple admins.", icon: Shield, mode: "Mid", color: "text-[#c8d0e0]", bg: "bg-[#0f1422]", border: "hover:border-[#4f8ef7]/50" },
              { title: "MMO Server", desc: "500+ employees. Advanced org units and exact RBAC mapping.", icon: Globe, mode: "Enterprise", color: "text-[#00e07a]", bg: "bg-[#00e07a]/10 border border-[#00e07a]/30 shadow-[0_0_30px_rgba(0,224,122,0.15)]", border: "border-[#00e07a]" }
            ].map((s, i) => (
              <div key={i} onClick={() => handleModeSelect(s.mode as SetupMode)} className={`group bg-[#0f1422] border border-white/[0.05] ${s.border} rounded-xl p-8 cursor-pointer transition-all hover:-translate-y-2 hover:shadow-[0_8px_30px_rgba(0,0,0,0.5)] flex flex-col items-center text-center relative overflow-hidden`}>
                <div className="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                <div className="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                <div className="absolute bottom-0 left-0 w-4 h-4 border-b-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                <div className="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>

                <div className={`w-14 h-14 rounded-lg ${s.bg} flex items-center justify-center mb-6 transition-all group-hover:scale-110`}>
                  <s.icon className={`w-7 h-7 ${s.color}`} />
                </div>
                <h3 className="text-xl font-bold text-white mb-3 font-['JetBrains_Mono']">{s.title}</h3>
                <p className="text-[#8b95a8] text-sm leading-relaxed">{s.desc}</p>
              </div>
            ))}
          </div>

          <div className="relative flex items-center py-8 max-w-4xl mx-auto">
            <div className="flex-grow border-t border-white/10"></div>
            <span className="flex-shrink-0 mx-6 text-[#8b95a8] text-sm font-['JetBrains_Mono'] tracking-widest uppercase">// Starting solo? I've got you covered</span>
            <div className="flex-grow border-t border-white/10"></div>
          </div>

          <div className="max-w-sm mx-auto">
            <div onClick={() => handleModeSelect("Solo")} className={`group bg-[#0f1422] border border-white/[0.05] hover:border-[#00e07a]/50 rounded-xl p-8 cursor-pointer transition-all hover:-translate-y-2 hover:shadow-[0_8px_30px_rgba(0,0,0,0.5)] flex flex-col items-center text-center relative overflow-hidden`}>
              <div className="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
              <div className="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
              <div className="absolute bottom-0 left-0 w-4 h-4 border-b-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
              <div className="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>

              <div className={`w-14 h-14 rounded-lg bg-[#0f1422] flex items-center justify-center mb-6 transition-all group-hover:scale-110`}>
                <User className="w-7 h-7 text-[#c8d0e0] group-hover:text-[#00e07a] transition-colors" />
              </div>
              <h3 className="text-xl font-bold text-white mb-3 font-['JetBrains_Mono']">Single Player</h3>
              <p className="text-[#8b95a8] text-sm leading-relaxed">1 employee. Direct dashboard access, no mapping required.</p>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}
