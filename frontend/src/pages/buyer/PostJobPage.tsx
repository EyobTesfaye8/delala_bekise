import React, { useState } from 'react';
import { useBuyer } from '../../context/useBuyer';

export const PostJobPage: React.FC = () => {
  const { createJob, isGuestMode, jobs } = useBuyer();

  const [step, setStep] = useState<1 | 2 | 3>(1);

  // Form State
  const [level1Category, setLevel1Category] = useState<'Real Estate' | 'Vehicles' | 'Labor & Services'>('Real Estate');
  const [subCategory, setSubCategory] = useState<string>('Residential Rental');

  // Dynamic Specs
  // Real estate specs
  const [bedrooms, setBedrooms] = useState<string>('2');
  const [bathrooms, setBathrooms] = useState<string>('2');
  const [furnished, setFurnished] = useState<boolean>(true);
  const [propertyType, setPropertyType] = useState<string>('Apartment');

  // Vehicle specs
  const [make, setMake] = useState<string>('Toyota');
  const [model, setModel] = useState<string>('Vitz');
  const [minYear, setMinYear] = useState<number>(2019);
  const [transmission, setTransmission] = useState<string>('Automatic');

  // Labor specs
  const [trade, setTrade] = useState<string>('Electrician');
  const [experienceYears, setExperienceYears] = useState<number>(3);

  // Core Details & Budget
  const [title, setTitle] = useState<string>('');
  const [description, setDescription] = useState<string>('');
  const [subcity, setSubcity] = useState<string>('Bole');
  const [minBudget, setMinBudget] = useState<number>(35000);
  const [maxBudget, setMaxBudget] = useState<number>(55000);

  const activeJobsCount = jobs.filter((j) => j.status === 'open' || j.status === 'negotiating').length;
  const isGuestLimitReached = isGuestMode && activeJobsCount >= 2;

  // Level 1 -> Subcategory mapping
  const subCategoryOptions: Record<string, string[]> = {
    'Real Estate': ['Residential Rental', 'Residential Sale', 'Commercial Lease'],
    'Vehicles': ['Passenger Cars', 'Commercial Vehicles', 'Motorcycles'],
    'Labor & Services': ['Skilled Trade', 'Property Maintenance', 'Legal Documentation'],
  };

  const handleSelectLevel1 = (cat: 'Real Estate' | 'Vehicles' | 'Labor & Services') => {
    setLevel1Category(cat);
    setSubCategory(subCategoryOptions[cat][0]);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Prepare attributes according to category
    let attributes: Record<string, any> = {};
    if (level1Category === 'Real Estate') {
      attributes = {
        bedrooms: parseInt(bedrooms),
        bathrooms: parseInt(bathrooms),
        furnished,
        property_type: propertyType,
      };
    } else if (level1Category === 'Vehicles') {
      attributes = {
        make,
        model,
        min_year: minYear,
        transmission,
      };
    } else {
      attributes = {
        trade,
        experience_years: experienceYears,
      };
    }

    createJob({
      title: title || `${subCategory} in Addis Ababa (${subcity})`,
      category_id: level1Category === 'Real Estate' ? 1 : level1Category === 'Vehicles' ? 5 : 8,
      category_name: subCategory,
      category_slug: subCategory.toLowerCase().replace(/\s+/g, '-'),
      location: `${subcity}, Addis Ababa`,
      location_region: 'Addis Ababa',
      location_subcity: subcity,
      budget_min_etb: minBudget,
      budget_max_etb: maxBudget,
      status: 'open',
      attributes,
      description,
    });
  };

  return (
    <div className="max-w-xl mx-auto py-4 sm:py-8 space-y-8">
      {/* Step Indicator Header */}
      <div className="text-center space-y-2">
        <span className="px-3 py-1 rounded-full bg-[var(--app-primary-light)] text-[var(--app-primary)] text-xs font-medium">
          Step {step} of 3, escrow requirement
        </span>
        <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-[var(--app-text)]">
          {step === 1 && 'Select requirement category'}
          {step === 2 && `Specify ${subCategory} details`}
          {step === 3 && 'Location and budget target'}
        </h1>
        <p className="text-xs text-[var(--app-text-muted)] max-w-sm mx-auto">
          {step === 1 && 'Choose the type of asset or service you are acquiring through escrow.'}
          {step === 2 && 'Fill in key attributes so verified Delalas can submit precise matching proposals.'}
          {step === 3 && 'Set your target budget in ETB and neighborhood preference in Addis Ababa.'}
        </p>
      </div>

      {/* Guest Limit Warning Banner */}
      {isGuestLimitReached && (
        <div className="p-4 rounded-2xl bg-[var(--app-accent-light)] border border-[var(--app-accent)]/30 text-[var(--app-accent)] text-xs space-y-2">
          <div className="flex items-center gap-2 font-bold">
            <span className="material-symbols-outlined text-[18px]">warning</span>
            <span>Guest Concurrency Limit (2/2 Active Jobs)</span>
          </div>
          <p className="leading-relaxed">
            Guest sessions are limited to 2 simultaneous open jobs to prevent platform spam. Please complete the 1-step JIT Upgrade to post unlimited requests.
          </p>
        </div>
      )}

      {/* Wizard Form Card */}
      <div className="rounded-2xl bg-[var(--app-card)] border border-[var(--app-border)] p-6 sm:p-8 shadow-xs">
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* ========================================== */}
          {/* STEP 1: 2-Level Category Tree */}
          {/* ========================================== */}
          {step === 1 && (
            <div className="space-y-5 animate-in fade-in duration-200">
              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-2">
                  1. Primary Market Domain
                </label>
                <div className="grid grid-cols-3 gap-2.5">
                  {(['Real Estate', 'Vehicles', 'Labor & Services'] as const).map((cat) => (
                    <button
                      key={cat}
                      type="button"
                      onClick={() => handleSelectLevel1(cat)}
                      className={`p-3.5 rounded-xl border text-center transition-all cursor-pointer ${
                        level1Category === cat
                          ? 'border-[var(--app-primary)] bg-[var(--app-primary-light)] text-[var(--app-primary)]'
                          : 'border-[var(--app-border)] bg-[var(--app-card-subtle)] text-[var(--app-text-muted)] hover:border-[var(--app-primary)]/40'
                      }`}
                    >
                      <span className="material-symbols-outlined text-[24px] block mb-1">
                        {cat === 'Real Estate' ? 'apartment' : cat === 'Vehicles' ? 'directions_car' : 'handyman'}
                      </span>
                      <span className="text-xs font-bold block">{cat}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-2">
                  2. Specific Category
                </label>
                <div className="grid grid-cols-1 gap-2">
                  {subCategoryOptions[level1Category].map((sub) => (
                    <button
                      key={sub}
                      type="button"
                      onClick={() => setSubCategory(sub)}
                      className={`p-3 rounded-xl border text-left transition-all cursor-pointer flex items-center justify-between ${
                        subCategory === sub
                          ? 'border-[var(--app-primary)] bg-[var(--app-primary-light)]'
                          : 'border-[var(--app-border)] bg-[var(--app-card-subtle)] hover:border-[var(--app-primary)]/40'
                      }`}
                    >
                      <span className="text-xs font-bold text-[var(--app-text)]">{sub}</span>
                      {subCategory === sub ? (
                        <span className="material-symbols-outlined text-[18px] text-[var(--app-primary)]">
                          check_circle
                        </span>
                      ) : (
                        <span className="material-symbols-outlined text-[18px] text-[var(--app-text-muted)]">
                          radio_button_unchecked
                        </span>
                      )}
                    </button>
                  ))}
                </div>
              </div>

              <div className="pt-4 flex justify-end">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="px-5 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
                >
                  <span>Continue to Specifications</span>
                  <span className="material-symbols-outlined text-[16px]">arrow_forward</span>
                </button>
              </div>
            </div>
          )}

          {/* ========================================== */}
          {/* STEP 2: Dynamic JSON-Schema Attributes */}
          {/* ========================================== */}
          {step === 2 && (
            <div className="space-y-5 animate-in fade-in duration-200">
              {level1Category === 'Real Estate' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Bedrooms</label>
                      <select
                        value={bedrooms}
                        onChange={(e) => setBedrooms(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                      >
                        <option value="1">1 Bedroom / Studio</option>
                        <option value="2">2 Bedrooms</option>
                        <option value="3">3 Bedrooms</option>
                        <option value="4">4+ Bedrooms / Villa</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Bathrooms</label>
                      <select
                        value={bathrooms}
                        onChange={(e) => setBathrooms(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                      >
                        <option value="1">1 Bathroom</option>
                        <option value="2">2 Bathrooms</option>
                        <option value="3">3+ Bathrooms</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Property Type</label>
                    <select
                      value={propertyType}
                      onChange={(e) => setPropertyType(e.target.value)}
                      className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                    >
                      <option value="Apartment">Modern Apartment</option>
                      <option value="Villa">Standalone Villa / Compound</option>
                      <option value="Townhouse">Townhouse / G+1</option>
                      <option value="Office">Commercial Office Space</option>
                    </select>
                  </div>

                  <div className="p-3.5 rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] flex items-center justify-between">
                    <div>
                      <span className="text-xs font-bold text-[var(--app-text)] block">Fully Furnished</span>
                      <span className="text-[11px] text-[var(--app-text-muted)]">Includes furniture, appliances &amp; kitchenware</span>
                    </div>
                    <button
                      type="button"
                      onClick={() => setFurnished(!furnished)}
                      className={`w-12 h-6 rounded-full transition-colors relative cursor-pointer border-0 ${
                        furnished ? 'bg-[var(--app-primary)]' : 'bg-[var(--app-border)]'
                      }`}
                    >
                      <span
                        className={`w-5 h-5 rounded-full bg-white absolute top-0.5 transition-transform ${
                          furnished ? 'left-6.5' : 'left-0.5'
                        }`}
                      ></span>
                    </button>
                  </div>
                </div>
              )}

              {level1Category === 'Vehicles' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Make</label>
                      <input
                        type="text"
                        value={make}
                        onChange={(e) => setMake(e.target.value)}
                        placeholder="e.g. Toyota"
                        className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Model</label>
                      <input
                        type="text"
                        value={model}
                        onChange={(e) => setModel(e.target.value)}
                        placeholder="e.g. Vitz / Corolla"
                        className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Minimum Model Year</label>
                      <input
                        type="number"
                        value={minYear}
                        onChange={(e) => setMinYear(parseInt(e.target.value) || 2018)}
                        className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] font-financial-numeric"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Transmission</label>
                      <select
                        value={transmission}
                        onChange={(e) => setTransmission(e.target.value)}
                        className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                      >
                        <option value="Automatic">Automatic Transmission</option>
                        <option value="Manual">Manual Transmission</option>
                      </select>
                    </div>
                  </div>
                </div>
              )}

              {level1Category === 'Labor & Services' && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Specialization</label>
                    <input
                      type="text"
                      value={trade}
                      onChange={(e) => setTrade(e.target.value)}
                      placeholder="e.g. Certified Electrician / Plumber"
                      className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Minimum Years of Experience</label>
                    <input
                      type="number"
                      value={experienceYears}
                      onChange={(e) => setExperienceYears(parseInt(e.target.value) || 1)}
                      className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] font-financial-numeric"
                    />
                  </div>
                </div>
              )}

              <div className="pt-4 flex items-center justify-between border-t border-[var(--app-border)]">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="px-4 py-2 text-xs font-semibold text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
                >
                  ← Back to Categories
                </button>
                <button
                  type="button"
                  onClick={() => setStep(3)}
                  className="px-5 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5"
                >
                  <span>Continue to Budget</span>
                  <span className="material-symbols-outlined text-[16px]">arrow_forward</span>
                </button>
              </div>
            </div>
          )}

          {/* ========================================== */}
          {/* STEP 3: Core Details & Budget */}
          {/* ========================================== */}
          {step === 3 && (
            <div className="space-y-5 animate-in fade-in duration-200">
              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Job Headline / Title</label>
                <input
                  type="text"
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder={`e.g. Clean 2BR furnished apartment near Bole Atlas`}
                  className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Detailed Description</label>
                <textarea
                  required
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Detail your requirements (e.g. standby generator, high-floor, security guards, preferred inspection dates)..."
                  className="w-full px-3.5 py-2.5 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] transition-colors resize-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Region</label>
                  <input
                    type="text"
                    disabled
                    value="Addis Ababa"
                    className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] text-[var(--app-text-muted)] cursor-not-allowed opacity-80"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">Subcity</label>
                  <select
                    value={subcity}
                    onChange={(e) => setSubcity(e.target.value)}
                    className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)]"
                  >
                    <option value="Bole">Bole (Atlas, Rwanda, Brass)</option>
                    <option value="Kirkos">Kirkos (Kazanchis, Meskel Sq)</option>
                    <option value="Yeka">Yeka (CMC, Meganagna)</option>
                    <option value="Lideta">Lideta (Balcha, Mexico)</option>
                    <option value="Nifas Silk">Nifas Silk-Lafto (Bisrate Gabriel, Sarbet)</option>
                  </select>
                </div>
              </div>

              {/* Budget Range */}
              <div>
                <label className="block text-xs font-semibold text-[var(--app-text)] mb-1">
                  Estimated Budget Range (ETB)
                </label>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <span className="text-xs font-medium text-[var(--app-text-muted)] block mb-1">Minimum target</span>
                    <input
                      type="number"
                      value={minBudget}
                      onChange={(e) => setMinBudget(parseInt(e.target.value) || 0)}
                      className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] font-financial-numeric"
                    />
                  </div>
                  <div>
                    <span className="text-xs font-medium text-[var(--app-text-muted)] block mb-1">Maximum ceiling</span>
                    <input
                      type="number"
                      value={maxBudget}
                      onChange={(e) => setMaxBudget(parseInt(e.target.value) || 0)}
                      className="w-full px-3 py-2 text-xs rounded-xl bg-[var(--app-card-subtle)] border border-[var(--app-border)] focus:border-[var(--app-primary)] focus:outline-hidden text-[var(--app-text)] font-financial-numeric"
                    />
                  </div>
                </div>
              </div>

              <div className="pt-4 flex items-center justify-between border-t border-[var(--app-border)]">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="px-4 py-2 text-xs font-semibold text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors cursor-pointer border-0 bg-transparent"
                >
                  ← Back
                </button>
                <button
                  type="submit"
                  disabled={isGuestLimitReached}
                  className="px-6 py-2.5 rounded-xl bg-[var(--app-primary)] hover:bg-[var(--app-primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer border-0 flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span className="material-symbols-outlined text-[16px]">send</span>
                  <span>Publish Escrow Request</span>
                </button>
              </div>
            </div>
          )}
        </form>
      </div>
    </div>
  );
};
