import { ThemeProvider } from "next-themes";
import ELRApp from "./employee-relations/app/App";
import "./employee-relations/styles/index.css";

export function EmployeeRelations({ mode = "admin" }: { mode?: "employee" | "admin" }) {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="system">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <ELRApp mode={mode} />
      </div>
    </ThemeProvider>
  );
}
