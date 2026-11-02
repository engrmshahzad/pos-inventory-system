/**
 * POS screen (Alpine component). Cart lives in this component and is mirrored to localStorage.
 * Totals shown here are a preview; the server recalculates everything on checkout.
 */
const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

let audioCtx;
const beep = (ok = true) => {
    try {
        audioCtx ??= new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.frequency.value = ok ? 1250 : 320;
        gain.gain.value = 0.08;
        osc.connect(gain).connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + (ok ? 0.08 : 0.25));
    } catch { /* audio not available */ }
};

export default (config) => ({
    config,
    // catalogue
    categories: config.categories,
    activeCategory: null,
    products: [],
    nextPage: 1,
    loadingProducts: false,
    // search
    query: '',
    results: [],
    showResults: false,
    highlighted: 0,
    searchTimer: null,
    // cart
    cart: null,
    // modals
    modal: null, // 'pay' | 'customer' | 'held' | 'success' | 'discount'
    busy: false,
    payments: [],
    customerQuery: '',
    customerResults: [],
    newCustomer: { name: '', phone: '', address: '' },
    customerErrors: {},
    showCustomerForm: false,
    customerReturn: false,
    held: [],
    heldCount: config.heldCount,
    lastSale: null,
    clock: '',
    fullscreen: false,

    init() {
        this.cart = this.loadCart();
        this.loadProducts(true);
        this.tickClock();
        setInterval(() => this.tickClock(), 1000);
        this.$watch('cart', () => this.saveCart(), { deep: true });
        this.$nextTick(() => this.focusSearch());
        document.addEventListener('fullscreenchange', () => (this.fullscreen = !!document.fullscreenElement));
    },

    /* ---------- persistence ---------- */
    emptyCart() {
        return { items: [], customer: this.config.defaultCustomer ?? null, discount_type: 'amount', discount_value: 0, shipping: 0, note: '', hold_id: null };
    },
    loadCart() {
        try {
            const saved = JSON.parse(localStorage.getItem(this.config.storageKey));
            return saved && Array.isArray(saved.items) ? { ...this.emptyCart(), ...saved } : this.emptyCart();
        } catch {
            return this.emptyCart();
        }
    },
    saveCart() {
        try { localStorage.setItem(this.config.storageKey, JSON.stringify(this.cart)); } catch { /* storage full or blocked */ }
    },

    /* ---------- helpers ---------- */
    money(v) {
        const n = round2(v);
        const fixed = Number.isInteger(n) ? n.toLocaleString('en-US') : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return `${this.config.currency} ${fixed}`;
    },
    qtyLabel(q) {
        return Number(q).toLocaleString('en-US', { maximumFractionDigits: 3 });
    },
    tickClock() {
        this.clock = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },
    async api(url, options = {}) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            ...options,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            const error = new Error(firstError || data.message || 'Something went wrong. Please try again.');
            error.status = response.status;
            error.errors = data.errors ?? {};
            throw error;
        }
        return data;
    },
    focusSearch() {
        if (!this.modal) this.$refs.search?.focus();
    },
    toggleFullscreen() {
        document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.();
    },

    /* ---------- products ---------- */
    async loadProducts(reset = false) {
        if (this.loadingProducts || (!reset && !this.nextPage)) return;
        if (reset) { this.products = []; this.nextPage = 1; }
        this.loadingProducts = true;
        try {
            const params = new URLSearchParams({ page: this.nextPage });
            if (this.activeCategory) params.set('category', this.activeCategory);
            const data = await this.api(`${this.config.urls.products}?${params}`);
            this.products.push(...data.data);
            this.nextPage = data.next_page;
        } catch (e) {
            toast('error', e.message);
        } finally {
            this.loadingProducts = false;
        }
    },
    selectCategory(id) {
        this.activeCategory = id;
        this.loadProducts(true);
        this.focusSearch();
    },

    /* ---------- search & scan ---------- */
    onSearchInput() {
        clearTimeout(this.searchTimer);
        const q = this.query.trim();
        if (q.length < 2) { this.results = []; this.showResults = false; return; }
        this.searchTimer = setTimeout(async () => {
            try {
                const data = await this.api(`${this.config.urls.products}?per_page=12&q=${encodeURIComponent(q)}`);
                if (this.query.trim() !== q) return; // a newer keystroke won
                this.results = data.data;
                this.highlighted = 0;
                this.showResults = true;
            } catch (e) { toast('error', e.message); }
        }, 250);
    },
    async submitSearch() {
        const code = this.query.trim();
        if (!code) { this.openPay(); return; }
        clearTimeout(this.searchTimer);
        try {
            // A scanner types the barcode + Enter: try an exact match first.
            const product = await this.api(`${this.config.urls.lookup}?code=${encodeURIComponent(code)}`);
            this.addToCart(product);
        } catch (e) {
            if (e.status === 404 && this.results.length) {
                this.addToCart(this.results[this.highlighted] ?? this.results[0]);
            } else if (e.status === 404) {
                if (this.config.beep) beep(false);
                toast('warning', e.message);
                this.query = '';
            } else {
                toast('error', e.message);
            }
        }
    },
    moveHighlight(step) {
        if (!this.results.length) return;
        this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
    },

    /* ---------- cart ---------- */
    addToCart(product, qty = 1) {
        const line = this.cart.items.find((i) => i.id === product.id);
        const inCart = line ? Number(line.qty) : 0;
        if (!this.config.allowNegative && inCart + qty > product.stock) {
            if (this.config.beep) beep(false);
            toast('warning', `Only ${this.qtyLabel(product.stock)} ${product.unit ?? ''} of ${product.name} in stock.`);
            return;
        }
        if (line) {
            line.qty = round2(Number(line.qty) + qty);
            line.stock = product.stock;
            // move to bottom so "last item" shortcuts act on it
            this.cart.items = [...this.cart.items.filter((i) => i !== line), line];
        } else {
            this.cart.items.push({ ...product, qty, discount: 0, price: product.price, key: Date.now() });
        }
        if (this.config.beep) beep(true);
        this.query = '';
        this.results = [];
        this.showResults = false;
        this.$nextTick(() => {
            const list = this.$refs.cartList;
            if (list) list.scrollTop = list.scrollHeight;
            this.focusSearch();
        });
    },
    setQty(item, value) {
        let qty = Number(value);
        if (!item.decimal) qty = Math.round(qty);
        if (!(qty > 0)) { this.removeItem(item); return; }
        if (!this.config.allowNegative && qty > item.stock) {
            toast('warning', `Only ${this.qtyLabel(item.stock)} ${item.unit ?? ''} in stock.`);
            qty = item.stock;
        }
        item.qty = round2(qty);
    },
    changeQty(item, step) {
        this.setQty(item, Number(item.qty) + step);
    },
    removeItem(item) {
        this.cart.items = this.cart.items.filter((i) => i !== item);
    },
    lastItem() {
        return this.cart.items[this.cart.items.length - 1];
    },
    clearCart(confirmFirst = true) {
        const reset = () => { this.cart = this.emptyCart(); this.focusSearch(); };
        if (!confirmFirst || !this.cart.items.length) { reset(); return; }
        confirmAction({ title: 'Clear this bill?', text: 'All items will be removed.', confirmText: 'Clear' }).then((ok) => ok && reset());
    },

    /* ---------- totals (preview; server recalculates) ---------- */
    lineGross(i) { return round2(Number(i.qty) * Number(i.price)); },
    lineNet(i) { return round2(this.lineGross(i) - Math.min(Number(i.discount) || 0, this.lineGross(i))); },
    lineTax(i) {
        const rate = this.config.taxEnabled ? (Number(i.tax_percent) || 0) : 0;
        const net = this.lineNet(i);
        return round2(this.config.taxInclusive ? net - net / (1 + rate / 100) : net * rate / 100);
    },
    lineTotal(i) { return this.config.taxInclusive ? this.lineNet(i) : round2(this.lineNet(i) + this.lineTax(i)); },
    get itemCount() { return this.cart.items.reduce((s, i) => s + Number(i.qty), 0); },
    get subtotal() { return round2(this.cart.items.reduce((s, i) => s + this.lineTotal(i) - this.lineTax(i), 0)); },
    get tax() { return round2(this.cart.items.reduce((s, i) => s + this.lineTax(i), 0)); },
    get discountAmount() {
        const v = Math.max(0, Number(this.cart.discount_value) || 0);
        return this.cart.discount_type === 'percent' ? round2(this.subtotal * Math.min(v, 100) / 100) : Math.min(v, this.subtotal);
    },
    get grandTotal() { return round2(this.subtotal - this.discountAmount + this.tax + Math.max(0, Number(this.cart.shipping) || 0)); },
    get overDiscountCap() {
        if (this.config.maxDiscount === null) return false;
        const gross = this.cart.items.reduce((s, i) => s + this.lineGross(i), 0);
        const disc = this.cart.items.reduce((s, i) => s + Math.min(Number(i.discount) || 0, this.lineGross(i)), 0) + this.discountAmount;
        return gross > 0 && disc / gross * 100 > this.config.maxDiscount + 0.001;
    },

    /* ---------- customer ---------- */
    async openCustomer() {
        this.customerReturn = this.modal === 'pay';
        this.modal = 'customer';
        this.customerQuery = '';
        this.showCustomerForm = false;
        this.customerErrors = {};
        await this.searchCustomers();
        this.$nextTick(() => this.$refs.customerSearch?.focus());
    },
    async searchCustomers() {
        try {
            this.customerResults = await this.api(`${this.config.urls.customers}?q=${encodeURIComponent(this.customerQuery)}`);
        } catch (e) { toast('error', e.message); }
    },
    chooseCustomer(customer) {
        this.cart.customer = customer;
        if (this.customerReturn) {
            this.modal = 'pay';
            this.customerReturn = false;
            return;
        }
        this.closeModal();
    },
    async saveCustomer() {
        this.customerErrors = {};
        try {
            const customer = await this.api(this.config.urls.customers, { method: 'POST', body: JSON.stringify(this.newCustomer) });
            this.newCustomer = { name: '', phone: '', address: '' };
            this.chooseCustomer(customer);
            toast('success', `${customer.name} added.`);
        } catch (e) {
            this.customerErrors = e.errors;
            if (!Object.keys(e.errors).length) toast('error', e.message);
        }
    },

    /* ---------- payment ---------- */
    openPay() {
        if (!this.cart.items.length) { toast('warning', 'Add items to the bill first.'); return; }
        if (this.overDiscountCap) { toast('warning', `Discount is above your ${this.config.maxDiscount}% limit.`); return; }
        this.payments = [{ method: 'cash', amount: this.grandTotal, reference: '' }];
        this.modal = 'pay';
        this.$nextTick(() => document.getElementById('payAmount0')?.select());
    },
    get tendered() { return round2(this.payments.reduce((s, p) => s + (Number(p.amount) || 0), 0)); },
    get change() { return Math.max(0, round2(this.tendered - this.grandTotal)); },
    get due() { return Math.max(0, round2(this.grandTotal - this.tendered)); },
    get creditBlocked() { return this.due > 0 && !this.cart.customer; },
    get overCreditLimit() {
        const c = this.cart.customer;
        return c && c.credit_limit > 0 && this.due > 0 && c.balance + this.due > c.credit_limit;
    },
    remaining() { return Math.max(0, round2(this.grandTotal - this.tendered)); },
    addMethod(method) {
        if (method === 'credit') {
            // Full udhaar: no payment now; the cashier can still add a part-payment row.
            if (!this.cart.customer) { toast('warning', 'Select a customer for credit (udhaar) sales.'); this.openCustomer(); return; }
            this.payments = [];
            return;
        }
        const existing = this.payments.find((p) => p.method === method);
        if (existing) return;
        const rest = this.remaining();
        // Replace an untouched full cash row instead of stacking a zero row.
        if (this.payments.length === 1 && this.payments[0].method === 'cash' && Number(this.payments[0].amount) === this.grandTotal) {
            this.payments = [{ method, amount: this.grandTotal, reference: '' }];
        } else {
            this.payments.push({ method, amount: rest, reference: '' });
        }
        this.$nextTick(() => document.getElementById(`payAmount${this.payments.length - 1}`)?.select());
    },
    removePayment(index) {
        this.payments.splice(index, 1);
    },
    quickCash(value) {
        let cash = this.payments.find((p) => p.method === 'cash');
        if (!cash) { cash = { method: 'cash', amount: 0, reference: '' }; this.payments.push(cash); }
        const others = round2(this.payments.filter((p) => p !== cash).reduce((s, p) => s + (Number(p.amount) || 0), 0));
        cash.amount = value === 'exact' ? Math.max(0, round2(this.grandTotal - others)) : value;
    },
    async checkout() {
        if (this.busy) return;
        if (this.creditBlocked) { toast('warning', 'Select a customer to leave an amount due.'); return; }
        this.busy = true;
        try {
            const sale = await this.api(this.config.urls.checkout, { method: 'POST', body: JSON.stringify(this.payload(true)) });
            this.lastSale = sale;
            this.cart = this.emptyCart();
            this.modal = 'success';
            this.loadProducts(true); // refresh stock badges
        } catch (e) {
            if (this.config.beep) beep(false);
            toast('error', e.message);
        } finally {
            this.busy = false;
        }
    },
    payload(withPayments = false) {
        return {
            customer_id: this.cart.customer?.id ?? null,
            hold_id: this.cart.hold_id,
            items: this.cart.items.map((i) => ({ product_id: i.id, quantity: i.qty, unit_price: i.price, discount: i.discount || 0 })),
            discount_type: this.cart.discount_type,
            discount_value: Number(this.cart.discount_value) || 0,
            shipping: Number(this.cart.shipping) || 0,
            note: this.cart.note || null,
            payments: withPayments ? this.payments.filter((p) => Number(p.amount) > 0) : [],
        };
    },
    printReceipt() {
        if (this.lastSale) window.open(this.lastSale.receipt_url, '_blank', 'width=420,height=720');
    },
    newSale() {
        this.closeModal();
        this.lastSale = null;
    },

    /* ---------- hold ---------- */
    async holdBill() {
        if (!this.cart.items.length) { toast('warning', 'Nothing to hold.'); return; }
        try {
            const data = await this.api(this.config.urls.hold, { method: 'POST', body: JSON.stringify(this.payload()) });
            this.heldCount = data.held_count;
            this.cart = this.emptyCart();
            toast('success', 'Bill put on hold.');
            this.focusSearch();
        } catch (e) { toast('error', e.message); }
    },
    async openHeld() {
        this.modal = 'held';
        try { this.held = await this.api(this.config.urls.held); } catch (e) { toast('error', e.message); }
    },
    async resumeHeld(bill) {
        const go = async () => {
            try {
                const data = await this.api(`${this.config.urls.held}/${bill.id}`);
                this.cart = {
                    ...this.emptyCart(),
                    items: data.items.map((i) => ({ ...i, key: Date.now() + Math.random() })),
                    customer: data.customer,
                    discount_type: data.discount_type,
                    discount_value: data.discount_value,
                    shipping: data.shipping,
                    note: data.note ?? '',
                    hold_id: data.hold_id,
                };
                this.closeModal();
                toast('info', 'Held bill resumed. It is removed from the hold list once paid.');
            } catch (e) { toast('error', e.message); }
        };
        if (this.cart.items.length && !this.cart.hold_id) {
            if (await confirmAction({ title: 'Replace the current bill?', text: 'Hold or clear it first if you need it.', confirmText: 'Replace', danger: false })) go();
        } else {
            go();
        }
    },
    async deleteHeld(bill) {
        if (!(await confirmAction({ title: 'Delete this held bill?' }))) return;
        try {
            const data = await this.api(`${this.config.urls.held}/${bill.id}`, { method: 'DELETE' });
            this.heldCount = data.held_count;
            this.held = this.held.filter((b) => b.id !== bill.id);
            if (this.cart.hold_id === bill.id) this.cart.hold_id = null;
        } catch (e) { toast('error', e.message); }
    },

    /* ---------- modals & keys ---------- */
    closeModal() {
        if (this.modal === 'success') this.lastSale = null;
        this.modal = null;
        this.$nextTick(() => this.focusSearch());
    },
    onKey(e) {
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target?.tagName) && e.target !== this.$refs.search;
        const searchEmpty = !this.query;

        switch (e.key) {
            case 'F2': e.preventDefault(); this.closeModal(); this.focusSearch(); return;
            case 'F4': e.preventDefault(); this.openCustomer(); return;
            case 'F8': e.preventDefault(); if (!this.modal) this.holdBill(); return;
            case 'F9': e.preventDefault(); if (!this.modal && this.config.canDiscount) { this.$refs.discount?.focus(); this.$refs.discount?.select(); } return;
            case 'F10': e.preventDefault(); this.modal === 'pay' ? this.checkout() : (!this.modal && this.openPay()); return;
            case 'Escape':
                if (this.showResults) { this.showResults = false; return; }
                if (this.modal) { e.preventDefault(); this.closeModal(); }
                return;
            case 'Enter':
                if (this.modal === 'success') { e.preventDefault(); this.newSale(); return; }
                if (this.modal === 'pay' && !e.target?.closest?.('[data-no-enter]')) { e.preventDefault(); this.checkout(); }
                return;
        }

        if (this.modal || typing || !searchEmpty) return;
        const last = this.lastItem();
        if (!last) return;
        if (e.key === '+' || e.key === '=') { e.preventDefault(); this.changeQty(last, 1); }
        else if (e.key === '-') { e.preventDefault(); this.changeQty(last, -1); }
        else if (e.key === 'Delete') { e.preventDefault(); this.removeItem(last); }
    },
});
