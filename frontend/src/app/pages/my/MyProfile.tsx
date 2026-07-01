import { useState, useEffect } from "react";
import { apiFetch } from "../../lib/apiClient";
import { User, Phone, ShieldAlert, BookOpen, AlertCircle, Loader2, CheckCircle } from "lucide-react";

interface ProfileData {
  id: number;
  employee_id: string;
  full_name: string;
  email: string;
  work_email: string;
  job_title: string;
  department: string;
  phone: string;
  emergency_name: string;
  emergency_phone: string;
  bio: string;
  profile_image: string;
}

export function MyProfile() {
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Editable fields
  const [phone, setPhone] = useState("");
  const [emergencyName, setEmergencyName] = useState("");
  const [emergencyPhone, setEmergencyPhone] = useState("");
  const [bio, setBio] = useState("");

  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);

  useEffect(() => {
    fetchProfile();
  }, []);

  const fetchProfile = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=core_hr&action=my_profile");
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      if (data.success && data.data) {
        const p: ProfileData = data.data;
        setProfile(p);
        setPhone(p.phone || "");
        setEmergencyName(p.emergency_name || "");
        setEmergencyPhone(p.emergency_phone || "");
        setBio(p.bio || "");
      } else {
        setError(data.error || "Failed to load profile details.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading profile.");
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSuccessMsg(null);
    setSaveError(null);

    try {
      const res = await apiFetch("/api/index.php?route=core_hr&action=update_my_profile", {
        method: "POST",
        body: JSON.stringify({
          phone,
          emergency_name: emergencyName,
          emergency_phone: emergencyPhone,
          bio
        })
      });

      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();

      if (data.success) {
        setSuccessMsg("Your profile adjustments have been securely saved.");
        // clear messages after 5 seconds
        setTimeout(() => setSuccessMsg(null), 5000);
      } else {
        setSaveError(data.error || "Failed to update profile.");
      }
    } catch (err: any) {
      console.error(err);
      setSaveError(err.message || "Connection failure. Unable to contact database.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#06070a] text-[#c8d0e0]">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div>
          <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk']">
            My Profile
          </h1>
          <p className="text-sm text-gray-400">View corporate metadata and edit self-service contact details</p>
        </div>
      </div>

      {/* Main Body */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Decrypting employee node profile...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-white">Connection Error</h3>
            <p className="text-sm text-gray-400">{error}</p>
            <button 
              onClick={fetchProfile}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs transition-colors border border-white/10"
            >
              Retry Connection
            </button>
          </div>
        ) : !profile ? (
          <div className="text-center text-gray-500 py-10">Profile record empty.</div>
        ) : (
          <form onSubmit={handleSave} className="max-w-4xl mx-auto space-y-6 animate-in fade-in duration-300">
            
            {/* Inline Notifications */}
            {successMsg && (
              <div className="p-4 bg-[#00e07a]/10 border border-[#00e07a]/20 rounded-xl text-[#00e07a] text-sm flex items-start gap-3">
                <CheckCircle className="w-5 h-5 flex-shrink-0" />
                <span>{successMsg}</span>
              </div>
            )}
            {saveError && (
              <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm flex items-start gap-3">
                <AlertCircle className="w-5 h-5 flex-shrink-0" />
                <span>{saveError}</span>
              </div>
            )}

            {/* Profile Intro Banner */}
            <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-6 shadow-lg flex flex-col md:flex-row items-center gap-6 relative overflow-hidden">
              <div className="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-[#00e07a]/5 to-[#00b8ff]/5 rounded-bl-full pointer-events-none"></div>
              
              <div className="w-20 h-20 rounded-full bg-gradient-to-tr from-[#00e07a] to-[#00b8ff] flex items-center justify-center text-black font-extrabold text-2xl uppercase shadow-[0_0_20px_rgba(0,224,122,0.2)]">
                {profile.full_name.charAt(0)}
              </div>
              
              <div className="text-center md:text-left space-y-1">
                <h2 className="text-2xl font-bold text-white tracking-tight">{profile.full_name}</h2>
                <div className="text-sm font-semibold text-[#00e07a] font-mono">{profile.job_title || "No Job Title"}</div>
                <div className="text-xs text-gray-500">{profile.department || "No Department"} · ID: {profile.employee_id}</div>
              </div>
            </div>

            {/* Grid for forms */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Read Only Corporate Meta */}
              <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-6 shadow-lg space-y-4">
                <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2 flex items-center gap-2">
                  <User size={16} className="text-gray-500" /> Corporate Metadata
                </h3>
                
                <div className="space-y-3">
                  <div>
                    <span className="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Full Legal Name</span>
                    <span className="text-sm text-white font-medium">{profile.full_name}</span>
                  </div>
                  <div>
                    <span className="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Corporate Email</span>
                    <span className="text-sm text-gray-300 font-mono">{profile.email || "—"}</span>
                  </div>
                  <div>
                    <span className="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Work Email</span>
                    <span className="text-sm text-gray-300 font-mono">{profile.work_email || "—"}</span>
                  </div>
                  <div>
                    <span className="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Department Node</span>
                    <span className="text-sm text-white font-medium">{profile.department || "—"}</span>
                  </div>
                  <div>
                    <span className="block text-xs text-gray-500 uppercase tracking-wider font-semibold">Employee ID Badge</span>
                    <span className="text-sm text-white font-mono">{profile.employee_id || "—"}</span>
                  </div>
                </div>
              </div>

              {/* Editable Fields */}
              <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-6 shadow-lg space-y-4">
                <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2 flex items-center gap-2">
                  <Phone size={16} className="text-[#00e07a]" /> Contact & Emergency Details
                </h3>

                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Personal Phone</label>
                    <input 
                      type="text" 
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20" 
                      placeholder="e.g. +63 917 123 4567"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Emergency Contact Name</label>
                    <input 
                      type="text" 
                      value={emergencyName}
                      onChange={(e) => setEmergencyName(e.target.value)}
                      className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20" 
                      placeholder="e.g. Maria Clara"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Emergency Contact Phone</label>
                    <input 
                      type="text" 
                      value={emergencyPhone}
                      onChange={(e) => setEmergencyPhone(e.target.value)}
                      className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20" 
                      placeholder="e.g. +63 917 987 6543"
                    />
                  </div>
                </div>
              </div>
            </div>

            {/* Profile Bio Field */}
            <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-6 shadow-lg space-y-4">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-2 flex items-center gap-2">
                <BookOpen size={16} className="text-[#00b8ff]" /> Professional Biography
              </h3>
              
              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Tell us about yourself</label>
                <textarea 
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                  rows={4}
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20 resize-none" 
                  placeholder="Share details regarding your skills, past projects, or hobbies..."
                ></textarea>
              </div>
            </div>

            {/* Save Button */}
            <div className="flex justify-end pt-2">
              <button 
                type="submit" 
                disabled={saving}
                className="px-6 py-3 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-all shadow-[0_0_20px_rgba(0,224,122,0.3)] disabled:opacity-50 flex items-center gap-2"
              >
                {saving && <Loader2 className="w-4 h-4 animate-spin" />}
                [ SAVE_PROFILE_CHANGES ]
              </button>
            </div>

          </form>
        )}
      </div>
    </div>
  );
}
