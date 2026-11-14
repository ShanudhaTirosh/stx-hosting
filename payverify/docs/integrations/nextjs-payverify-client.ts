/**
 * PayVerify TypeScript / Next.js SDK Client
 * Lightweight API client for custom e-commerce websites and mobile webapps.
 */

export interface PayVerifyConfig {
  baseUrl: string;
  apiKey: string;
}

export interface CreateOrderParams {
  invoiceNo: string;
  amountMinor: number;
  customerEmail?: string;
  customerName?: string;
  customerPhone?: string;
  uniqueAmount?: boolean;
}

export interface CreateOrderResponse {
  order_id: number;
  invoice_no: string;
  amount_minor: number;
  amount_formatted: string;
  payment_code: string;
  pay_to_bank: string;
  pay_to_account_name: string;
  pay_to_account_number: string;
  status: string;
  instructions: string;
}

export interface VerifyProofParams {
  invoiceNo: string;
  amountMinor: number;
  reference?: string;
  customerEmail?: string;
  customerName?: string;
  proofBase64?: string;
  proofMime?: string;
}

export interface VerifyProofResponse {
  verified: boolean;
  status: string;
  order_id: number;
  invoice_no: string;
  claim_id: number;
  amount_minor: number;
  amount_formatted: string;
  payment_code: string;
  verification_basis: string | null;
  status_reason: string | null;
}

export interface OrderStatusResponse {
  order_id: number;
  invoice_no: string;
  order_status: string;
  is_paid: boolean;
  expected_amount_minor: number;
  payment_code: string;
  claim: {
    id: number;
    status: string;
    verification_basis: string | null;
    verified_at: string | null;
    reference: string | null;
    amount_minor: number;
  } | null;
}

export class PayVerifyClient {
  private baseUrl: string;
  private apiKey: string;

  constructor(config: PayVerifyConfig) {
    this.baseUrl = config.baseUrl.replace(/\/$/, "");
    this.apiKey = config.apiKey;
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      "Content-Type": "application/json",
      "X-API-Key": this.apiKey,
      ...options.headers,
    };

    const res = await fetch(url, { ...options, headers });
    if (!res.ok) {
      const errText = await res.text();
      throw new Error(`PayVerify API error (${res.status}): ${errText}`);
    }
    return res.json() as Promise<T>;
  }

  /**
   * Register a new checkout order to get bank details & unique reference code.
   */
  async createOrder(params: CreateOrderParams): Promise<CreateOrderResponse> {
    return this.request<CreateOrderResponse>("/api/v1/external/orders", {
      method: "POST",
      body: JSON.stringify({
        invoice_no: params.invoiceNo,
        amount_minor: params.amountMinor,
        customer_email: params.customerEmail,
        customer_name: params.customerName,
        customer_phone: params.customerPhone,
        unique_amount: params.uniqueAmount ?? false,
      }),
    });
  }

  /**
   * Submit payment proof (slip image base64 or reference code) for automated verification.
   */
  async verifyPayment(params: VerifyProofParams): Promise<VerifyProofResponse> {
    return this.request<VerifyProofResponse>("/api/v1/external/verify", {
      method: "POST",
      body: JSON.stringify({
        invoice_no: params.invoiceNo,
        amount_minor: params.amountMinor,
        reference: params.reference,
        customer_email: params.customerEmail,
        customer_name: params.customerName,
        proof_base64: params.proofBase64,
        proof_mime: params.proofMime || "image/jpeg",
        auto_match: true,
      }),
    });
  }

  /**
   * Query real-time payment status of an order.
   */
  async getOrderStatus(invoiceOrId: string | number): Promise<OrderStatusResponse> {
    return this.request<OrderStatusResponse>(`/api/v1/external/orders/${invoiceOrId}`);
  }
}

