import { ThemeProvider } from "next-themes";
import ELRApp from "./employee-relations/app/App";
import "./employee-relations/styles/index.css";

export function EmployeeRelations() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <ELRApp />
      </div>
    </ThemeProvider>
  );
}
