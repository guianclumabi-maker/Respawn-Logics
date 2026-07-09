import { apiFetch } from "../../lib/apiClient";
import { useTheme } from "next-themes";
import { useEffect, useState } from "react";
import { Sun, Moon, Gamepad2 } from "lucide-react";

export function GamifiedThemeToggle({ collapsed = false }: { collapsed?: boolean }) {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);
  if (!mounted) return null;
  const isDark = theme === "dark";

  const toggle = () => {
    const next = isDark ? "light" : "dark";
    setTheme(next);
    apiFetch("/api/index.php?route=iam&action=update_theme", {
      method: "POST",
      body: JSON.stringify({ theme: next }),
    }).catch((e) => console.error("theme sync failed", e));
  };

  return (
    <div className="flex items-center justify-between p-3 bg-card border border-border rounded-xl">
      <div className="flex items-center gap-2">
        <Gamepad2 size={16} className={isDark ? "text-primary" : "text-cyan-600"} />
        {!collapsed && (
          <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground font-mono">
            {isDark ? "Night Ops" : "Day Cycle"}
          </span>
        )}
      </div>
      <button
        onClick={toggle}
        aria-label="Toggle theme"
        className={`relative w-12 h-6 rounded-full flex items-center px-1 transition-all ${isDark ? "bg-[#0f1422] border border-primary/50" : "bg-gray-200 border border-cyan-400/50"}`}
      >
        <span className={`absolute w-4 h-4 rounded-full flex items-center justify-center transition-all ${isDark ? "translate-x-6 bg-primary" : "translate-x-0 bg-cyan-500"}`}>
          {isDark ? <Moon size={10} className="text-[#0b0f1a]" /> : <Sun size={10} className="text-white" />}
        </span>
      </button>
    </div>
  );
}
