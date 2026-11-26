@extends('layouts.app')
@section('title', 'New bill') @section('section', 'New bill')
@section('content')
    <style>
        .pos-screen .bill-panel-head,
        .pos-screen .customer-picker {
            display: none
        }

        .pos-screen .customer-bill-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
            margin-top: 8px
        }

        .pos-screen .customer-bill-details label {
            font-size: 10px
        }

        .pos-screen .customer-bill-details input {
            width: 100%;
            min-width: 0;
            height: 30px;
            padding: 4px 7px;
            border: 1px solid #e5ece8;
            border-radius: 5px;
            font-size: 10px
        }

        .pos-screen .customer-picker {
            display: block
        }

        .pos-search-wrap {
            padding: 16px;
            background: #edf7f2;
            border-bottom: 1px solid #d5e8dd;
            z-index: 4
        }

        .pos-search {
            height: 52px;
            border: 2px solid #620263 !important;
            background: #fff;
            box-shadow: 0 0 0 4px #62026320
        }

        .pos-search input {
            font-size: 16px !important;
            color: #18352e !important;
            font-weight: 600
        }

        .pos-search input::placeholder {
            color: #667a70;
            opacity: 1
        }

        .pos-search:focus-within {
            border-color: #4f0250 !important;
            box-shadow: 0 0 0 5px #62026332
        }

        .search-results {
            border: 2px solid #620263;
            box-shadow: 0 14px 32px #173b2d30
        }

        .search-result {
            padding: 14px 16px
        }

        .search-result b {
            font-size: 14px;
            color: #18352e
        }

        .search-result small {
            font-size: 12px;
            color: #50675b
        }

        .search-result:hover,
        .search-result:focus {
            background: #f5e8f5;
            outline: 2px solid #620263;
            outline-offset: -2px
        }

        .search-empty {
            font-size: 13px;
            color: #394f43
        }

        .change-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 11px;
            margin-top: -5px;
            background: #f8eff8;
            border: 1px solid #e3cce5;
            border-radius: 6px;
            color: #355644;
            font-size: 12px
        }

        .change-row b {
            font-size: 15px;
            color: #620263
        }

        .exclude-tax-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #fff7e8;
            border: 1px solid #e6c98f;
            border-radius: 7px;
            color: #483a1f;
            font-weight: 650;
            font-size: 12px
        }

        .exclude-tax-toggle input {
            width: 17px;
            height: 17px;
            accent-color: #a56316
        }

        .bill-math>div {
            font-size: 12px;
            color: #52645a
        }

        .cart-line-main b {
            font-size: 14px;
            color: #263c31
        }

        .cart-line-main small {
            font-size: 11px;
            color: #586e61
        }

        .qty-control span,
        .line-discount {
            font-size: 11px;
            color: #52645a
        }

        .line-discount input {
            font-size: 12px;
            color: #24392e
        }

        .complete-button {
            font-size: 14px
        }

        .secure-note {
            font-size: 10px;
            color: #586d60
        }

        @media (min-width:761px) {
            body.pos-screen {
                height: 100vh;
                overflow: hidden
            }

            .pos-screen .main-area {
                height: 100vh;
                min-height: 0;
                overflow: hidden
            }

            .pos-screen .page-content {
                height: calc(100vh - 64px);
                padding: 12px 20px;
                display: flex;
                flex-direction: column;
                overflow: hidden
            }

            .pos-screen .pos-heading {
                flex: none;
                margin: 0 0 10px;
                min-height: 48px
            }

            .pos-screen .pos-heading .eyebrow {
                margin-bottom: 4px
            }

            .pos-screen .pos-heading h1 {
                font-size: 21px
            }

            .pos-screen .pos-heading p {
                margin-top: 3px;
                font-size: 10px
            }

            .pos-screen .pos-layout {
                flex: 1;
                min-height: 0;
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: minmax(210px, 1fr) auto;
                align-items: stretch;
                gap: 10px
            }

            .pos-screen .pos-products {
                min-height: 0;
                display: flex;
                flex-direction: column
            }

            .pos-screen .pos-search-wrap {
                padding: 10px 12px 0
            }

            .pos-screen .pos-search {
                height: 42px
            }

            .pos-screen .pos-hint {
                padding: 6px 14px;
                font-size: 9px
            }

            .pos-screen .cart-items {
                flex: 1;
                min-height: 0;
                overflow: auto;
                padding: 0 12px 8px
            }

            .pos-screen .cart-table {
                width: 100%;
                font-size: 11px
            }

            .pos-screen .cart-table-head,
            .pos-screen .cart-line {
                display: grid;
                grid-template-columns: minmax(0, 2.3fr) minmax(0, .75fr) minmax(0, .5fr) minmax(88px, 1fr) minmax(94px, 1fr) minmax(88px, 1fr) 28px;
                align-items: center;
                gap: 8px
            }

            .pos-screen .cart-table-head {
                position: sticky;
                top: 0;
                z-index: 1;
                padding: 9px 8px;
                background: #f7f8fa;
                border-bottom: 1px solid #e6e8ee;
                color: #697386;
                font-size: 10px;
                font-weight: 700
            }

            .pos-screen .cart-line {
                padding: 9px 8px;
                border-bottom: 1px solid #eaecf0
            }

            .pos-screen .cart-line-main b {
                font-size: 12px
            }

            .pos-screen .cart-line-main small {
                font-size: 10px
            }

            .pos-screen .cart-price,
            .pos-screen .cart-line-total {
                font-size: 11px;
                color: #374151
            }

            .pos-screen .cart-line-bottom {
                display: contents
            }

            .pos-screen .cart-line-bottom .qty-control {
                width: max-content
            }

            .pos-screen .cart-line-bottom .line-discount {
                font-size: 10px
            }

            .pos-screen .cart-line .qty-control button {
                width: 25px;
                height: 24px
            }

            .pos-screen .cart-line .line-discount input {
                width: 55px;
                margin: 0;
                padding: 4px
            }

            .pos-screen .cart-line-bottom>b {
                margin: 0;
                text-align: right
            }

            .pos-screen .empty-state {
                padding: 26px 12px
            }

            .pos-screen .bill-panel {
                min-height: 0;
                padding: 10px 13px;
                position: static;
                overflow: visible;
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1.7fr) minmax(0, .9fr);
                grid-template-rows: auto auto auto auto;
                grid-template-areas: "math payment total" "math payment customer" "math payment complete" "math payment secure";
                align-items: center;
                gap: 7px 10px
            }

            .pos-screen .customer-bill-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 7px;
                margin-top: 8px
            }

            .pos-screen .customer-bill-details label {
                font-size: 10px
            }

            .pos-screen .customer-bill-details input {
                width: 100%;
                min-width: 0;
                height: 30px;
                padding: 4px 7px;
                border: 1px solid #e5ece8;
                border-radius: 5px;
                font-size: 10px
            }

            .pos-screen .split-fields {
                grid-column: 1 / -1;
                display: grid;
                grid-template-columns: 1fr auto;
                align-items: center;
                gap: 6px;
                margin: 6px 0
            }

            .pos-screen .split-payment-row {
                display: flex;
                gap: 6px
            }

            .pos-screen .bill-math {
                grid-area: math
            }

            .pos-screen .bill-total {
                grid-area: total
            }

            .pos-screen .payment-area {
                grid-area: payment
            }

            .pos-screen #complete-sale {
                grid-area: complete
            }

            .pos-screen .secure-note {
                grid-area: secure
            }

            .pos-screen .bill-panel-head {
                padding: 0;
                border: 0
            }

            .pos-screen .bill-panel-head .eyebrow {
                margin-bottom: 3px
            }

            .pos-screen .bill-panel-head h2 {
                font-size: 13px
            }

            .pos-screen .customer-picker {
                grid-area: customer;
                padding: 0
            }

            .pos-screen .customer-search {
                height: 32px
            }

            .pos-screen .customer-search input {
                min-width: 0;
                font-size: 10px
            }

            .pos-screen .credit-toggle {
                margin-top: 5px
            }

            .pos-screen .customer-bill-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 7px;
                margin-top: 8px
            }

            .pos-screen .customer-bill-details label {
                font-size: 10px
            }

            .pos-screen .customer-bill-details input {
                width: 100%;
                min-width: 0;
                height: 30px;
                padding: 4px 7px;
                border: 1px solid #e5ece8;
                border-radius: 5px;
                font-size: 10px
            }

            .pos-screen .split-fields {
                grid-column: 1 / -1;
                display: grid;
                grid-template-columns: 1fr auto;
                align-items: center;
                gap: 6px;
                margin: 6px 0
            }

            .pos-screen .split-payment-row {
                display: flex;
                gap: 6px
            }

            .pos-screen .bill-math {
                padding: 0;
                gap: 7px
            }

            .pos-screen .exclude-tax-toggle {
                padding: 6px 8px;
                font-size: 10px
            }

            .pos-screen .bill-total {
                padding: 0;
                border: 0;
                display: grid;
                gap: 5px
            }

            .pos-screen .bill-total .split-toggle {
                margin: 5px 0 0;
            }

            .pos-screen .bill-total strong {
                font-size: 20px
            }


            .pos-screen .payment-area {
                padding: 0
            }

            .pos-screen .payment-label {
                margin-bottom: 6px
            }

            .pos-screen .amount-label {
                margin-top: 8px
            }

            .pos-screen .rupee-input.large {
                height: 34px
            }

            .pos-screen .change-row {
                padding: 7px 9px
            }

            .pos-screen .split-toggle {
                margin-top: 7px
            }

            .pos-screen .complete-button {
                height: 38px;
                flex: none
            }

            .pos-screen .secure-note {
                margin: 0;
                flex: none
            }
        }

        @media (max-height:680px) and (min-width:761px) {
            .pos-screen .page-content {
                padding-top: 7px;
                padding-bottom: 7px
            }

            .pos-screen .pos-heading {
                min-height: 40px;
                margin-bottom: 6px
            }

            .pos-screen .pos-heading h1 {
                font-size: 18px
            }

            .pos-screen .pos-heading p {
                display: none
            }

            .pos-screen .pos-layout {
                grid-template-rows: minmax(170px, 1fr) auto;
                gap: 7px
            }

            .pos-screen .bill-panel {
                padding: 7px 10px;
                gap: 8px
            }

            .pos-screen .payment-methods {
                gap: 3px
            }

            .pos-screen .payment-option {
                height: 25px
            }

            .pos-screen .amount-label {
                margin-top: 5px
            }

            .pos-screen .rupee-input.large {
                height: 29px
            }
        }

        @media(max-width:760px) {
            .cart-table-head {
                display: none
            }

            .cart-line {
                border-bottom: 1px solid #eaecf0;
                padding: 10px 2px
            }
        }

        .pos-screen .search-result.keyboard-active {
            background: var(--green-soft);
            outline: 2px solid var(--green);
            outline-offset: -2px
        }

        .pos-screen .secure-note small {
            display: block;
            margin-top: 4px;
            font-size: 9px;
            color: #7c8985
        }
    </style>
    <div class="page-heading pos-heading">
        <div><span class="eyebrow">POINT OF SALE</span>
            <h1>New bill</h1>
            <p>Scan a barcode or search for a style to add it.</p>
        </div><a href="{{ route('sales.index') }}" class="button secondary">▤ Bill history <kbd>F9</kbd></a>
    </div>
    <div class="pos-layout">
        <section class="panel pos-products">
            <div class="pos-search-wrap"><label class="search-box pos-search"><span>⌕</span><input id="product-search"
                        autocomplete="off" placeholder="Search name, SKU or scan barcode…"><kbd>F2</kbd></label>
                <div id="search-results" class="search-results hidden"></div>
            </div>
            <div class="pos-hint"><span>↳</span> Search by a product name, SKU or barcode. Scanner input works here too.
            </div>
            <div id="cart-items" class="cart-items">
                <div class="empty-state">
                    <div class="empty-icon">＋</div><b>Start a fresh bill</b>
                    <p>Add products to see them listed here.</p>
                </div>
            </div>
        </section>
        <aside class="panel bill-panel">
            <div class="bill-panel-head">
                <div><span class="eyebrow">CURRENT SALE</span>
                    <h2>Bill summary</h2>
                </div><button class="text-link" type="button" id="clear-cart">Clear all</button>
            </div>
            <div class="customer-picker">
                <div id="customer-picked" class="customer-picked hidden"><span id="customer-label"></span><button
                        type="button" id="clear-customer">×</button></div><label
                    class="search-box customer-search"><span>♙</span><input id="customer-search" autocomplete="off"
                        placeholder="Find existing customer by name or mobile…"></label>
                <div id="customer-results" class="search-results hidden"></div>
                <div class="customer-bill-details"><label>Customer name<input id="bill-customer-name" maxlength="150"
                            placeholder="Optional"></label><label>Mobile number<input id="bill-customer-mobile"
                            maxlength="20" inputmode="tel" placeholder="Optional"></label></div>
            </div>
            <div class="bill-math">
                <div><span>Items <b id="item-count">0</b></span><span id="subtotal">₹0.00</span></div>
                <div class="discount-row"><label for="bill-discount">Bill discount</label>
                    <div class="rupee-input"><select id="bill-discount-mode" aria-label="Discount type">
                            <option value="amount">Rs</option>
                            <option value="percent">%</option>
                        </select><input type="number" id="bill-discount" value="0" min="0" step="0.01"></div>
                </div>
                <div><span>Total discount</span><span id="discount-total">₹0.00</span></div>
                <div><span id="tax-label">GST included</span><span id="tax-total">₹0.00</span></div>
                <div><span>Total before discount</span><span id="before-discount-total">₹0.00</span></div>
                <label class="exclude-tax-toggle"><input type="checkbox" id="exclude-tax"> Exclude GST for this bill</label>
            </div>
            <div class="bill-total">
                <div class="bill-total-main"><span>Total payable</span><strong id="grand-total">₹0.00</strong></div><label
                    class="split-toggle"><input type="checkbox" id="split-payment"> Multiple Payments</label>
            </div>
            <div class="payment-area">
                <div class="payment-label">PAYMENT METHOD</div>
                <div class="payment-methods"><button type="button" class="payment-option selected"
                        data-method="cash">Cash</button><button type="button" class="payment-option"
                        data-method="upi">UPI</button><button type="button" class="payment-option"
                        data-method="card">Card</button><button type="button" class="payment-option"
                        data-method="other">Other</button></div><label class="amount-label"><span
                        id="payment-amount-label">Cash tendered</span>
                    <div class="rupee-input large"><span>₹</span><input type="number" id="paid-amount" min="0" step="0.01"
                            value="0.00"></div>
                </label>
                <div id="change-row" class="change-row"><span>Change due</span><b id="change-due">₹0.00</b></div>
                <div id="split-fields" class="split-fields hidden"></div>
            </div><button class="button primary complete-button" type="button" id="complete-sale"><span>Complete
                    bill</span></button>
        </aside>
    </div>
    <div id="pos-toast" class="toast hidden"></div>
