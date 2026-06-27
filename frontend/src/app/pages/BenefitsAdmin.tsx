import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Users, Plus, X } from 'lucide-react';
import { Navigate } from 'react-router-dom';

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === 'localhost' ? '/respawn-logics' : ''));

interface BenefitPlan {
  id: number;
  name: string;
  description: string;
  provider: string;
  type: string;
  company_cost: string;
  employee_cost: string;
  enrolled_count: number;
}

interface Enrollment {
  id: number;
  full_name: string;
  employee_number: string;
  plan_name: string;
  type: string;
  dependent_count: number;
  employee_cost: string;
  status: string;
}

export function BenefitsAdmin() {
  const { user, hasPermission, loading } = useAuth();
  
  const [activeTab, setActiveTab] = useState<'plans' | 'enrollments'>('plans');
  const [plans, setPlans] = useState<BenefitPlan[]>([]);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showPlanModal, setShowPlanModal] = useState(false);

  // Form state
  const [planName, setPlanName] = useState('');
  const [planProvider, setPlanProvider] = useState('');
  const [planType, setPlanType] = useState('HMO');
  const [planCompanyCost, setPlanCompanyCost] = useState('0.00');
  const [planEmployeeCost, setPlanEmployeeCost] = useState('0.00');
  const [planDesc, setPlanDesc] = useState('');

  const loadPlans = async () => {
    setIsLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/index.php?route=benefits&action=hr_plans`);
      const data = await res.json();
      if (data.success) {
        setPlans(data.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  const loadEnrollments = async () => {
    setIsLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/index.php?route=benefits&action=hr_enrollments`);
      const data = await res.json();
      if (data.success) {
        setEnrollments(data.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (!loading && hasPermission('benefits.manage')) {
      if (activeTab === 'plans') loadPlans();
      else loadEnrollments();
    }
  }, [activeTab, loading]);

  if (loading) return <div className="p-8 text-white">Loading...</div>;
  if (!hasPermission('benefits.manage')) return <Navigate to="/" />;

  const submitPlan = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('name', planName);
    formData.append('provider', planProvider);
    formData.append('type', planType);
    formData.append('company_cost', planCompanyCost);
    formData.append('employee_cost', planEmployeeCost);
    formData.append('description', planDesc);

    try {
      const res = await fetch(`${API_BASE}/api/index.php?route=benefits&action=hr_create_plan`, { 
        method: 'POST', 
        body: formData 
      });
      const data = await res.json();
      if(data.success) {
        setShowPlanModal(false);
        // Reset form
        setPlanName('');
        setPlanProvider('');
        setPlanType('HMO');
        setPlanCompanyCost('0.00');
        setPlanEmployeeCost('0.00');
        setPlanDesc('');
        loadPlans();
      } else { 
        alert(data.error); 
      }
    } catch(e) {
      console.error(e);
    }
  };

  return (
    <div className="flex flex-col h-full bg-[#0b0f19] text-[#8899b4] p-6 relative font-['Inter',sans-serif]">
      {/* Ambient Glow */}
      <div className="absolute top-0 left-1/4 w-96 h-96 bg-emerald-500/10 blur-[120px] pointer-events-none rounded-full" />

      {/* Header */}
      <div className="flex justify-between items-start mb-6 z-10">
        <div>
          <h1 className="text-2xl font-bold text-white mb-1">Benefits Administration</h1>
          <p className="text-sm text-slate-400">Manage company-sponsored HMO plans, De Minimis allowances, and track employee enrollments.</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-4 mb-6 border-b border-white/10 pb-3 z-10">
        <button 
          className={`px-4 py-2 text-sm rounded-md transition-colors ${activeTab === 'plans' ? 'bg-white/5 text-white' : 'text-slate-400 hover:text-white'}`}
          onClick={() => setActiveTab('plans')}
        >
          Benefit Plans
        </button>
        <button 
          className={`px-4 py-2 text-sm rounded-md transition-colors ${activeTab === 'enrollments' ? 'bg-white/5 text-white' : 'text-slate-400 hover:text-white'}`}
          onClick={() => setActiveTab('enrollments')}
        >
          Employee Enrollments
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto z-10">
        {activeTab === 'plans' ? (
          <div className="bg-[#161922]/70 border border-white/10 rounded-xl p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg text-white font-medium">Active Benefit Plans</h3>
              <button 
                className="flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs font-medium transition-colors"
                onClick={() => setShowPlanModal(true)}
              >
                <Plus size={14} /> New Plan
              </button>
            </div>
            
            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Name</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Provider</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Type</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Company Cost (Principal)</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Employee Cost (Dependent)</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Enrolled Emps</th>
                  </tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr><td colSpan={6} className="text-center p-8 text-slate-400">Loading...</td></tr>
                  ) : plans.length === 0 ? (
                    <tr><td colSpan={6} className="text-center p-8 text-slate-400">No benefit plans found.</td></tr>
                  ) : (
                    plans.map(p => (
                      <tr key={p.id}>
                        <td className="p-3 border-b border-white/10 text-white text-sm">
                          <strong>{p.name}</strong><br/>
                          <span className="text-xs text-slate-400">{p.description}</span>
                        </td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">{p.provider}</td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">
                          <span className="bg-white/10 px-2 py-1 rounded text-xs">{p.type}</span>
                        </td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">${parseFloat(p.company_cost).toLocaleString()}</td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">${parseFloat(p.employee_cost).toLocaleString()}</td>
                        <td className="p-3 border-b border-white/10 text-white text-sm flex items-center gap-1">
                          {p.enrolled_count} <Users size={12} className="text-slate-400" />
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
          <div className="bg-[#161922]/70 border border-white/10 rounded-xl p-6">
            <h3 className="text-lg text-white font-medium mb-4">Company-wide Enrollments</h3>
            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Employee</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Plan Name</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Type</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Dependents Enrolled</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Payroll Deduction</th>
                    <th className="p-3 text-left text-sm font-semibold text-slate-400 border-b border-white/10">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr><td colSpan={6} className="text-center p-8 text-slate-400">Loading...</td></tr>
                  ) : enrollments.length === 0 ? (
                    <tr><td colSpan={6} className="text-center p-8 text-slate-400">No enrollments found.</td></tr>
                  ) : (
                    enrollments.map(e => (
                      <tr key={e.id}>
                        <td className="p-3 border-b border-white/10 text-white text-sm">
                          <strong>{e.full_name}</strong><br/>
                          <span className="text-xs text-slate-400">{e.employee_number}</span>
                        </td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">{e.plan_name}</td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">{e.type}</td>
                        <td className="p-3 border-b border-white/10 text-white text-sm">{e.dependent_count}</td>
                        <td className="p-3 border-b border-white/10 text-red-400 text-sm">
                          ${(parseFloat(e.employee_cost) * e.dependent_count).toLocaleString()}
                        </td>
                        <td className="p-3 border-b border-white/10 text-[#00e07a] text-sm">{e.status}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Plan Modal */}
      {showPlanModal && (
        <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-[#161922] w-full max-w-[450px] rounded-xl border border-white/10 overflow-hidden shadow-2xl">
            <div className="p-5 border-b border-white/10 flex justify-between items-center">
              <h3 className="text-lg font-bold text-white m-0">Create Benefit Plan</h3>
              <button className="text-slate-400 hover:text-white" onClick={() => setShowPlanModal(false)}>
                <X size={20} />
              </button>
            </div>
            <div className="p-5">
              <form onSubmit={submitPlan}>
                <div className="mb-3">
                  <label className="block text-xs text-slate-400 mb-1">Plan Name</label>
                  <input 
                    type="text" 
                    required 
                    placeholder="e.g. Maxicare Platinum"
                    className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:border-blue-500 outline-none"
                    value={planName}
                    onChange={e => setPlanName(e.target.value)}
                  />
                </div>
                <div className="mb-3">
                  <label className="block text-xs text-slate-400 mb-1">Provider</label>
                  <input 
                    type="text" 
                    placeholder="e.g. Maxicare"
                    className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:border-blue-500 outline-none"
                    value={planProvider}
                    onChange={e => setPlanProvider(e.target.value)}
                  />
                </div>
                <div className="mb-3">
                  <label className="block text-xs text-slate-400 mb-1">Type</label>
                  <select 
                    className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:border-blue-500 outline-none"
                    value={planType}
                    onChange={e => setPlanType(e.target.value)}
                  >
                    <option value="HMO">HMO</option>
                    <option value="De Minimis">De Minimis Allowance</option>
                    <option value="Perk">Perk</option>
                  </select>
                </div>
                <div className="flex gap-3 mb-3">
                  <div className="flex-1">
                    <label className="block text-xs text-slate-400 mb-1">Company Cost</label>
                    <input 
                      type="number" 
                      step="0.01" 
                      className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:border-blue-500 outline-none"
                      value={planCompanyCost}
                      onChange={e => setPlanCompanyCost(e.target.value)}
                    />
                  </div>
                  <div className="flex-1">
                    <label className="block text-xs text-slate-400 mb-1">Employee Cost (per Dep)</label>
                    <input 
                      type="number" 
                      step="0.01" 
                      className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:border-blue-500 outline-none"
                      value={planEmployeeCost}
                      onChange={e => setPlanEmployeeCost(e.target.value)}
                    />
                  </div>
                </div>
                <div className="mb-6">
                  <label className="block text-xs text-slate-400 mb-1">Description</label>
                  <textarea 
                    rows={2} 
                    className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:border-blue-500 outline-none"
                    value={planDesc}
                    onChange={e => setPlanDesc(e.target.value)}
                  />
                </div>
                <button type="submit" className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-md transition-colors">
                  Create Plan
                </button>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
