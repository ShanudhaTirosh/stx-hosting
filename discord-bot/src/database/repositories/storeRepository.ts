import { getSupabase } from '../supabase.js';
import { logger } from '../../utils/logger.js';

export interface ProductResult {
  id: string;
  name: string;
  slug: string;
  category: string;
  description: string | null;
  features: string[];
  variants: {
    id: string;
    name: string;
    vcpu: number | null;
    ram_mb: number | null;
    storage_gb: number | null;
    pricing: {
      interval: string;
      price_cents: number;
      currency: string;
    }[];
  }[];
}

export interface OrderLookupResult {
  uuid: string;
  status: string;
  total_cents: number;
  currency: string;
  created_at: string;
  customer_email: string;
  items: {
    product_name: string;
    quantity: number;
    unit_price_cents: number;
    hostname?: string;
  }[];
}

export interface MonitorResult {
  id: string;
  name: string;
  public_label: string;
  group_name: string;
  current_status: 'operational' | 'degraded' | 'down';
  uptime_percentage: number;
  last_latency_ms: number;
}

export interface IncidentResult {
  id: string;
  title: string;
  status: string;
  severity: string;
  created_at: string;
}

export async function searchProducts(query?: string, category?: string): Promise<ProductResult[]> {
  const supabase = getSupabase();
  let dbQuery = supabase
    .from('products')
    .select(`
      id,
      name,
      slug,
      category,
      description,
      features,
      product_variants (
        id,
        name,
        vcpu,
        ram_mb,
        storage_gb,
        pricing_tiers (
          interval,
          price_cents,
          currency
        )
      )
    `)
    .eq('is_active', true);

  if (category) {
    dbQuery = dbQuery.eq('category', category);
  }

  if (query) {
    dbQuery = dbQuery.or(`name.ilike.%${query}%,slug.ilike.%${query}%,description.ilike.%${query}%`);
  }

  const { data, error } = await dbQuery.limit(10);

  if (error) {
    logger.error({ error, query, category }, 'Error searching products');
    return [];
  }

  return (data || []).map((p: any) => ({
    id: p.id,
    name: p.name,
    slug: p.slug,
    category: p.category,
    description: p.description,
    features: Array.isArray(p.features) ? p.features : [],
    variants: (p.product_variants || []).map((v: any) => ({
      id: v.id,
      name: v.name,
      vcpu: v.vcpu,
      ram_mb: v.ram_mb,
      storage_gb: v.storage_gb,
      pricing: (v.pricing_tiers || []).map((pt: any) => ({
        interval: pt.interval || 'monthly',
        price_cents: pt.price_cents || 0,
        currency: pt.currency || 'USD',
      })),
    })),
  }));
}

export async function getAllCategories(): Promise<{ category: string; count: number }[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('products')
    .select('category')
    .eq('is_active', true);

  if (error) {
    logger.error({ error }, 'Error fetching product categories');
    return [];
  }

  const counts = new Map<string, number>();
  for (const item of data || []) {
    counts.set(item.category, (counts.get(item.category) || 0) + 1);
  }

  return Array.from(counts.entries()).map(([category, count]) => ({ category, count }));
}

/**
 * Strict Order Verification:
 * Looks up an order by UUID or ID and requires an exact case-insensitive match on the customer email.
 * If there is any mismatch, returns null to prevent data leakage.
 */
export async function lookupOrder(orderUuidOrId: string, email: string): Promise<OrderLookupResult | null> {
  const supabase = getSupabase();
  const normalizedEmail = email.trim().toLowerCase();

  // Try matching by uuid first, or by id if UUID format
  const { data: order, error } = await supabase
    .from('orders')
    .select(`
      uuid,
      status,
      total_cents,
      currency,
      created_at,
      profiles:user_id (
        email
      ),
      order_items (
        quantity,
        unit_price_cents,
        hostname,
        product_variants (
          name,
          products (
            name
          )
        )
      )
    `)
    .or(`uuid.eq.${orderUuidOrId},id.eq.${orderUuidOrId}`)
    .maybeSingle();

  if (error || !order) {
    return null;
  }

  const profile = order.profiles as any;
  const ownerEmail = (profile?.email || '').trim().toLowerCase();

  if (!ownerEmail || ownerEmail !== normalizedEmail) {
    return null; // Return null on email mismatch to avoid disclosure
  }

  const items = (order.order_items || []).map((item: any) => {
    const productName =
      item.product_variants?.products?.name
        ? `${item.product_variants.products.name} (${item.product_variants.name})`
        : item.product_variants?.name || 'Product';
    return {
      product_name: productName,
      quantity: item.quantity,
      unit_price_cents: item.unit_price_cents,
      hostname: item.hostname || undefined,
    };
  });

  return {
    uuid: order.uuid,
    status: order.status,
    total_cents: order.total_cents,
    currency: order.currency,
    created_at: order.created_at,
    customer_email: ownerEmail,
    items,
  };
}

export async function getPlatformMonitors(): Promise<MonitorResult[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('monitors')
    .select('id, name, public_label, group_name, current_status, uptime_percentage, last_latency_ms')
    .eq('is_public', true)
    .eq('is_active', true)
    .order('display_order', { ascending: true });

  if (error) {
    logger.error({ error }, 'Error fetching platform monitors');
    return [];
  }

  return (data as MonitorResult[]) || [];
}

export async function getActiveIncidents(): Promise<IncidentResult[]> {
  const supabase = getSupabase();
  const { data, error } = await supabase
    .from('system_incidents')
    .select('id, title, status, severity, created_at')
    .neq('status', 'resolved')
    .order('created_at', { ascending: false });

  if (error) {
    logger.error({ error }, 'Error fetching active incidents');
    return [];
  }

  return (data as IncidentResult[]) || [];
}
