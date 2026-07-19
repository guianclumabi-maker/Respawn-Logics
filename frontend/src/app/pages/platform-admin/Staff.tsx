import { apiFetch } from "../../lib/apiClient";
import { useEffect, useState } from "react";
import { Plus, Trash2, Users, Shield, HeadphonesIcon, RefreshCw, X } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=saas_staff`;

type StaffMember = {
  id: number;
  full_name: string;
  email: string;
  role: string;
  employment_status: string;
};

const ROLE_ICONS: Record<string, any> = {
  Platform_Admin: Shield,
  Support_Agent: HeadphonesIcon,
  Implementation_Specialist: Users,
};

const ROLE_COLORS: Record<string, string> = {
  Platform_Admin: "bg-violet-500/20 text-violet-300 border-violet-500/30",
  Support_Agent: "bg-blue-500/20 text-blue-300 border-blue-500/30",
  Implementation_Specialist: "bg-emerald-500/20 text-emerald-300 border-emerald-500/30",
};

async function getCsrf(): Promise<string> {
  const r = await apiFetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
  const d = await r.json();
  return d.csrf_token ?? "";
}

export function PlatformAdminStaff() {
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [showModal, setShowModal] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ first_name: "", last_name: "", email: "", password: "", role: "Support_Agent" });
  const [formError, setFormError] = useState("");

  const load = () => {
    setLoading(true);
    apiFetch(`${API}&action=list`, { credentials: "include" })
      .then((r) => r.json())
      .then((d) => { if (d.success) setStaff(d.data ?? []); else setError(d.error ?? "Failed to load."); })
      .catch(() => setError("Could not reach server."))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const handleCreate = async () => {
    setSaving(true);
    setFormError("");
    try {
      const csrf = await getCsrf();
      const r = await apiFetch(`${API}&action=create`, {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
        body: JSON.stringify(form),
      });
      const d = await r.json();
      if (d.success) {
        setShowModal(false);
        setForm({ first_name: "", last_name: "", email: "", password: "", role: "Support_Agent" });
        load();
      } else {
        setFormError(d.error ?? "Failed to create.");
      }
    } catch {
      setFormError("Network error.");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Remove this staff member?")) return;
    const csrf = await getCsrf();
    const r = await apiFetch(`${API}&action=delete`, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
      body: JSON.stringify({ user_id: id }),
    });
    const d = await r.json();
    if (d.success) load();
    else alert(d.error ?? "Failed to delete.");
  };

  return (
    <div className="p-8 max-w-5xl mx-auto">
      <div className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
            Internal Staff
          </h1>
          <p className="text-slate-500 text-sm mt-1">Manage Platform Admins, Support Agents, and Implementation Specialists.</p>
        </div>
        <div className="flex gap-2">
          <button onClick={load} className="flex items-center gap-2 px-3 py-2 rounded-lg bg-card/[0.03] border border-white/[0.07] text-muted-foreground hover:text-foreground text-sm transition-colors">
            <RefreshCw size={14} className={loading ? "animate-spin" : ""} />
          </button>
          <button
            onClick={() => setShowModal(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-foreground text-sm font-medium transition-colors"
          >
            <Plus size={15} /> Add Staff
          </button>
        </div>
      </div>

      {error && <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400 text-sm mb-6">{error}</div>}

      <div className="grid gap-3">
        {loading && (
          <div className="flex items-center gap-2 text-slate-500 py-10">
            <div className="w-4 h-4 border-2 border-violet-500/40 border-t-violet-500 rounded-full animate-spin" />
            Loading…
          </div>
        )}
        {!loading && staff.length === 0 && (
          <div className="text-center py-16 text-slate-600">
            <Users size={32} className="mx-auto mb-3 opacity-30" />
            No internal staff found.
          </div>
        )}
        {!loading && staff.map((s) => {
          const Icon = ROLE_ICONS[s.role] ?? Users;
          return (
            <div key={s.id} className="bg-background border border-white/[0.05] rounded-xl px-5 py-4 flex items-center gap-4 hover:border-border transition-all">
              <div className="w-10 h-10 rounded-full bg-violet-500/10 border border-violet-500/20 flex items-center justify-center flex-shrink-0">
                <Icon size={16} className="text-violet-300" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-foreground font-medium text-sm">{s.full_name}</p>
                <p className="text-slate-500 text-xs">{s.email}</p>
              </div>
              <span className={`px-2.5 py-1 rounded-full text-xs font-medium border ${ROLE_COLORS[s.role] ?? "bg-muted text-slate-300 border-slate-600"}`}>
                {s.role.replace(/_/g, " ")}
              </span>
              <span className={`px-2 py-0.5 rounded-full text-xs ${s.employment_status === "Active" ? "bg-emerald-500/10 text-emerald-400" : "bg-muted text-muted-foreground"}`}>
                {s.employment_status}
              </span>
              <button
                onClick={() => handleDelete(s.id)}
                className="text-slate-600 hover:text-red-400 transition-colors ml-2 cursor-pointer"
              >
                <Trash2 size={15} />
              </button>
            </div>
          );
        })}
      </div>

      {/* Add Staff Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-background border border-white/[0.08] rounded-2xl w-full max-w-md shadow-2xl">
            <div className="px-6 py-5 border-b border-white/[0.05] flex items-center justify-between">
              <h2 className="text-base font-semibold text-foreground">Add Internal Staff</h2>
              <button onClick={() => setShowModal(false)} className="text-slate-500 hover:text-foreground cursor-pointer">
                <X size={18} />
              </button>
            </div>
            <div className="px-6 py-5 space-y-4">
              {formError && <div className="text-red-400 text-sm bg-red-500/10 rounded-lg px-3 py-2">{formError}</div>}
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-slate-500 mb-1.5">First Name</label>
                  <input value={form.first_name} onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
                    className="w-full px-3 py-2.5 bg-background border border-white/[0.07] rounded-lg text-sm text-foreground focus:outline-none focus:border-violet-500/40" />
                </div>
                <div>
                  <label className="block text-xs text-slate-500 mb-1.5">Last Name</label>
                  <input value={form.last_name} onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
                    className="w-full px-3 py-2.5 bg-background border border-white/[0.07] rounded-lg text-sm text-foreground focus:outline-none focus:border-violet-500/40" />
                </div>
              </div>
              <div>
                <label className="block text-xs text-slate-500 mb-1.5">Email</label>
                <input type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                  className="w-full px-3 py-2.5 bg-background border border-white/[0.07] rounded-lg text-sm text-foreground focus:outline-none focus:border-violet-500/40" />
              </div>
              <div>
                <label className="block text-xs text-slate-500 mb-1.5">Password</label>
                <input type="password" value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                  className="w-full px-3 py-2.5 bg-background border border-white/[0.07] rounded-lg text-sm text-foreground focus:outline-none focus:border-violet-500/40" />
              </div>
              <div>
                <label className="block text-xs text-slate-500 mb-1.5">Role</label>
                <select value={form.role} onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}
                  className="w-full px-3 py-2.5 bg-background border border-white/[0.07] rounded-lg text-sm text-foreground focus:outline-none focus:border-violet-500/40">
                  <option value="Support_Agent">Support Agent</option>
                  <option value="Implementation_Specialist">Implementation Specialist</option>
                  <option value="Platform_Admin">Platform Admin</option>
                </select>
              </div>
            </div>
            <div className="px-6 pb-5 flex justify-end gap-3">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 rounded-lg text-muted-foreground hover:text-foreground text-sm transition-colors cursor-pointer">
                Cancel
              </button>
              <button onClick={handleCreate} disabled={saving}
                className="px-5 py-2 rounded-lg bg-violet-600 hover:bg-violet-500 text-foreground text-sm font-medium transition-colors disabled:opacity-50 cursor-pointer">
                {saving ? "Saving…" : "Create"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
