import React from 'react';
import { Save, ServerCog, Banknote, Plus, ToggleRight, ToggleLeft } from 'lucide-react';
import { usePayroll } from './PayrollContext';

export function SettingsTab() {
  const {
    settings,
    setSettings,
    payComponents,
    showComponentModal,
    setShowComponentModal,
    editingComponent,
    setEditingComponent,
    handleSaveSettings,
    handleSaveComponent,
    handleDeleteComponent
  } = usePayroll();

  return (
    <div className="dashboard-content animate-slide-up pb-20">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold">Payroll Configuration</h2>
          <p className="text-muted mt-1">Configure global payroll policies and pay components.</p>
        </div>
        <button className="btn btn-primary" onClick={handleSaveSettings}><Save size={18} /> Save Settings</button>
      </div>

      <div className="dashboard-bottom-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
        
        {/* General Settings */}
        <div className="card col-span-2">
          <h3 className="mb-4 flex items-center gap-2"><ServerCog className="text-blue-500"/> General Policies</h3>
          <div className="grid grid-cols-2 gap-4">
            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Default Pay Frequency</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.default_pay_frequency || 'Semi-Monthly'}
                onChange={e => setSettings({...settings, default_pay_frequency: e.target.value})}
              >
                <option value="Monthly">Monthly</option>
                <option value="Semi-Monthly">Semi-Monthly</option>
                <option value="Weekly">Weekly</option>
                <option value="Daily">Daily</option>
              </select>
            </div>
            
            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Proration Method</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.proration_method || 'split_even'}
                onChange={e => setSettings({...settings, proration_method: e.target.value})}
              >
                <option value="split_even">Split Evenly (50% per cutoff)</option>
                <option value="full_first_cutoff">Full deduction on First Cutoff</option>
                <option value="full_second_cutoff">Full deduction on Second Cutoff</option>
              </select>
            </div>

            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Default Pay Basis</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.default_pay_basis || 'monthly'}
                onChange={e => setSettings({...settings, default_pay_basis: e.target.value})}
              >
                <option value="monthly">Monthly</option>
                <option value="daily">Daily</option>
                <option value="hourly">Hourly</option>
              </select>
            </div>

            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Rounding Mode</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.rounding_mode || 'half_up'}
                onChange={e => setSettings({...settings, rounding_mode: e.target.value})}
              >
                <option value="half_up">Round Half Up (.5 goes up)</option>
                <option value="half_even">Round Half Even (Banker's Rounding)</option>
              </select>
            </div>

            <div className="form-group flex items-center justify-between p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Tax Annualization</h4>
                <p className="text-xs text-muted">Automatically calculate annualized tax on last run of year</p>
              </div>
              <button onClick={() => setSettings({...settings, tax_annualization: settings.tax_annualization ? 0 : 1})} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.tax_annualization ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>

            <div className="form-group flex items-center justify-between p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">MWE Auto Exempt</h4>
                <p className="text-xs text-muted">Automatically zero out tax for Minimum Wage Earners</p>
              </div>
              <button onClick={() => setSettings({...settings, mwe_auto_exempt: settings.mwe_auto_exempt ? 0 : 1})} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.mwe_auto_exempt ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>

          </div>
        </div>

        {/* Pay Components Table */}
        <div className="card p-5 col-span-2">
          <div className="flex justify-between items-center mb-4">
            <h3 className="flex items-center gap-2"><Banknote className="text-emerald-500"/> Pay Components</h3>
            <button className="btn btn-primary py-1 px-3" onClick={() => { setEditingComponent({ kind: 'earning', calc_type: 'fixed', is_active: 1, taxable: 1 }); setShowComponentModal(true); }}>
              <Plus size={16}/> Add Component
            </button>
          </div>
          
          <div className="table-container">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-border-color">
                  <th className="p-3">Code</th>
                  <th className="p-3">Name</th>
                  <th className="p-3">Type</th>
                  <th className="p-3">Calculation</th>
                  <th className="p-3">Value</th>
                  <th className="p-3">Taxable</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {payComponents.length === 0 ? (
                  <tr><td colSpan={8} className="p-4 text-center text-muted">No components configured.</td></tr>
                ) : payComponents.map(c => (
                  <tr key={c.id} className="border-b border-border-color hover:bg-bg-card-hover">
                    <td className="p-3 font-mono text-sm">{c.code}</td>
                    <td className="p-3 font-semibold">{c.name}</td>
                    <td className="p-3">
                      <span className={`badge ${c.kind === 'earning' ? 'badge-emerald' : 'badge-rose'}`}>{c.kind}</span>
                    </td>
                    <td className="p-3 text-sm">{c.calc_type.replace('_', ' ')}</td>
                    <td className="p-3 font-mono">{c.value ? parseFloat(c.value).toLocaleString() : '-'}</td>
                    <td className="p-3">{c.taxable ? 'Yes' : 'No'}</td>
                    <td className="p-3">{c.is_active ? 'Active' : 'Inactive'}</td>
                    <td className="p-3 flex gap-2">
                      <button className="text-blue-500 hover:text-blue-400" onClick={() => { setEditingComponent(c); setShowComponentModal(true); }}>Edit</button>
                      <button className="text-rose-500 hover:text-rose-400" onClick={() => handleDeleteComponent(c.id)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

      </div>

      {showComponentModal && (
        <div className="modal-overlay flex items-center justify-center fixed inset-0 bg-black/50 z-50">
          <div className="modal-content bg-bg-card p-6 rounded-lg w-[500px] border border-border-color shadow-xl">
            <h3 className="text-xl font-bold mb-4">{editingComponent?.id ? 'Edit Component' : 'Add Component'}</h3>
            <form onSubmit={handleSaveComponent} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm mb-1">Code</label>
                  <input required className="w-full p-2 rounded border border-border-color bg-bg-app text-foreground" 
                    value={editingComponent?.code || ''} 
                    onChange={e => setEditingComponent({...editingComponent, code: e.target.value})} 
                  />
                </div>
                <div>
                  <label className="block text-sm mb-1">Name</label>
                  <input required className="w-full p-2 rounded border border-border-color bg-bg-app text-foreground" 
                    value={editingComponent?.name || ''} 
                    onChange={e => setEditingComponent({...editingComponent, name: e.target.value})} 
                  />
                </div>
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm mb-1">Kind</label>
                  <select className="w-full p-2 rounded border border-border-color bg-bg-app text-foreground"
                    value={editingComponent?.kind || 'earning'}
                    onChange={e => setEditingComponent({...editingComponent, kind: e.target.value})}
                  >
                    <option value="earning">Earning</option>
                    <option value="deduction">Deduction</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm mb-1">Calc Type</label>
                  <select className="w-full p-2 rounded border border-border-color bg-bg-app text-foreground"
                    value={editingComponent?.calc_type || 'fixed'}
                    onChange={e => setEditingComponent({...editingComponent, calc_type: e.target.value})}
                  >
                    <option value="fixed">Fixed Amount</option>
                    <option value="percent_of_base">% of Base Salary</option>
                    <option value="statutory">Statutory Config</option>
                  </select>
                </div>
              </div>

              {(editingComponent?.calc_type === 'fixed' || editingComponent?.calc_type === 'percent_of_base') && (
                <div>
                  <label className="block text-sm mb-1">Value {editingComponent?.calc_type === 'percent_of_base' ? '(%)' : '(Amount)'}</label>
                  <input type="number" step="0.01" className="w-full p-2 rounded border border-border-color bg-bg-app text-foreground" 
                    value={editingComponent?.value || ''} 
                    onChange={e => setEditingComponent({...editingComponent, value: e.target.value})} 
                  />
                </div>
              )}

              {editingComponent?.calc_type === 'statutory' && (
                <div>
                  <label className="block text-sm mb-1">Statutory Key</label>
                  <input className="w-full p-2 rounded border border-border-color bg-bg-app text-foreground" placeholder="e.g. SSS_EE, PHIC_EE"
                    value={editingComponent?.statutory_key || ''} 
                    onChange={e => setEditingComponent({...editingComponent, statutory_key: e.target.value})} 
                  />
                </div>
              )}

              <div className="flex gap-4">
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" checked={editingComponent?.taxable == 1} 
                    onChange={e => setEditingComponent({...editingComponent, taxable: e.target.checked ? 1 : 0})} 
                  /> Taxable
                </label>
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" checked={editingComponent?.is_active == 1} 
                    onChange={e => setEditingComponent({...editingComponent, is_active: e.target.checked ? 1 : 0})} 
                  /> Active
                </label>
              </div>

              <div className="flex justify-end gap-3 mt-6">
                <button type="button" className="btn bg-slate-700 hover:bg-slate-600 text-slate-900 dark:text-white" onClick={() => setShowComponentModal(false)}>Cancel</button>
                <button type="submit" className="btn btn-primary">Save Component</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
