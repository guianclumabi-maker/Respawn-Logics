
  import { createRoot } from "react-dom/client";
  import App from "./app/App.tsx";
  import { ThemeProvider } from "next-themes";
  import "./styles/index.css";

  createRoot(document.getElementById("root")!).render(
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <App />
    </ThemeProvider>
  );
  