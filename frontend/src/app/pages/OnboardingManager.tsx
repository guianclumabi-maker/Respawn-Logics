import { ThemeProvider } from "next-themes";
import OnboardingApp from "./onboarding/app/App";
import "./onboarding/styles/index.css";

export function OnboardingManager() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <OnboardingApp />
      </div>
    </ThemeProvider>
  );
}
