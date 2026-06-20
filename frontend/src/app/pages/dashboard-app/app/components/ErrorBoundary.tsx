import React, { Component, ErrorInfo, ReactNode } from "react";
import { AlertTriangle, RefreshCcw } from "lucide-react";

interface Props {
  children?: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error?: Error;
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error("Uncaught error:", error, errorInfo);
  }

  public render() {
    if (this.state.hasError) {
      if (this.props.fallback) return this.props.fallback;

      return (
        <div style={{
          display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
          height: "100%", width: "100%", padding: "2rem",
          background: "rgba(255,255,255,0.02)", borderRadius: "0.5rem",
          color: "#f3f4f6", textAlign: "center", border: "1px solid rgba(239, 68, 68, 0.2)"
        }}>
          <AlertTriangle size={48} color="#ef4444" style={{ marginBottom: "1rem", opacity: 0.8 }} />
          <h2 style={{ fontFamily: "'Outfit', sans-serif", fontSize: "1.25rem", margin: "0 0 0.5rem 0", color: "#fca5a5" }}>
            Something went wrong
          </h2>
          <p style={{ color: "#9ca3af", fontSize: "0.875rem", maxWidth: "400px", marginBottom: "1.5rem" }}>
            A critical error occurred while rendering this interface. Our team has been notified.
          </p>
          <button 
            onClick={() => window.location.reload()}
            style={{
              display: "flex", alignItems: "center", gap: "0.5rem",
              background: "rgba(239, 68, 68, 0.1)", border: "1px solid rgba(239, 68, 68, 0.2)",
              color: "#ef4444", padding: "0.5rem 1rem", borderRadius: "0.375rem",
              cursor: "pointer", fontSize: "0.875rem", fontWeight: 500, transition: "all 0.2s"
            }}
            onMouseEnter={e => e.currentTarget.style.background = "rgba(239, 68, 68, 0.2)"}
            onMouseLeave={e => e.currentTarget.style.background = "rgba(239, 68, 68, 0.1)"}
          >
            <RefreshCcw size={14} /> Reload Page
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
