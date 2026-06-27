import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

export function AdminRoles() {
    const { user } = useAuth();
    const [users, setUsers] = useState<any[]>([]);
    const [roles, setRoles] = useState<any[]>([]);
    const [orgUnits, setOrgUnits] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    // Form state
    const [selectedUser, setSelectedUser] = useState('');
    const [selectedRole, setSelectedRole] = useState('');
    const [selectedScope, setSelectedScope] = useState('tenant');
    const [selectedOrgUnit, setSelectedOrgUnit] = useState('');

    useEffect(() => {
        if (user?.tenant_setup_mode === 'Solo') {
            return; // Hide for solo
        }
        fetchData();
    }, [user]);

    const fetchData = async () => {
        try {
            const [usersRes, rolesRes, unitsRes] = await Promise.all([
                fetch('/api/index.php?route=iam&action=users', {credentials: 'include'}).then(r=>r.json()),
                fetch('/api/index.php?route=iam&action=roles', {credentials: 'include'}).then(r=>r.json()),
                fetch('/api/index.php?route=iam&action=org_units', {credentials: 'include'}).then(r=>r.json())
            ]);
            setUsers(usersRes.data || []);
            setRoles(rolesRes.data || []);
            setOrgUnits(unitsRes.data || []);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    const handleAssignRole = async (e: any) => {
        e.preventDefault();
        try {
            await fetch('/api/index.php?route=iam&action=assign_role', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    user_id: selectedUser,
                    role_id: selectedRole,
                    scope: selectedScope,
                    org_unit_id: selectedScope === 'department' || selectedScope === 'team' ? selectedOrgUnit : null
                })
            });
            fetchData();
            setSelectedUser('');
            setSelectedRole('');
            setSelectedScope('tenant');
            setSelectedOrgUnit('');
        } catch (e) {
            alert('Failed to assign role');
        }
    };

    if (user?.tenant_setup_mode === 'Solo') {
        return <div className="p-8 text-center text-gray-500">Roles are hidden in Solo mode.</div>;
    }

    if (loading) return <div className="p-8 text-white">Loading...</div>;

    return (
        <div className="p-8 bg-[#0b0f19] min-h-screen text-gray-300">
            <h1 className="text-2xl font-bold text-white mb-6">Manage Roles & Scopes</h1>
            
            <div className="bg-white/5 border border-white/10 rounded-lg p-6 mb-8">
                <h2 className="text-lg font-semibold text-white mb-4">Assign / Update Role Scope</h2>
                <form onSubmit={handleAssignRole} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm mb-1">User</label>
                        <select 
                            value={selectedUser} 
                            onChange={e => setSelectedUser(e.target.value)} 
                            className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white"
                            required
                        >
                            <option value="">-- Select User --</option>
                            {users.map(u => (
                                <option key={u.id} value={u.id}>{u.full_name} ({u.email})</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm mb-1">Role</label>
                        <select 
                            value={selectedRole} 
                            onChange={e => setSelectedRole(e.target.value)} 
                            className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white"
                            required
                        >
                            <option value="">-- Select Role --</option>
                            {roles.map(r => (
                                <option key={r.id} value={r.id}>{r.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm mb-1">Scope</label>
                        <select 
                            value={selectedScope} 
                            onChange={e => setSelectedScope(e.target.value)} 
                            className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white"
                            required
                        >
                            <option value="tenant">Tenant (All)</option>
                            <option value="department">Department</option>
                            <option value="team">Team (Direct Reports)</option>
                            <option value="self">Self</option>
                        </select>
                    </div>
                    {(selectedScope === 'department' || selectedScope === 'team') && (
                        <div>
                            <label className="block text-sm mb-1">Org Unit</label>
                            <select 
                                value={selectedOrgUnit} 
                                onChange={e => setSelectedOrgUnit(e.target.value)} 
                                className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white"
                                required
                            >
                                <option value="">-- Select Org Unit --</option>
                                {orgUnits.map(u => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div className="md:col-span-2 pt-2">
                        <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            Assign / Update Role
                        </button>
                    </div>
                </form>
            </div>

            <div className="bg-white/5 border border-white/10 rounded-lg p-6">
                <h2 className="text-lg font-semibold text-white mb-4">Current Assignments</h2>
                <div className="space-y-4">
                    {users.map(u => (
                        <div key={u.id} className="bg-white/5 p-4 rounded border border-white/5">
                            <div className="font-medium text-white mb-2">{u.full_name} <span className="text-sm text-gray-500">({u.email})</span></div>
                            {u.roles && u.roles.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {u.roles.map((r: any, i: number) => (
                                        <span key={i} className="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs rounded border border-blue-500/30">
                                            {r.name} — Scope: {r.scope} {r.org_unit_id ? `(Org Unit ID: ${r.org_unit_id})` : ''}
                                        </span>
                                    ))}
                                </div>
                            ) : (
                                <span className="text-xs text-gray-500">No roles assigned</span>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
