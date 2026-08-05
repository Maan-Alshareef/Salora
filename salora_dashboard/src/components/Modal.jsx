import React from "react";
import Button from "./Button";

export default function Modal({
  isOpen,
  title,
  children,
  onClose,
  onSave,
  showSave = true,
  saveText = "Save",
}) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div className="glass-panel neon-edge-blue p-8 rounded-3xl max-w-lg w-full animate-fadeIn">
        <div className="flex items-center justify-between mb-6 pb-4 border-b border-white/10">
          <h2 className="text-2xl font-bold text-white">{title}</h2>
          <button
            onClick={onClose}
            className="text-blue-300 hover:text-white transition-colors"
          >
            ✕
          </button>
        </div>

        <div className="mb-6">{children}</div>

        <div className="flex gap-3 pt-4 border-t border-white/10">
          <Button
            variant="glass"
            onClick={onClose}
            className="flex-1 justify-center"
          >
            Cancel
          </Button>
          {showSave && (
            <Button
              variant="primary"
              onClick={onSave}
              className="flex-1 justify-center"
            >
              {saveText}
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
