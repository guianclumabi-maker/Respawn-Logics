import { apiFetch } from "../lib/apiClient";
import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { Settings, Send, ChevronLeft, ChevronRight, Info, X } from 'lucide-react';
import { Navigate } from 'react-router-dom';

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === 'localhost' ? '/respawn-logics' : ''));

interface ShiftType {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
}

interface Employee {
  user_id: number;
  full_name: string;
  profile_image?: string;
  job_title: string;
  department: string;
  shifts?: Record<string, { shift_id: number }>;
}

export function Scheduling() {
  const { user, hasPermission, loading } = useAuth();
  
  const [currentDate, setCurrentDate] = useState(() => {
    const d = new Date();
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust to Monday
    return new Date(d.setDate(diff));
  });

  const [shiftTypes, setShiftTypes] = useState<ShiftType[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [rosterChanges, setRosterChanges] = useState<Record<string, number>>({});
  const [isLoading, setIsLoading] = useState(false);
  const [showShiftModal, setShowShiftModal] = useState(false);

  const [newShiftName, setNewShiftName] = useState('');
  const [newShiftStart, setNewShiftStart] = useState('');
  const [newShiftEnd, setNewShiftEnd] = useState('');

  const getDatesForWeek = (startDate: Date) => {
    let dates = [];
    for (let i = 0; i < 7; i++) {
      let d = new Date(startDate);
      d.setDate(startDate.getDate() + i);
      dates.push(d);
    }
    return dates;
  };

  const formatDateYMD = (date: Date) => {
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
  };

  const formatDisplayDate = (date: Date) => {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${days[date.getDay()]} ${months[date.getMonth()]} ${date.getDate()}`;
  };

  const loadShiftTypes = async () => {
    try {
      const res = await apiFetch(`/api/index.php?route=shifts&action=fetch_shift_types`);
      const data = await res.json();
      if (data.success) {
        setShiftTypes(data.data);
      }
    } catch (e) {
      console.error(e);
    }
  };

  const loadRoster = async () => {
    setIsLoading(true);
    const dates = getDatesForWeek(currentDate);
    const startDateStr = formatDateYMD(dates[0]);
    const endDateStr = formatDateYMD(dates[6]);
    
    try {
      const res = await apiFetch(`/api/index.php?route=shifts&action=fetch_roster&start_date=${startDateStr}&end_date=${endDateStr}`);
      const data = await res.json();
      if (data.success) {
        setEmployees(data.data);
      } else {
        setEmployees([]);
      }
    } catch (e) {
      console.error(e);
      setEmployees([]);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (!loading && hasPermission('shifts.manage')) {
      loadShiftTypes();
      loadRoster();
    }
  }, [currentDate, loading]);

  if (loading) return <div className="p-8 text-white">Loading...</div>;
  if (!hasPermission('shifts.manage')) return <Navigate to="/" />;

  const dates = getDatesForWeek(currentDate);

  const prevWeek = () => {
    const d = new Date(currentDate);
    d.setDate(d.getDate() - 7);
    setCurrentDate(d);
    setRosterChanges({});
  };

  const nextWeek = () => {
    const d = new Date(currentDate);
    d.setDate(d.getDate() + 7);
    setCurrentDate(d);
    setRosterChanges({});
  };

  const recordChange = (userId: number, dateStr: string, shiftId: number) => {
    setRosterChanges(prev => ({
      ...prev,
      [`${userId}_${dateStr}`]: shiftId
    }));
  };

  const publishSchedule = async () => {
    const changes = Object.keys(rosterChanges).map(key => {
      const parts = key.split('_');
      return {
        user_id: parseInt(parts[0]),
        date: parts[1],
        shift_id: rosterChanges[key]
      };
    });

    if (changes.length === 0) {
      alert('No changes to publish.');
      return;
    }

    try {
      
      const res = await apiFetch(`/api/index.php?route=shifts&action=publish_roster`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', },
        body: JSON.stringify({ assignments: changes })
      });
      const data = await res.json();
      if (data.success) {
        alert('Schedule published! Notifications have been sent to updated employees.');
        setRosterChanges({});
        loadRoster();
      } else {
        alert('Error: ' + data.error);
      }
    } catch (e) {
      console.error(e);
      alert('Failed to publish schedule.');
    }
  };

  const createShift = async () => {
    if (!newShiftName || !newShiftStart || !newShiftEnd) return alert('Fill all fields');

    try {
      
      const res = await apiFetch(`/api/index.php?route=shifts&action=create_shift_type`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', },
        body: JSON.stringify({ name: newShiftName, start_time: newShiftStart, end_time: newShiftEnd })
      });
      const data = await res.json();
      if (data.success) {
        setNewShiftName('');
        setNewShiftStart('');
        setNewShiftEnd('');
        await loadShiftTypes();
        loadRoster();
      } else {
        alert('Error: ' + data.error);
      }
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="flex flex-col h-full bg-[#0b0f19] text-[#8899b4] p-6 font-['Inter',sans-serif]">
      {/* Header */}
      <div className="flex justify-between items-center mb-6 pb-4 border-b border-white/10">
        <div>
          <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk',sans-serif]">Shift Scheduler</h1>
          <p className="text-sm text-slate-400">Manage your weekly team roster and automate shift notifications.</p>
        </div>
        <div className="flex gap-3">
          <button 
            className="flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 rounded-md text-white text-sm transition-colors"
            onClick={() => setShowShiftModal(true)}
          >
            <Settings size={16} /> Manage Shift Types
          </button>
          <button 
            className="flex items-center gap-2 px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black font-bold rounded-md text-sm transition-colors"
            onClick={publishSchedule}
          >
            <Send size={16} /> Publish Schedule
          </button>
        </div>
      </div>

      {/* Controls Bar */}
      <div className="flex justify-between items-center bg-[#161922] p-4 rounded-lg border border-white/10 mb-6">
        <div className="flex items-center gap-4">
          <button 
            onClick={prevWeek}
            className="w-9 h-9 rounded-full border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition-colors"
          >
            <ChevronLeft size={18} />
          </button>
          <div className="font-semibold text-lg text-white font-['Space_Grotesk',sans-serif] min-w-[250px] text-center">
            {formatDisplayDate(dates[0])} - {formatDisplayDate(dates[6])}
          </div>
          <button 
            onClick={nextWeek}
            className="w-9 h-9 rounded-full border border-white/10 flex items-center justify-center text-white hover:bg-white/10 transition-colors"
          >
            <ChevronRight size={18} />
          </button>
        </div>
        <div className="text-sm text-slate-400 flex items-center gap-2">
          <Info size={16} /> Changes made here must be published to notify employees.
        </div>
      </div>

      {/* Roster Table */}
      <div className="bg-[#161922] rounded-lg border border-white/10 overflow-x-auto flex-1">
        <table className="w-full min-w-[1000px] border-collapse">
          <thead>
            <tr>
              <th className="sticky left-0 bg-[#161922] z-10 p-4 text-left text-xs font-semibold uppercase text-slate-400 border-b border-r border-white/10 w-[250px]">
                Employee
              </th>
              {dates.map((d, i) => {
                const isToday = formatDateYMD(d) === formatDateYMD(new Date());
                return (
                  <th key={i} className={`p-4 text-left text-xs font-semibold uppercase border-b border-r border-white/10 ${isToday ? 'text-[#3b82f6]' : 'text-slate-400'}`}>
                    {formatDisplayDate(d)}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              <tr><td colSpan={8} className="text-center p-10 text-slate-400">Loading Roster...</td></tr>
            ) : employees.length === 0 ? (
              <tr><td colSpan={8} className="text-center p-10 text-slate-400">No employees found.</td></tr>
            ) : (
              employees.map(emp => {
                const initials = emp.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                return (
                  <tr key={emp.user_id}>
                    <td className="sticky left-0 bg-[#161922] z-[1] p-3 border-b border-r border-white/10">
                      <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-full bg-[#0b0f19] flex items-center justify-center font-bold text-white overflow-hidden shrink-0">
                          {emp.profile_image ? (
                            <img src={`${API_BASE}/uploads/${emp.profile_image}`} alt={emp.full_name} className="w-full h-full object-cover" />
                          ) : initials}
                        </div>
                        <div className="min-w-0">
                          <div className="font-semibold text-sm text-white truncate">{emp.full_name}</div>
                          <div className="text-xs text-slate-400 truncate">{emp.job_title} &bull; {emp.department}</div>
                        </div>
                      </div>
                    </td>
                    {dates.map((d, i) => {
                      const dateStr = formatDateYMD(d);
                      const shiftData = emp.shifts ? emp.shifts[dateStr] : null;
                      const assignedShiftId = shiftData ? shiftData.shift_id : 0;
                      const isChanged = rosterChanges[`${emp.user_id}_${dateStr}`] !== undefined;
                      const currentVal = isChanged ? rosterChanges[`${emp.user_id}_${dateStr}`] : assignedShiftId;
                      
                      const activeClass = currentVal > 0 ? 'bg-blue-500/10 border-blue-500 text-blue-400 font-semibold' : 'bg-[#161922] border-white/10 text-white';

                      return (
                        <td key={i} className="p-3 border-b border-r border-white/10 bg-[#0b0f19] align-top">
                          <select 
                            className={`w-full p-2 rounded-md border text-xs cursor-pointer outline-none focus:border-blue-500 transition-colors ${activeClass}`}
                            value={currentVal}
                            onChange={(e) => recordChange(emp.user_id, dateStr, parseInt(e.target.value))}
                          >
                            <option value={0}>Off</option>
                            {shiftTypes.map(s => (
                              <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                          </select>
                        </td>
                      );
                    })}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Shift Modal */}
      {showShiftModal && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center">
          <div className="bg-[#161922] w-[450px] rounded-xl border border-white/10 shadow-2xl overflow-hidden flex flex-col">
            <div className="p-5 border-b border-white/10 flex justify-between items-center">
              <h3 className="text-lg font-bold text-white font-['Space_Grotesk',sans-serif] m-0">Manage Shift Types</h3>
              <button className="text-slate-400 hover:text-white transition-colors" onClick={() => setShowShiftModal(false)}>
                <X size={20} />
              </button>
            </div>
            <div className="p-5">
              <div className="mb-6 max-h-[150px] overflow-y-auto pr-2">
                {shiftTypes.length === 0 ? (
                  <div className="text-sm text-slate-400">No shifts defined yet.</div>
                ) : (
                  <ul className="list-none p-0 m-0 space-y-2">
                    {shiftTypes.map(s => (
                      <li key={s.id} className="p-2 border border-white/10 rounded-md text-sm text-slate-300 flex justify-between">
                        <strong>{s.name}</strong> <span>{s.start_time.substring(0,5)} - {s.end_time.substring(0,5)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
              
              <hr className="border-white/10 mb-4" />
              <h4 className="text-white mb-3 text-sm font-semibold">Create New Shift</h4>
              
              <div className="mb-3">
                <label className="block text-xs text-slate-400 mb-1">Shift Name (e.g. Morning Shift)</label>
                <input 
                  type="text" 
                  value={newShiftName} 
                  onChange={(e) => setNewShiftName(e.target.value)} 
                  className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:outline-none focus:border-[#00e07a]"
                />
              </div>
              <div className="flex gap-3 mb-3">
                <div className="flex-1">
                  <label className="block text-xs text-slate-400 mb-1">Start Time</label>
                  <input 
                    type="time" 
                    value={newShiftStart} 
                    onChange={(e) => setNewShiftStart(e.target.value)} 
                    className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:outline-none focus:border-[#00e07a]"
                  />
                </div>
                <div className="flex-1">
                  <label className="block text-xs text-slate-400 mb-1">End Time</label>
                  <input 
                    type="time" 
                    value={newShiftEnd} 
                    onChange={(e) => setNewShiftEnd(e.target.value)} 
                    className="w-full bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm text-white focus:outline-none focus:border-[#00e07a]"
                  />
                </div>
              </div>
            </div>
            <div className="p-5 border-t border-white/10 flex justify-end gap-3">
              <button 
                className="px-4 py-2 border border-white/10 hover:bg-white/5 rounded-md text-white text-sm transition-colors"
                onClick={() => setShowShiftModal(false)}
              >
                Cancel
              </button>
              <button 
                className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96d] text-black font-bold rounded-md text-sm transition-colors"
                onClick={createShift}
              >
                Save Shift Type
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
