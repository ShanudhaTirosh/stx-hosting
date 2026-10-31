import React from 'react';
import Navbar from '../../components/Navbar';
import Footer from '../../components/Footer';
import GradientMeshOrb from '../../components/GradientMeshOrb';
import ProductCatalogShowcase from '../../components/ProductCatalogShowcase';
import DiscordBanner from '../../components/DiscordBanner';
import { fetchProducts } from '../../lib/api';
import { Globe, Lock, Zap, RefreshCw, Server, Sparkles } from 'lucide-react';

export const metadata = {
  title: 'Managed Web & WordPress Hosting | High-Performance Cloud | ShanuTechX',
  description: 'Enterprise managed web hosting for WordPress, CMS platforms, and custom PHP applications with free Let\'s Encrypt SSL, NVMe SSDs, and global CDN.',
};

export const revalidate = 0; // Dynamic catalog fetching

export default async function WebHostingPage() {
  const products = await fetchProducts('web_hosting');
  const dashboardUrl = process.env.NEXT_PUBLIC_DASHBOARD_URL || 'http://localhost:8000';

  return (
    <div className="min-h-screen bg-transparent text-white flex flex-col selection:bg-purple-500 selection:text-white relative overflow-hidden">
      {/* Background Grid Pattern Overlay */}
      <div className="fixed inset-0 bg-[radial-gradient(#1e1b4b_1px,transparent_1px)] [background-size:32px_32px] opacity-20 pointer-events-none z-0" />

      <Navbar />

      <main className="flex-1 max-w-7xl mx-auto px-6 pt-32 pb-24 space-y-20 relative z-10 w-full">
        {/* Hero Section */}
        <div className="relative text-center space-y-6 pt-6 pb-2">
          <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none select-none z-0">
            <GradientMeshOrb size={520} opacity={0.85} glowColor="rgba(147, 51, 234, 0.75)" />
          </div>

          <div className="relative z-10 space-y-4 max-w-3xl mx-auto">
            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-300 text-[11px] font-mono font-bold tracking-widest uppercase shadow-glow-violet">
              <Globe className="h-3.5 w-3.5" />
              MANAGED CLOUD WEB PLATFORM
            </div>

            <h1 className="text-4xl md:text-6xl font-black tracking-tight text-white leading-tight">
              Fast, Reliable <span className="text-purple-400">Web & WordPress</span> Hosting
            </h1>

            <p className="text-base md:text-lg text-text-muted leading-relaxed max-w-2xl mx-auto font-sans">
              Optimized for speed and conversion with automated SSL certificates, built-in Redis object caching, and daily off-site cloud backups.
            </p>
          </div>
        </div>

        {/* Feature Badges Grid */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-5xl mx-auto">
          <div className="p-4 rounded-2xl bg-white/[0.03] border border-white/10 text-center space-y-1.5 backdrop-blur-md">
            <Lock className="h-5 w-5 text-emerald-400 mx-auto" />
            <div className="text-sm font-bold text-white">Free Wildcard SSL</div>
            <div className="text-[11px] text-text-muted">Automated Let's Encrypt certificates</div>
          </div>
          <div className="p-4 rounded-2xl bg-white/[0.03] border border-white/10 text-center space-y-1.5 backdrop-blur-md">
            <Zap className="h-5 w-5 text-yellow-400 mx-auto" />
            <div className="text-sm font-bold text-white">Redis Object Cache</div>
            <div className="text-[11px] text-text-muted">Sub-millisecond query responses</div>
          </div>
          <div className="p-4 rounded-2xl bg-white/[0.03] border border-white/10 text-center space-y-1.5 backdrop-blur-md">
            <RefreshCw className="h-5 w-5 text-purple-400 mx-auto" />
            <div className="text-sm font-bold text-white">Daily Snapshots</div>
            <div className="text-[11px] text-text-muted">Automated S3 disaster recovery</div>
          </div>
          <div className="p-4 rounded-2xl bg-white/[0.03] border border-white/10 text-center space-y-1.5 backdrop-blur-md">
            <Sparkles className="h-5 w-5 text-indigo-400 mx-auto" />
            <div className="text-sm font-bold text-white">1-Click Apps</div>
            <div className="text-[11px] text-text-muted">WordPress, Ghost, Directus & PHP</div>
          </div>
        </div>

        {/* Dynamic Catalog Showcase */}
        <ProductCatalogShowcase
          products={products}
          categoryTitle="Web & WordPress Hosting Configurations"
          dashboardUrl={dashboardUrl}
        />

        <DiscordBanner className="py-4" />
      </main>

      <Footer />
    </div>
  );
}

