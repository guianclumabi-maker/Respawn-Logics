import { apiFetch } from "../lib/apiClient";
import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { User, Mail, Shield, Circle, Edit, UserX, UserCheck, X } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=iam&action=users`;

type UserData = {
  id: number;
  full_name: string;
  email: string;
  roles: any[];
  status: string;
  legacy_role?: string;
  employment_status?: string;
};

type Role = { id: number; name: string };

export function AdminUsers() {
  const navigate = useNavigate();
  const [users, setUsers] = useState<UserData[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Role-assignment modal state
  const [roles, setRoles] = useState<Role[]>([]);
  const [editingUser, setEditingUser] = useState<UserData | null>(null);
  const [selectedRoleId, setSelectedRoleId] = useState("");
  const [busy, setBusy] = useState(false);

  const fetchUsers = async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const res = await apiFetch(API, { credentials: "include" });
      const json = res.ok ? await res.json() : null;
      if (json?.success && json.data) {
        setUsers(json.data.map((u: any) => ({
          ...u,
          status: u.status || u.employment_status || "Active",
        })));
      } else {
        // No mock fallback: showing fake users would hide a real outage.
        setUsers([]);
        setLoadError(json?.error || "Could not load users from the server.");
      }
    } catch (e) {
      console.error(e);
      setUsers([]);
      setLoadError("Could not reach the server.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchUsers(); }, []);

  const openEdit = async (u: UserData) => {
    setEditingUser(u);
    setSelectedRoleId("");
    if (roles.length === 0) {
      try {
        const res = await apiFetch(`${API_BASE.replace(window.location.origin, "")}/api/index.php?route=iam&action=roles`, { credentials: "include" });
        const json = await res.json();
        if (json?.success) setRoles(json.data || []);
      } catch (e) { console.error(e); }
    }
  };

  const assignRole = async () => {
    if (!editingUser || !selectedRoleId) return;
    setBusy(true);
    try {
      const res = await apiFetch(`/api/index.php?route=iam&action=assign_role`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        // scope intentionally omitted -> backend applies the tenant tier's default scope
        // (sending "tenant" here would silently over-grant visibility for every role).
        body: JSON.stringify({ user_id: editingUser.id, role_id: parseInt(selectedRoleId, 10) }),
      });
      const json = await res.json();
      if (json?.success) {
        setEditingUser(null);
        fetchUsers();
      } else {
        alert(json?.error || "Failed to assign role.");
      }
    } catch (e) {
      console.error(e);
      alert("Error assigning role.");
    } finally {
      setBusy(false);
    }
  };

  // There is deliberately no hard-delete: records must survive for audit/DPA.
  // Suspend/Reinstate (Core HR) is the real lifecycle action.
  const toggleSuspension = async (u: UserData) => {
    const suspending = u.status !== "Suspended";
    const action = suspending ? "suspend_employee" : "reinstate_employee";
    if (!confirm(`${suspending ? "Suspend" : "Reinstate"} ${u.full_name}?`)) return;
    const reason = suspending ? prompt("Reason for suspension:") : null;
    if (suspending && !reason) return;
    try {
      const res = await apiFetch(`/api/index.php?route=core_hr&action=${action}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(suspending ? { employee_id: u.id, reason } : { employee_id: u.id }),
      });
      const json = await res.json();
      if (json?.success) {
        if (json.warning) alert(json.warning);
        fetchUsers();
      } else {
        alert(json?.error || `Failed to ${suspending ? "suspend" : "reinstate"}.`);
      }
    } catch (e) {
      console.error(e);
      alert("Error updating employment status.");
    }
  };

  if (loading) {
    return <div className="p-8 text-muted-foreground">Loading users...</div>;
  }

  return (
    <div className="h-full w-full flex flex-col p-8 overflow-y-auto" >
      <header className="mb-6 flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-foreground tracking-tight mb-2">User Management</h1>
          <p className="text-muted-foreground">Manage platform users, roles, and access status.</p>
        </div>
        {/* Users are created through hiring (ATS) or the HR Directory — route there. */}
        <button
          onClick={() => navigate("/hr-directory")}
          className="bg-blue-600 hover:bg-blue-700 text-foreground px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-2 cursor-pointer"
        >
          <User className="w-4 h-4" />
          Add User (via HR Directory)
        </button>
      </header>

      {loadError && (
        <div className="mb-4 p-3 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-sm">
          {loadError}
        </div>
      )}

      <div className="bg-card rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-muted-foreground">
            <thead className="bg-muted/50 border-b border-gray-200 text-foreground uppercase text-xs font-semibold">
              <tr>
                <th className="px-6 py-4">User</th>
                <th className="px-6 py-4">Contact</th>
                <th className="px-6 py-4">Roles</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {users.map(u => (
                <tr key={u.id} className="hover:bg-muted/50 transition-colors">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-primary/10 text-blue-600 flex items-center justify-center font-bold">
                        {u.full_name.charAt(0)}
                      </div>
                      <div className="font-medium text-foreground">{u.full_name}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <Mail className="w-4 h-4" />
                      {u.email}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex gap-2">
                      {u.roles && u.roles.map((role: any, i: number) => (
                        <span key={i} className="bg-slate-100 text-slate-700 border border-slate-200 px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
                          <Shield className="w-3 h-3" />
                          {typeof role === "string" ? role : role.name}
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${u.status === "Active" ? "bg-emerald-100 text-emerald-800" : u.status === "Suspended" ? "bg-red-100 text-red-800" : "bg-gray-100 text-gray-800"}`}>
                      <Circle className={`w-2 h-2 ${u.status === "Active" ? "fill-emerald-500 text-emerald-500" : u.status === "Suspended" ? "fill-red-500 text-red-500" : "fill-gray-500 text-muted-foreground"}`} />
                      {u.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right">
                    <button onClick={() => openEdit(u)} className="text-blue-600 hover:text-blue-800 p-2 transition-colors cursor-pointer" title="Assign role">
                      <Edit className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => toggleSuspension(u)}
                      className={`p-2 transition-colors cursor-pointer ${u.status === "Suspended" ? "text-emerald-600 hover:text-emerald-700" : "text-red-500 hover:text-red-700"}`}
                      title={u.status === "Suspended" ? "Reinstate" : "Suspend"}
                    >
                      {u.status === "Suspended" ? <UserCheck className="w-4 h-4" /> : <UserX className="w-4 h-4" />}
                    </button>
                  </td>
                </tr>
              ))}
              {users.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-muted-foreground">
                    No users found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Role-assignment modal */}
      {editingUser && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onClick={() => setEditingUser(null)}>
          <div className="bg-card border border-border rounded-xl p-6 w-full max-w-sm" onClick={(e) => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-4">
              <h3 className="font-bold text-foreground">Assign role — {editingUser.full_name}</h3>
              <button onClick={() => setEditingUser(null)} className="text-muted-foreground hover:text-foreground cursor-pointer"><X className="w-4 h-4" /></button>
            </div>
            <select
              value={selectedRoleId}
              onChange={(e) => setSelectedRoleId(e.target.value)}
              className="w-full mb-4 p-2 rounded-md bg-input border border-border text-foreground text-sm"
            >
              <option value="">Select a role…</option>
              {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
            <div className="flex justify-end gap-2">
              <button onClick={() => setEditingUser(null)} className="px-3 py-2 text-sm rounded-lg border border-border text-foreground hover:bg-accent cursor-pointer">Cancel</button>
              <button onClick={assignRole} disabled={busy || !selectedRoleId} className="px-3 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium disabled:opacity-50 cursor-pointer">
                {busy ? "Assigning…" : "Assign role"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
