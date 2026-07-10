import React from 'react';
import { CalendarClock } from 'lucide-react';
import { usePayroll } from './PayrollContext';

export function HolidaysTab() {
  const {
    holidays,
    isHolidaysLoading,
    tsStart,
    tsEnd,
    newHoliday,
    setNewHoliday,
    handleSaveHoliday,
    handleDeleteHoliday
  } = usePayroll();

  return (
    <div className="card mb-6 bg-card text-card-foreground/40 border-border p-6 animate-slide-up">
      <div className="flex justify-between items-center mb-4 pb-2 border-b border-border">
        <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
          <CalendarClock size={20} className="text-blue-500" />
          Holiday Calendar ({tsStart} to {tsEnd})
        </h3>
        <span className="text-xs text-muted-foreground">Only applicable to holidays in the selected period</span>
      </div>

      {isHolidaysLoading ? (
        <div className="text-center text-muted py-4">Loading holidays...</div>
      ) : (
        <div className="grid grid-cols-3 gap-6">
          {/* Add Holiday Form */}
          <div className="col-span-1 bg-input border-border p-4 rounded-lg border border-border">
            <h4 className="font-semibold text-sm mb-3 text-foreground">Add Holiday</h4>
            <form onSubmit={handleSaveHoliday} className="space-y-3">
              <div className="form-group">
                <label className="text-xs text-tertiary block mb-1">Date</label>
                <input 
                  type="date" 
                  required
                  className="w-full p-2 rounded bg-input border-border border border-border text-foreground text-xs outline-none focus:border-emerald-500"
                  value={newHoliday.holiday_date}
                  onChange={e => setNewHoliday({ ...newHoliday, holiday_date: e.target.value })}
                />
              </div>
              <div className="form-group">
                <label className="text-xs text-tertiary block mb-1">Name</label>
                <input 
                  type="text" 
                  required
                  placeholder="e.g. Christmas Day"
                  className="w-full p-2 rounded bg-input border-border border border-border text-foreground text-xs outline-none focus:border-emerald-500"
                  value={newHoliday.name}
                  onChange={e => setNewHoliday({ ...newHoliday, name: e.target.value })}
                />
              </div>
              <div className="form-group">
                <label className="text-xs text-tertiary block mb-1">Type</label>
                <select 
                  className="w-full p-2 rounded bg-input border-border border border-border text-foreground text-xs outline-none focus:border-emerald-500"
                  value={newHoliday.type}
                  onChange={e => setNewHoliday({ ...newHoliday, type: e.target.value })}
                >
                  <option value="Regular Holiday">Regular Holiday</option>
                  <option value="Special Non-Working">Special Non-Working</option>
                </select>
              </div>
              <button 
                type="submit"
                className="w-full py-2 bg-blue-600 hover:bg-blue-700 text-foreground text-xs font-bold rounded transition-all cursor-pointer"
              >
                + Save Holiday
              </button>
            </form>
          </div>

          {/* Holiday List Table */}
          <div className="col-span-2 overflow-hidden border border-border rounded-lg">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-black/40 border-b border-border text-muted-foreground font-semibold">
                  <th className="p-3">Date</th>
                  <th className="p-3">Name</th>
                  <th className="p-3">Type</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {holidays.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="p-4 text-center text-muted">No holidays registered in this period.</td>
                  </tr>
                ) : (
                  holidays.map(h => (
                    <tr key={h.id} className="border-b border-border hover:bg-accent">
                      <td className="p-3 font-mono text-foreground">{h.holiday_date}</td>
                      <td className="p-3 font-semibold text-foreground">{h.name}</td>
                      <td className="p-3">
                        <span className={`badge ${h.type === 'Regular Holiday' ? 'badge-blue' : 'badge-amber'}`}>
                          {h.type}
                        </span>
                      </td>
                      <td className="p-3 text-right">
                        <button 
                          onClick={() => handleDeleteHoliday(h.id)}
                          className="px-2 py-1 bg-rose-600/20 text-rose-400 hover:bg-rose-600/30 rounded text-[10px] font-bold transition-all cursor-pointer"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
