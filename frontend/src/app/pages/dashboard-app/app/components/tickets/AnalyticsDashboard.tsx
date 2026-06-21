import { Ticket, STATUS_META, PRIORITY_META } from "./data";
import { TrendingUp, TrendingDown, Clock, CheckCircle2, AlertTriangle, Zap } from "lucide-react";
import { AreaChart, Area, BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell, PieChart, Pie } from "recharts";

interface Props {
  tickets: Ticket[];
}

// @ts-nocheck
import { useState, useMemo } from "react";

// Colors for categories
const CATEGORY_COLORS = ["#00e07a", "#00b8ff", "#f59e0b", "#6366f1", "#ef4444", "#a78bfa", "#4b5a6e"];

const CustomTooltip = ({ active, payload, label }: any) => {
  if (!active || !payload?.length) return null;
  return (
    <div style={{
      background: "#161b27",
      border: "1px solid rgba(255,255,255,0.1)",
      borderRadius: "0.625rem",
      padding: "0.625rem 0.875rem",
      fontSize: "0.8125rem",
    }}>
      <p style={{ color: "#8899b4", marginBottom: "0.25rem" }}>{label}</p>
      {payload.map((p: any) => (
        <p key={p.name} style={{ color: p.color, margin: "0.125rem 0" }}>
          {p.name}: <span style={{ color: "#f0f4ff", fontFamily: "'JetBrains Mono', monospace" }}>{p.value}</span>
        </p>
      ))}
    </div>
  );
};

