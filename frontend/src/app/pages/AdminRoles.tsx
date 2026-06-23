export function AdminRoles() {
  return (
    <div className="h-full w-full flex flex-col items-center justify-center p-8 text-center" style={{ backgroundColor: '#0b0f19', color: '#8899b4' }}>
      <div className="max-w-md space-y-4">
        <div className="w-16 h-16 bg-blue-500/10 rounded-2xl border border-blue-500/20 flex items-center justify-center mx-auto mb-6 shadow-[0_0_20px_rgba(59,130,246,0.15)]">
          <svg className="w-8 h-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <h2 className="text-2xl font-bold text-white tracking-tight">AdminRoles</h2>
        <p className="text-sm">This module has been structurally migrated to the new Unified React SPA, but its internal logic is pending implementation in a future phase.</p>
        <div className="pt-6">
          <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/5 border border-white/10 rounded-full text-xs font-medium text-gray-300">
            <span className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
            Migration Pending
          </div>
        </div>

        {/* Maker-Checker Continuity Warning */}
        <div className="mt-8 text-left bg-amber-500/10 border border-amber-500/20 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <svg className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
              <h4 className="text-sm font-semibold text-amber-500 mb-1">Payroll Maker-Checker Policy</h4>
              <p className="text-xs text-amber-500/80 leading-relaxed">
                Existing payroll managers retain both <code className="bg-amber-500/20 px-1 rounded">payroll.run</code> and <code className="bg-amber-500/20 px-1 rounded">payroll.approve</code> access to ensure business continuity. 
                However, this defeats separation of duties. Please review and split these permissions into separate Maker and Checker roles when managing users.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
