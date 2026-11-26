# Database design (current foundation)

All business tables carry `shop_id` directly or are reachable through a sale/product that does. Controllers and services must derive the shop from the authenticated user, never from submitted form data. Tenant ownership must be checked before loading or changing records.

| Table | Purpose / relationships |
|---|---|
| `shops` | Business identity and local operating options. One shop has many users, products, variants and sales. |
| `users` | Laravel-authenticated owner/staff identity; `shop_id`, `role`, active flag and optional PIN hash. |
| `categories` | Shop-owned product group names. |
| `products` | Shop-owned style; optional category; has variants. |
| `product_variants` | Shop-owned size/colour combination; unique per-shop SKU and barcode; cost, price, tax, stock and threshold. |
| `sales` | Per-shop unique bill number, cashier, status, totals and sale timestamp. |
| `sale_items` | Immutable sale-time product/SKU/variant, unit price, cost, discount, tax and quantity snapshots. |
| `payments` | Shop-owned payment method, amount, reference and timestamp; associated with sale when relevant. |
| `stock_movements` | Append-only stock quantity ledger with before/after, direction, actor and reference. |
| `expenses` | Shop-owned dated expenses recorded by a user; included in estimated operating profit reports. |
| `suppliers` | Shop-owned supplier contact and activity record. |
| `purchases` / `purchase_items` | Supplier invoice totals and historical item/cost snapshots. Purchase rows receive stock inside the same transaction. |
| `supplier_payments` | Shop/supplier/purchase payment history, including initial and later partial payment. |
| `sale_returns` / `sale_return_items` | Return transaction and per-sale-item quantities, condition, restock flag, refund, returned tax and cost snapshots. |
| `refund_payments` | Refund method, amount, reference and timestamp for a return. |
| `customers` | Shop-owned optional customer identity and contact details. |
| `customer_payments` | Customer payment collection and initial paid tender records linked to bills. |
| `audit_logs` | Append-only shop-scoped action, actor, subject and structured detail record. Important operations write the event inside the same transaction as the business change. |

## Billing invariants

- Validate all cart variants against the authenticated user's shop.
- Lock each variant row during checkout, check available stock unless the shop setting explicitly enables negative stock, and change the balance only inside the sale transaction.
- Create sale, item snapshots, payment rows, and stock movement rows in the same transaction. Any failure rolls all of them back.
- Do not delete completed financial records. A future cancellation/return must be an audited reversal.
- `sale_items.cost_price` is the cost snapshot used for gross profit; current catalogue costs must never rewrite historical profit.
- `payments` currently stores collected tender amounts; UPI method selection is not proof of payment verification.

## Planned tables

Audit events; backup metadata and user activity. Design them with shop ownership, restrictive financial history, unique transaction identifiers and explicit status transitions. Later supplier payments still need a workflow.
