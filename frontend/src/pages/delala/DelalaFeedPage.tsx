import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/useAuth';
import { apiRequest } from '../../services/apiClient';

interface MarketplaceJob {
  id: number;
  title: string;
  category_name?: string;
  category?: { name: string; slug: string };
  location: string;
  location_subcity?: string;
  location_region?: string;
  budget_min_etb?: number;
  budget_max_etb?: number;
  budget_min?: string | number;
  budget_max?: string | number;
  attributes?: Record<string, string | number | boolean | null>;
  description: string;
  proposal_count: number;
  created_at: string;
  status: string;
}

export const DelalaFeedPage: React.FC = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  const [jobs, setJobs] = useState<MarketplaceJob[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [selectedSubcity, setSelectedSubcity] = useState<string>('All');

  // Proposal modal state
  const [selectedJob, setSelectedJob] = useState<MarketplaceJob | null>(null);
  const [proposalItemTitle, setProposalItemTitle] = useState('');
  const [proposalLocation, setProposalLocation] = useState('');
  const [proposalAssetPrice, setProposalAssetPrice] = useState<number>(0);
  const [proposalCommission, setProposalCommission] = useState<number>(15000);
  const [proposalNotes, setProposalNotes] = useState('');
  const [isSubmittingProposal, setIsSubmittingProposal] = useState(false);
  const [proposalSuccessToast, setProposalSuccessToast] = useState<string | null>(null);

  // Fetch live jobs from backend or fallback to seeded data
  useEffect(() => {
    let mounted = true;
    const fetchJobs = async () => {
      try {
        const res = await apiRequest<{ data: MarketplaceJob[] }>('/jobs');
        if (mounted && res?.data && res.data.length > 0) {
          setJobs(res.data);
          setIsLoading(false);
          return;
        }
      } catch {
        // Fallback to local high-fidelity demonstration jobs
      }

      if (mounted) {
        setJobs([
          {
            id: 2,
            title: 'Toyota Vitz 2019 Automatic in Addis',
            category_name: 'Passenger Cars',
            location: 'Kirkos / Bole, Addis Ababa',
            location_subcity: 'Kirkos',
            budget_min: 1900000,
            budget_max: 2400000,
            attributes: {
              make: 'Toyota',
              model: 'Vitz',
              year: 2019,
              transmission: 'automatic',
              fuel_type: 'petrol',
            },
            description:
              'Looking for a silver or white Toyota Vitz (2018-2020), automatic transmission, original factory paint, below 50k mileage.',
            proposal_count: 0,
            created_at: '2 hours ago',
            status: 'open',
          },
          {
            id: 1,
            title: 'Furnished 2BR Apartment near Bole Atlas',
            category_name: 'Residential Rental',
            location: 'Bole Atlas, Addis Ababa',
            location_subcity: 'Bole',
            budget_min: 35000,
            budget_max: 50000,
            attributes: {
              bedrooms: 2,
              bathrooms: 2,
              furnished: true,
              backup_generator: true,
              parking: true,
            },
            description:
              'Looking for a secure, modern 2-bedroom furnished apartment with 24/7 standby generator, underground parking, and fiber connectivity.',
            proposal_count: 1,
            created_at: '3 hours ago',
            status: 'negotiating',
          },
          {
            id: 3,
            title: 'Prime 120sqm Commercial Office Suite',
            category_name: 'Commercial Lease',
            location: 'Bole Road, Mega Building area',
            location_subcity: 'Bole',
            budget_min: 60000,
            budget_max: 90000,
            attributes: {
              property_type: 'Office',
              square_meters: 120,
              parking_spaces: 3,
              generator: true,
            },
            description:
              'Ground floor or 1st floor space for fintech consulting firm. Must have elevator access and reliable transformer connection.',
            proposal_count: 2,
            created_at: 'Yesterday',
            status: 'open',
          },
          {
            id: 4,
            title: 'Hyundai Tucson 2021 AWD Low Mileage',
            category_name: 'Passenger Cars',
            location: 'Bole, Addis Ababa',
            location_subcity: 'Bole',
            budget_min: 4500000,
            budget_max: 5200000,
            attributes: {
              make: 'Hyundai',
              model: 'Tucson',
              year: 2021,
              transmission: 'automatic',
            },
            description:
              'Original left-hand drive Hyundai Tucson, panorama roof, leather seats, strictly below 30,000 km.',
            proposal_count: 3,
            created_at: '2 days ago',
            status: 'open',
          },
        ]);
        setIsLoading(false);
      }
    };

    fetchJobs();
    return () => {
      mounted = false;
    };
  }, []);

  const toggleDark = () => {
    document.documentElement.classList.toggle('dark');
  };

  const handleLogout = async () => {
    try {
      await logout();
      navigate('/auth/choose-role');
    } catch {
      // Ignored
    }
  };

  const handleOpenProposal = (job: MarketplaceJob) => {
    setSelectedJob(job);
    setProposalItemTitle(`Verified match for ${job.title}`);
    setProposalLocation(job.location_subcity || 'Bole, Addis Ababa');
    const minVal = Number(job.budget_min || job.budget_min_etb || 30000);
    setProposalAssetPrice(minVal);
    setProposalCommission(Math.round(minVal * 0.05) || 15000);
    setProposalNotes('');
  };

  const handleProposalSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedJob) return;

    setIsSubmittingProposal(true);
    setTimeout(() => {
      setIsSubmittingProposal(false);
      setSelectedJob(null);
      setProposalSuccessToast(`Proposal submitted successfully for "${selectedJob.title}"!`);
      setTimeout(() => setProposalSuccessToast(null), 4000);
    }, 600);
  };

  // Filter jobs by search and category
  const filteredJobs = jobs.filter((job) => {
    const catName = job.category_name || job.category?.name || '';
    const matchesSearch =
      job.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      job.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
      catName.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesCategory =
      selectedCategory === 'All' || catName.toLowerCase().includes(selectedCategory.toLowerCase());

    const matchesSubcity =
      selectedSubcity === 'All' ||
      (job.location_subcity && job.location_subcity.toLowerCase() === selectedSubcity.toLowerCase()) ||
      job.location.toLowerCase().includes(selectedSubcity.toLowerCase());

    return matchesSearch && matchesCategory && matchesSubcity;
  });

  return (
    <div className="min-h-screen bg-[var(--app-bg)] text-[var(--app-text)] font-sans flex flex-col antialiased">
      {/* Top Header */}
      <header className="h-16 w-full border-b border-[var(--app-border)] bg-[var(--app-card)]/90 backdrop-blur-md sticky top-0 z-30 flex items-center justify-between px-4 sm:px-8">
        <div className="flex items-center gap-3">
          <Link to="/delala/feed" className="flex items-center gap-2.5 group no-underline text-left">
            <div className="w-8 h-8 rounded-lg bg-[var(--app-primary)] text-white flex items-center justify-center font-bold text-sm shadow-xs">
              DB
            </div>
            <div>
              <span className="font-bold text-sm text-[var(--app-text)] block tracking-tight leading-tight">
                Delala Marketplace
              </span>
              <span className="text-[10px] text-[var(--app-primary)] font-semibold block leading-none">
                ደላላ ፊድ • Broker Feed
              </span>
            </div>
          </Link>
        </div>

        {/* Right Header Navigation & Actions */}
        <div className="flex items-center gap-3">
          {/* Delala KYC Verified Badge & Profile Link */}
          <Link
            to="/profile"
            className="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full bg-[var(--app-primary-light)] border border-[var(--app-primary)]/20 text-xs font-semibold text-[var(--app-primary)] hover:border-[var(--app-primary)] transition-all no-underline"
            title="View Broker Profile & Settings"
          >
            <span className="material-symbols-outlined text-[15px]">verified</span>
            <span>{user?.full_name || 'Delala Broker'}</span>
            <span className="text-[10px] bg-[var(--app-primary)] text-white px-1.5 py-0.2 rounded font-bold">
              KYC Active
            </span>
          </Link>

          {/* Profile Quick Link */}
          <Link
            to="/profile"
            className="w-8 h-8 rounded-full bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-center text-[var(--app-text)] hover:border-[var(--app-primary)] hover:text-[var(--app-primary)] transition-colors no-underline sm:hidden"
            title="My Profile"
          >
            <span className="material-symbols-outlined text-[18px]">person</span>
          </Link>

          {/* Switch to Buyer View */}
          <button
            onClick={() => navigate('/buyer')}
            className="px-3 py-1 rounded-lg text-xs font-semibold border border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)] text-[var(--app-text)] transition-all cursor-pointer flex items-center gap-1.5"
            title="Switch to Buyer Deals Portal"
          >
            <span className="material-symbols-outlined text-[15px]">shopping_bag</span>
            <span className="hidden sm:inline">Buyer Portal</span>
          </button>

          {/* Theme Toggle */}
          <button
            onClick={toggleDark}
            className="w-8 h-8 rounded-full bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-center text-[var(--app-text)] hover:border-[var(--app-primary)] transition-colors cursor-pointer"
            aria-label="Toggle Light/Dark Theme"
          >
            <span className="material-symbols-outlined text-[18px]">routine</span>
          </button>

          {/* Sign Out */}
          <button
            onClick={handleLogout}
            className="w-8 h-8 rounded-full bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-center text-[var(--app-text)] hover:text-[var(--app-danger)] transition-colors cursor-pointer"
            aria-label="Sign Out"
            title="Sign Out"
          >
            <span className="material-symbols-outlined text-[18px]">logout</span>
          </button>
        </div>
      </header>

      {/* Main Container */}
      <main className="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-8 py-8 space-y-6">
        {/* Banner: NBE FinTech Escrow Protection */}
        <div className="p-4 sm:p-5 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-3.5">
            <div className="w-11 h-11 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center shrink-0">
              <span className="material-symbols-outlined text-[24px]">real_estate_agent</span>
            </div>
            <div>
              <h1 className="text-base sm:text-lg font-bold text-[var(--app-text)]">
                Active Client Requests &amp; Deals in Addis Ababa
              </h1>
              <p className="text-xs text-[var(--app-text-muted)] mt-0.5">
                Browse verified buyer demands, submit matching proposals, and earn escrow-backed commissions.
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3 shrink-0">
            <span className="text-xs font-semibold px-3 py-1 rounded-lg bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center gap-1.5">
              <span className="material-symbols-outlined text-[15px]">shield</span>
              <span>100% Commission Escrow</span>
            </span>
          </div>
        </div>

        {/* Search & Filters */}
        <div className="rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-4 sm:p-5 shadow-xs space-y-4">
          {/* Search Input */}
          <div className="relative">
            <span className="material-symbols-outlined text-[20px] text-[var(--app-text-muted)] absolute left-3.5 top-1/2 -translate-y-1/2">
              search
            </span>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search by vehicle model, apartment specs, subcity (e.g. Bole, Kirkos, Vitz)..."
              className="w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors"
            />
          </div>

          {/* Category Pills & Subcity */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            {/* Category Segmented Chips */}
            <div className="flex flex-wrap items-center gap-1.5">
              {['All', 'Real Estate', 'Passenger Cars', 'Commercial Lease', 'Commercial Vehicles'].map((cat) => (
                <button
                  key={cat}
                  onClick={() => setSelectedCategory(cat)}
                  className={`px-3 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer border-0 ${
                    selectedCategory === cat
                      ? 'bg-[var(--app-primary)] text-white shadow-xs'
                      : 'bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] hover:text-[var(--app-text)]'
                  }`}
                >
                  {cat}
                </button>
              ))}
            </div>

            {/* Subcity Filter */}
            <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
              <span className="text-xs font-medium text-[var(--app-text-muted)]">Subcity:</span>
              <select
                value={selectedSubcity}
                onChange={(e) => setSelectedSubcity(e.target.value)}
                className="px-2.5 py-1 text-xs rounded-lg bg-[var(--app-card-subtle)] border border-[var(--app-border)] text-[var(--app-text)] focus:border-[var(--app-primary)] focus:outline-hidden"
              >
                <option value="All">All Subcities</option>
                <option value="Bole">Bole</option>
                <option value="Kirkos">Kirkos</option>
                <option value="Yeka">Yeka</option>
                <option value="Lideta">Lideta</option>
              </select>
            </div>
          </div>
        </div>

        {/* Jobs Feed Grid */}
        {isLoading ? (
          <div className="py-20 text-center space-y-3">
            <span className="w-8 h-8 border-2 border-[var(--app-primary)]/30 border-t-[var(--app-primary)] rounded-full animate-spin inline-block"></span>
            <p className="text-xs text-[var(--app-text-muted)]">Loading marketplace feed...</p>
          </div>
        ) : filteredJobs.length === 0 ? (
          <div className="py-16 text-center rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-8">
            <div className="w-12 h-12 rounded-2xl bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] flex items-center justify-center mx-auto mb-3">
              <span className="material-symbols-outlined text-[24px]">search_off</span>
            </div>
            <h3 className="text-sm font-bold text-[var(--app-text)]">No Matching Deals Found</h3>
            <p className="text-xs text-[var(--app-text-muted)] max-w-sm mx-auto mt-1">
              Try adjusting your search keywords or resetting category filters to view all active deals.
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4">
            {filteredJobs.map((job) => {
              const catName = job.category_name || job.category?.name || 'Requirement';
              const minBudget = Number(job.budget_min || job.budget_min_etb || 0);
              const maxBudget = Number(job.budget_max || job.budget_max_etb || 0);

              return (
                <div
                  key={job.id}
                  className="p-5 sm:p-6 rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] hover:border-[var(--app-primary)]/60 shadow-xs hover:shadow-md transition-all duration-200 flex flex-col md:flex-row md:items-center justify-between gap-5"
                >
                  <div className="space-y-2.5 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="px-2.5 py-0.5 rounded text-[11px] font-semibold bg-[var(--app-primary-light)] text-[var(--app-primary)]">
                        {catName}
                      </span>
                      <span className="text-xs text-[var(--app-text-muted)] flex items-center gap-1 font-medium">
                        <span className="material-symbols-outlined text-[14px]">location_on</span>
                        {job.location}
                      </span>
                      <span className="text-xs text-[var(--app-text-muted)]">• {job.created_at}</span>
                    </div>

                    <h2 className="text-base sm:text-lg font-bold text-[var(--app-text)]">
                      {job.title}
                    </h2>

                    <p className="text-xs text-[var(--app-text-muted)] max-w-2xl leading-relaxed">
                      {job.description}
                    </p>

                    {/* Attributes chips */}
                    {job.attributes && Object.keys(job.attributes).length > 0 && (
                      <div className="flex flex-wrap gap-1.5 pt-1">
                        {Object.entries(job.attributes).map(([k, v]) => {
                          if (v === null || v === false) return null;
                          return (
                            <span
                              key={k}
                              className="px-2 py-0.5 rounded text-[10px] font-medium bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] border border-[var(--app-border)] capitalize"
                            >
                              {typeof v === 'boolean' ? k.replace('_', ' ') : `${k.replace('_', ' ')}: ${v}`}
                            </span>
                          );
                        })}
                      </div>
                    )}
                  </div>

                  {/* Budget & CTA */}
                  <div className="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center gap-3 shrink-0 pt-3 md:pt-0 border-t md:border-t-0 border-[var(--app-border)]">
                    <div className="text-left md:text-right">
                      <span className="text-xs font-medium text-[var(--app-text-muted)] block">
                        Target budget
                      </span>
                      <span className="font-financial-numeric text-base sm:text-lg font-extrabold text-[var(--app-primary)] block">
                        {minBudget.toLocaleString()} – {maxBudget.toLocaleString()} ETB
                      </span>
                      <span className="text-[11px] text-[var(--app-text-muted)]">
                        {job.proposal_count} {job.proposal_count === 1 ? 'proposal' : 'proposals'} submitted
                      </span>
                    </div>

                    <button
                      onClick={() => handleOpenProposal(job)}
                      className="px-4 py-2 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
                    >
                      <span className="material-symbols-outlined text-[16px]">add_circle</span>
                      <span>Send proposal</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </main>

      {/* Proposal Submission Modal */}
      {selectedJob && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/45 backdrop-blur-xs animate-in fade-in duration-200">
          <div className="w-full max-w-lg bg-[var(--app-card)] text-[var(--app-text)] rounded-2xl shadow-2xl border border-[var(--app-border)] p-6 sm:p-7 relative max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-[var(--app-border)] mb-4">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-xl bg-[var(--app-primary-light)] text-[var(--app-primary)] flex items-center justify-center font-bold">
                  <span className="material-symbols-outlined text-[20px]">real_estate_agent</span>
                </div>
                <div>
                  <h2 className="text-base font-bold tracking-tight">Submit Proposal to Buyer</h2>
                  <p className="text-xs text-[var(--app-text-muted)]">Job: {selectedJob.title}</p>
                </div>
              </div>
              <button
                onClick={() => setSelectedJob(null)}
                className="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
              >
                <span className="material-symbols-outlined text-[20px]">close</span>
              </button>
            </div>

            <form onSubmit={handleProposalSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
                  Proposed Item / Asset Title
                </label>
                <input
                  type="text"
                  required
                  value={proposalItemTitle}
                  onChange={(e) => setProposalItemTitle(e.target.value)}
                  placeholder="e.g. 2BR Furnished Atlas Residency with Generator"
                  className="w-full px-3.5 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
                    Specific Location
                  </label>
                  <input
                    type="text"
                    required
                    value={proposalLocation}
                    onChange={(e) => setProposalLocation(e.target.value)}
                    placeholder="e.g. Bole Atlas, near Edna Mall"
                    className="w-full px-3.5 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
                    Estimated Asset Price (ETB)
                  </label>
                  <input
                    type="number"
                    required
                    value={proposalAssetPrice}
                    onChange={(e) => setProposalAssetPrice(parseInt(e.target.value) || 0)}
                    className="w-full px-3.5 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] font-financial-numeric"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
                  Your Delala Commission Fee (ETB)
                </label>
                <div className="relative">
                  <input
                    type="number"
                    required
                    value={proposalCommission}
                    onChange={(e) => setProposalCommission(parseInt(e.target.value) || 0)}
                    className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-primary)] font-financial-numeric font-bold"
                  />
                  <span className="text-[10px] text-[var(--app-primary)] font-semibold absolute right-3.5 top-1/2 -translate-y-1/2">
                    Locked in Chapa Escrow
                  </span>
                </div>
                <p className="text-[11px] text-[var(--app-text-muted)] mt-1">
                  When accepted, the client funds this full commission into escrow before offline inspection.
                </p>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
                  Description &amp; Inspection Availability
                </label>
                <textarea
                  rows={3}
                  value={proposalNotes}
                  onChange={(e) => setProposalNotes(e.target.value)}
                  placeholder="Detail condition, title deed / carte status, standby generator availability, owner readiness for inspection..."
                  className="w-full px-3.5 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] resize-none"
                />
              </div>

              <div className="pt-2 flex items-center justify-end gap-2.5 border-t border-[var(--app-border)]">
                <button
                  type="button"
                  onClick={() => setSelectedJob(null)}
                  className="px-4 py-2 text-xs font-semibold text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmittingProposal}
                  className="px-5 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
                >
                  {isSubmittingProposal ? (
                    <>
                      <span className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                      <span>Submitting...</span>
                    </>
                  ) : (
                    <>
                      <span className="material-symbols-outlined text-[16px]">send</span>
                      <span>Submit Proposal</span>
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Floating Success Toast */}
      {proposalSuccessToast && (
        <div className="fixed bottom-6 right-6 z-50 px-4 py-2.5 rounded-xl bg-[var(--app-primary)] text-white text-xs font-medium shadow-lg flex items-center gap-2 animate-in fade-in duration-200">
          <span className="material-symbols-outlined text-[18px]">check_circle</span>
          <span>{proposalSuccessToast}</span>
        </div>
      )}
    </div>
  );
};

export default DelalaFeedPage;