@endsection
@push('scripts')
    <script>
        (() => {
            const search = document.getElementById('product-search'), results = document.getElementById('search-results'), cartEl = document.getElementById('cart-items'), csrf = document.querySelector('meta[name="csrf-token"]').content;
            let cart = [], method = 'cash', timer, activeResult = -1, customerId = null, customerTimer, currentTotal = 0;
            const money = n => '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            function refreshChange() { const cashMode = method === 'cash' && !document.getElementById('split-payment').checked; document.getElementById('payment-amount-label').textContent = cashMode ? 'Cash tendered' : 'Amount received'; document.getElementById('change-row').classList.toggle('hidden', !cashMode); document.getElementById('change-due').textContent = money(Math.max(0, (Number(document.getElementById('paid-amount').value) || 0) - currentTotal)) } function totals() { let subtotal = cart.reduce((a, x) => a + x.price * x.qty, 0), itemDiscount = cart.reduce((a, x) => a + x.discount, 0), mode = document.getElementById('bill-discount-mode').value, entered = Number(document.getElementById('bill-discount').value) || 0, preTaxBase = Math.max(0, subtotal - itemDiscount), tax = document.getElementById("exclude-tax").checked ? 0 : cart.reduce((a, x) => a + Math.round(x.price * x.qty * x.tax), 0) / 100, base = preTaxBase + tax, disc = Math.min(mode === 'percent' ? base * entered / 100 : entered, base), alloc = cart.map(() => 0), remaining = disc, open = cart.map((x, i) => i).filter(i => cart[i].price * cart[i].qty - cart[i].discount > 0); while (remaining > 0.001 && open.length) { let share = remaining / open.length, next = []; for (const i of open) { let cap = cart[i].price * cart[i].qty - cart[i].discount, add = Math.min(cap, share); alloc[i] += add; remaining -= add; if (cap - add > 0.001) next.push(i) } open = next } let total = Math.round(Math.max(0, base - disc) * 100) / 100; document.getElementById('item-count').textContent = cart.reduce((a, x) => a + x.qty, 0); document.getElementById('subtotal').textContent = money(subtotal); document.getElementById('tax-total').textContent = money(tax); document.getElementById('before-discount-total').textContent = money(subtotal + tax); document.getElementById('tax-label').textContent = document.getElementById('exclude-tax').checked ? 'GST excluded' : 'GST included'; document.getElementById('discount-total').textContent = money(disc); document.getElementById('grand-total').textContent = money(total); currentTotal = total; if (!document.getElementById('split-payment').checked) document.getElementById('paid-amount').value = total.toFixed(2); refreshChange(); cart.forEach((x, i) => x.billDiscount = alloc[i]); cartEl.querySelectorAll(`[data-line-total]`).forEach((el, i) => el.textContent = money(cart[i].price * cart[i].qty - cart[i].discount - alloc[i])); cartEl.querySelectorAll(`[data-discount]`).forEach((el, i) => el.value = (cart[i].discount + alloc[i]).toFixed(2)); return { subtotal, tax, total, disc, alloc }; }
            function draw() { if (!cart.length) { cartEl.innerHTML = '<div class="empty-state"><div class="empty-icon">＋</div><b>Start a fresh bill</b><p>Add products to see them listed here.</p></div>'; totals(); return } cartEl.innerHTML = '<div class="cart-table"><div class="cart-table-head"><span>ITEM</span><span>PRICE</span><span>TAX</span><span>QUANTITY</span><span>DISCOUNT</span><span>AMOUNT</span><span></span></div>' + cart.map((x, i) => `<article class="cart-line"><div class="cart-line-main"><b>${esc(x.name)}</b><small>${esc(x.sku)}${x.size || x.colour ? ' · ' + esc([x.size, x.colour].filter(Boolean).join(' · ')) : ''}</small></div><span class="cart-price">${money(x.price)}</span><span class="cart-tax">${Number(x.tax || 0)}%</span><div class="qty-control"><button data-qty="${i}" data-step="-1">−</button><span>${x.qty}</span><button data-qty="${i}" data-step="1">+</button></div><label class="line-discount">₹ <input data-discount="${i}" type="text" inputmode="decimal" autocomplete="off" disabled value="${(x.discount + (x.billDiscount || 0)).toFixed(2)}"></label><b class="cart-line-total" data-line-total="${i}">${money(x.price * x.qty - x.discount - (x.billDiscount || 0))}</b><button class="remove-item" data-remove="${i}" title="Remove">×</button></article>`).join('') + '</div>'; totals(); }
            const customerSearch = document.getElementById('customer-search'), customerResults = document.getElementById('customer-results');
            customerSearch.addEventListener('input', () => { clearTimeout(customerTimer); const q = customerSearch.value.trim(); if (!q) { customerResults.classList.add('hidden'); return } customerTimer = setTimeout(async () => { try { const r = await fetch(`{{ route('customers.search') }}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } }), list = await r.json(); customerResults.dataset.items = JSON.stringify(list); customerResults.innerHTML = list.length ? list.map(c => `<button type="button" class="search-result" data-customer="${c.id}"><span><b>${esc(c.name)}</b><small>${esc(c.mobile || 'No mobile')}</small></span><span><small>Balance ₹${Number(c.balance).toFixed(2)}</small></span></button>`).join('') : '<div class="search-empty">No customer found. Add one in Customers.</div>'; customerResults.classList.remove('hidden') } catch (e) { customerResults.innerHTML = '<div class="search-empty">Customer search failed.</div>'; customerResults.classList.remove('hidden') } }, 180) });
            customerResults.addEventListener('click', e => { const b = e.target.closest('[data-customer]'); if (!b) return; const customer = JSON.parse(customerResults.dataset.items || '[]').find(x => x.id == b.dataset.customer); if (!customer) return; customerId = customer.id; document.getElementById('bill-customer-name').value = customer.name; document.getElementById('bill-customer-mobile').value = customer.mobile || ''; document.getElementById('customer-label').textContent = `${customer.name}${customer.mobile ? ' · ' + customer.mobile : ''}`; document.getElementById('customer-picked').classList.remove('hidden'); customerSearch.closest('.customer-search').classList.add('hidden'); customerResults.classList.add('hidden') });
            document.getElementById('clear-customer').addEventListener('click', () => { customerId = null; document.getElementById('bill-customer-name').value = ''; document.getElementById('bill-customer-mobile').value = ''; document.getElementById('customer-picked').classList.add('hidden'); customerSearch.closest('.customer-search').classList.remove('hidden'); customerSearch.value = ''; document.getElementById('paid-amount').value = totals().total.toFixed(2) });
            let searchRequestId = 0;
            function addScannedBarcode(variant) { const existing = cart.find(x => x.id === variant.id); if (existing) existing.qty++; else cart.push({ ...variant, qty: 1, discount: 0 }); search.value = ''; results.classList.add('hidden'); activeResult = -1; draw() }
            search.addEventListener('input', () => { clearTimeout(timer); const q = search.value.trim(), requestId = ++searchRequestId; if (!q) { results.classList.add('hidden'); activeResult = -1; return } results.classList.add('hidden'); activeResult = -1; timer = setTimeout(async () => { try { const response = await fetch(`{{ route('products.search') }}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } }); const list = await response.json(); if (requestId !== searchRequestId || search.value.trim() !== q) return; if (list.length === 1 && list[0].barcode === q) { addScannedBarcode(list[0]); return } results.innerHTML = list.length ? list.map(x => `<button type="button" class="search-result" data-id="${x.id}"><span><b>${esc(x.name)}</b><small>${esc(x.sku)}${x.size || x.colour ? ' · ' + esc([x.size, x.colour].filter(Boolean).join(' · ')) : ''}</small></span><span><b>${money(x.price)}</b><small>${x.stock} in stock</small></span></button>`).join('') : '<div class="search-empty">No matching product found.</div>'; results.classList.remove('hidden'); results.dataset.products = JSON.stringify(list); activeResult = -1 } catch (e) { toast('Could not search products. Try again.') } }, 180) });
            results.addEventListener('click', e => { const b = e.target.closest('[data-id]'); if (!b) return; const v = JSON.parse(results.dataset.products || '[]').find(x => x.id == b.dataset.id); if (!v) return; const existing = cart.find(x => x.id === v.id); if (existing) existing.qty++; else cart.push({ ...v, qty: 1, discount: 0 }); search.value = ''; results.classList.add('hidden'); activeResult = -1; draw(); search.focus() });
            cartEl.addEventListener('click', e => { const remove = e.target.closest('[data-remove]'), q = e.target.closest('[data-qty]'); if (remove) cart.splice(Number(remove.dataset.remove), 1); if (q) { const item = cart[Number(q.dataset.qty)]; item.qty += Number(q.dataset.step); if (item.qty < 1) cart.splice(Number(q.dataset.qty), 1) } draw() }); cartEl.addEventListener('change', e => { if (e.target.matches('[data-discount]')) { const item = cart[Number(e.target.dataset.discount)], entered = Math.max(0, Number(String(e.target.value).replace(',', '.')) || 0); item.discount = Math.min(item.price * item.qty - (item.billDiscount || 0), Math.max(0, entered - (item.billDiscount || 0))); draw() } });
            document.getElementById('bill-discount').addEventListener('input', totals); document.getElementById('bill-discount-mode').addEventListener('change', () => { document.getElementById('bill-discount').value = '0'; totals(); }); document.getElementById('paid-amount').addEventListener('input', refreshChange); document.getElementById('exclude-tax').addEventListener('change', totals); document.getElementById('clear-cart').addEventListener('click', () => { cart = []; draw() });
            document.querySelectorAll('.payment-option').forEach(b => b.addEventListener('click', () => { method = b.dataset.method; refreshChange(); document.querySelectorAll('.payment-option').forEach(x => x.classList.toggle('selected', x === b)) }));
            function updateSplitBalance(source) {
                if (!document.getElementById('split-payment').checked) return;
                const rows = [...document.querySelectorAll('.split-payment-row')];
                if (rows.length < 2) return;
                const amounts = rows.map(row => row.querySelector('input'));
                const balanceInput = amounts[amounts.length - 1];
                if (source === balanceInput) return;
                const alreadyPaid = amounts.slice(0, -1).reduce((sum, input) => sum + (Number(input.value) || 0), 0);
                balanceInput.value = Math.max(0, Math.round((totals().total - alreadyPaid) * 100) / 100).toFixed(2);
            }
            document.getElementById('split-fields').addEventListener('input', e => {
                if (e.target.matches('input[type="number"]')) updateSplitBalance(e.target);
            }); document.getElementById('split-payment').addEventListener('change', e => { const box = document.getElementById('split-fields'); box.classList.toggle('hidden', !e.target.checked); if (e.target.checked) { box.innerHTML = '<div class="split-payment-row"><select><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="other">Other</option></select><input type="number" min="0" step="0.01" placeholder="Amount"></div><button type="button" class="text-link" id="add-payment">＋ Add payment method</button>'; document.getElementById('paid-amount').disabled = true; refreshChange(); if (!box.dataset.listener) box.addEventListener('click', e => { if (e.target.id === 'add-payment') { const row = document.createElement('div'); row.className = 'split-payment-row'; row.innerHTML = '<select><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="other">Other</option></select><input type="number" min="0" step="0.01" placeholder="Amount"><button type="button" class="remove-split">×</button>'; box.insertBefore(row, e.target); updateSplitBalance() } if (e.target.classList.contains('remove-split')) e.target.parentElement.remove() }); box.dataset.listener = 'true' } else { box.innerHTML = ''; document.getElementById('paid-amount').disabled = false; totals(); refreshChange() } });
            document.getElementById('complete-sale').addEventListener('click', async () => { if (!cart.length) { toast('Add at least one item to the bill.'); return } const { total, disc, alloc } = totals(); const customerName = document.getElementById('bill-customer-name').value.trim(), customerMobile = document.getElementById('bill-customer-mobile').value.trim(); const customerPrompt = (customerName || customerMobile)
                    : 'No customer name or mobile number has been entered. Save this bill without customer details?';
                if (!customerName && !customerMobile && !window.confirm(customerPrompt)) return; let payments, tenderedAmount = 0; if (document.getElementById('split-payment').checked) { payments = [...document.querySelectorAll('.split-payment-row')].map(r => ({ method: r.querySelector('select').value, amount: Number(r.querySelector('input').value) })).filter(p => p.amount > 0) } else { const entered = Number(document.getElementById('paid-amount').value) || 0; tenderedAmount = method === 'cash' ? entered : 0; const amount = method === 'cash' ? Math.min(entered, total) : entered; payments = amount > 0 ? [{ method, amount }] : [] } const button = document.getElementById('complete-sale'); button.disabled = true; button.querySelector('span').textContent = 'Saving bill…'; try { const response = await fetch('{{ route('sales.store') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ items: cart.map((x, i) => ({ variant_id: x.id, quantity: x.qty, discount: x.discount + alloc[i] })), discount: 0, payments, customer_id: customerId, customer_name: customerName, customer_mobile: customerMobile, include_tax: !document.getElementById('exclude-tax').checked, tendered_amount: tenderedAmount }) }); const data = await response.json(); if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Could not complete bill.'); location.href = data.receipt } catch (e) { toast(e.message) } finally { button.disabled = false; button.querySelector('span').textContent = 'Complete bill' } });
            function toast(s, type = 'error') { const t = document.getElementById('pos-toast'); t.textContent = s; t.className = `toast alert ${type}`; t.setAttribute('role', type === 'error' ? 'alert' : 'status'); setTimeout(() => t.classList.add('hidden'), 4000) }
            function highlightSearchResult(index) { const options = [...results.querySelectorAll('[data-id]')]; if (!options.length) return; activeResult = ((index % options.length) + options.length) % options.length; options.forEach((option, i) => option.classList.toggle('keyboard-active', i === activeResult)); options[activeResult].scrollIntoView({ block: 'nearest' }) }
            search.addEventListener('keydown', async e => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                clearTimeout(timer);
                const requestId = ++searchRequestId;
                const q = search.value.trim();
                if (!q) return;
                try {
                    const response = await fetch(`{{ route('products.search') }}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
                    const list = await response.json();
                    if (requestId !== searchRequestId || search.value.trim() !== q) return;
                    const variant = list.find(item => item.barcode === q);
                    if (!variant) return;
                    addScannedBarcode(variant);
                } catch (error) { toast('Could not search barcode. Try again.'); }
            });
            search.addEventListener('keydown', e => { if (results.classList.contains('hidden')) return; const options = results.querySelectorAll('[data-id]'); if (e.key === 'ArrowDown' && options.length) { e.preventDefault(); highlightSearchResult(activeResult + 1) } else if (e.key === 'ArrowUp' && options.length) { e.preventDefault(); highlightSearchResult(activeResult < 0 ? options.length - 1 : activeResult - 1) } else if (e.key === 'Enter' && activeResult >= 0 && options[activeResult]) { e.preventDefault(); options[activeResult].click() } });
            const keyboardRoutes = { F5: @json((auth()->user()->role === 'owner' || auth()->user()->canAccess('products')) ? route('products.index') : null), F6: @json((auth()->user()->role === 'owner' || auth()->user()->canAccess('inventory')) ? route('inventory.index') : null), F7: @json((auth()->user()->role === 'owner' || auth()->user()->canAccess('purchases')) ? route('purchases.index') : null), F9: @json((auth()->user()->role === 'owner' || auth()->user()->canAccess('sales')) ? route('sales.index') : null) };
            document.addEventListener('keydown', e => { if (e.code === 'ControlRight' || (e.key === 'Control' && e.location === 2)) { e.preventDefault(); document.getElementById('complete-sale').click(); return } if (e.key === 'F2') { e.preventDefault(); search.focus(); return } const target = keyboardRoutes[e.key]; if (target) { e.preventDefault(); window.location.assign(target); return } if (e.key === 'Escape') { results.classList.add('hidden'); activeResult = -1 } });
})();</script>@endpush
