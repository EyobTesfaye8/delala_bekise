import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';
import { ChapaCheckoutModal } from '../../components/buyer/ChapaCheckoutModal';

export const NegotiationChatPage: React.FC = () => {
  const {
    chatThreads,
    chatMessages,
    selectedChatId,
    sendMessage,
    openProposalInbox,
  } = useBuyer();

  const [activeChatId, setActiveChatId] = useState<number>(selectedChatId || 1);
  const [inputText, setInputText] = useState('');
  const [circumventionWarning, setCircumventionWarning] = useState<string | null>(null);

  // Checkout modal triggered directly from in-chat system cards
  const [checkoutModal, setCheckoutModal] = useState<{
    isOpen: boolean;
    jobId: number;
    proposalId: number;
    itemId: number;
    itemTitle: string;
    delalaName: string;
    commissionAmountEtb: number;
  }>({
    isOpen: false,
    jobId: 0,
    proposalId: 0,
    itemId: 0,
    itemTitle: '',
    delalaName: '',
    commissionAmountEtb: 0,
  });

  const activeThread = chatThreads.find((t) => t.id === activeChatId) || chatThreads[0];
  const currentMessages = activeThread ? chatMessages[activeThread.id] || [] : [];
  const isEscrowFunded = activeThread?.is_escrow_funded ?? false;

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setInputText(val);

    // Live Anti-Circumvention Pattern Check
    if (!isEscrowFunded) {
      const phoneRegex = /(\+?251|0)?[97]\d{7,8}/;
      const handleRegex = /@\w{3,}/;
      const emailRegex = /[\w.-]+@[\w.-]+\.\w+/;

      if (phoneRegex.test(val) || handleRegex.test(val) || emailRegex.test(val)) {
        setCircumventionWarning(
          '⚠️ Anti-Circumvention Policy: Phone numbers, Telegram handles, and emails are strictly blocked until escrow is funded.'
        );
      } else {
        setCircumventionWarning(null);
      }
    }
  };

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim()) return;

    const ok = sendMessage(activeThread.id, inputText);
    if (!ok) {
      setCircumventionWarning(
        'Message Blocked: Sharing contact info before Escrow is funded violates marketplace guidelines.'
      );
    } else {
      setInputText('');
      setCircumventionWarning(null);
    }
  };

  // Helper button to simulate a rule violation for testing purposes
  const testSimulateViolation = () => {
    setInputText('Call me on 0911448920 or @ethio_buyer');
    setCircumventionWarning(
      '⚠️ Anti-Circumvention Policy: Phone numbers, Telegram handles, and emails are strictly blocked until escrow is funded.'
    );
  };

  return (
    <div className="h-[calc(100vh-12rem)] min-h-[520px] rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs flex overflow-hidden">
      {/* Left Pane: Conversation List */}
      <div className="w-80 border-r border-[var(--app-border)] flex flex-col shrink-0 bg-[var(--app-card)]">
        <div className="p-4 border-b border-[var(--app-border)] flex items-center justify-between">
          <h2 className="text-xs font-bold text-[var(--app-text)] uppercase tracking-wider">
            Conversations
          </h2>
          <span className="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[var(--app-primary-light)] text-[var(--app-primary)]">
            {chatThreads.length} Active
          </span>
        </div>

        <div className="flex-1 overflow-y-auto divide-y divide-[var(--app-border)]">
          {chatThreads.map((thread) => {
            const isSelected = thread.id === activeThread.id;
            return (
              <button
                key={thread.id}
                onClick={() => {
                  setActiveChatId(thread.id);
                  setCircumventionWarning(null);
                }}
                className={`w-full p-4 text-left transition-colors cursor-pointer border-0 flex items-start gap-3 ${
                  isSelected
                    ? 'bg-[var(--app-card-subtle)]'
                    : 'bg-transparent hover:bg-[var(--app-card-subtle)]/60'
                }`}
              >
                <div className="relative shrink-0">
                  <img
                    src={thread.delala_avatar}
                    alt={thread.delala_name}
                    className="w-10 h-10 rounded-xl object-cover"
                  />
                  {thread.is_escrow_funded && (
                    <span
                      className="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-[var(--app-primary)] text-white flex items-center justify-center text-[10px]"
                      title="Escrow Funded"
                    >
                      <span className="material-symbols-outlined text-[10px]">lock</span>
                    </span>
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between mb-0.5">
                    <span className="text-xs font-bold text-[var(--app-text)] truncate">
                      {thread.delala_name}
                    </span>
                    <span className="text-[10px] text-[var(--app-text-muted)] shrink-0">
                      {thread.last_message_at}
                    </span>
                  </div>

                  <p className="text-[11px] text-[var(--app-primary)] font-medium truncate mb-1">
                    {thread.job_title}
                  </p>

                  <p className="text-[11px] text-[var(--app-text-muted)] truncate leading-relaxed">
                    {thread.last_message}
                  </p>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      {/* Right Pane: Active Thread */}
      <div className="flex-1 flex flex-col bg-[var(--app-card-subtle)]/40 relative">
        {/* Sticky Anti-Circumvention Banner (Top of Chat) */}
        {isEscrowFunded ? (
          <div className="p-3 bg-[var(--app-primary-light)] border-b border-[var(--app-primary)]/30 text-[var(--app-primary)] text-xs flex items-center justify-between px-5">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-[18px]">verified</span>
              <span>
                <strong>✓ Escrow Funded:</strong> Contact info unlocked for offline meetup. Call Delala {activeThread.delala_name} at <strong className="font-financial-numeric">{activeThread.delala_phone}</strong>.
              </span>
            </div>
            <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-[var(--app-primary)] text-white">
              Unlocked
            </span>
          </div>
        ) : (
          <div className="p-3 bg-[var(--app-accent-light)] border-b border-[var(--app-accent)]/30 text-[var(--app-accent)] text-xs flex items-center justify-between px-5">
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-[18px]">shield</span>
              <span>
                <strong>Anti-Circumvention Protection:</strong> For your protection, sharing phone numbers, Telegram handles, or emails is blocked until Escrow is funded.
              </span>
            </div>
            <button
              type="button"
              onClick={testSimulateViolation}
              className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border border-[var(--app-accent)] text-[var(--app-accent)] hover:bg-[var(--app-accent)] hover:text-white transition-colors cursor-pointer bg-transparent"
              title="Test the anti-circumvention rule detector"
            >
              Simulate Violation
            </button>
          </div>
        )}

        {/* Chat Thread Header */}
        <div className="px-5 py-3 border-b border-[var(--app-border)] bg-[var(--app-card)] flex items-center justify-between">
          <div className="flex items-center gap-3">
            <img
              src={activeThread.delala_avatar}
              alt={activeThread.delala_name}
              className="w-9 h-9 rounded-xl object-cover"
            />
            <div>
              <div className="flex items-center gap-2">
                <h3 className="text-xs font-bold text-[var(--app-text)]">{activeThread.delala_name}</h3>
                {activeThread.is_kyc_verified && (
                  <span className="text-[10px] font-semibold text-[var(--app-primary)] flex items-center gap-0.5">
                    <span className="material-symbols-outlined text-[13px]">verified</span>
                    <span>KYC Verified</span>
                  </span>
                )}
              </div>
              <p className="text-[11px] text-[var(--app-text-muted)] truncate max-w-sm">
                Discussing: <strong className="text-[var(--app-text)]">{activeThread.job_title}</strong>
              </p>
            </div>
          </div>

          <button
            onClick={() => openProposalInbox(activeThread.job_id)}
            className="px-3 py-1.5 rounded-lg text-xs font-semibold text-[var(--app-primary)] hover:bg-[var(--app-primary-light)] transition-colors cursor-pointer border-0 bg-transparent flex items-center gap-1"
          >
            <span>View Proposals</span>
            <span className="material-symbols-outlined text-[15px]">arrow_forward</span>
          </button>
        </div>

        {/* Message Stream */}
        <div className="flex-1 overflow-y-auto p-5 space-y-4">
          {currentMessages.map((msg) => {
            if (msg.is_system && msg.system_event) {
              // Rich System Event Card
              return (
                <div key={msg.id} className="w-full flex justify-center my-4 animate-in fade-in duration-150">
                  <div className="max-w-md w-full p-4 rounded-xl bg-[var(--app-card)] border border-[var(--app-primary)]/40 shadow-xs space-y-2.5 text-center">
                    <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[var(--app-primary-light)] text-[var(--app-primary)] text-[10px] font-bold uppercase tracking-wider">
                      <span className="material-symbols-outlined text-[13px]">published_with_changes</span>
                      <span>Marketplace Negotiation Event</span>
                    </div>

                    <p className="text-xs text-[var(--app-text)] font-medium leading-relaxed">
                      Delala updated Commission Fee for <strong>'{msg.system_event.item_title}'</strong> from{' '}
                      <span className="line-through text-[var(--app-text-muted)] font-financial-numeric">
                        {msg.system_event.old_amount_etb?.toLocaleString()} ETB
                      </span>{' '}
                      to{' '}
                      <strong className="text-[var(--app-primary)] font-financial-numeric">
                        {msg.system_event.new_amount_etb?.toLocaleString()} ETB
                      </strong>
                    </p>

                    <div className="pt-1 flex items-center justify-center gap-2">
                      <button
                        onClick={() =>
                          setCheckoutModal({
                            isOpen: true,
                            jobId: activeThread.job_id,
                            proposalId: 101,
                            itemId: msg.system_event?.item_id || 1001,
                            itemTitle: msg.system_event?.item_title || 'Negotiated Asset',
                            delalaName: activeThread.delala_name,
                            commissionAmountEtb: msg.system_event?.new_amount_etb || 16500,
                          })
                        }
                        className="px-4 py-1.5 rounded-lg bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1"
                      >
                        <span className="material-symbols-outlined text-[14px]">lock</span>
                        <span>Accept &amp; Fund {msg.system_event.new_amount_etb?.toLocaleString()} ETB</span>
                      </button>
                    </div>
                  </div>
                </div>
              );
            }

            const isMe = msg.sender_type === 'buyer';

            return (
              <div
                key={msg.id}
                className={`flex flex-col ${isMe ? 'items-end' : 'items-start'} space-y-1`}
              >
                <div
                  className={`max-w-md px-4 py-2.5 rounded-2xl text-xs leading-relaxed ${
                    isMe
                      ? 'bg-[var(--app-primary)] text-white rounded-br-xs'
                      : 'bg-[var(--app-card)] text-[var(--app-text)] border border-[var(--app-border)] rounded-bl-xs shadow-xs'
                  }`}
                >
                  {msg.text}
                </div>
                <span className="text-[10px] text-[var(--app-text-muted)] px-1">{msg.created_at}</span>
              </div>
            );
          })}
        </div>

        {/* Inline Red Violation Warning */}
        {circumventionWarning && (
          <div className="mx-4 mb-2 p-3 rounded-xl bg-[var(--app-danger-light)] text-[var(--app-danger)] text-xs flex items-center gap-2 animate-in fade-in duration-150">
            <span className="material-symbols-outlined text-[16px] shrink-0">block</span>
            <span>{circumventionWarning}</span>
          </div>
        )}

        {/* Input Bar */}
        <form
          onSubmit={handleSend}
          className="p-3 border-t border-[var(--app-border)] bg-[var(--app-card)] flex items-center gap-2"
        >
          <input
            type="text"
            value={inputText}
            onChange={handleInputChange}
            placeholder={
              isEscrowFunded
                ? `Message Delala ${activeThread.delala_name}...`
                : 'Message broker (sharing contact info blocked until escrow is funded)...'
            }
            className="flex-1 px-4 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors"
          />
          <button
            type="submit"
            className="px-4 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold transition-all cursor-pointer border-0 flex items-center gap-1.5 shadow-xs"
          >
            <span>Send</span>
            <span className="material-symbols-outlined text-[16px]">send</span>
          </button>
        </form>

        {/* Chapa Checkout modal from in-chat CTA */}
        <ChapaCheckoutModal
          isOpen={checkoutModal.isOpen}
          jobId={checkoutModal.jobId}
          proposalId={checkoutModal.proposalId}
          itemId={checkoutModal.itemId}
          itemTitle={checkoutModal.itemTitle}
          delalaName={checkoutModal.delalaName}
          commissionAmountEtb={checkoutModal.commissionAmountEtb}
          onClose={() => setCheckoutModal((prev) => ({ ...prev, isOpen: false }))}
        />
      </div>
    </div>
  );
};
