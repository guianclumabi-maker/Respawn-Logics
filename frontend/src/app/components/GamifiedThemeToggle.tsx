import { useTheme } from "next-themes";
import { useEffect, useState } from "react";
import { Sun, Moon, Gamepad2 } from "lucide-react";

export function GamifiedThemeToggle({ collapsed }: { collapsed?: boolean }) {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted) return null;

  const isDark = theme === "dark";

  return (
    <div className="flex items-center justify-between p-3 bg-white/[0.02] dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-xl relative overflow-hidden group">
      {/* Gamified Background Sweep */}
      <div className={`absolute inset-0 transition-all duration-500 opacity-20 ${isDark ? 'bg-gradient-to-r from-[#0b0f1a] to-[#00e07a]/20' : 'bg-gradient-to-r from-white to-cyan-500/20'}`} />
      
      <div className="relative z-10 flex items-center gap-2">
        <Gamepad2 size={16} className={isDark ? "text-[#00e07a]" : "text-cyan-600"} />
        {!collapsed && (
          <span className="text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-300 font-mono">
            {isDark ? "Night Ops" : "Day Cycle"}
          </span>
        )}
      </div>

      <button
        onClick={() => {
          const newTheme = isDark ? "light" : "dark";
          setTheme(newTheme);
          // Sync with the main PHP backend session
          fetch("/api/index.php?route=iam&action=update_theme", { credentials: "include", 
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ 
                  theme: newTheme,
                  csrf_token: (window as any).__CSRF_TOKEN__ || ""
              })
          }).catch(err => console.error("Failed to sync theme with backend:", err));
        }}
        className={`relative w-12 h-6 rounded-full transition-all duration-300 focus:outline-none flex items-center px-1 shadow-inner ${
          isDark ? 'bg-[#0f1422] border border-[#00e07a]/50 shadow-[#00e07a]/20' : 'bg-gray-200 border border-cyan-400/50 shadow-cyan-400/20'
        }`}
      >
        <span
          className={`absolute w-4 h-4 rounded-full transition-all duration-300 flex items-center justify-center ${
            isDark 
              ? 'translate-x-6 bg-[#00e07a] shadow-[0_0_8px_#00e07a]' 
              : 'translate-x-0 bg-cyan-500 shadow-[0_0_8px_#06b6d4]'
          }`}
        >
          {isDark ? (
            <Moon size={10} className="text-[#0b0f1a]" />
          ) : (
            <Sun size={10} className="text-white" />
          )}
        </span>
      </button>
    </div>
  );
}
