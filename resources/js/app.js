import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import TomSelect from 'tom-select';
import Swal from 'sweetalert2';
import posApp from './pos';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.TomSelect = TomSelect;
window.Swal = Swal;

const storage = {
    get(key, fallback = null) {
        try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; }
    },
    set(key, value) {
        try { localStorage.setItem(key, value); } catch { /* private mode */ }
    },
};

/* ---------- Global stores ---------- */
document.addEventListener('alpine:init', () => {
    Alpine.store('sidebar', {
        collapsed: storage.get('sidebar-collapsed') === '1',
        mobileOpen: false,
        toggle() {
            if (window.innerWidth < 1024) {
                this.mobileOpen = !this.mobileOpen;
                return;
            }
            this.collapsed = !this.collapsed;
            storage.set('sidebar-collapsed', this.collapsed ? '1' : '0');
        },
    });

    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            storage.set('theme', this.dark ? 'dark' : 'light');
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: this.dark } }));
        },
    });

    Alpine.store('toasts', {
        items: [],
        add(type, message, timeout = 4000) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type, message });
            setTimeout(() => this.remove(id), timeout);
        },
        remove(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    /* Searchable select: <select x-data x-tomselect> */
    Alpine.directive('tomselect', (el, { expression }, { evaluate, cleanup }) => {
        const options = expression ? evaluate(expression) : {};
        const instance = new TomSelect(el, {
            allowEmptyOption: true,
            maxOptions: 200,
            plugins: el.multiple ? ['remove_button'] : [],
            ...options,
        });
        el.addEventListener('ts-sync', () => instance.sync());
        cleanup(() => instance.destroy());
    });
});

window.toast = (type, message) => Alpine.store('toasts').add(type, message);

/* ---------- Confirm dialogs: <form data-confirm="Delete this branch?"> ---------- */
window.confirmAction = (options = {}) => Swal.fire({
    title: options.title ?? 'Are you sure?',
    text: options.text ?? 'This action cannot be undone.',
    icon: options.icon ?? 'warning',
    showCancelButton: true,
    confirmButtonText: options.confirmText ?? 'Yes, continue',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusCancel: true,
    confirmButtonColor: options.danger === false ? '#4F46E5' : '#EF4444',
    cancelButtonColor: '#94A3B8',
}).then((result) => result.isConfirmed);

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) {
        return;
    }
    event.preventDefault();
    const ok = await window.confirmAction({
        title: form.dataset.confirm,
        text: form.dataset.confirmText,
        confirmText: form.dataset.confirmButton,
        danger: form.dataset.confirmDanger !== 'false',
    });
    if (ok) {
        form.dataset.confirmed = '1';
        form.requestSubmit ? form.requestSubmit() : form.submit();
    }
});

/* Disable submit buttons and show spinner while a form is posting. */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    const outside = form.id ? document.querySelectorAll(`button[form="${form.id}"][data-loading]`) : [];
    [...form.querySelectorAll('button[type=submit][data-loading]'), ...outside].forEach((btn) => {
        btn.setAttribute('data-busy', '1');
        btn.disabled = true;
    });
});

/* ---------- Charts: shared brand options ---------- */
window.chartTheme = () => {
    const dark = document.documentElement.classList.contains('dark');
    return {
        chart: { fontFamily: 'Inter, system-ui, sans-serif', toolbar: { show: false }, background: 'transparent', foreColor: dark ? '#94A3B8' : '#64748B' },
        colors: ['#4F46E5', '#10B981', '#F59E0B', '#0EA5E9', '#EF4444', '#8B5CF6'],
        grid: { borderColor: dark ? '#1F2937' : '#EEF2F7', strokeDashArray: 4, xaxis: { lines: { show: false } } },
        theme: { mode: dark ? 'dark' : 'light' },
        dataLabels: { enabled: false },
        tooltip: { theme: dark ? 'dark' : 'light' },
    };
};

window.formatMoney = (value, symbol = 'Rs') => {
    const n = Number(value) || 0;
    const abs = Math.abs(n);
    const text = Number.isInteger(Math.round(abs * 100) / 100) ? Math.round(abs).toLocaleString('en-US') : abs.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${n < 0 ? '-' : ''}${symbol} ${text}`; // same shape as PHP money(): -Rs 1,050
};

/* Alpine component for any ApexCharts chart. Re-renders on theme change. */
document.addEventListener('alpine:init', () => {
    Alpine.data('apexChart', (options) => ({
        chart: null,
        init() {
            this.render();
            window.addEventListener('theme-changed', () => this.render());
        },
        render() {
            this.chart?.destroy();
            const base = window.chartTheme();
            const merged = {
                ...base,
                ...options,
                chart: { ...base.chart, ...(options.chart ?? {}) },
                grid: { ...base.grid, ...(options.grid ?? {}) },
                tooltip: { ...base.tooltip, ...(options.tooltip ?? {}) },
            };
            if (options.moneyDonut) {
                merged.tooltip.y = { formatter: (v) => window.formatMoney(v) };
            }
            if (options.moneyTooltip) {
                merged.tooltip.y = { formatter: (v) => window.formatMoney(v) };
                merged.yaxis = { ...(options.yaxis ?? {}), labels: { formatter: (v) => (Math.abs(v) >= 1000 ? `${Math.round(v / 1000)}k` : Math.round(v)) } };
            }
            delete merged.moneyTooltip; // our own flags, not ApexCharts options
            delete merged.moneyDonut;
            this.chart = new ApexCharts(this.$el, merged);
            this.chart.render();
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});

document.addEventListener('alpine:init', () => Alpine.data('posApp', posApp));

/* ---------- Command palette (Ctrl/Cmd + K) ---------- */
document.addEventListener('alpine:init', () => {
    Alpine.data('commandPalette', (items, searchUrl) => ({
        open: false,
        query: '',
        index: 0,
        items,
        remote: [],
        loading: false,
        timer: null,
        get results() {
            const q = this.query.trim().toLowerCase();
            const pages = q ? this.items.filter((i) => `${i.label} ${i.group}`.toLowerCase().includes(q)) : this.items;
            return [...pages.map((p) => ({ ...p, group: `Page · ${p.group}` })), ...this.remote];
        },
        search() {
            this.index = 0;
            clearTimeout(this.timer);
            const q = this.query.trim();
            if (q.length < 2 || !searchUrl) { this.remote = []; return; }
            this.loading = true;
            this.timer = setTimeout(async () => {
                try {
                    const data = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } }).then((r) => r.json());
                    if (this.query.trim() === q) this.remote = data;
                } catch { this.remote = []; } finally { this.loading = false; }
            }, 200);
        },
        show() {
            this.open = true;
            this.query = '';
            this.remote = [];
            this.index = 0;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        move(step) {
            const count = this.results.length;
            if (count) this.index = (this.index + step + count) % count;
        },
        go() {
            const item = this.results[this.index];
            if (item) window.location.href = item.url;
        },
    }));
});

Alpine.start();
