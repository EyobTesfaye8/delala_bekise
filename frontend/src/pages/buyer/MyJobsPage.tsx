import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';
import { ChapaCheckoutModal } from '../../components/buyer/ChapaCheckoutModal';

export const MyJobsPage: React.FC = () => {
  const {
    jobs,
    selectedJobId,
    proposalsByJobId,
    isGuestMode,
    openProposalInbox,
    setRoute,
    cloneJob,
    openChatForJob,
    rejectProposalItem,
  } = useBuyer();

  // Tab filter for Sub-view 1A: 'active' | 'contracted' | 'completed'
  const [activeTab, setActiveTab] = useState<'active' | 'contracted' | 'completed'>('active');

  // Checkout modal state for Sub-view 1B
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

  // Filter jobs based on active tab
  const filteredJobs = jobs.filter((job) => {
    if (activeTab === 'active') {
      return job.status === 'open' || job.status === 'negotiating';
    }
    if (activeTab === 'contracted') {
      return job.status === 'contracted';
    }
    return job.status === 'completed' || job.status === 'canceled';
  });

  const activeJobsCount = jobs.filter((j) => j.status === 'open' || j.status === 'negotiating').length;

  // Selected job for Sub-view 1B
  const selectedJob = selectedJobId ? jobs.find((j) => j.id === selectedJobId) : null;
  const currentProposals = selectedJobId ? proposalsByJobId[selectedJobId] || [] : [];

  // ==========================================
  // SUB-VIEW 1B: Proposal Review Inbox
  // ==========================================
  if (selectedJob) {
    return (
      <div className="space-y-6">
        {/* Header with Back Button */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--app-border)]">
          <div className="flex items-center gap-3">
            <button
              onClick={() => openProposalInbox(0)}
              className="w-9 h-9 rounded-xl bg-[var(--app-card)] border border-[var(--app-border)] hover:border-[var(--app-primary)] flex items-center justify-center text-[var(--app-text)] transition-colors cursor-pointer shadow-xs"
              title="Back to My Jobs"
            >
              <span className="material-symbols-outlined text-[20px]">arrow_back</span>
            </button>
            <div>
              <div className="flex items-center gap-2">
                <span className="px-2 py-0.5 rounded text-xs font-medium bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                  {selectedJob.category_name}
                </span>
                <span className="text-xs text-[var(--app-text-muted)]">{selectedJob.location}</span>
              </div>
              <h1 className="text-lg sm:text-xl font-bold tracking-tight text-[var(--app-text)] mt-0.5">
                {selectedJob.title}
              </h1>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="px-3.5 py-1.5 rounded-xl bg-[var(--app-card)] border border-[var(--app-border)] text-right">
              <p className="text-xs text-[var(--app-text-muted)] font-medium">Budget range</p>
              <p className="font-financial-numeric text-xs font-bold text-[var(--app-text)]">
                {selectedJob.budget_min_etb.toLocaleString()} – {selectedJob.budget_max_etb.toLocaleString()} ETB
              </p>
            </div>
          </div>
        </div>

        {/* Proposals List */}
        {currentProposals.length === 0 ? (
          <div className="py-16 text-center rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-8">
            <div className="w-12 h-12 rounded-2xl bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] flex items-center justify-center mx-auto mb-3">
              <span className="material-symbols-outlined text-[24px]">inbox</span>
            </div>
            <h3 className="text-sm font-bold text-[var(--app-text)]">No proposals received yet</h3>
            <p className="text-xs text-[var(--app-text-muted)] max-w-sm mx-auto mt-1">
              Verified local Delalas in {selectedJob.location_subcity || 'Addis Ababa'} have been notified of your requirement.
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <h2 className="text-xs font-semibold text-[var(--app-text)]">
                {currentProposals.length} verified broker proposals
              </h2>
              <span className="text-xs text-[var(--app-text-muted)]">
                Select an item to inspect or fund escrow
              </span>
            </div>

            {currentProposals.map((proposal) => (
              <div
                key={proposal.id}
                className="rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-5 sm:p-6 shadow-xs space-y-5"
              >
                {/* Delala Proposal Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-[var(--app-border)]">
                  <div className="flex items-center gap-3.5">
                    <img
                      src={proposal.delala_avatar}
                      alt={proposal.delala_name}
                      className="w-11 h-11 rounded-xl object-cover"
                    />
                    <div>
                      <div className="flex items-center gap-2">
                        <h3 className="text-sm font-bold text-[var(--app-text)]">{proposal.delala_name}</h3>
                        {proposal.is_kyc_verified && (
                          <span className="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                            <span className="material-symbols-outlined text-[13px]">verified</span>
                            <span>Verified KYC</span>
                          </span>
                        )}
                      </div>
                      <div className="flex items-center gap-3 text-xs text-[var(--app-text-muted)] mt-0.5">
                        <span className="flex items-center gap-1 font-semibold text-[var(--app-accent)]">
                          <span className="material-symbols-outlined text-[14px]">star</span>
                          {proposal.rating}
                        </span>
                        <span>•</span>
                        <span>{proposal.deals_completed} completed deals</span>
                        <span>•</span>
                        <span>Submitted {proposal.created_at}</span>
                      </div>
                    </div>
                  </div>

                  <button
                    onClick={() => openChatForJob(selectedJob.id, proposal.delala_id)}
                    className="px-3.5 py-1.5 rounded-xl bg-[var(--app-card-subtle)] hover:bg-[var(--app-border)] text-[var(--app-text)] text-xs font-semibold transition-colors cursor-pointer border-0 flex items-center gap-1.5 self-start sm:self-auto"
                  >
                    <span className="material-symbols-outlined text-[16px]">chat</span>
                    <span>Chat &amp; Negotiate</span>
                  </button>
                </div>

                {/* Multi-Item Proposal Grid (1 to N Items) */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {proposal.items.map((item) => (
                    <div
                      key={item.id}
                      className="rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] p-4 flex flex-col justify-between hover:border-[var(--app-primary)]/40 transition-all duration-200"
                    >
                      <div className="space-y-3">
                        {/* Image Preview with pill */}
                        {item.image_urls.length > 0 && (
                          <div className="relative rounded-lg overflow-hidden h-40 bg-black/10">
                            <img
                              src={item.image_urls[0]}
                              alt={item.item_name}
                              className="w-full h-full object-cover"
                            />
                            <div className="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full bg-black/60 text-white text-[10px] font-medium backdrop-blur-xs flex items-center gap-1">
                              <span className="material-symbols-outlined text-[12px]">photo_library</span>
                              <span>1/{item.image_urls.length} photos</span>
                            </div>
                          </div>
                        )}

                        <div>
                          <div className="flex items-center justify-between mb-1">
                            <h4 className="text-xs font-bold text-[var(--app-text)] leading-snug">
                              {item.item_name}
                            </h4>
                          </div>
                          <p className="text-[11px] text-[var(--app-primary)] font-medium flex items-center gap-1 mb-1.5">
                            <span className="material-symbols-outlined text-[13px]">location_on</span>
                            {item.text_location}
                          </p>
                          <p className="text-xs text-[var(--app-text-muted)] line-clamp-2 leading-relaxed">
                            {item.description}
                          </p>
                        </div>

                        {/* Financial Information */}
                        <div className="pt-2 border-t border-[var(--app-border)] space-y-1">
                          <p className="text-[11px] text-[var(--app-text-muted)] font-financial-numeric">
                            Est. Asset Price: <strong className="text-[var(--app-text)]">{item.estimated_price_etb.toLocaleString()} ETB</strong> (Paid Offline)
                          </p>
                          <div className="flex items-baseline justify-between">
                            <span className="text-xs text-[var(--app-text-muted)] font-medium">Delala Commission:</span>
                            <span className="font-financial-numeric text-base font-extrabold text-[var(--app-primary)]">
                              {item.commission_fee_etb.toLocaleString()} ETB
                            </span>
                          </div>
                        </div>
                      </div>

                      {/* Item Actions */}
                      <div className="pt-4 mt-3 border-t border-[var(--app-border)] flex items-center justify-between gap-2">
                        <button
                          onClick={() => rejectProposalItem(selectedJob.id, proposal.id, item.id)}
                          className="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-[var(--app-text-muted)] hover:text-[var(--app-danger)] transition-colors cursor-pointer border-0 bg-transparent"
                        >
                          Reject
                        </button>
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => openChatForJob(selectedJob.id, proposal.delala_id)}
                            className="px-3 py-1.5 rounded-lg text-xs font-semibold text-[var(--app-text)] bg-[var(--app-card)] hover:bg-[var(--app-border)] transition-colors cursor-pointer border-0"
                          >
                            Negotiate
                          </button>
                          <button
                            onClick={() =>
                              setCheckoutModal({
                                isOpen: true,
                                jobId: selectedJob.id,
                                proposalId: proposal.id,
                                itemId: item.id,
                                itemTitle: item.item_name,
                                delalaName: proposal.delala_name,
                                commissionAmountEtb: item.commission_fee_etb,
                              })
                            }
                            className="px-3.5 py-1.5 rounded-lg text-xs font-bold text-white bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
                          >
                            <span className="material-symbols-outlined text-[15px]">lock</span>
                            <span>Accept &amp; Fund</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Checkout Modal */}
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
    );
  }

  // ==========================================
  // SUB-VIEW 1A: Jobs Overview
  // ==========================================
  return (
    <div className="space-y-6">
      {/* Header & Post CTA */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-[var(--app-text)]">
            My Jobs &amp; Proposal Inbox
          </h1>
          <p className="text-xs sm:text-sm text-[var(--app-text-muted)] mt-0.5">
            Manage your escrow search requirements and review verified broker proposals.
          </p>
        </div>

        <button
          onClick={() => setRoute('post-job')}
          className="px-4 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-2 self-start sm:self-auto"
        >
          <span className="material-symbols-outlined text-[18px]">add_circle</span>
          <span>Post a Request</span>
        </button>
      </div>

      {/* Guest Mode Job Limit Notice (2/2 Active) */}
      {isGuestMode && activeJobsCount >= 2 && (
        <div className="p-3.5 rounded-xl bg-[var(--app-accent-light)] border border-[var(--app-accent)]/30 text-[var(--app-accent)] text-xs flex items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-[18px] shrink-0">info</span>
            <span>
              <strong>Guest limit: 2/2 active open jobs.</strong> You have reached the maximum concurrent requests for a guest session.
            </span>
          </div>
          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--app-accent)] text-white shrink-0">
            Limit Reached
          </span>
        </div>
      )}

      {/* Minimalist Segmented Tabs */}
      <div className="flex items-center gap-1.5 p-1 rounded-xl bg-[var(--app-card)] border border-[var(--app-border)] w-fit">
        <button
          onClick={() => setActiveTab('active')}
          className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer border-0 ${
            activeTab === 'active'
              ? 'bg-[var(--app-primary)] text-white shadow-xs'
              : 'text-[var(--app-text-muted)] hover:text-[var(--app-text)] bg-transparent'
          }`}
        >
          Active (Open/Negotiating)
        </button>
        <button
          onClick={() => setActiveTab('contracted')}
          className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer border-0 ${
            activeTab === 'contracted'
              ? 'bg-[var(--app-primary)] text-white shadow-xs'
              : 'text-[var(--app-text-muted)] hover:text-[var(--app-text)] bg-transparent'
          }`}
        >
          Contracted Escrow
        </button>
        <button
          onClick={() => setActiveTab('completed')}
          className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer border-0 ${
            activeTab === 'completed'
              ? 'bg-[var(--app-primary)] text-white shadow-xs'
              : 'text-[var(--app-text-muted)] hover:text-[var(--app-text)] bg-transparent'
          }`}
        >
          Completed / Canceled
        </button>
      </div>

      {/* Job Cards Grid */}
      {filteredJobs.length === 0 ? (
        <div className="py-16 text-center rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-8">
          <div className="w-12 h-12 rounded-2xl bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] flex items-center justify-center mx-auto mb-3">
            <span className="material-symbols-outlined text-[24px]">work_outline</span>
          </div>
          <h3 className="text-sm font-bold text-[var(--app-text)]">No Jobs in this Category</h3>
          <p className="text-xs text-[var(--app-text-muted)] max-w-sm mx-auto mt-1">
            {activeTab === 'active'
              ? 'Post your first escrow-backed property or vehicle request to begin receiving offers.'
              : 'Jobs with contracted or completed escrows will appear here.'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {filteredJobs.map((job) => (
            <div
              key={job.id}
              onClick={() => openProposalInbox(job.id)}
              className="group p-5 sm:p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] hover:border-[var(--app-primary)]/60 shadow-xs hover:shadow-md transition-all duration-200 cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-5"
            >
              <div className="space-y-2 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="px-2.5 py-0.5 rounded text-[11px] font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                    {job.category_name}
                  </span>
                  <span className="text-xs text-[var(--app-text-muted)] flex items-center gap-1 font-medium">
                    <span className="material-symbols-outlined text-[14px]">location_on</span>
                    {job.location}
                  </span>
                  <span className="text-xs text-[var(--app-text-muted)]">• Posted {job.created_at}</span>
                </div>

                <h3 className="text-base font-bold text-[var(--app-text)] group-hover:text-[var(--app-primary)] transition-colors">
                  {job.title}
                </h3>

                <p className="text-xs text-[var(--app-text-muted)] line-clamp-1 max-w-2xl leading-relaxed">
                  {job.description}
                </p>

                <div className="flex items-center gap-2 pt-1 font-financial-numeric">
                  <span className="text-xs text-[var(--app-text-muted)] font-medium">Budget:</span>
                  <span className="text-xs font-bold text-[var(--app-text)]">
                    {job.budget_min_etb.toLocaleString()} – {job.budget_max_etb.toLocaleString()} ETB
                  </span>
                </div>
              </div>

              {/* Status & Action Badge */}
              <div className="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-3 shrink-0 pt-3 md:pt-0 border-t md:border-t-0 border-[var(--app-border)]">
                {/* High Contrast Proposal Count Pill */}
                <div className="px-3 py-1 rounded-full bg-[var(--app-primary)] text-white text-xs font-bold flex items-center gap-1.5 shadow-xs">
                  <span className="material-symbols-outlined text-[15px]">mail</span>
                  <span>{job.proposal_count} Proposals Received</span>
                </div>

                <div className="flex items-center gap-2">
                  {/* Subtle Clone & Re-post for Registered Buyers on Completed Jobs */}
                  {!isGuestMode && job.status === 'completed' && (
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        cloneJob(job.id);
                      }}
                      className="px-2.5 py-1 rounded-lg text-xs font-semibold text-[var(--app-text-muted)] hover:text-[var(--app-primary)] hover:bg-[var(--app-card-subtle)] transition-colors cursor-pointer border-0 flex items-center gap-1"
                      title="Clone and post this request again"
                    >
                      <span className="material-symbols-outlined text-[15px]">content_copy</span>
                      <span>Clone &amp; Re-post</span>
                    </button>
                  )}

                  <span className="text-xs font-semibold text-[var(--app-text-muted)] group-hover:text-[var(--app-primary)] flex items-center gap-0.5">
                    <span>Review Inbox</span>
                    <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
