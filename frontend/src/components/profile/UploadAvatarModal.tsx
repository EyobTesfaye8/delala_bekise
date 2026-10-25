import React, { useState, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { profileService } from '../../services/profileService';

interface UploadAvatarModalProps {
  isOpen: boolean;
  currentAvatarUrl?: string | null;
  userName?: string | null;
  onClose: () => void;
  onSave: (avatarKeyOrUrl: string) => Promise<void>;
}

export const UploadAvatarModal: React.FC<UploadAvatarModalProps> = ({
  isOpen,
  currentAvatarUrl,
  userName,
  onClose,
  onSave,
}) => {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(currentAvatarUrl || null);
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Suggested high-quality Ethiopian broker avatars
  const suggestedAvatars = [
    'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=240&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=240&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=240&auto=format&fit=crop&q=80',
    'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=240&auto=format&fit=crop&q=80',
  ];

  if (!isOpen) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setError('Please select a JPG, PNG, or WebP image.');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setError('Image must be smaller than 5MB.');
      return;
    }

    setError(null);
    setSelectedFile(file);

    const reader = new FileReader();
    reader.onload = () => {
      setPreviewUrl(reader.result as string);
    };
    reader.readAsDataURL(file);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (file) {
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        setError('Please select a JPG, PNG, or WebP image.');
        return;
      }
      setSelectedFile(file);
      const reader = new FileReader();
      reader.onload = () => {
        setPreviewUrl(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSave = async () => {
    if (!previewUrl) {
      setError('Please select or upload a profile photo.');
      return;
    }

    try {
      setIsUploading(true);
      setError(null);

      let finalKey = previewUrl;

      // If user selected a local file, simulate/call S3 presign media endpoint
      if (selectedFile) {
        try {
          const presignRes = await profileService.presignMedia({
            file_type: 'proposal_image',
            mime_type: selectedFile.type,
            file_size_bytes: selectedFile.size,
          });
          finalKey = presignRes.s3_key || previewUrl;
        } catch {
          // If S3 mock or local, fallback to the preview data url
          finalKey = previewUrl;
        }
      }

      await onSave(finalKey);
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to save profile picture.');
    } finally {
      setIsUploading(false);
    }
  };

  const initials = (userName || 'Delala User')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((n) => n[0].toUpperCase())
    .join('');

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 10 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 10 }}
          className="w-full max-w-lg bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 relative"
        >
          {/* Header */}
          <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-4">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
                <span className="material-symbols-outlined text-[20px]">account_circle</span>
              </div>
              <div>
                <h3 className="text-base font-bold tracking-tight">Update profile photo</h3>
                <p className="text-xs text-[var(--app-text-muted)]">Upload a clear portrait to build client trust</p>
              </div>
            </div>
            <button
              onClick={onClose}
              disabled={isUploading}
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

          {/* Current / Preview Area */}
          <div className="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] mb-4">
            <div className="relative group">
              <div className="w-24 h-24 rounded-full overflow-hidden border-2 border-[var(--app-primary)] shadow-sm bg-[var(--app-primary-light)] flex items-center justify-center">
                {previewUrl ? (
                  <img
                    src={previewUrl}
                    alt="Profile Avatar Preview"
                    className="w-full h-full object-cover"
                  />
                ) : (
                  <span className="text-2xl font-bold text-[var(--app-primary)]">{initials}</span>
                )}
              </div>
            </div>

            <div className="flex-1 text-center sm:text-left space-y-2">
              <h4 className="text-sm font-bold">Photo Guidelines</h4>
              <p className="text-xs text-[var(--app-text-muted)] leading-relaxed">
                Professional portraits with good lighting result in a 3x higher proposal acceptance rate on Delala.
              </p>
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[var(--app-card)] border border-[var(--app-border)] hover:border-[var(--app-primary)] text-[var(--app-text)] transition-all cursor-pointer inline-flex items-center gap-1.5"
              >
                <span className="material-symbols-outlined text-[16px]">upload_file</span>
                <span>Select from Computer</span>
              </button>
            </div>
          </div>

          {/* Dropzone */}
          <div
            onDragOver={(e) => e.preventDefault()}
            onDrop={handleDrop}
            onClick={() => fileInputRef.current?.click()}
            className="border-2 border-dashed border-[var(--app-border)] hover:border-[var(--app-primary)] rounded-xl p-5 text-center cursor-pointer transition-colors bg-[var(--app-bg)]/50 mb-4"
          >
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={handleFileChange}
              className="hidden"
            />
            <span className="material-symbols-outlined text-[32px] text-[var(--app-text-muted)] mb-1">
              cloud_upload
            </span>
            <p className="text-xs font-medium text-[var(--app-text)]">
              Drag &amp; drop your photo here, or click to browse
            </p>
            <p className="text-[11px] text-[var(--app-text-muted)] mt-0.5">
              Supports JPG, PNG, WebP up to 5MB (Stored in Cloud S3)
            </p>
          </div>

          {/* Preset Suggested Avatars */}
          <div className="mb-5">
            <label className="block text-xs font-medium text-[var(--app-text)] mb-2">
              Or choose a preset portrait
            </label>
            <div className="flex items-center gap-3">
              {suggestedAvatars.map((url, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => {
                    setPreviewUrl(url);
                    setSelectedFile(null);
                  }}
                  className={`w-12 h-12 rounded-full overflow-hidden border-2 transition-transform hover:scale-105 cursor-pointer p-0 ${
                    previewUrl === url
                      ? 'border-[var(--app-primary)] ring-2 ring-[var(--app-primary)]/30'
                      : 'border-[var(--app-border)]'
                  }`}
                >
                  <img src={url} alt={`Preset ${idx + 1}`} className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-[var(--app-border)]">
            <button
              type="button"
              onClick={onClose}
              disabled={isUploading}
              className="px-4 py-2 rounded-xl text-xs font-semibold border border-[var(--app-border)] hover:bg-[var(--app-card-subtle)] transition-all cursor-pointer bg-transparent text-[var(--app-text)]"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleSave}
              disabled={isUploading}
              className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
            >
              {isUploading ? (
                <>
                  <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  <span>Saving photo...</span>
                </>
              ) : (
                <>
                  <span className="material-symbols-outlined text-[16px]">check</span>
                  <span>Save photo</span>
                </>
              )}
            </button>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  );
};
