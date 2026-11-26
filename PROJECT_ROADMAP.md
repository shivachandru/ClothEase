# Project roadmap

Status key: **Done** = implemented in this checkout; **In progress** = partial foundation; **Pending** = not implemented.

| Phase | Status | Current evidence / next work |
|---|---|---|
| 1. Foundation | In progress | Laravel 12/PHP 8.2, SQLite development config, first-run shop/owner setup, password/PIN login throttling, owner-created staff roles and shop_id foundations. Finish password recovery, shop settings and full authorization. |
| 2. Static UI prototype | In progress | Overview, sign-in/setup, catalogue, create-product, POS, inventory, purchases, suppliers, customers, expenses, sales report, staff, bill history, return/exchange and receipt screens. Add remaining operational screens as they become real workflows. |
| 3. Database/core logic | In progress | Shops, users, categories, products/variants, sales/items/payments, stock movements, expenses, suppliers, purchases, return/refund, customer credit and audit records. Expand isolation and model coverage. |
| 4. Billing | In progress | Search by name/SKU/barcode, scanner exact-match add, cart quantities/discounts, single/split payments, optional customer, partial Udhaar, transactional sale, stock deduction, sale snapshots, browser receipt and shortcuts. Finish hold/resume, cashier permissions and reversal workflow. |
| 5. Purchases and returns | In progress | Purchase receiving, partial and later supplier payments, stock movements and balance due are implemented. Partial return/refund, exchange credit, extra collection, excess-quantity prevention and condition-based restocking are implemented. Add reversal/audit flows and dedicated returns reports. |
| 6. Reports and finance | In progress | Today dashboard, dated sales/profit report and CSV export, expense entry, customer balances/payment collection and supplier payables/payment collection. Returns/refunds and restocked cost are reflected in profit. Add product/category/payment/purchase/inventory/return reports. |
| 7. Hardware/local features | In progress | Private SQLite backup create/verify/restore and a daily 02:00 Laravel scheduled command are implemented. Windows Task Scheduler registration, MySQL backup, thermal layouts, UPI instruction display, Tamil and LAN access hardening remain. |
| 8. Windows installer | Pending | XAMPP exists on the development machine, but no installer/server manager has been built or clean-machine tested. Evaluate bundled runtime and installer before release. |
| 9. Cloud readiness | Pending | Keep environment-based config; document HTTPS production deployment and future one-way-safe sync design. |
| 10. Quality assurance | In progress | 18 workflow tests pass on local SQLite. Verify MySQL, concurrency, LAN, offline, printers, live-app restore and Windows restart behavior. |

## Immediate next actions

1. Wire the Laravel scheduler into Windows Task Scheduler and implement a MySQL backup path.
2. Add remaining reversal, held-bill, report, printer and shop-configuration workflows.
3. Validate MySQL/MariaDB, multi-device LAN and clean Windows deployment before any release claim.
