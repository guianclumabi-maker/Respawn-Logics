import { useState, useEffect } from "react";
import { Shield, ShieldAlert, Key, Plus, Check } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=iam&action=roles`;

type Role = {
  id: number;
  name: string;
  description: string;
  permissions: string[];
};

export function AdminRoles() {
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchRoles = async () => {
      try {
        const res = await fetch(API, { credentials: "include" });
        if (res.ok) {
          const json = await res.json();
          if (json.success && json.roles) {
            setRoles(json.roles);
            return;
          }
        }
        // Mock data
        setRoles([
          { id: 1, name: "Super Admin", description: "Full access to all system features.", permissions: ["all"] },
          { id: 2, name: "HR Manager", description: "Manage employees, attendance, and payroll.", permissions: ["users.view", "users.edit", "payroll.manage"] },
          { id: 3, name: "Employee", description: "Basic access to self-service portal.", permissions: ["self.view"] }
        ]);
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    };
    fetchRoles();
  }, []);

  if (loading) {
    return <div className="p-8 text-slate-400">Loading roles...</div>;
  }

  return (
    <div className="h-full w-full flex flex-col p-8 overflow-y-auto" style={{ backgroundColor: "#f9fafb" }}>
      <header className="mb-6 flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight mb-2">Roles & Permissions</h1>
          <p className="text-gray-500">Define access control levels and assign permissions to roles.</p>
        </div>
        <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
          <Plus className="w-4 h-4" />
          Create Role
        </button>
      </header>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        {roles.map(role => (
          <div key={role.id} className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
            <div className="p-5 border-b border-gray-100 flex gap-4 items-start">
              <div className="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                {role.name === 'Super Admin' ? (
                  <ShieldAlert className="w-5 h-5 text-indigo-600" />
                ) : (
                  <Shield className="w-5 h-5 text-indigo-500" />
                )}
              </div>
              <div>
                <h3 className="font-bold text-gray-900 text-lg">{role.name}</h3>
                <p className="text-sm text-gray-500 mt-1">{role.description}</p>
              </div>
            </div>
            <div className="p-5 flex-1 bg-gray-50/50">
              <div className="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                <Key className="w-4 h-4 text-gray-400" />
                Permissions
              </div>
              <ul className="space-y-2">
                {role.permissions.map((perm, idx) => (
                  <li key={idx} className="text-sm text-gray-600 flex items-center gap-2">
                    <Check className="w-3 h-3 text-emerald-500" />
                    <code className="bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded text-xs border border-gray-200">
                      {perm}
                    </code>
                  </li>
                ))}
              </ul>
            </div>
            <div className="p-4 border-t border-gray-100 bg-white flex justify-end gap-2">
              <button className="px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors">
                Edit
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
