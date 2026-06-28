import { apiFetch } from "../lib/apiClient";
import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Navigate } from 'react-router-dom';

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === 'localhost' ? '/respawn-logics' : ''));

interface Claim {
  id: number;
  employee_name: string;
  expense_date: string;
  category_name: string;
  amount: string;
  receipt_path: string | null;
}

export function ExpensesAdmin() {
  const { user, hasPermission, loading } = useAuth();
  
  const [activeTab, setActiveTab] = useState<'manager' | 'finance'>('manager');
  const [claims, setClaims] = useState<Claim[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const loadData = async (tab: 'manager' | 'finance') => {
    setIsLoading(true);
    try {
      const action = tab === 'manager' ? 'manager_pending' : 'finance_pending';
      const res = await apiFetch(`/api/index.php?route=expenses&action=${action}`);
      const data = await res.json();
      if (data.success) {
        setClaims(data.data);
      } else {
        setClaims([]);
      }
    } catch (e) {
      console.error(e);
      setClaims([]);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (!loading) {
      // For Expenses, there's a backend check. 
      // If we are here, we attempt to load data based on the active tab.
      loadData(activeTab);
    }
  }, [activeTab, loading]);

  const approveClaim = async (id: number, decision: 'Approve' | 'Reject') => {
    const comments = window.prompt(`Enter optional comments for ${decision}:`);
    if (comments === null) return; // Cancelled
    
    try {
      
      const res = await apiFetch(`/api/index.php?route=expenses&action=approve_claim`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', },
        body: JSON.stringify({ claim_id: id, decision: decision, comments: comments })
      });
      const data = await res.json();
      if (data.success) {
        loadData(activeTab);
      } else {
        alert(data.error);
      }
    } catch(e) {
      console.error(e);
    }
  };

  if (loading) return <div className="p-8 text-white">Loading...</div>;

  return (
    <div className="flex flex-col h-full bg-[#0b0f19] text-[#8899b4] p-6 relative font-['Inter',sans-serif]">
      {/* Ambient Glow */}
      <div className="absolute top-0 right-1/4 w-96 h-96 bg-green-500/5 blur-[120px] pointer-events-none rounded-full" />

      {/* Header */}
      <div className="flex justify-between items-start mb-6 z-10">
        <div>
          <h1 className="text-2xl font-bold text-white mb-1">Expense & Claims Console</h1>
          <p className="text-sm text-slate-400">Approve employee reimbursements and queue for payroll.</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-white/10 mb-6 z-10">
        <button 
          className={`px-6 py-3 text-sm font-semibold transition-all border-b-2 ${activeTab === 'manager' ? 'text-[#00e07a] border-[#00e07a]' : 'text-slate-400 border-transparent hover:text-white'}`}
          onClick={() => setActiveTab('manager')}
        >
          Manager Approvals (My Team)
        </button>
        {hasPermission('expenses.manage') && (
          <button 
            className={`px-6 py-3 text-sm font-semibold transition-all border-b-2 ${activeTab === 'finance' ? 'text-[#00e07a] border-[#00e07a]' : 'text-slate-400 border-transparent hover:text-white'}`}
            onClick={() => setActiveTab('finance')}
          >
            Finance Approvals (All Teams)
          </button>
        )}
      </div>

      {/* Panel */}
      <div className="flex-1 overflow-y-auto z-10">
        <div className="bg-[#161922]/70 border border-white/10 rounded-xl p-5">
          <h3 className="text-lg text-white font-medium mb-5">
            {activeTab === 'manager' ? 'Pending Manager Approval' : 'Pending Finance Approval'}
          </h3>
          
          <div className="overflow-x-auto">
            <table className="w-full border-collapse min-w-[800px]">
              <thead>
                <tr>
                  <th className="text-left p-3 text-xs font-semibold uppercase text-slate-400 border-b border-white/10">Employee</th>
                  <th className="text-left p-3 text-xs font-semibold uppercase text-slate-400 border-b border-white/10">Date / Category</th>
                  <th className="text-left p-3 text-xs font-semibold uppercase text-slate-400 border-b border-white/10">Amount</th>
                  <th className="text-left p-3 text-xs font-semibold uppercase text-slate-400 border-b border-white/10">Receipt</th>
                  <th className="text-right p-3 text-xs font-semibold uppercase text-slate-400 border-b border-white/10">Actions</th>
                </tr>
              </thead>
              <tbody>
                {isLoading ? (
                  <tr><td colSpan={5} className="text-center p-8 text-slate-400">Loading claims...</td></tr>
                ) : claims.length === 0 ? (
                  <tr><td colSpan={5} className="text-center p-8 text-slate-400">
                    {activeTab === 'manager' ? 'No pending team claims.' : 'No pending finance approvals.'}
                  </td></tr>
                ) : (
                  claims.map(e => (
                    <tr key={e.id} className="hover:bg-white/[0.02]">
                      <td className="p-4 border-b border-white/5 text-white text-sm align-middle">
                        <strong>{e.employee_name}</strong>
                      </td>
                      <td className="p-4 border-b border-white/5 align-middle">
                        <div className="text-white text-sm">{e.expense_date}</div>
                        <div className="text-xs text-slate-400">{e.category_name}</div>
                      </td>
                      <td className="p-4 border-b border-white/5 text-[#00e07a] font-bold text-sm align-middle">
                        ${parseFloat(e.amount).toLocaleString()}
                      </td>
                      <td className="p-4 border-b border-white/5 align-middle">
                        {e.receipt_path ? (
                          <a href={`${API_BASE}${e.receipt_path.startsWith('/') ? '' : '/'}${e.receipt_path}`} target="_blank" rel="noreferrer" className="text-[#00e07a] hover:underline text-sm">
                            View File
                          </a>
                        ) : <span className="text-sm text-slate-500">None</span>}
                      </td>
                      <td className="p-4 border-b border-white/5 text-right align-middle">
                        <div className="flex gap-2 justify-end">
                          <button 
                            className="bg-[#00e07a]/10 hover:bg-[#00e07a] text-[#00e07a] hover:text-white border border-[#00e07a] px-3 py-1.5 rounded text-xs transition-colors"
                            onClick={() => approveClaim(e.id, 'Approve')}
                          >
                            {activeTab === 'finance' ? 'Clear for Payout' : 'Approve'}
                          </button>
                          <button 
                            className="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white border border-red-500 px-3 py-1.5 rounded text-xs transition-colors"
                            onClick={() => approveClaim(e.id, 'Reject')}
                          >
                            Reject
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
