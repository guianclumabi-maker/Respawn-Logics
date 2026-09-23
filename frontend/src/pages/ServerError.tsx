import React from "react";
import { AlertOctagon, RefreshCw } from "lucide-react";

export function ServerError({ error }: { error?: Error }) {
  return (
    <div className="flex flex-col items-center justify-center min-h-[calc(100vh-64px)] px-4 bg-background">
      <div className="bg-card border border-border rounded-xl shadow-sm p-10 max-w-md w-full text-center">
        <div className="w-16 h-16 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
          <AlertOctagon className="w-8 h-8" />
        </div>
        <h1 className="text-3xl font-bold mb-2">Oops!</h1>
        <h2 className="text-xl font-semibold mb-4">Something went wrong</h2>
        <p className="text-muted-foreground mb-4">
          The application encountered an unexpected error. Our engineering team has been notified.
        </p>
        
        {error && (
          <div className="bg-muted p-3 rounded text-left text-xs text-muted-foreground overflow-auto max-h-32 mb-6 font-mono whitespace-pre-wrap">
            {error.toString()}
          </div>
        )}

        <button 
          onClick={() => window.location.reload()}
          className="inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary text-primary-foreground font-medium rounded-lg hover:bg-primary/90 transition-colors w-full"
        >
          <RefreshCw className="w-4 h-4" />
          Reload Page
        </button>
      </div>
    </div>
  );
}
