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
        if (!user?.tier_config?.org_units) {
            return; // Hide if tier doesn't support org units
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

    if (!user?.tier_config?.org_units) {
        return <div className="p-8 text-center text-muted-foreground">Org Units are not available on your current plan. Please upgrade to access this feature.</div>;
    }

    if (loading) return <div className="p-8 text-foreground">Loading...</div>;

    return (
        <div className="p-8 bg-background min-h-screen text-foreground">
            <h1 className="text-2xl font-bold text-foreground mb-6">Organization Units</h1>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                {/* Create Org Unit */}
                <div className="bg-card/50 border border-border rounded-lg p-6">
                    <h2 className="text-lg font-semibold text-foreground mb-4">Create / Edit Org Unit</h2>
                    <form onSubmit={handleCreateUnit} className="space-y-4">
                        <div>
                            <label className="block text-sm mb-1">Unit Name</label>
                            <input 
                                type="text" 
                                value={name} 
                                onChange={e => setName(e.target.value)} 
                                className="w-full bg-card/50 border border-border rounded px-3 py-2 text-foreground focus:outline-none focus:border-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm mb-1">Parent Unit</label>
                            <select 
                                value={parentId} 
                                onChange={e => setParentId(e.target.value)} 
                                className="w-full bg-background border border-border rounded px-3 py-2 text-foreground focus:outline-none focus:border-blue-500"
                            >
                                <option value="">None (Top Level)</option>
                                {units.map(u => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-foreground px-4 py-2 rounded transition-colors">
                            Save Unit
                        </button>
                    </form>
                </div>

                {/* Assign Users */}
                <div className="bg-card/50 border border-border rounded-lg p-6">
                    <h2 className="text-lg font-semibold text-foreground mb-4">Assign Users to Org Units</h2>
                    <form onSubmit={handleAssignUser} className="space-y-4">
                        <div>
                            <label className="block text-sm mb-1">Select User</label>
                            <select 
                                value={selectedUser} 
                                onChange={e => setSelectedUser(e.target.value)} 
                                className="w-full bg-background border border-border rounded px-3 py-2 text-foreground focus:outline-none focus:border-blue-500"
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
                                className="w-full bg-background border border-border rounded px-3 py-2 text-foreground focus:outline-none focus:border-blue-500"
                            >
                                <option value="">None (Unassigned)</option>
                                {units.map(u => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </div>
                        <button type="submit" className="bg-green-600 hover:bg-green-700 text-foreground px-4 py-2 rounded transition-colors">
                            Assign User
                        </button>
                    </form>
                </div>
            </div>

            {/* List Units */}
            <div className="mt-8 bg-card/50 border border-border rounded-lg p-6">
                <h2 className="text-lg font-semibold text-foreground mb-4">Current Organization Units</h2>
                {units.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No organization units defined.</p>
                ) : (
                    <ul className="space-y-2">
                        {units.map(u => (
                            <li key={u.id} className="flex justify-between items-center bg-card/50 p-3 rounded border border-border">
                                <div>
                                    <span className="font-medium text-foreground">{u.name}</span>
                                    {u.parent_id && <span className="text-xs text-muted-foreground ml-2">(Parent ID: {u.parent_id})</span>}
                                </div>
                                <span className="text-xs text-muted-foreground">ID: {u.id}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
