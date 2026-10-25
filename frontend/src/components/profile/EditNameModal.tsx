import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

interface EditNameModalProps {
  isOpen: boolean;
  currentName: string;
  onClose: () => void;
  onSave: (newName: string) => Promise<void>;
}

export const EditNameModal: React.FC<EditNameModalProps> = ({
  isOpen,
  currentName,
  onClose,
  onSave,
}) => {
  const [name, setName] = useState(currentName);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = name.trim();
    if (!trimmed) {
      setError('Full name cannot be empty.');
      return;
    }
    if (trimmed.length < 3) {
      setError('Name must be at least 3 characters.');
      return;
    }

    try {
      setIsSaving(true);
      setError(null);
      await onSave(trimmed);
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to update name.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 10 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 10 }}
          className="w-full max-w-md bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 relative"
        >
          {/* Header */}
          <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-4">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
                <span className="material-symbols-outlined text-[20px]">badge</span>
              </div>
              <div>
                <h3 className="text-base font-bold tracking-tight">Edit legal or display name</h3>
                <p className="text-xs text-[var(--app-text-muted)]">Shown on proposals, deals, and contracts</p>
              </div>
            </div>
            <button
              onClick={onClose}
              disabled={isSaving}
              className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 bg-transparent"
            >
              <span className="material-symbols-outlined text-[20px]">close</span>
            </button>
          </div>

          {error && (
            <div className="mb-4 p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2">
              <span className="material-symbols-outlined text-[16px]">error</span>
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                Full legal name
              </label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Abebe Kebede Tadesse"
                required
                className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)] transition-all font-medium"
              />
              <p className="mt-1.5 text-[11px] text-[var(--app-text-muted)] leading-relaxed">
                Matches your verified Kebele ID or National ID for NBE escrow compliance.
              </p>
            </div>

            <div className="flex items-center justify-end gap-2.5 pt-2 border-t border-[var(--app-border)]">
              <button
                type="button"
                onClick={onClose}
                disabled={isSaving}
                className="px-4 py-2 rounded-xl text-xs font-semibold border border-[var(--app-border)] hover:bg-[var(--app-card-subtle)] transition-all cursor-pointer bg-transparent text-[var(--app-text)]"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSaving}
                className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
              >
                {isSaving ? (
                  <>
                    <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Saving...</span>
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-[16px]">check</span>
                    <span>Save changes</span>
                  </>
                )}
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </AnimatePresence>
  );
};