function StatCard({ label, value, sub, icon, color, trend }: {
  label: string; value: string; sub?: string;
  icon: React.ReactNode; color: string; trend?: { dir: "up" | "down"; val: string; good?: boolean };
}) {
  return (
    <div style={{
      background: "rgba(255,255,255,0.04)",
      border: "1px solid rgba(255,255,255,0.08)",
      borderRadius: "1rem",
      padding: "1.25rem",
    }}>
      <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", marginBottom: "0.875rem" }}>
        <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>{label}</span>
        <div style={{
          width: 32, height: 32, borderRadius: "0.5rem",
          background: `${color}18`,
          border: `1px solid ${color}30`,
          display: "flex", alignItems: "center", justifyContent: "center",
          color,
        }}>
          {icon}
        </div>
      </div>
      <p style={{
        fontFamily: "'JetBrains Mono', monospace",
        fontSize: "1.875rem",
        fontWeight: 600,
        color: "#f0f4ff",
        lineHeight: 1,
        marginBottom: "0.5rem",
      }}>{value}</p>
      <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
        {trend && (
          <span style={{ display: "flex", alignItems: "center", gap: "0.2rem", fontSize: "0.75rem",
            color: trend.good !== false
              ? (trend.dir === "down" ? "#10b981" : "#ef4444")
              : (trend.dir === "up" ? "#10b981" : "#ef4444"),
          }}>
            {trend.dir === "up" ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
            {trend.val}
          </span>
        )}
        {sub && <span style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>{sub}</span>}
      </div>
    </div>
  );
}

export function AnalyticsDashboard({ tickets }: Props) {
  const byStatus = tickets.reduce<Record<string, number>>((acc, t) => {
    acc[t.status] = (acc[t.status] || 0) + 1;
    return acc;
  }, {});

  const pieData = (["open", "in_progress", "waiting", "resolved", "closed"] as const).map(s => ({
    name: STATUS_META[s].label,
    value: byStatus[s] || 0,
    color: STATUS_META[s].color,
  })).filter(d => d.value > 0);

  const dynamicData = useMemo(() => {
    const volumeMap: Record<string, { day: string, created: number, resolved: number }> = {};
    const categoryMap: Record<string, number> = {};
    const agentMap: Record<string, { name: string, initials: string, color: string, resolved: number, csatSum: number, csatCount: number }> = {};
    let resolvedCount = 0;
    let breachedCount = 0;
    
    // For avg response time
    let totalResponseHours = 0;
    let respondedTickets = 0;
    const responseTimesByDay: Record<string, number[]> = {};

    tickets.forEach(t => {
      // Category count
      const cat = t.category || "Other";
      categoryMap[cat] = (categoryMap[cat] || 0) + 1;

      // Volume (by day of created)
      const d = new Date(t.created);
      const dayStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      if (!volumeMap[dayStr]) volumeMap[dayStr] = { day: dayStr, created: 0, resolved: 0 };
      volumeMap[dayStr].created += 1;

      // Resolved count + Agent stats
      if (t.status === 'resolved' || t.status === 'closed') {
        resolvedCount++;
        // Volume (by day of resolved)
        const rd = new Date(t.updated);
        const rDayStr = rd.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        if (!volumeMap[rDayStr]) volumeMap[rDayStr] = { day: rDayStr, created: 0, resolved: 0 };
        volumeMap[rDayStr].resolved += 1;
      }

      // Assignee
      const aName = t.assignee ? t.assignee.name : "Unassigned";
      if (!agentMap[aName]) {
        agentMap[aName] = {
          name: aName,
          initials: t.assignee ? t.assignee.initials : "—",
          color: t.assignee ? t.assignee.color : "#4b5a6e",
          resolved: 0,
          csatSum: 0,
          csatCount: 0
        };
      }
      if (t.status === 'resolved' || t.status === 'closed') {
        agentMap[aName].resolved += 1;
        if (t.csat_score !== undefined && t.csat_score !== null) {
          agentMap[aName].csatSum += (t.csat_score / 5) * 100;
          agentMap[aName].csatCount += 1;
        }
      }

      // Check SLA Breach
      if (t.sla_breach_at) {
        const breachTime = new Date(t.sla_breach_at).getTime();
        if (t.status === 'resolved' || t.status === 'closed') {
          if (new Date(t.updated).getTime() > breachTime) breachedCount++;
        } else {
          if (Date.now() > breachTime) breachedCount++;
        }
      }

      // Calculate First Response Time
      const firstAgentMsg = t.messages?.find(m => m.author.role === 'agent');
      if (firstAgentMsg) {
        const responseMs = new Date(firstAgentMsg.timestamp).getTime() - new Date(t.created).getTime();
        const responseHours = Math.max(0, responseMs / (1000 * 60 * 60));
        totalResponseHours += responseHours;
        respondedTickets += 1;

        if (!responseTimesByDay[dayStr]) responseTimesByDay[dayStr] = [];
        responseTimesByDay[dayStr].push(responseHours);
      }
    });

    const avgResponseHours = respondedTickets > 0 ? (totalResponseHours / respondedTickets).toFixed(1) + "h" : "0h";

    const VOLUME_DATA = Object.values(volumeMap).sort((a, b) => new Date(a.day + ", 2026").getTime() - new Date(b.day + ", 2026").getTime());
    const CATEGORY_DATA = Object.entries(categoryMap).map(([name, count], i) => ({
      name, count, color: CATEGORY_COLORS[i % CATEGORY_COLORS.length]
    })).sort((a, b) => b.count - a.count);
    const AGENT_DATA = Object.values(agentMap).sort((a, b) => b.resolved - a.resolved).map(a => ({
      ...a, avg: avgResponseHours, csat: a.csatCount > 0 ? Math.round(a.csatSum / a.csatCount) : null
    }));
    
    // Calculate P50 and P95 for RESPONSE_DATA based on actual first response times by day
    const RESPONSE_DATA = VOLUME_DATA.map(v => {
      const times = (responseTimesByDay[v.day] || []).sort((a, b) => a - b);
      let p50 = 0, p95 = 0;
      if (times.length > 0) {
        p50 = times[Math.floor(times.length * 0.5)];
        p95 = times[Math.floor(times.length * 0.95)];
      }
      return { day: v.day, p50: Number(p50.toFixed(1)), p95: Number(p95.toFixed(1)) };
    }).slice(-6);

    return { 
      VOLUME_DATA, CATEGORY_DATA, AGENT_DATA, RESPONSE_DATA, 
      totalTickets: tickets.length, 
      slaComplianceRate: tickets.length ? Math.round(((tickets.length - breachedCount) / tickets.length) * 100) : 100,
      avgResponseHours
    };
  }, [tickets]);

  const { VOLUME_DATA, CATEGORY_DATA, AGENT_DATA, RESPONSE_DATA, totalTickets, slaComplianceRate, avgResponseHours } = dynamicData;
  const totalCategoryCount = CATEGORY_DATA.reduce((s, c) => s + c.count, 0);

  return (
    <div style={{ padding: "2rem", overflowY: "auto", height: "100%" }}>
      {/* Page title */}
      <div style={{ marginBottom: "1.75rem" }}>
        <h1 style={{ fontSize: "1.375rem", fontWeight: 600, color: "#f0f4ff", margin: 0, lineHeight: 1.3 }}>
          Ticket Analytics
        </h1>
        <p style={{ color: "#4b5a6e", fontSize: "0.875rem", marginTop: "0.25rem" }}>
          Last 30 days · Updated just now
        </p>
      </div>

      {/* Stat row */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: "0.875rem", marginBottom: "1.5rem" }}>
        <StatCard
          label="Total Tickets"
          value={String(totalTickets)}
          sub="all time"
          icon={<Zap size={15} />}
          color="#3b82f6"
        />
        <StatCard
          label="Open"
          value={String(byStatus["open"] || 0)}
          sub="need attention"
          icon={<AlertTriangle size={15} />}
          color="#f59e0b"
        />
        <StatCard
          label="Avg First Response"
          value={avgResponseHours}
          sub="all time average"
          icon={<Clock size={15} />}
          color="#10b981"
        />
        <StatCard
          label="SLA Compliance"
          value={`${slaComplianceRate}%`}
          sub="met target SLA"
          icon={<CheckCircle2 size={15} />}
          color="#10b981"
        />
      </div>

      {/* Charts row */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 320px", gap: "0.875rem", marginBottom: "1.5rem" }}>
        {/* Volume chart */}
        <div style={{
          background: "rgba(255,255,255,0.04)",
          border: "1px solid rgba(255,255,255,0.08)",
          borderRadius: "1rem",
          padding: "1.25rem",
        }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "1.25rem" }}>
            <div>
              <p style={{ fontSize: "0.875rem", fontWeight: 500, color: "#f0f4ff", margin: 0 }}>Ticket Volume</p>
              <p style={{ fontSize: "0.75rem", color: "#4b5a6e", margin: "0.125rem 0 0" }}>Created vs resolved · Jun 1–12</p>
            </div>
            <div style={{ display: "flex", gap: "1rem" }}>
              {[{ label: "Created", color: "#6366f1" }, { label: "Resolved", color: "#10b981" }].map(l => (
                <div key={l.label} style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
                  <div style={{ width: 8, height: 2, background: l.color, borderRadius: 99 }} />
                  <span style={{ fontSize: "0.6875rem", color: "#4b5a6e" }}>{l.label}</span>
                </div>
              ))}
            </div>
          </div>
          <ResponsiveContainer width="100%" height={180}>
            <AreaChart data={VOLUME_DATA} margin={{ top: 0, right: 0, left: -20, bottom: 0 }}>
              <defs>
                <linearGradient id="gCreated" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                </linearGradient>
                <linearGradient id="gResolved" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                  <stop offset="95%" stopColor="#10b981" stopOpacity={0} />
                </linearGradient>
              </defs>
              <XAxis dataKey="day" tick={{ fill: "#4b5a6e", fontSize: 11 }} axisLine={false} tickLine={false} interval={2} />
              <YAxis tick={{ fill: "#4b5a6e", fontSize: 11 }} axisLine={false} tickLine={false} />
              <Tooltip content={<CustomTooltip />} />
              <Area type="monotone" dataKey="created" name="Created" stroke="#6366f1" strokeWidth={2} fill="url(#gCreated)" dot={false} />
              <Area type="monotone" dataKey="resolved" name="Resolved" stroke="#10b981" strokeWidth={2} fill="url(#gResolved)" dot={false} />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Status donut */}
        <div style={{
          background: "rgba(255,255,255,0.04)",
          border: "1px solid rgba(255,255,255,0.08)",
          borderRadius: "1rem",
          padding: "1.25rem",
          display: "flex",
          flexDirection: "column",
        }}>
          <p style={{ fontSize: "0.875rem", fontWeight: 500, color: "#f0f4ff", margin: "0 0 0.125rem" }}>By Status</p>
          <p style={{ fontSize: "0.75rem", color: "#4b5a6e", margin: "0 0 0.875rem" }}>Current distribution</p>
          <div style={{ display: "flex", alignItems: "center", gap: "1.25rem", flex: 1 }}>
            <ResponsiveContainer width={110} height={110}>
              <PieChart>
                <Pie data={pieData} cx="50%" cy="50%" innerRadius={32} outerRadius={50}
                  dataKey="value" strokeWidth={2} stroke="rgba(0,0,0,0.4)">
                  {pieData.map((entry, i) => <Cell key={i} fill={entry.color} />)}
                </Pie>
              </PieChart>
            </ResponsiveContainer>
            <div style={{ flex: 1, display: "flex", flexDirection: "column", gap: "0.4rem" }}>
              {pieData.map(d => (
                <div key={d.name} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "0.5rem" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
                    <div style={{ width: 6, height: 6, borderRadius: "50%", background: d.color, flexShrink: 0 }} />
                    <span style={{ fontSize: "0.75rem", color: "#8899b4" }}>{d.name}</span>
                  </div>
                  <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.75rem", color: "#f0f4ff" }}>
                    {d.value}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Bottom row */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.875rem" }}>
        {/* Category bar chart */}
        <div style={{
          background: "rgba(255,255,255,0.04)",
          border: "1px solid rgba(255,255,255,0.08)",
          borderRadius: "1rem",
          padding: "1.25rem",
        }}>
          <p style={{ fontSize: "0.875rem", fontWeight: 500, color: "#f0f4ff", margin: "0 0 0.125rem" }}>Top Categories</p>
          <p style={{ fontSize: "0.75rem", color: "#4b5a6e", margin: "0 0 1.25rem" }}>Tickets by category · last 30 days</p>
          <div style={{ display: "flex", flexDirection: "column", gap: "0.625rem" }}>
            {CATEGORY_DATA.map(cat => (
              <div key={cat.name}>
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "0.25rem" }}>
                  <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>{cat.name}</span>
                  <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.8125rem", color: "#f0f4ff" }}>
                    {cat.count}
                  </span>
                </div>
                <div style={{ height: 5, background: "rgba(255,255,255,0.06)", borderRadius: 99, overflow: "hidden" }}>
                  <div style={{
                    height: "100%",
                    width: `${(cat.count / totalCategoryCount) * 100}%`,
                    background: cat.color,
                    borderRadius: 99,
                    transition: "width 0.6s ease",
                  }} />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Agent leaderboard */}
        <div style={{
          background: "rgba(255,255,255,0.04)",
          border: "1px solid rgba(255,255,255,0.08)",
          borderRadius: "1rem",
          padding: "1.25rem",
        }}>
          <p style={{ fontSize: "0.875rem", fontWeight: 500, color: "#f0f4ff", margin: "0 0 0.125rem" }}>Agent Performance</p>
          <p style={{ fontSize: "0.75rem", color: "#4b5a6e", margin: "0 0 1.25rem" }}>Resolved tickets · response time · CSAT</p>
          <div style={{ display: "flex", flexDirection: "column", gap: "0.75rem" }}>
            {/* Table header */}
            <div style={{
              display: "grid", gridTemplateColumns: "1fr 60px 70px 60px",
              padding: "0 0 0.375rem",
              borderBottom: "1px solid rgba(255,255,255,0.06)",
            }}>
              {["Agent", "Resolved", "Avg resp.", "CSAT"].map(h => (
                <span key={h} style={{ fontSize: "0.6875rem", color: "#4b5a6e", fontWeight: 500, textTransform: "uppercase", letterSpacing: "0.05em" }}>
                  {h}
                </span>
              ))}
            </div>
            {AGENT_DATA.map(agent => (
              <div key={agent.name} style={{
                display: "grid", gridTemplateColumns: "1fr 60px 70px 60px",
                alignItems: "center",
              }}>
                <div style={{ display: "flex", alignItems: "center", gap: "0.625rem" }}>
                  <div style={{
                    width: 28, height: 28, borderRadius: "50%",
                    background: `${agent.color}22`,
                    border: `1px solid ${agent.color}44`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontSize: "0.625rem", fontWeight: 700, color: agent.color,
                    flexShrink: 0,
                  }}>
                    {agent.initials}
                  </div>
                  <span style={{ fontSize: "0.8125rem", color: "#c8d4e8" }}>{agent.name}</span>
                </div>
                <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.8125rem", color: "#f0f4ff" }}>
                  {agent.resolved}
                </span>
                <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.8125rem", color: "#8899b4" }}>
                  {agent.avg}
                </span>
                <span style={{
                  fontFamily: "'JetBrains Mono', monospace", fontSize: "0.8125rem",
                  color: agent.csat && agent.csat >= 97 ? "#10b981" : agent.csat ? "#f59e0b" : "#4b5a6e",
                }}>
                  {agent.csat ? `${agent.csat}%` : "—"}
                </span>
              </div>
            ))}
          </div>

          {/* Response time mini-chart */}
          <div style={{ marginTop: "1.25rem", paddingTop: "1rem", borderTop: "1px solid rgba(255,255,255,0.06)" }}>
            <p style={{ fontSize: "0.75rem", color: "#4b5a6e", marginBottom: "0.75rem" }}>Response time trend (hours)</p>
            <ResponsiveContainer width="100%" height={70}>
              <BarChart data={RESPONSE_DATA} margin={{ top: 0, right: 0, left: -30, bottom: 0 }} barGap={2}>
                <XAxis dataKey="day" tick={{ fill: "#4b5a6e", fontSize: 10 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fill: "#4b5a6e", fontSize: 10 }} axisLine={false} tickLine={false} />
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="p50" name="P50" fill="#3b82f6" radius={[2, 2, 0, 0]} maxBarSize={12} />
                <Bar dataKey="p95" name="P95" fill="rgba(59,130,246,0.25)" radius={[2, 2, 0, 0]} maxBarSize={12} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );
}
