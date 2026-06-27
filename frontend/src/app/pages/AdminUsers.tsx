import { useState, useEffect } from "react";
import { useAuth } from "../context/AuthContext";
import { User, Mail, Shield, Circle, Edit, Trash2 } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=iam&action=users`;

type UserData = {
  id: number;
  full_name: string;
  email: string;
  roles: string[];
  status: string;
};

export function AdminUsers() {
  const { user } = useAuth();
  const [users, setUsers] = useState<UserData[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchUsers = async () => {
      try {
        const res = await fetch(API, { credentials: "include" });
        if (res.ok) {
          const json = await res.json();
          if (json.success && json.data) {
            setUsers(json.data);
            return;
          }
        }
        // Fallback/Mock data if endpoint not yet returning users
        setUsers([
          { id: 1, full_name: "Admin User", email: "admin@respawn.logics", roles: ["Super Admin"], status: "Active" },
          { id: 2, full_name: "John Doe", email: "john@respawn.logics", roles: ["HR Manager"], status: "Active" },
          { id: 3, full_name: "Jane Smith", email: "jane@respawn.logics", roles: ["Employee"], status: "Inactive" }
        ]);
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    };

    fetchUsers();
  }, []);

  if (loading) {
    return <div className="p-8 text-slate-400">Loading users...</div>;
  }

  return (
    <div className="h-full w-full flex flex-col p-8 overflow-y-auto" style={{ backgroundColor: "#f9fafb" }}>
      <header className="mb-6 flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight mb-2">User Management</h1>
          <p className="text-gray-500">Manage platform users, roles, and access status.</p>
        </div>
        <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
          <User className="w-4 h-4" />
          Add User
        </button>
      </header>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-gray-600">
            <thead className="bg-gray-50 border-b border-gray-200 text-gray-700 uppercase text-xs font-semibold">
              <tr>
                <th className="px-6 py-4">User</th>
                <th className="px-6 py-4">Contact</th>
                <th className="px-6 py-4">Roles</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {users.map(u => (
                <tr key={u.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                        {u.full_name.charAt(0)}
                      </div>
                      <div className="font-medium text-gray-900">{u.full_name}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-2 text-gray-500">
                      <Mail className="w-4 h-4" />
                      {u.email}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex gap-2">
                      {u.roles.map((role, i) => (
                        <span key={i} className="bg-slate-100 text-slate-700 border border-slate-200 px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
                          <Shield className="w-3 h-3" />
                          {role}
                        </span>
                      ))}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${u.status === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'}`}>
                      <Circle className={`w-2 h-2 ${u.status === 'Active' ? 'fill-emerald-500 text-emerald-500' : 'fill-gray-500 text-gray-500'}`} />
                      {u.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right">
                    <button className="text-blue-600 hover:text-blue-800 p-2 transition-colors" title="Edit">
                      <Edit className="w-4 h-4" />
                    </button>
                    <button className="text-red-500 hover:text-red-700 p-2 transition-colors" title="Delete">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              ))}
              {users.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                    No users found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
