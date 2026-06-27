import React from "react";
import { ThemeProvider } from "next-themes";
import { FileWarning } from "lucide-react";

export function KnowledgeAdmin() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 flex flex-col items-center justify-center bg-[#0a0a0a] text-white">
        <FileWarning size={64} className="text-yellow-400 mb-4" />
        <h1 className="text-2xl font-bold mb-2">Migration Pending</h1>
        <p className="text-gray-400">This module is pending backend API implementation.</p>
      </div>
    </ThemeProvider>
  );
}
