import { ThemeProvider } from "next-themes";
import DashboardApp from "./dashboard-app/app/App";
import "./dashboard-app/styles/index.css";

export function HomeDashboard() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <DashboardApp />
      </div>
    </ThemeProvider>
  );
}
