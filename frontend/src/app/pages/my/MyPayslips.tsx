import { useState, useEffect } from "react";
import { apiFetch } from "../../lib/apiClient";
import { Download, FileText, Calendar, DollarSign, AlertCircle, Loader2 } from "lucide-react";

interface Payslip {
  id: number;
  payroll_run_id: number;
  employee_id: number;
  gross_pay: number;
  net_pay: number;
  total_deductions: number;
  total_earnings: number;
  payroll_period_start: string;
  payroll_period_end: string;
  pay_date: string;
  download_url: string;
}

export function MyPayslips() {
  const [payslips, setPayslips] = useState<Payslip[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedPayslip, setSelectedPayslip] = useState<Payslip | null>(null);

  const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

  useEffect(() => {
    fetchPayslips();
  }, []);

  const fetchPayslips = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=payroll_engine&action=my_payslips");
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      if (data.success) {
        setPayslips(data.data || []);
      } else {
        setError(data.error || "Failed to retrieve payslips.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading payslips.");
    } finally {
      setLoading(false);
    }
  };

  const getDownloadUrl = (ps: Payslip) => {
    return `${API_BASE}/api/index.php?route=payroll_engine&action=download_payslip&id=${ps.id}`;
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency: "PHP"
    }).format(amount);
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div>
          <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
            My Payslips
          </h1>
          <p className="text-sm text-muted-foreground">View and download your salary statements</p>
        </div>
      </div>

      {/* Main Body */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-muted-foreground">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Decrypting payslip records...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-slate-900 dark:text-white">Access Denied / Failed</h3>
            <p className="text-sm text-muted-foreground">{error}</p>
            <button 
              onClick={fetchPayslips}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-accent text-slate-900 dark:text-white rounded-lg text-xs transition-colors border border-border"
            >
              Retry Connection
            </button>
          </div>
        ) : payslips.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center space-y-3 bg-card text-card-foreground/30 border border-border rounded-2xl max-w-2xl mx-auto">
            <FileText className="w-12 h-12 text-gray-600" />
            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">No Payslips Available</h3>
            <p className="text-sm text-gray-500">You do not have any processed payslips yet.</p>
          </div>
        ) : (
          <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                  <tr>
                    <th className="py-4 px-6">Pay Date</th>
                    <th className="py-4 px-6">Pay Period</th>
                    <th className="py-4 px-6">Gross Pay</th>
                    <th className="py-4 px-6">Deductions</th>
                    <th className="py-4 px-6">Net Pay</th>
                    <th className="py-4 px-6 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/[0.03]">
                  {payslips.map((ps) => (
                    <tr key={ps.id} className="hover:bg-white/[0.02] transition-colors">
                      <td className="py-4 px-6 font-medium text-foreground font-mono">
                        {ps.pay_date}
                      </td>
                      <td className="py-4 px-6 text-sm text-gray-300">
                        {ps.payroll_period_start} to {ps.payroll_period_end}
                      </td>
                      <td className="py-4 px-6 text-sm text-[#00e07a] font-mono">
                        {formatCurrency(ps.gross_pay)}
                      </td>
                      <td className="py-4 px-6 text-sm text-red-400 font-mono">
                        {formatCurrency(ps.total_deductions)}
                      </td>
                      <td className="py-4 px-6 text-sm text-foreground font-bold font-mono">
                        {formatCurrency(ps.net_pay)}
                      </td>
                      <td className="py-4 px-6 text-right space-x-2">
                        <button
                          onClick={() => setSelectedPayslip(ps)}
                          className="px-3 py-1.5 bg-white/5 hover:bg-accent text-slate-900 dark:text-white rounded text-xs font-semibold transition-colors border border-border"
                        >
                          View Details
                        </button>
                        <a
                          href={getDownloadUrl(ps)}
                          download
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#00e07a]/10 hover:bg-[#00e07a]/20 text-[#00e07a] border border-[#00e07a]/30 rounded text-xs font-semibold transition-colors"
                        >
                          <Download size={13} /> Download
                        </a>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Details Modal */}
      {selectedPayslip && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-card text-card-foreground border border-border rounded-xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 text-foreground">
            <div className="p-6 border-b border-border flex justify-between items-center bg-black/10">
              <div>
                <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                  <FileText className="text-[#00e07a]" size={20} /> Payslip Breakdown
                </h3>
                <p className="text-xs text-muted-foreground mt-1">Paid on {selectedPayslip.pay_date}</p>
              </div>
              <button 
                onClick={() => setSelectedPayslip(null)} 
                className="text-muted-foreground hover:text-foreground text-2xl leading-none"
              >
                &times;
              </button>
            </div>
            
            <div className="p-6 space-y-6">
              {/* Period details */}
              <div className="flex justify-between items-center bg-background p-4 border border-border rounded-lg text-sm">
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Calendar size={16} /> Pay Period:
                </div>
                <div className="font-medium text-foreground">
                  {selectedPayslip.payroll_period_start} – {selectedPayslip.payroll_period_end}
                </div>
              </div>

              {/* Financial columns */}
              <div className="grid grid-cols-2 gap-4">
                <div className="bg-background p-4 border border-border rounded-lg space-y-3">
                  <h4 className="text-xs font-bold text-[#00e07a] uppercase tracking-wider border-b border-border pb-2">
                    Earnings
                  </h4>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Gross Salary:</span>
                    <span className="font-mono text-slate-900 dark:text-white">{formatCurrency(selectedPayslip.gross_pay)}</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Additions:</span>
                    <span className="font-mono text-slate-900 dark:text-white">{formatCurrency(selectedPayslip.total_earnings - selectedPayslip.gross_pay > 0 ? selectedPayslip.total_earnings - selectedPayslip.gross_pay : 0)}</span>
                  </div>
                  <div className="flex justify-between border-t border-border pt-2 font-bold text-sm">
                    <span className="text-muted-foreground">Total:</span>
                    <span className="font-mono text-[#00e07a]">{formatCurrency(selectedPayslip.total_earnings || selectedPayslip.gross_pay)}</span>
                  </div>
                </div>

                <div className="bg-background p-4 border border-border rounded-lg space-y-3">
                  <h4 className="text-xs font-bold text-red-400 uppercase tracking-wider border-b border-border pb-2">
                    Deductions
                  </h4>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Tax/Gov/Others:</span>
                    <span className="font-mono text-slate-900 dark:text-white">{formatCurrency(selectedPayslip.total_deductions)}</span>
                  </div>
                  <div className="flex justify-between border-t border-border pt-2 font-bold text-sm">
                    <span className="text-muted-foreground font-bold">Total:</span>
                    <span className="font-mono text-red-400">{formatCurrency(selectedPayslip.total_deductions)}</span>
                  </div>
                </div>
              </div>

              {/* Net Pay Highlight */}
              <div className="flex justify-between items-center bg-[#00e07a]/5 border border-[#00e07a]/20 p-5 rounded-lg">
                <div>
                  <span className="text-xs font-bold text-muted-foreground uppercase tracking-wider block">Net Take-Home Pay</span>
                  <span className="text-xs text-gray-500">All taxes and mandatory deductions cleared.</span>
                </div>
                <div className="text-2xl font-bold text-[#00e07a] font-mono flex items-center">
                  <DollarSign size={20} className="mt-0.5" />
                  {selectedPayslip.net_pay.toLocaleString("en-PH", { minimumFractionDigits: 2 })}
                </div>
              </div>

              {/* Actions */}
              <div className="flex justify-end gap-3 pt-2">
                <button
                  onClick={() => setSelectedPayslip(null)}
                  className="px-4 py-2 text-muted-foreground hover:text-foreground text-sm font-semibold transition-colors"
                >
                  Close
                </button>
                <a
                  href={getDownloadUrl(selectedPayslip)}
                  download
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-sm transition-colors shadow-[0_0_15px_rgba(0,224,122,0.3)]"
                >
                  <Download size={15} /> Download Statement
                </a>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
