import React from 'react';
import { HelpCircle, AlertTriangle, Save, AlertCircle, Trash2 } from 'lucide-react';
import { usePayroll } from './PayrollContext';
import { HolidaysTab } from './HolidaysTab';

export function TimesheetsTab() {
  const {
    timesheets,
    selectedTsIds,
    setSelectedTsIds,
    tsStart,
    setTsStart,
    tsEnd,
    setTsEnd,
    tsEmpId,
    setTsEmpId,
    tsStatus,
    setTsStatus,
    employees,
    editingTsId,
    setEditingTsId,
    editingTsData,
    setEditingTsData,
    isTsLoading,
    isGenerating,
    showHolidays,
    setShowHolidays,
    handleSetTsStatus,
    handleSetTsStatusPeriod,
    handleDeleteTsRow,
    handleGenerateDraft,
    handleSaveTsRow,
    startTimesheetTour
  } = usePayroll();

  const isAllSelected = timesheets.length > 0 && selectedTsIds.length === timesheets.length;

  const handleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked) {
      setSelectedTsIds(timesheets.map(t => t.id));
    } else {
      setSelectedTsIds([]);
    }
  };

  const handleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
      setSelectedTsIds([...selectedTsIds, id]);
    } else {
      setSelectedTsIds(selectedTsIds.filter(x => x !== id));
    }
  };

  return (
    <div className="dashboard-content animate-slide-up pb-20">
      <div id="tour-ts-header" className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold flex items-center gap-2">
            Timesheets Checkpoint
            <button
              type="button"
              onClick={startTimesheetTour}
              title="Replay the guided tour"
              className="text-muted-foreground hover:text-[#00e07a] transition-colors"
            >
              <HelpCircle size={18} />
            </button>
          </h2>
          <p className="text-muted mt-1">Review, edit, and approve employee daily work hours for payroll calculation.</p>
        </div>

        <div id="tour-ts-approve" className="flex gap-2">
          <button
            onClick={() => handleSetTsStatus('Approved')}
            disabled={selectedTsIds.length === 0}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${selectedTsIds.length === 0 ? 'bg-slate-700 text-muted-foreground cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-foreground cursor-pointer'}`}
          >
            Approve Selected ({selectedTsIds.length})
          </button>
          <button 
            onClick={() => handleSetTsStatus('Rejected')}
            disabled={selectedTsIds.length === 0}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${selectedTsIds.length === 0 ? 'bg-slate-700 text-muted-foreground cursor-not-allowed' : 'bg-rose-600 hover:bg-rose-700 text-foreground cursor-pointer'}`}
          >
            Reject Selected ({selectedTsIds.length})
          </button>
        </div>
      </div>

      {/* Warning banner about payroll compliance requirement */}
      <div className="card bg-amber-500/10 border-amber-500/30 mb-6 flex items-start gap-3 p-4">
        <AlertTriangle className="text-amber-500 flex-shrink-0 mt-0.5" size={20} />
        <div>
          <h4 className="font-semibold text-amber-200 text-sm">Compliance Note</h4>
          <p className="text-xs text-amber-200/70 mt-1">
            Only <strong>Approved</strong> timesheet entries are processed for payouts by the payroll system. 
            Any hours that are <strong>Pending</strong> or <strong>Rejected</strong> are completely ignored during payroll calculation.
          </p>
        </div>
      </div>

      {/* Filters */}
      <div id="tour-ts-filters" className="card mb-6 grid grid-cols-5 gap-4 items-end bg-card text-card-foreground/50 border-border">
        <div className="form-group">
          <label className="text-xs text-tertiary font-semibold mb-1 block">Start Date</label>
          <input 
            type="date" 
            className="w-full p-2 rounded bg-input border-border border border-border text-foreground focus:border-emerald-500 outline-none" 
            value={tsStart}
            onChange={e => setTsStart(e.target.value)}
          />
        </div>
        <div className="form-group">
          <label className="text-xs text-tertiary font-semibold mb-1 block">End Date</label>
          <input 
            type="date" 
            className="w-full p-2 rounded bg-input border-border border border-border text-foreground focus:border-emerald-500 outline-none" 
            value={tsEnd}
            onChange={e => setTsEnd(e.target.value)}
          />
        </div>
        <div className="form-group">
          <label className="text-xs text-tertiary font-semibold mb-1 block">Employee</label>
          <select 
            className="w-full p-2 rounded bg-input border-border border border-border text-foreground focus:border-emerald-500 outline-none"
            value={tsEmpId}
            onChange={e => setTsEmpId(e.target.value)}
          >
            <option value="">All Employees</option>
            {employees.map(e => (
              <option key={e.id} value={e.id}>{e.full_name} ({e.email})</option>
            ))}
          </select>
        </div>
        <div className="form-group">
          <label className="text-xs text-tertiary font-semibold mb-1 block">Status</label>
          <select 
            className="w-full p-2 rounded bg-input border-border border border-border text-foreground focus:border-emerald-500 outline-none"
            value={tsStatus}
            onChange={e => setTsStatus(e.target.value)}
          >
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
          </select>
        </div>
        <div className="flex gap-2 justify-end mb-[2px]">
          {tsEmpId && (
            <>
              <button 
                onClick={() => handleSetTsStatusPeriod('Approved')}
                className="px-3 py-2 bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30 rounded text-xs font-semibold transition-all cursor-pointer"
                title="Approve all matching rows in selected date range for this employee"
              >
                Approve Period
              </button>
              <button 
                onClick={() => handleSetTsStatusPeriod('Rejected')}
                className="px-3 py-2 bg-rose-600/20 text-rose-400 border border-rose-500/30 hover:bg-rose-600/30 rounded text-xs font-semibold transition-all cursor-pointer"
                title="Reject all matching rows in selected date range for this employee"
              >
                Reject Period
              </button>
            </>
          )}
        </div>
      </div>

      {/* Generation & Holiday Calendar Actions */}
      <div className="card mb-6 flex justify-between items-center bg-card text-card-foreground/20 border-border p-4 gap-4">
        <div className="flex items-center gap-3">
          <button
            onClick={handleGenerateDraft}
            disabled={isGenerating}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all flex-shrink-0 ${isGenerating ? 'bg-slate-700 text-muted-foreground cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-foreground cursor-pointer'}`}
          >
            {isGenerating ? "Generating..." : "Generate from Attendance"}
          </button>
          <span className="text-xs text-muted-foreground leading-normal">
            Drafts are created as Pending — review and approve before running payroll. Break/OT/rest-day rules follow company policy defaults.
          </span>
        </div>
        <div>
          <button
            onClick={() => setShowHolidays(!showHolidays)}
            className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all border border-border ${showHolidays ? 'bg-blue-600 text-slate-900 dark:text-white' : 'bg-white/5 hover:bg-accent text-foreground cursor-pointer'}`}
          >
            {showHolidays ? "Hide Holiday Calendar" : "Show Holiday Calendar"}
          </button>
        </div>
      </div>

      {/* Holiday Calendar Panel */}
      {showHolidays && <HolidaysTab />}

      {/* Timesheets Data Table */}
      <div id="tour-ts-table" className="card p-0 overflow-hidden bg-card text-card-foreground/30 border-border">
        {isTsLoading ? (
          <div className="p-8 text-center text-muted">Loading timesheets...</div>
        ) : timesheets.length === 0 ? (
          <div className="p-8 text-center text-muted flex flex-col items-center gap-2">
            <p>No timesheets found for this range.</p>
            <button 
              onClick={() => {
                if(!tsEmpId) {
                  alert("Please select a specific employee to initialize a timesheet row.");
                  return;
                }
                setEditingTsId(-1);
                setEditingTsData({
                  employee_id: tsEmpId,
                  timesheet_date: tsStart,
                  regular_hours: 8,
                  overtime_hours: 0,
                  rest_day_hours: 0,
                  special_day_hours: 0,
                  regular_holiday_hours: 0,
                  night_diff_hours: 0,
                  status: 'Pending'
                });
              }}
              className="px-4 py-2 bg-white/5 hover:bg-accent border border-border rounded text-xs font-semibold text-foreground cursor-pointer"
            >
              + Add Daily Entry
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="data-table w-full text-left border-collapse">
              <thead>
                <tr className="bg-input border-border border-b border-border">
                  <th className="p-3 w-10 text-center">
                    <input 
                      type="checkbox" 
                      className="accent-[#00e07a]" 
                      checked={isAllSelected}
                      onChange={handleSelectAll}
                    />
                  </th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider">Employee</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider">Date</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">Reg Hrs</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">OT Hrs</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">Rest Day</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">Spec Day</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">Reg Hol</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">Night Diff</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-center">Status</th>
                  <th className="p-3 text-tertiary font-semibold text-xs uppercase tracking-wider text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {/* Inline creation form */}
                {editingTsId === -1 && (
                  <tr className="bg-emerald-500/5 border-b border-border">
                    <td className="p-3"></td>
                    <td className="p-3 font-semibold text-foreground">
                      {employees.find(e => e.id == editingTsData.employee_id)?.full_name || 'Select Employee'}
                    </td>
                    <td className="p-3">
                      <input 
                        type="date"
                        className="w-32 bg-input border-border border border-border rounded px-2 py-1 text-xs text-foreground"
                        value={editingTsData.timesheet_date || ''}
                        onChange={e => setEditingTsData({ ...editingTsData, timesheet_date: e.target.value })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <input 
                        type="number" step="0.5" min="0" max="24"
                        className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                        value={editingTsData.regular_hours ?? 0}
                        onChange={e => setEditingTsData({ ...editingTsData, regular_hours: parseFloat(e.target.value) || 0 })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <input 
                        type="number" step="0.5" min="0" max="24"
                        className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                        value={editingTsData.overtime_hours ?? 0}
                        onChange={e => setEditingTsData({ ...editingTsData, overtime_hours: parseFloat(e.target.value) || 0 })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <input 
                        type="number" step="0.5" min="0" max="24"
                        className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                        value={editingTsData.rest_day_hours ?? 0}
                        onChange={e => setEditingTsData({ ...editingTsData, rest_day_hours: parseFloat(e.target.value) || 0 })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <input 
                        type="number" step="0.5" min="0" max="24"
                        className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                        value={editingTsData.special_day_hours ?? 0}
                        onChange={e => setEditingTsData({ ...editingTsData, special_day_hours: parseFloat(e.target.value) || 0 })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <input 
                        type="number" step="0.5" min="0" max="24"
                        className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                        value={editingTsData.regular_holiday_hours ?? 0}
                        onChange={e => setEditingTsData({ ...editingTsData, regular_holiday_hours: parseFloat(e.target.value) || 0 })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <input 
                        type="number" step="0.5" min="0" max="24"
                        className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                        value={editingTsData.night_diff_hours ?? 0}
                        onChange={e => setEditingTsData({ ...editingTsData, night_diff_hours: parseFloat(e.target.value) || 0 })}
                      />
                    </td>
                    <td className="p-3 text-center">
                      <span className="badge badge-amber">Pending</span>
                    </td>
                    <td className="p-3 text-right flex gap-1 justify-end">
                      <button onClick={() => handleSaveTsRow(editingTsData)} className="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 rounded text-xs text-foreground flex items-center gap-1 font-semibold cursor-pointer">
                        <Save size={12}/> Save
                      </button>
                      <button onClick={() => setEditingTsId(null)} className="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-foreground cursor-pointer">
                        Cancel
                      </button>
                    </td>
                  </tr>
                )}

                {timesheets.map((ts) => {
                  const isEditing = editingTsId === ts.id;
                  const isSelected = selectedTsIds.includes(ts.id);

                  return (
                    <tr key={ts.id} className={`border-b border-border transition-colors ${isEditing ? 'bg-blue-500/5' : 'hover:bg-accent'}`}>
                      <td className="p-3 text-center">
                        <input 
                          type="checkbox" 
                          className="accent-[#00e07a]" 
                          checked={isSelected}
                          onChange={(e) => handleSelectRow(ts.id, e.target.checked)}
                        />
                      </td>
                      <td className="p-3">
                        <span className="font-semibold text-slate-900 dark:text-white block">{ts.full_name || 'N/A'}</span>
                        <span className="text-xs text-gray-500">{ts.department || 'Staff'}</span>
                      </td>
                      <td className="p-3 font-mono text-sm text-gray-300">{ts.timesheet_date}</td>
                      
                      <td className="p-3 text-center">
                        {isEditing ? (
                          <input 
                            type="number" step="0.5" min="0" max="24"
                            className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                            value={editingTsData.regular_hours ?? 0}
                            onChange={e => setEditingTsData({ ...editingTsData, regular_hours: parseFloat(e.target.value) || 0 })}
                          />
                        ) : (
                          <span className="font-mono text-sm">{parseFloat(ts.regular_hours || 0)}</span>
                        )}
                      </td>
                      
                      <td className="p-3 text-center">
                        {isEditing ? (
                          <input 
                            type="number" step="0.5" min="0" max="24"
                            className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                            value={editingTsData.overtime_hours ?? 0}
                            onChange={e => setEditingTsData({ ...editingTsData, overtime_hours: parseFloat(e.target.value) || 0 })}
                          />
                        ) : (
                          <span className="font-mono text-sm text-muted-foreground">{parseFloat(ts.overtime_hours || 0) || '-'}</span>
                        )}
                      </td>

                      <td className="p-3 text-center">
                        {isEditing ? (
                          <input 
                            type="number" step="0.5" min="0" max="24"
                            className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                            value={editingTsData.rest_day_hours ?? 0}
                            onChange={e => setEditingTsData({ ...editingTsData, rest_day_hours: parseFloat(e.target.value) || 0 })}
                          />
                        ) : (
                          <span className="font-mono text-sm text-muted-foreground">{parseFloat(ts.rest_day_hours || 0) || '-'}</span>
                        )}
                      </td>

                      <td className="p-3 text-center">
                        {isEditing ? (
                          <input 
                            type="number" step="0.5" min="0" max="24"
                            className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                            value={editingTsData.special_day_hours ?? 0}
                            onChange={e => setEditingTsData({ ...editingTsData, special_day_hours: parseFloat(e.target.value) || 0 })}
                          />
                        ) : (
                          <span className="font-mono text-sm text-muted-foreground">{parseFloat(ts.special_day_hours || 0) || '-'}</span>
                        )}
                      </td>

                      <td className="p-3 text-center">
                        {isEditing ? (
                          <input 
                            type="number" step="0.5" min="0" max="24"
                            className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                            value={editingTsData.regular_holiday_hours ?? 0}
                            onChange={e => setEditingTsData({ ...editingTsData, regular_holiday_hours: parseFloat(e.target.value) || 0 })}
                          />
                        ) : (
                          <span className="font-mono text-sm text-muted-foreground">{parseFloat(ts.regular_holiday_hours || 0) || '-'}</span>
                        )}
                      </td>

                      <td className="p-3 text-center">
                        {isEditing ? (
                          <input 
                            type="number" step="0.5" min="0" max="24"
                            className="w-14 bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center"
                            value={editingTsData.night_diff_hours ?? 0}
                            onChange={e => setEditingTsData({ ...editingTsData, night_diff_hours: parseFloat(e.target.value) || 0 })}
                          />
                        ) : (
                          <span className="font-mono text-sm text-muted-foreground">{parseFloat(ts.night_diff_hours || 0) || '-'}</span>
                        )}
                      </td>

                      <td className="p-3 text-center">
                        {isEditing ? (
                          <select 
                            className="bg-input border-border border border-border rounded px-1 py-0.5 text-xs text-foreground text-center outline-none"
                            value={editingTsData.status || 'Pending'}
                            onChange={e => setEditingTsData({ ...editingTsData, status: e.target.value })}
                          >
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                          </select>
                        ) : (
                          <span className={`badge ${
                            ts.status === 'Approved' ? 'badge-emerald' : 
                            ts.status === 'Rejected' ? 'badge-red' : 'badge-amber'
                          }`}>
                            {ts.status || 'Pending'}
                          </span>
                        )}
                      </td>

                      <td className="p-3 text-right">
                        <div className="flex gap-1 justify-end">
                          {isEditing ? (
                            <>
                              <button onClick={() => handleSaveTsRow(editingTsData)} className="p-1.5 bg-emerald-600 hover:bg-emerald-500 rounded text-foreground cursor-pointer" title="Save">
                                <Save size={14}/>
                              </button>
                              <button onClick={() => setEditingTsId(null)} className="p-1.5 bg-slate-700 hover:bg-slate-600 rounded text-foreground cursor-pointer" title="Cancel">
                                <AlertCircle size={14}/>
                              </button>
                            </>
                          ) : (
                            <>
                              <button 
                                onClick={() => {
                                  setEditingTsId(ts.id);
                                  setEditingTsData({ ...ts });
                                }}
                                className="px-2 py-1 bg-white/5 hover:bg-accent border border-border rounded text-xs text-foreground cursor-pointer"
                              >
                                Edit
                              </button>
                              <button 
                                onClick={() => handleSetTsStatus('Approved', [ts.id])}
                                className="px-2 py-1 bg-emerald-600/10 text-emerald-400 hover:bg-emerald-600/20 border border-emerald-500/20 rounded text-xs cursor-pointer"
                              >
                                Approve
                              </button>
                              <button 
                                onClick={() => handleSetTsStatus('Rejected', [ts.id])}
                                className="px-2 py-1 bg-rose-600/10 text-rose-400 hover:bg-rose-600/20 border border-rose-500/20 rounded text-xs cursor-pointer"
                              >
                                Reject
                              </button>
                              <button 
                                onClick={() => handleDeleteTsRow(ts.id)}
                                className="p-1 hover:bg-rose-500/20 text-rose-500 rounded cursor-pointer"
                                title="Delete Daily Entry"
                              >
                                <Trash2 size={14}/>
                              </button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Helper button to init entry if not in edit mode */}
      {editingTsId === null && (
        <div className="mt-4 flex justify-end">
          <button 
            onClick={() => {
              if(!tsEmpId) {
                alert("Please select a specific employee to initialize a timesheet row.");
                return;
              }
              setEditingTsId(-1);
              setEditingTsData({
                employee_id: tsEmpId,
                timesheet_date: tsStart,
                regular_hours: 8,
                overtime_hours: 0,
                rest_day_hours: 0,
                special_day_hours: 0,
                regular_holiday_hours: 0,
                night_diff_hours: 0,
                status: 'Pending'
              });
            }}
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-foreground rounded text-sm font-semibold cursor-pointer"
          >
            + Add Daily Entry
          </button>
        </div>
      )}
    </div>
  );
}
