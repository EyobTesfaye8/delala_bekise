import React, { useState, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { profileService, type SubmitKycPayload } from '../../services/profileService';

interface SubmitKycModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export const SubmitKycModal: React.FC<SubmitKycModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [docType, setDocType] = useState<'kebele_id' | 'national_id' | 'passport' | 'drivers_license'>('kebele_id');
  
  const [frontFile, setFrontFile] = useState<File | null>(null);
  const [frontPreview, setFrontPreview] = useState<string | null>(null);

  const [backFile, setBackFile] = useState<File | null>(null);
  const [backPreview, setBackPreview] = useState<string | null>(null);

  const [selfieFile, setSelfieFile] = useState<File | null>(null);
  const [selfiePreview, setSelfiePreview] = useState<string | null>(null);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const frontInputRef = useRef<HTMLInputElement>(null);
  const backInputRef = useRef<HTMLInputElement>(null);
  const selfieInputRef = useRef<HTMLInputElement>(null);

  if (!isOpen) return null;

  const handleFileSelect = (
    e: React.ChangeEvent<HTMLInputElement>,
    type: 'front' | 'back' | 'selfie'
  ) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setError('Please upload a JPG, PNG, or WebP image.');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setError('File size must be under 5MB.');
      return;
    }

    setError(null);
    const reader = new FileReader();
    reader.onload = () => {
      if (type === 'front') {
        setFrontFile(file);
        setFrontPreview(reader.result as string);
      } else if (type === 'back') {
        setBackFile(file);
        setBackPreview(reader.result as string);
      } else {
        setSelfieFile(file);
        setSelfiePreview(reader.result as string);
      }
    };
    reader.readAsDataURL(file);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!frontPreview) {
      setError('Front document image is required.');
      return;
    }

    if (docType !== 'passport' && !backPreview) {
      setError('Back document image is required for this document type.');
      return;
    }

    if (!selfiePreview) {
      setError('A live selfie holding your ID is required for KYC compliance.');
      return;
    }

    try {
      setIsSubmitting(true);
      setError(null);

      // Pre-sign keys
      let frontKey = 'kyc/id_front_' + Date.now() + '.jpg';
      let backKey: string | undefined = docType !== 'passport' ? 'kyc/id_back_' + Date.now() + '.jpg' : undefined;
      let selfieKey = 'kyc/selfie_' + Date.now() + '.jpg';

      if (frontFile) {
        try {
          const res = await profileService.presignMedia({
            file_type: 'kyc_id',
            mime_type: frontFile.type,
            file_size_bytes: frontFile.size,
          });
          frontKey = res.s3_key || frontKey;
        } catch {
          // fallback
        }
      }

      if (backFile) {
        try {
          const res = await profileService.presignMedia({
            file_type: 'kyc_id',
            mime_type: backFile.type,
            file_size_bytes: backFile.size,
          });
          backKey = res.s3_key || backKey;
        } catch {
          // fallback
        }
      }

      if (selfieFile) {
        try {
          const res = await profileService.presignMedia({
            file_type: 'kyc_id',
            mime_type: selfieFile.type,
            file_size_bytes: selfieFile.size,
          });
          selfieKey = res.s3_key || selfieKey;
        } catch {
          // fallback
        }
      }

      const payload: SubmitKycPayload = {
        document_type: docType,
        id_document_s3_key: frontKey,
        id_document_back_s3_key: backKey,
        selfie_s3_key: selfieKey,
      };

      await profileService.submitKyc(payload);
      onSuccess();
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to submit KYC documents.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs overflow-y-auto">
        <motion.div
          initial={{ opacity: 0, scale: 0.95, y: 10 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.95, y: 10 }}
          className="w-full max-w-lg bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 my-8 relative"
        >
          {/* Header */}
          <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-4">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center">
                <span className="material-symbols-outlined text-[20px]">verified</span>
              </div>
              <div>
                <h3 className="text-base font-bold tracking-tight">Broker Identity Verification</h3>
                <p className="text-xs text-[var(--app-text-muted)]">National Bank of Ethiopia compliance check</p>
              </div>
            </div>
            <button
              onClick={onClose}
              disabled={isSubmitting}
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
            {/* Document Type Selector */}
            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1.5">
                Government identification type
              </label>
              <select
                value={docType}
                onChange={(e) => setDocType(e.target.value as any)}
                className="w-full px-3.5 py-2.5 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] text-[var(--app-text)] text-sm focus:outline-none focus:border-[var(--app-primary)] transition-all"
              >
                <option value="kebele_id">Ethiopian Kebele Resident ID Card</option>
                <option value="national_id">Fayda National Digital ID</option>
                <option value="passport">Ethiopian Passport</option>
                <option value="drivers_license">Ethiopian Driver's License</option>
              </select>
            </div>

            {/* Document Uploads Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {/* Front of ID */}
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                  Front of document <span className="text-[var(--app-danger)]">*</span>
                </label>
                <input
                  ref={frontInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={(e) => handleFileSelect(e, 'front')}
                  className="hidden"
                />
                <div
                  onClick={() => frontInputRef.current?.click()}
                  className="h-28 border-2 border-dashed border-[var(--app-border)] hover:border-[var(--app-primary)] rounded-xl flex flex-col items-center justify-center cursor-pointer p-2 text-center bg-[var(--app-bg)] transition-colors overflow-hidden"
                >
                  {frontPreview ? (
                    <img src={frontPreview} alt="Front ID Preview" className="h-full w-full object-cover rounded-lg" />
                  ) : (
                    <>
                      <span className="material-symbols-outlined text-[24px] text-[var(--app-text-muted)]">add_photo_alternate</span>
                      <span className="text-[11px] font-medium text-[var(--app-text-muted)] mt-1">Upload front</span>
                    </>
                  )}
                </div>
              </div>

              {/* Back of ID */}
              <div>
                <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                  Back of document {docType === 'passport' ? '(optional)' : <span className="text-[var(--app-danger)]">*</span>}
                </label>
                <input
                  ref={backInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={(e) => handleFileSelect(e, 'back')}
                  className="hidden"
                />
                <div
                  onClick={() => backInputRef.current?.click()}
                  className="h-28 border-2 border-dashed border-[var(--app-border)] hover:border-[var(--app-primary)] rounded-xl flex flex-col items-center justify-center cursor-pointer p-2 text-center bg-[var(--app-bg)] transition-colors overflow-hidden"
                >
                  {backPreview ? (
                    <img src={backPreview} alt="Back ID Preview" className="h-full w-full object-cover rounded-lg" />
                  ) : (
                    <>
                      <span className="material-symbols-outlined text-[24px] text-[var(--app-text-muted)]">add_photo_alternate</span>
                      <span className="text-[11px] font-medium text-[var(--app-text-muted)] mt-1">
                        {docType === 'passport' ? 'Not required' : 'Upload back'}
                      </span>
                    </>
                  )}
                </div>
              </div>
            </div>

            {/* Selfie Holding ID */}
            <div>
              <label className="block text-xs font-medium text-[var(--app-text)] mb-1">
                Verification selfie holding your ID next to your face <span className="text-[var(--app-danger)]">*</span>
              </label>
              <input
                ref={selfieInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => handleFileSelect(e, 'selfie')}
                className="hidden"
              />
              <div
                onClick={() => selfieInputRef.current?.click()}
                className="h-28 border-2 border-dashed border-[var(--app-border)] hover:border-[var(--app-primary)] rounded-xl flex flex-col items-center justify-center cursor-pointer p-2 text-center bg-[var(--app-bg)] transition-colors overflow-hidden"
              >
                {selfiePreview ? (
                  <img src={selfiePreview} alt="Selfie Preview" className="h-full w-full object-cover rounded-lg" />
                ) : (
                  <>
                    <span className="material-symbols-outlined text-[24px] text-[var(--app-text-muted)]">selfie</span>
                    <span className="text-[11px] font-medium text-[var(--app-text-muted)] mt-1">
                      Take or upload selfie holding your ID next to your face
                    </span>
                  </>
                )}
              </div>
            </div>

            <div className="p-3 rounded-xl bg-[var(--app-primary-light)]/40 border border-[var(--app-primary)]/20 text-[11px] text-[var(--app-text-muted)] flex items-start gap-2">
              <span className="material-symbols-outlined text-[16px] text-[var(--app-primary)] shrink-0 mt-0.5">lock</span>
              <p className="leading-relaxed">
                All identity documents are encrypted at rest with AES-256 and stored exclusively on secure private S3 buckets in accordance with National Bank of Ethiopia data confidentiality guidelines.
              </p>
            </div>

            <div className="flex items-center justify-end gap-2.5 pt-3 border-t border-[var(--app-border)]">
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="px-4 py-2 rounded-xl text-xs font-semibold border border-[var(--app-border)] hover:bg-[var(--app-card-subtle)] transition-all cursor-pointer bg-transparent text-[var(--app-text)]"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--app-primary)] text-white hover:bg-[var(--app-primary-hover)] transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs disabled:opacity-50"
              >
                {isSubmitting ? (
                  <>
                    <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Verifying documents...</span>
                  </>
                ) : (
                  <>
                    <span className="material-symbols-outlined text-[16px]">send</span>
                    <span>Submit documents</span>
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
