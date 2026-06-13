import { useState } from "react";
import { 
  Briefcase, 
  MapPin, 
  Users, 
  Plus, 
  Search, 
  Filter, 
  MoreHorizontal, 
  Check, 
  Edit2, 
  Calendar 
} from "lucide-react";

type JobsPageProps = {
  onViewChange: (view: any) => void;
};

export function JobsPage({ onViewChange }: JobsPageProps) {
  const [search, setSearch] = useState("");
  const [selectedDept, setSelectedDept] = useState("All");

  // Mock list of issue categories
  const jobs = [
    { id: 1, title: "Overtime & Pay Dispute", dept: "Finance / Ops", loc: "HQ Office", candidates: 5, status: "Open", date: "May 12, 2026" },
    { id: 2, title: "Policy Violations Inquiry", dept: "Legal / Compliance", loc: "HQ Office", candidates: 2, status: "Open", date: "May 10, 2026" },
    { id: 3, title: "Interpersonal Disputes", dept: "HR / Admin", loc: "Regional Hub", candidates: 3, status: "Open", date: "May 15, 2026" },
    { id: 4, title: "Department Transfers", dept: "Operations", loc: "HQ Office", candidates: 1, status: "Closed", date: "Apr 20, 2026" },
  ];

  const depts = ["All", "Finance / Ops", "Legal / Compliance", "HR / Admin", "Operations"];

  const filteredJobs = jobs.filter(j => {
    const matchesSearch = j.title.toLowerCase().includes(search.toLowerCase()) || j.loc.toLowerCase().includes(search.toLowerCase());
    const matchesDept = selectedDept === "All" || j.dept === selectedDept;
    return matchesSearch && matchesDept;
  });

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin" style={{ backgroundColor: "#0d0f19" }}>
      {/* Background glow */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#8b5cf6] blur-[120px] opacity-10 pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#ec4899] blur-[140px] opacity-8 pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent" style={{ fontFamily: "'Outfit', 'Inter', sans-serif" }}>
            Issue Categories
          </h1>
          <p className="text-xs text-[#9ca3af] mt-1">Manage dispute classes, departments, and tracking statuses.</p>
        </div>
        <button
          className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold transition-all hover:opacity-95 cursor-pointer bg-gradient-to-r from-[#8b5cf6] to-[#ec4899] text-white shadow-lg shadow-purple-500/10 border-0"
        >
          <Plus size={14} />
          Create New Category
        </button>
      </div>

      {/* Toolbar / Filters */}
      <div className="relative z-10 flex flex-col sm:flex-row gap-3 mb-6 items-stretch sm:items-center">
        {/* Search */}
        <div
          className="flex-1 flex items-center gap-2 px-3 py-2 rounded-xl border bg-[#161922]/30 backdrop-blur-md transition-all focus-within:border-cyan-500/50"
          style={{ borderColor: "rgba(255, 255, 255, 0.08)" }}
        >
          <Search size={14} style={{ color: "#4b5563" }} />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search issue categories..."
            className="flex-1 bg-transparent outline-none text-xs text-white placeholder-gray-600"
          />
        </div>

        {/* Department Filters */}
        <div className="flex gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-thin">
          {depts.map(dept => (
            <button
              key={dept}
              onClick={() => setSelectedDept(dept)}
              className="px-3 py-2 rounded-xl text-xs font-semibold border transition-all cursor-pointer whitespace-nowrap"
              style={{
                backgroundColor: selectedDept === dept ? "rgba(139, 92, 246, 0.12)" : "rgba(17, 19, 28, 0.4)",
                borderColor: selectedDept === dept ? "#8b5cf6" : "rgba(255, 255, 255, 0.06)",
                color: selectedDept === dept ? "#ffffff" : "#9ca3af",
              }}
            >
              {dept}
            </button>
          ))}
        </div>
      </div>

      {/* Jobs Grid list */}
      <div className="relative z-10 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
        {filteredJobs.length === 0 ? (
          <div className="col-span-full p-12 text-center text-xs text-gray-500 rounded-2xl border border-white/5 bg-[#161922]/10">
            No active categories match your filters.
          </div>
        ) : (
          filteredJobs.map((job) => {
            const isClosed = job.status === "Closed";
            return (
              <div 
                key={job.id}
                className="p-5 rounded-2xl border bg-[#161922]/20 backdrop-blur-md transition-all hover:border-white/10 flex flex-col justify-between group h-48"
                style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}
              >
                <div>
                  <div className="flex items-start justify-between mb-3">
                    <span className="text-[9px] font-bold tracking-wider text-purple-400 uppercase bg-purple-500/10 px-2 py-0.5 rounded border border-purple-500/20">
                      {job.dept}
                    </span>
                    <span 
                      className={`text-[8px] font-black uppercase px-2 py-0.5 rounded border ${
                        isClosed 
                          ? "border-rose-500/20 bg-rose-500/10 text-rose-400" 
                          : "border-emerald-500/20 bg-emerald-500/10 text-emerald-400"
                      }`}
                    >
                      {job.status}
                    </span>
                  </div>
                  
                  <h3 
                    onClick={() => job.title === "Overtime & Pay Dispute" && onViewChange("Candidates")}
                    className={`text-sm font-semibold tracking-wide block truncate group-hover:text-cyan-300 transition-colors ${
                      job.title === "Overtime & Pay Dispute" ? "cursor-pointer hover:underline" : ""
                    }`}
                  >
                    {job.title}
                  </h3>
                  
                  <div className="flex items-center gap-1 mt-1 text-[10px] text-gray-400">
                    <MapPin size={10} />
                    <span>{job.loc}</span>
                  </div>
                </div>

                <div className="border-t border-white/[0.04] pt-3 mt-4 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Users size={12} className="text-[#a855f7]" />
                    <span className="text-xs font-semibold text-white">{job.candidates} Active Cases</span>
                  </div>
                  
                  <div className="flex items-center gap-1.5 text-[9px] text-gray-500">
                    <Calendar size={10} />
                    <span>{job.date}</span>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
