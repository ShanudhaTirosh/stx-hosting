/**
 * Shopify + PayVerify Webhook Handler (Node.js / Express)
 * Listens for PayVerify `payment.verified` webhooks, validates HMAC-SHA256 signature,
 * and automatically marks the matching Shopify Order as 'paid'.
 */

const express = require('express');
const crypto = require('crypto');
const axios = require('axios');

const app = express();
app.use(express.raw({ type: 'application/json' }));

const PORT = process.env.PORT || 4000;
const PAYVERIFY_WEBHOOK_SECRET = process.env.PAYVERIFY_WEBHOOK_SECRET || 'your_webhook_secret';
const SHOPIFY_SHOP_DOMAIN = process.env.SHOPIFY_SHOP_DOMAIN || 'your-store.myshopify.com';
const SHOPIFY_ADMIN_API_TOKEN = process.env.SHOPIFY_ADMIN_API_TOKEN || 'shpat_xxxx';

/**
 * Verifies PayVerify HMAC-SHA256 signature.
 */
function verifyPayVerifySignature(rawBody, signatureHeader) {
  if (!PAYVERIFY_WEBHOOK_SECRET || !signatureHeader) return false;
  const hmac = crypto.createHmac('sha256', PAYVERIFY_WEBHOOK_SECRET);
  const digest = 'sha256=' + hmac.update(rawBody).digest('hex');
  return crypto.timingSafeEqual(Buffer.from(digest), Buffer.from(signatureHeader));
}

app.post('/api/payverify-webhook', async (req, res) => {
  const signature = req.headers['x-payverify-signature'];

  if (!verifyPayVerifySignature(req.body, signature)) {
    console.error('Invalid PayVerify webhook signature!');
    return res.status(401).json({ error: 'Invalid HMAC signature' });
  }

  try {
    const payload = JSON.parse(req.body.toString('utf-8'));
    console.log(`Received PayVerify event: ${payload.event}`);

    if (payload.event === 'payment.verified') {
      const { order_id, invoice_no, amount_formatted, reference, verification_basis } = payload.data;
      console.log(`Payment Verified for Order ${invoice_no} (${amount_formatted}) via ${verification_basis}`);

      // 1. Find order in Shopify by invoice name / reference
      const searchRes = await axios.get(
        `https://${SHOPIFY_SHOP_DOMAIN}/admin/api/2024-01/orders.json?name=${encodeURIComponent(invoice_no)}`,
        {
          headers: {
            'X-Shopify-Access-Token': SHOPIFY_ADMIN_API_TOKEN,
            'Content-Type': 'application/json',
          },
        }
      );

      const shopifyOrder = searchRes.data.orders?.[0];
      if (shopifyOrder) {
        // 2. Create Transaction to mark order as paid
        await axios.post(
          `https://${SHOPIFY_SHOP_DOMAIN}/admin/api/2024-01/orders/${shopifyOrder.id}/transactions.json`,
          {
            transaction: {
              kind: 'capture',
              status: 'success',
              amount: shopifyOrder.total_price,
              gateway: 'Commercial Bank Direct (PayVerify)',
              authorization: reference || 'BANK_MATCH',
            },
          },
          {
            headers: {
              'X-Shopify-Access-Token': SHOPIFY_ADMIN_API_TOKEN,
              'Content-Type': 'application/json',
            },
          }
        );
        console.log(`Successfully marked Shopify Order #${shopifyOrder.order_number} as PAID!`);
      }
    }

    res.status(200).json({ received: true });
  } catch (err) {
    console.error('Error processing webhook:', err.message);
    res.status(500).json({ error: 'Processing error' });
  }
});

app.listen(PORT, () => {
  console.log(`PayVerify Shopify Webhook Gateway listening on port ${PORT}`);
});

