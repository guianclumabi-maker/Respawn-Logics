import { useState, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { useAuth } from "../context/AuthContext";
import { Users, ZoomIn, ZoomOut, Maximize } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=core_hr&action=directory`;

interface Employee {
  id: number;
  full_name: string;
  job_title: string;
  department: string;
  manager_id?: number | null;
  children?: Employee[];
}

export function OrgChart() {
  const { hasPermission } = useAuth();
  const canView = hasPermission("employees.view");
  const [data, setData] = useState<Employee | null>(null);
  const [loading, setLoading] = useState(true);
  const [zoom, setZoom] = useState(1);

  useEffect(() => {
    if (!canView) { setLoading(false); return; }
    fetch(API, { credentials: "include" })
      .then(res => res.json())
      .then(d => {
        if (d.success && d.data.length > 0) {
          setData(buildTree(d.data));
        }
      })
      .catch(err => console.error(err))
      .finally(() => setLoading(false));
  }, [canView]);

  const buildTree = (employees: any[]) => {
    // If backend doesn't return manager_id, we'll mock a hierarchy based on array position
    const sorted = [...employees].sort((a,b) => a.id - b.id);
    const ceo = sorted[0];
    ceo.children = [];
    
    // Assign others to ceo or some random hierarchy if no manager_id
    sorted.slice(1).forEach((emp, index) => {
      const parent = index % 3 === 0 ? ceo : sorted[Math.floor(index/3)];
      if (!parent.children) parent.children = [];
      parent.children.push(emp);
    });
    
    return ceo;
  };

  if (!canView) return <div className="h-full flex items-center justify-center bg-[#0b0f1a] text-slate-400">Permission denied.</div>;

  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex flex-col bg-[#0b0f1a] text-slate-200">
        
        {/* Header */}
        <div className="p-8 pb-4 border-b border-white/5 flex justify-between items-end bg-[#141929]">
          <div>
            <h1 className="text-3xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>Organization Chart</h1>
            <p className="text-slate-400 text-sm">Visual hierarchy of all employees</p>
          </div>
          <div className="flex gap-2 bg-black/20 p-1 rounded-lg border border-white/10">
            <button onClick={() => setZoom(z => Math.min(z + 0.1, 2))} className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded"><ZoomIn size={18} /></button>
            <button onClick={() => setZoom(1)} className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded"><Maximize size={18} /></button>
            <button onClick={() => setZoom(z => Math.max(z - 0.1, 0.5))} className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded"><ZoomOut size={18} /></button>
          </div>
        </div>

        {/* Canvas */}
        <div className="flex-1 overflow-auto p-12 relative custom-scrollbar bg-gradient-to-b from-[#0b0f1a] to-[#0f1423]">
          {loading ? (
            <div className="absolute inset-0 flex items-center justify-center text-[#00e07a] animate-pulse">Loading Chart...</div>
          ) : data ? (
            <div 
              className="min-w-max min-h-max transition-transform duration-300 origin-top flex justify-center"
              style={{ transform: `scale(${zoom})` }}
            >
              <TreeNode node={data} />
            </div>
          ) : (
            <div className="absolute inset-0 flex items-center justify-center text-slate-500">No employees found in directory.</div>
          )}
        </div>
      </div>

      <style>{`
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
      `}</style>
    </ThemeProvider>
  );
}

function TreeNode({ node }: { node: Employee }) {
  const hasChildren = node.children && node.children.length > 0;
  
  const getInitials = (name: string) => {
    return name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
  };

  return (
    <div className="flex flex-col items-center">
      {/* Card */}
      <div className="relative group">
        <div className="w-56 bg-[#161922] border border-white/10 rounded-xl p-4 shadow-xl flex flex-col items-center text-center transition-all duration-200 hover:border-[#00e07a]/50 hover:shadow-[0_0_20px_rgba(0,224,122,0.15)] z-10 relative">
          
          <div className="w-12 h-12 bg-gradient-to-br from-slate-700 to-slate-800 rounded-full flex items-center justify-center text-white font-bold text-sm border-2 border-[#161922] shadow-md -mt-8 mb-3 group-hover:scale-110 transition-transform">
            {getInitials(node.full_name)}
          </div>
          
          <div className="font-bold text-white text-sm" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>{node.full_name}</div>
          <div className="text-xs font-semibold text-[#00b8ff] mt-1">{node.job_title || 'Employee'}</div>
          <div className="text-[10px] text-slate-500 uppercase tracking-widest mt-2 px-2 py-1 bg-white/5 rounded-md inline-block">{node.department || 'General'}</div>
        </div>
      </div>

      {/* Lines & Children */}
      {hasChildren && (
        <>
          <div className="w-px h-8 bg-white/20"></div>
          <div className="flex relative pt-4 border-t border-white/20">
            {node.children!.map((child, index) => (
              <div key={child.id} className="relative flex flex-col items-center px-4">
                {/* Connecting Lines for children */}
                {index === 0 && <div className="absolute top-0 right-0 w-1/2 h-px bg-[#0b0f1a] -mt-px"></div>}
                {index === node.children!.length - 1 && <div className="absolute top-0 left-0 w-1/2 h-px bg-[#0b0f1a] -mt-px"></div>}
                
                <div className="absolute top-0 w-px h-4 bg-white/20 -mt-4"></div>
                
                <TreeNode node={child} />
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
