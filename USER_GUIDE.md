# ClothEase user guide (development build)

## First sign-in

On first launch, choose **Set up your shop**, enter the store and owner details, and choose a strong password. Setup closes after the first shop is created. Later users sign in with email and password.

## Add the first product

Open **Products → Add a product**. Enter the style name, category, size/colour, unique SKU, optional barcode, purchase cost, selling price, tax rate and opening quantity. Opening quantity is recorded as a stock movement. Additional variants can be added and edited from the product page.

## Barcode labels

On **Products → product details**, set a variant's barcode (or leave it blank to use its SKU), choose the number of rows, and select **Print barcode**. ClothEase sends the job directly to the Windows printer driver without opening Chrome's print dialog. Each landscape row contains two identical 50 × 25 mm Code 39 labels. Install the Retsol R220 Windows driver and set the printer's label stock to 100 × 25 mm. If Windows lists the printer under a different name, set `CLOTHEASE_BARCODE_PRINTER` in `.env` to that exact name. Values can contain A–Z, 0–9, spaces, or `- . $ / + %`.

## Create a bill

Open **New bill**, search by name/SKU or scan a barcode into the search box. Add matching variants, adjust quantity and item discount, then choose a payment method. For split payment, enable the split option and allocate amounts. The sum of payment rows must equal the bill total. Select **Complete bill**; stock, sale snapshots, payments and movements commit together. The receipt opens with a browser print action.

Shortcuts: **F2** focuses product search; **F8** completes a bill when focus is outside an input; **Esc** closes search suggestions. The POS controls remain usable without shortcuts.

## Dashboard and bill history

The overview reads today's completed bills, quantities, low-stock variants and recorded expenses from the database. Bill history lists completed sales and links to printable receipts.

## Inventory, expenses and reports

Inventory shows per-variant quantities, cost valuation, movement history and low-stock status. Owners and stock managers can set a physical count with a required reason; the app records before/after quantities and the actor. Owners can record dated shop expenses. The Sales report accepts a custom date range, estimates gross profit from sale-time costs, subtracts recorded expenses for estimated operating profit, and exports bill rows as CSV.

## Purchases and returns

Add a supplier before recording an invoice. In **Purchases**, search variants, enter supplier invoice quantities and costs, record any payment made now, and save. Stock increases and the unpaid balance is recorded with the invoice. Open a supplier account to record later payments; collections apply to the oldest open invoices first. For a customer return, open the bill receipt and choose **Process return**. Select each item and remaining quantity, then mark its condition. Good returns re-enter stock; damaged goods do not. Return quantities are checked against the original sold and already returned quantities. The exchange panel can issue replacements, use return value as credit, and collect or refund the difference.

Customer profiles are optional. In POS, search and attach a customer only when useful. Enable **Allow Udhaar / partial payment** to record less than the bill total; the outstanding amount is visible on the customer account. Later, record a collection from that account; it applies to the oldest outstanding bills first. Exchanges can use the return value as credit, collect the difference, or add the difference to the customer balance when the original bill has a customer.

## Staff and activity records

Owners can add cashier and stock-manager accounts from **Staff accounts**. Set a password and optionally a 4–8 digit quick PIN. PIN sign-in is throttled; do not reuse a PIN across shops. The owner-only **Activity log** lists recorded sales, purchases, returns/exchanges, stock adjustments, expenses, payments, product changes and new staff accounts.

## Backups (development build)

Owners can create a manual backup from **Backups**. The current implementation supports file-backed SQLite only; it stores backups under private application storage and checks both the SHA-256 manifest and SQLite integrity. Restoring a backup first creates a separate pre-restore snapshot. Laravel also schedules `backup:create` daily at 02:00; on Windows this runs only after Laravel's scheduler is registered with Task Scheduler (that machine-level setup is not included yet). MySQL backup, retention policy and off-device copy are not implemented.

## Limitations

The current build is still a development build. Password recovery, cancellation/reversal, held bills, MySQL backup, automated Windows scheduler registration, backup retention, multi-device LAN deployment, full report coverage, Tamil and installer remain incomplete. Do not use it as a production POS until recovery, deployment and hardware workflows are validated.
