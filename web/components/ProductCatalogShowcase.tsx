'use client';

import React, { useState } from 'react';
import { ArrowRight, CheckCircle2, Sparkles, Zap, ShieldCheck } from 'lucide-react';
import type { Product, Plan } from '../lib/api';

interface ProductCatalogShowcaseProps {
  products: Product[];
  categoryTitle?: string;
  dashboardUrl?: string;
}

export default function ProductCatalogShowcase({
  products,
  categoryTitle = 'Available Plans',
  dashboardUrl = 'http://localhost:8000',
}: ProductCatalogShowcaseProps) {
  const [currency, setCurrency] = useState<'USD' | 'LKR'>('USD');
  const [selectedBilling, setSelectedBilling] = useState<string>('monthly');

  // Extract all plans across products in this category
  const allPlans = products.flatMap((p) =>
    (p.plans || []).map((plan) => ({
      ...plan,
      productName: p.name,
      productDescription: p.description,
      productBadge: p.badge,
    }))
  );

  return (
    <div className="space-y-12 font-sans w-full max-w-7xl mx-auto">
      {/* Dynamic Filter Controls */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-white/[0.03] border border-white/10 backdrop-blur-md">
        <div>
          <h3 className="text-lg font-bold text-white tracking-tight">{categoryTitle}</h3>
          <p className="text-xs text-text-muted">Dynamic infrastructure tiers provisioned instantly upon order verification.</p>
        </div>

        <div className="flex items-center gap-3">
          {/* Currency Toggle */}
          <div className="bg-[#0b0c1f] border border-white/10 p-1 rounded-full inline-flex items-center shadow-inner">
            <button
              onClick={() => setCurrency('USD')}
              className={`px-3 py-1 rounded-full text-xs font-mono font-bold transition-all ${
                currency === 'USD' ? 'bg-purple-600 text-white shadow' : 'text-text-muted hover:text-white'
              }`}
            >
              USD ($)
            </button>
            <button
              onClick={() => setCurrency('LKR')}
              className={`px-3 py-1 rounded-full text-xs font-mono font-bold transition-all ${
                currency === 'LKR' ? 'bg-purple-600 text-white shadow' : 'text-text-muted hover:text-white'
              }`}
            >
              LKR (Rs)
            </button>
          </div>
        </div>
      </div>

      {/* Plans Grid */}
      {allPlans.length === 0 ? (
        <div className="text-center py-16 px-4 rounded-3xl border border-white/10 bg-white/[0.02]">
          <p className="text-sm text-text-muted">No plans currently published in this category. Check back shortly!</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {allPlans.map((plan) => {
            const isPopular = plan.isPopular || plan.badge === 'Most Popular' || plan.productBadge === 'High Performance';
            const priceDisplay =
              currency === 'USD'
                ? `$${(plan.price || 0).toFixed(2)}`
                : `Rs ${(plan.price ? plan.price * 320 : 0).toLocaleString()}`;

            const specs = plan.specsJson && typeof plan.specsJson === 'object' ? plan.specsJson : {};

            return (
              <div
                key={plan.id}
                className={`relative rounded-3xl p-8 flex flex-col justify-between transition-all duration-300 backdrop-blur-xl ${
                  isPopular
                    ? 'bg-gradient-to-b from-[#181236]/90 to-[#0e0c24]/90 border-2 border-purple-500/60 shadow-glow-violet scale-[1.02]'
                    : 'bg-[#0f1026]/70 border border-white/10 hover:border-purple-500/40 hover:bg-[#121430]/90 shadow-xl'
                }`}
              >
                {/* Popular / Custom Badge */}
                {plan.badge && (
                  <div className="absolute -top-3.5 right-6 px-3.5 py-1 rounded-full text-[10px] font-mono font-black tracking-widest uppercase bg-gradient-to-r from-purple-500 to-indigo-500 text-white shadow-md">
                    {plan.badge}
                  </div>
                )}

                <div className="space-y-6">
                  {/* Plan Header */}
                  <div>
                    <span className="text-[11px] font-mono font-semibold uppercase text-purple-400 tracking-wider">
                      {plan.productName}
                    </span>
                    <h4 className="text-xl font-black text-white mt-1 tracking-tight">{plan.name}</h4>
                    {plan.productDescription && (
                      <p className="text-xs text-text-muted mt-2 line-clamp-2 leading-relaxed">
                        {plan.productDescription}
                      </p>
                    )}
                  </div>

                  {/* Pricing Display */}
                  <div className="pt-2 pb-4 border-b border-white/10 flex items-baseline gap-1.5">
                    <span className="text-3xl sm:text-4xl font-black text-white tracking-tight">{priceDisplay}</span>
                    <span className="text-xs font-mono text-text-muted">/ month</span>
                  </div>

                  {/* Specifications & Features */}
                  <div className="space-y-3 pt-2">
                    <div className="text-[11px] font-mono uppercase tracking-wider text-neutral-400 font-bold">
                      Included Specifications
                    </div>
                    <ul className="space-y-2.5 text-xs text-neutral-300">
                      {Object.entries(specs).map(([key, val]) => (
                        <li key={key} className="flex items-start gap-2.5">
                          <CheckCircle2 className="h-4 w-4 text-purple-400 shrink-0 mt-0.5" />
                          <span>
                            <strong className="text-white capitalize">{key.replace(/_/g, ' ')}:</strong>{' '}
                            {String(val)}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>

                {/* Checkout Action Button */}
                <div className="pt-8 mt-6 border-t border-white/10">
                  <a
                    href={`${dashboardUrl}/portal/checkout?plan=${encodeURIComponent(plan.id)}`}
                    className={`w-full py-3.5 px-6 rounded-2xl font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 ${
                      isPopular
                        ? 'bg-gradient-to-r from-purple-600 via-indigo-600 to-purple-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-glow-violet'
                        : 'bg-white/10 hover:bg-white/20 text-white border border-white/10'
                    }`}
                  >
                    <span>Configure & Deploy</span>
                    <ArrowRight className="h-3.5 w-3.5" />
                  </a>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

