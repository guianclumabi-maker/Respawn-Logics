import { 
  Calendar, 
  Clock, 
  User, 
  Video, 
  MapPin, 
  UserCheck, 
  Search 
} from "lucide-react";

type InterviewsPageProps = {
  onViewChange: (view: any) => void;
};

export function InterviewsPage({ onViewChange }: InterviewsPageProps) {
  // Mock upcoming hearings schedule
  const interviews = [
    {
      id: 1,
      candidate: "John Doe - Dispute",
      job: "Overtime & Pay Dispute",
      type: "Mediation Hearing",
      time: "2:00 PM - 3:00 PM",
      date: "Today, June 4",
      method: "HQ Room A",
      interviewer: "Jane Doe (Relations Officer)",
      status: "Confirmed",
      isToday: true
    },
    {
      id: 2,
      candidate: "Alice Williams - Appeal",
      job: "Performance Feedback Appeal",
      type: "Formal Grievance Review",
      time: "10:00 AM - 11:30 AM",
      date: "Tomorrow, June 5",
      method: "HQ Boardroom B",
      interviewer: "Jane Doe (Relations Officer)",
      status: "Confirmed",
      isToday: false
    },
    {
      id: 3,
      candidate: "Charlie Brown - Escalation",
      job: "System Access Issues",
      type: "IT Security Review",
      time: "4:30 PM - 5:00 PM",
      date: "Monday, June 8",
      method: "Zoom Video Link",
      interviewer: "Security Operations Team",
      status: "Awaiting Confirmation",
      isToday: false
    }
  ];

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin" style={{ backgroundColor: "#0d0f19" }}>
      {/* Background glow animations */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#8b5cf6] blur-[120px] opacity-10 pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#ec4899] blur-[140px] opacity-8 pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
            Scheduled Hearings
          </h1>
          <p className="text-xs text-[#9ca3af] mt-1">Calendar agenda of scheduled mediation and investigation hearings.</p>
        </div>
      </div>

      {/* Interviews agenda list */}
      <div className="relative z-10 space-y-6 max-w-4xl">
        {interviews.map((item) => {
          let methodIcon = <Video size={13} className="text-[#a855f7]" />;
          if (item.method.includes("HQ") || item.method.includes("Room")) {
            methodIcon = <MapPin size={13} className="text-cyan-400" />;
          }

          let badgeColor = "border-cyan-500/20 bg-cyan-500/10 text-cyan-400";
          if (item.status.includes("Awaiting")) {
            badgeColor = "border-amber-500/20 bg-amber-500/10 text-amber-400";
          }

          return (
            <div 
              key={item.id} 
              className={`p-6 rounded-2xl border transition-all hover:border-white/10 flex flex-col md:flex-row gap-5 justify-between items-start md:items-center ${
                item.isToday 
                  ? "bg-gradient-to-r from-[#8b5cf6]/5 to-[#ec4899]/5 border-[#8b5cf6]/20 shadow-[0_0_20px_rgba(139,92,246,0.05)]" 
                  : "bg-[#161922]/20 backdrop-blur-md"
              }`}
              style={{ borderColor: item.isToday ? undefined : "rgba(255, 255, 255, 0.06)" }}
            >
              {/* Left Column: Date and Time Info */}
              <div className="flex gap-4 items-start">
                <div 
                  className={`w-12 h-12 rounded-xl flex flex-col items-center justify-center flex-shrink-0 text-center border ${
                    item.isToday 
                      ? "border-[#8b5cf6]/30 bg-[#8b5cf6]/10 text-white" 
                      : "border-white/[0.04] bg-white/[0.02] text-gray-400"
                  }`}
                >
                  <Calendar size={15} className={item.isToday ? "text-[#c084fc]" : "text-gray-500"} />
                  <span className="text-[8px] font-bold uppercase mt-1 tracking-wider">
                    {item.isToday ? "TODAY" : "CAL"}
                  </span>
                </div>
                
                <div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">{item.date}</h3>
                  <div className="flex items-center gap-1.5 mt-1 text-white font-semibold text-sm">
                    <Clock size={13} className="text-[#a855f7]" />
                    <span>{item.time}</span>
                  </div>
                </div>
              </div>

              {/* Middle Column: Candidate & Interview Type */}
              <div className="flex-1 md:px-6">
                <h4 className="text-sm font-semibold text-white tracking-wide">{item.candidate}</h4>
                <div className="flex items-center gap-1.5 mt-0.5 text-xs text-cyan-400 font-medium">
                  <span>{item.job}</span>
                  <span className="text-gray-600">•</span>
                  <span className="text-gray-400 text-[11px] font-normal">{item.type}</span>
                </div>
                
                <div className="flex items-center gap-4 mt-3 text-[10px] text-gray-500 flex-wrap">
                  <span className="flex items-center gap-1.5">
                    {methodIcon}
                    {item.method}
                  </span>
                  <span className="flex items-center gap-1.5">
                    <UserCheck size={11} className="text-pink-500" />
                    Officer: {item.interviewer}
                  </span>
                </div>
              </div>

              {/* Right Column: Status & Action Buttons */}
              <div className="flex md:flex-col items-end gap-3 justify-between w-full md:w-auto border-t md:border-t-0 border-white/[0.04] pt-4 md:pt-0 mt-2 md:mt-0">
                <span className={`text-[8px] font-black uppercase px-2 py-0.5 rounded border ${badgeColor} whitespace-nowrap`}>
                  {item.status}
                </span>
                
                {item.isToday && (
                  <button 
                    className="px-4 py-2 bg-gradient-to-r from-[#8b5cf6] to-[#ec4899] text-[10px] font-bold tracking-wide uppercase rounded-xl hover:opacity-95 cursor-pointer shadow-lg shadow-purple-500/10 border-0 text-white"
                  >
                    Join Meeting
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
