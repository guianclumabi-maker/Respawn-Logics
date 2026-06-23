import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';

export function OrgUnits() {
    const { user } = useAuth();
    const [units, setUnits] = useState<any[]>([]);
    const [users, setUsers] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [name, setName] = useState('');
    const [parentId, setParentId] = useState('');
    const [selectedUser, setSelectedUser] = useState('');
    const [selectedUnit, setSelectedUnit] = useState('');

    useEffect(() => {
        if (user?.tenant_setup_mode === 'Solo' || user?.tenant_setup_mode === 'Small') {
            return; // Hide for small tenants
        }
        fetchData();
    }, [user]);

    const fetchData = async () => {
        try {
            const [unitsRes, usersRes] = await Promise.all([
                fetch('/api/index.php?route=iam&action=org_units', {credentials: 'include'}).then(r=>r.json()),
                fetch('/api/index.php?route=iam&action=users', {credentials: 'include'}).then(r=>r.json())
            ]);
            setUnits(unitsRes.data || []);
            setUsers(usersRes.data || []);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    const handleCreateUnit = async (e: any) => {
        e.preventDefault();
        await fetch('/api/index.php?route=iam&action=save_org_unit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ name, parent_id: parentId || null })
        });
        setName('');
        setParentId('');
        fetchData();
    };

    const handleAssignUser = async (e: any) => {
        e.preventDefault();
        await fetch('/api/index.php?route=iam&action=assign_org_unit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ user_id: selectedUser, org_unit_id: selectedUnit || null })
        });
        setSelectedUser('');
        setSelectedUnit('');
        fetchData();
    };

    if (user?.tenant_setup_mode === 'Solo' || user?.tenant_setup_mode === 'Small') {
        return <div className="p-8 text-center text-gray-500">Org Units are only available for Enterprise plans.</div>;
    }

    if (loading) return <div className="p-8 text-white">Loading...</div>;

    return (
        <div className="p-8 bg-[#0b0f19] min-h-screen text-gray-300">
            <h1 className="text-2xl font-bold text-white mb-6">Organization Units</h1>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                {/* Create Org Unit */}
                <div className="bg-white/5 border border-white/10 rounded-lg p-6">
                    <h2 className="text-lg font-semibold text-white mb-4">Create / Edit Org Unit</h2>
                    <form onSubmit={handleCreateUnit} className="space-y-4">
                        <div>
                            <label className="block text-sm mb-1">Unit Name</label>
                            <input 
                                type="text" 
                                value={name} 
                                onChange={e => setName(e.target.value)} 
                                className="w-full bg-white/5 border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm mb-1">Parent Unit</label>
                            <select 
                                value={parentId} 
                                onChange={e => setParentId(e.target.value)} 
                                className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500"
                            >
                                <option value="">None (Top Level)</option>
                                {units.map(u => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                            Save Unit
                        </button>
                    </form>
                </div>

                {/* Assign Users */}
                <div className="bg-white/5 border border-white/10 rounded-lg p-6">
                    <h2 className="text-lg font-semibold text-white mb-4">Assign Users to Org Units</h2>
                    <form onSubmit={handleAssignUser} className="space-y-4">
                        <div>
                            <label className="block text-sm mb-1">Select User</label>
                            <select 
                                value={selectedUser} 
                                onChange={e => setSelectedUser(e.target.value)} 
                                className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500"
                                required
                            >
                                <option value="">-- Select User --</option>
                                {users.map(u => (
                                    <option key={u.id} value={u.id}>{u.full_name} ({u.email})</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm mb-1">Select Org Unit</label>
                            <select 
                                value={selectedUnit} 
                                onChange={e => setSelectedUnit(e.target.value)} 
                                className="w-full bg-[#0b0f19] border border-white/10 rounded px-3 py-2 text-white focus:outline-none focus:border-blue-500"
                            >
                                <option value="">None (Unassigned)</option>
                                {units.map(u => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        <button type="submit" className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors">
                            Assign User
                        </button>
                    </form>
                </div>
            </div>

            {/* List Units */}
            <div className="mt-8 bg-white/5 border border-white/10 rounded-lg p-6">
                <h2 className="text-lg font-semibold text-white mb-4">Current Organization Units</h2>
                {units.length === 0 ? (
                    <p className="text-gray-500 text-sm">No organization units defined.</p>
                ) : (
                    <ul className="space-y-2">
                        {units.map(u => (
                            <li key={u.id} className="flex justify-between items-center bg-white/5 p-3 rounded border border-white/5">
                                <div>
                                    <span className="font-medium text-white">{u.name}</span>
                                    {u.parent_id && <span className="text-xs text-gray-500 ml-2">(Parent ID: {u.parent_id})</span>}
                                </div>
                                <span className="text-xs text-gray-500">ID: {u.id}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
