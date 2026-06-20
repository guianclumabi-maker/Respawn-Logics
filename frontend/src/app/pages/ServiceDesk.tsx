import { ThemeProvider } from "next-themes";
import ServiceDeskApp from "./service-desk/app/App";
import "./service-desk/styles/index.css";

export function ServiceDesk() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <ServiceDeskApp />
      </div>
    </ThemeProvider>
  );
}
