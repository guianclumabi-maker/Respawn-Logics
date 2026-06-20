import { useState, useEffect } from "react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || "";
const API = `${API_BASE}/api/index.php?route=core_hr`;

interface Employee {
  id: number;
  full_name: string;
  email: string;
  employment_status: string;
  department: string;
  job_title: string;
  created_at: string;
}

export function HRDirectory() {
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDirectory();
  }, []);

  const fetchDirectory = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${API}&action=directory`, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch");
      const data = await res.json();
      if (data.success && data.data) {
        setEmployees(data.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const filteredEmployees = employees.filter(emp => {
    const q = search.toLowerCase();
    return (
      (emp.full_name || "").toLowerCase().includes(q) ||
      (emp.email || "").toLowerCase().includes(q) ||
      (emp.department || "").toLowerCase().includes(q) ||
      (emp.job_title || "").toLowerCase().includes(q)
    );
  });

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Active": return "bg-[#00e07a]/10 text-[#00e07a] border-[#00e07a]/20";
      case "Terminated": return "bg-red-500/10 text-red-500 border-red-500/20";
      case "LOA": return "bg-amber-500/10 text-amber-500 border-amber-500/20";
      case "Probation": return "bg-[#c084fc]/10 text-[#c084fc] border-[#c084fc]/20";
      default: return "bg-gray-500/10 text-gray-400 border-gray-500/20";
    }
  };

  const getInitials = (name: string) => {
    if (!name) return "?";
    return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-white mb-1" style={{ fontFamily: "'Plus Jakarta Sans', sans-serif" }}>
              Employee Master Directory
            </h1>
            <p className="text-sm text-gray-400">Core HR &bull; Manage personnel records</p>
          </div>
          <button className="px-4 py-2 bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/20 rounded-lg text-sm font-medium hover:bg-[#00e07a]/20 transition-all shadow-[0_0_15px_rgba(0,224,122,0.15)] flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            Add Employee
          </button>
        </div>
      </div>

      {/* Main Content */}
      <div className="flex-1 overflow-auto p-8">
        <div className="bg-[#161922]/70 border border-white/5 rounded-xl flex flex-col max-h-full">
          {/* Panel Header */}
          <div className="flex-none p-5 border-b border-white/5 flex justify-between items-center">
            <div className="relative w-72">
              <input
                type="text"
                placeholder="Search employees..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-[#1a1d27]/80 border border-white/10 rounded-lg py-2 pl-9 pr-4 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 transition-colors"
              />
              <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div className="text-sm text-gray-400">
              {filteredEmployees.length} record{filteredEmployees.length !== 1 ? "s" : ""}
            </div>
          </div>

          {/* Table */}
          <div className="flex-1 overflow-auto">
            {loading ? (
              <div className="p-8 text-center text-gray-400">Loading directory...</div>
            ) : filteredEmployees.length === 0 ? (
              <div className="p-8 text-center text-gray-500">No employees found.</div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead className="sticky top-0 bg-[#161922] shadow-sm z-10">
                  <tr>
                    <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-white/5">Employee</th>
                    <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-white/5">Role & Department</th>
                    <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-white/5">Status</th>
                    <th className="py-3 px-5 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-white/5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredEmployees.map((emp) => (
                    <tr key={emp.id} className="hover:bg-white/[0.02] transition-colors border-b border-white/[0.02]">
                      <td className="py-4 px-5 align-middle">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-full bg-[#00e07a]/15 border border-[#00e07a]/30 flex items-center justify-center text-[#c084fc] font-bold text-sm">
                            {getInitials(emp.full_name)}
                          </div>
                          <div>
                            <div className="text-sm font-semibold text-white mb-0.5">{emp.full_name}</div>
                            <div className="text-xs text-gray-500">{emp.email}</div>
                          </div>
                        </div>
                      </td>
                      <td className="py-4 px-5 align-middle">
                        <div className="text-sm text-gray-300">{emp.job_title || "—"}</div>
                        <div className="text-xs text-gray-500">{emp.department || "—"}</div>
                      </td>
                      <td className="py-4 px-5 align-middle">
                        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${getStatusColor(emp.employment_status)}`}>
                          {emp.employment_status || "Unknown"}
                        </span>
                      </td>
                      <td className="py-4 px-5 align-middle text-right">
                        <button className="px-3 py-1.5 bg-transparent border border-white/10 rounded text-xs font-medium text-white hover:bg-white/5 hover:border-white/20 transition-all">
                          View Master
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
