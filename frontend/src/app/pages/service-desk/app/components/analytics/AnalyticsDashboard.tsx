// @ts-nocheck
import { Ticket } from "../tickets/data";
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, BarChart, Bar, PieChart, Pie, Cell } from "recharts";
import { Clock, AlertTriangle, CheckCircle, Activity } from "lucide-react";

interface AnalyticsProps {
  tickets: Ticket[];
}

export function AnalyticsDashboard({ tickets }: AnalyticsProps) {
  const total = tickets.length;
  const openCount = tickets.filter(t => t.status === "open" || t.status === "in_progress").length;
  
  // Fake chart data to match mockup for now, but scalable to real data
  const volumeData = [
    { name: "Jun 1", created: 4, resolved: 2 },
    { name: "Jun 3", created: 3, resolved: 3 },
    { name: "Jun 5", created: 5, resolved: 4 },
    { name: "Jun 7", created: 2, resolved: 5 },
    { name: "Jun 9", created: 6, resolved: 4 },
    { name: "Jun 11", created: 4, resolved: 6 },
    { name: "Jun 13", created: 3, resolved: 3 },
  ];

  const statusData = [
    { name: "Resolved", value: tickets.filter(t => t.status === "resolved" || t.status === "closed").length },
    { name: "Open", value: openCount },
  ];

  const categoryData = [
    { name: "Tenant: Respawn Logic (Internal)", value: total },
  ];

  const agentData = [
    { name: "Agent", initial: "AA", resolved: 2, avgResponse: "0h", csat: "100%" },
    { name: "Unassigned", initial: "--", resolved: 1, avgResponse: "0h", csat: "100%" },
  ];

  const COLORS = ["#00e07a", "#4b5a6e"];

  const Card = ({ children, style }: any) => (
    <div style={{
      background: "rgba(0, 224, 122, 0.02)",
      border: "1px solid rgba(255,255,255,0.05)",
      borderRadius: "0.75rem",
      padding: "1.25rem",
      ...style
    }}>
      {children}
    </div>
  );

  return (
    <div style={{ flex: 1, overflowY: "auto", padding: "2rem", fontFamily: "'Inter', sans-serif" }}>
      <div style={{ marginBottom: "2rem" }}>
        <h1 style={{ fontSize: "1.25rem", fontWeight: 700, margin: "0 0 0.5rem 0", fontFamily: "'Space Grotesk', sans-serif" }}>Ticket Analytics</h1>
        <p style={{ color: "#8899b4", fontSize: "0.875rem", margin: 0 }}>Last 30 days · Updated just now</p>
      </div>

      {/* Top Metrics */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: "1.5rem", marginBottom: "1.5rem" }}>
        <Card>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "1rem" }}>
            <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>Total Tickets</span>
            <Activity size={14} color="#00e07a" />
          </div>
          <div style={{ fontSize: "2rem", fontWeight: 700, fontFamily: "'JetBrains Mono', monospace", marginBottom: "0.25rem" }}>{total}</div>
          <div style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>all time</div>
        </Card>
        
        <Card>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "1rem" }}>
            <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>Open</span>
            <AlertTriangle size={14} color="#f59e0b" />
          </div>
          <div style={{ fontSize: "2rem", fontWeight: 700, fontFamily: "'JetBrains Mono', monospace", marginBottom: "0.25rem" }}>{openCount}</div>
          <div style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>need attention</div>
        </Card>

        <Card>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "1rem" }}>
            <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>Avg First Response</span>
            <Clock size={14} color="#00e07a" />
          </div>
          <div style={{ fontSize: "2rem", fontWeight: 700, fontFamily: "'JetBrains Mono', monospace", marginBottom: "0.25rem" }}>0h</div>
          <div style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>all time average</div>
        </Card>

        <Card>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "1rem" }}>
            <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>SLA Compliance</span>
            <CheckCircle size={14} color="#00e07a" />
          </div>
          <div style={{ fontSize: "2rem", fontWeight: 700, fontFamily: "'JetBrains Mono', monospace", marginBottom: "0.25rem" }}>100%</div>
          <div style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>met target SLA</div>
        </Card>
      </div>

      {/* Main Charts */}
      <div style={{ display: "grid", gridTemplateColumns: "3fr 1fr", gap: "1.5rem", marginBottom: "1.5rem" }}>
        <Card>
          <h3 style={{ fontSize: "0.875rem", fontWeight: 600, margin: "0 0 0.25rem 0" }}>Ticket Volume</h3>
          <p style={{ fontSize: "0.75rem", color: "#8899b4", margin: "0 0 1.5rem 0" }}>Created vs resolved · Jun 1-13</p>
          <div style={{ height: 250 }}>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={volumeData}>
                <defs>
                  <linearGradient id="colorCreated" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#00e07a" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="#00e07a" stopOpacity={0}/>
                  </linearGradient>
                  <linearGradient id="colorResolved" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#00b8ff" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="#00b8ff" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <XAxis dataKey="name" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: "#4b5a6e" }} dy={10} />
                <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: "#4b5a6e" }} dx={-10} />
                <Tooltip contentStyle={{ background: "#0f1422", border: "1px solid rgba(0,224,122,0.2)", borderRadius: "8px" }} />
                <Area type="monotone" dataKey="created" stroke="#00e07a" fillOpacity={1} fill="url(#colorCreated)" />
                <Area type="monotone" dataKey="resolved" stroke="#00b8ff" fillOpacity={1} fill="url(#colorResolved)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card>

        <Card>
          <h3 style={{ fontSize: "0.875rem", fontWeight: 600, margin: "0 0 0.25rem 0" }}>By Status</h3>
          <p style={{ fontSize: "0.75rem", color: "#8899b4", margin: "0 0 1.5rem 0" }}>Current distribution</p>
          <div style={{ height: 200, display: "flex", alignItems: "center", justifyContent: "center" }}>
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={statusData} innerRadius={60} outerRadius={80} paddingAngle={5} dataKey="value" stroke="none">
                  {statusData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip contentStyle={{ background: "#0f1422", border: "1px solid rgba(0,224,122,0.2)", borderRadius: "8px" }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </Card>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1.5rem" }}>
        <Card>
          <h3 style={{ fontSize: "0.875rem", fontWeight: 600, margin: "0 0 0.25rem 0" }}>Top Categories</h3>
          <p style={{ fontSize: "0.75rem", color: "#8899b4", margin: "0 0 1.5rem 0" }}>Tickets by category · last 30 days</p>
          <div style={{ height: 200 }}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={categoryData} layout="vertical" margin={{ left: 0, right: 0 }}>
                <XAxis type="number" hide />
                <YAxis dataKey="name" type="category" axisLine={false} tickLine={false} width={200} tick={{ fontSize: 11, fill: "#f0f4ff" }} />
                <Tooltip cursor={{ fill: "rgba(255,255,255,0.02)" }} contentStyle={{ background: "#0f1422", border: "1px solid rgba(0,224,122,0.2)", borderRadius: "8px" }} />
                <Bar dataKey="value" fill="#00b8ff" radius={[0, 4, 4, 0]} barSize={6} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Card>

        <Card>
          <h3 style={{ fontSize: "0.875rem", fontWeight: 600, margin: "0 0 0.25rem 0" }}>Agent Performance</h3>
          <p style={{ fontSize: "0.75rem", color: "#8899b4", margin: "0 0 1.5rem 0" }}>Resolved tickets · response time · CSAT</p>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "0.8125rem" }}>
            <thead>
              <tr style={{ color: "#4b5a6e", textAlign: "left", fontFamily: "'JetBrains Mono', monospace", fontSize: "0.6875rem" }}>
                <th style={{ paddingBottom: "0.75rem", fontWeight: 500 }}>AGENT</th>
                <th style={{ paddingBottom: "0.75rem", fontWeight: 500 }}>RESOLVED</th>
                <th style={{ paddingBottom: "0.75rem", fontWeight: 500 }}>AVG RESP.</th>
                <th style={{ paddingBottom: "0.75rem", fontWeight: 500 }}>CSAT</th>
              </tr>
            </thead>
            <tbody>
              {agentData.map((agent, i) => (
                <tr key={i} style={{ borderTop: "1px solid rgba(255,255,255,0.05)" }}>
                  <td style={{ padding: "0.75rem 0", display: "flex", alignItems: "center", gap: "0.5rem" }}>
                    <div style={{ width: 24, height: 24, borderRadius: "50%", background: "rgba(0,224,122,0.1)", color: "#00e07a", display: "flex", alignItems: "center", justifyContent: "center", fontSize: "0.625rem", fontWeight: 700, fontFamily: "'JetBrains Mono', monospace" }}>
                      {agent.initial}
                    </div>
                    {agent.name}
                  </td>
                  <td style={{ padding: "0.75rem 0", fontFamily: "'JetBrains Mono', monospace" }}>{agent.resolved}</td>
                  <td style={{ padding: "0.75rem 0", fontFamily: "'JetBrains Mono', monospace" }}>{agent.avgResponse}</td>
                  <td style={{ padding: "0.75rem 0", color: "#00e07a", fontWeight: 600, fontFamily: "'JetBrains Mono', monospace" }}>{agent.csat}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      </div>
    </div>
  );
}
