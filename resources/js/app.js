import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';
import { createIcons, Cross, LayoutDashboard, Files, Inbox, UsersRound, ChartNoAxesCombined, Grid2x2, ListChecks, Database, Mail, Archive, PanelsTopLeft, Headset, ArrowUpRight, Info, Menu, LogOut, LogIn, ArrowRight, ShieldCheck, Check, UserRoundPlus, ScanLine, FlaskConical, FolderKey, Laptop, AppWindow, BadgePlus, ArrowRightLeft, UserRoundMinus, ShieldAlert, CircleCheck, CircleAlert, Search, ArrowLeft, UserRound, FileDown, Plus, X, Printer, Download, Presentation, FileText, HeartHandshake, LockKeyhole, HeartPulse, BedDouble, FileSearch, Pill, Contact, MessageSquare, FileHeart, Images, KeyRound, MailWarning, Route, MonitorSmartphone, Clock3, Send } from 'lucide';
const icons = { Cross, LayoutDashboard, Files, Inbox, UsersRound, ChartNoAxesCombined, Grid2x2, ListChecks, Database, Mail, Archive, PanelsTopLeft, Headset, ArrowUpRight, Info, Menu, LogOut, LogIn, ArrowRight, ShieldCheck, Check, UserRoundPlus, ScanLine, FlaskConical, FolderKey, Laptop, AppWindow, BadgePlus, ArrowRightLeft, UserRoundMinus, ShieldAlert, CircleCheck, CircleAlert, Search, ArrowLeft, UserRound, FileDown, Plus, X, Printer, Download, Presentation, FileText, HeartHandshake, LockKeyhole, HeartPulse, BedDouble, FileSearch, Pill, Contact, MessageSquare, FileHeart, Images, KeyRound, MailWarning, Route, MonitorSmartphone, Clock3, Send };

Alpine.data('staffSearch', () => ({
    open: false, results: [], message: '', controller: null,
    async search(query) {
        this.controller?.abort();
        if (query.trim().length < 2) { this.open = false; return; }
        this.controller = new AbortController();
        this.open = true; this.results = []; this.message = 'Searching the directory…';
        try {
            const response = await fetch(`/directory/search?query=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
            if (!response.ok) throw new Error('Directory search is unavailable. Enter the full name manually.');
            this.results = await response.json(); this.message = 'No matches. You can enter the full name manually.';
        } catch (error) { if (error.name !== 'AbortError') this.message = error.message; }
    },
    choose(person) { this.$el.querySelector('input').value = person.name; this.open = false; },
    destroy() { this.controller?.abort(); },
}));

Alpine.data('signaturePad', () => ({
    pad: null, empty: true, observer: null,
    init() {
        this.pad = new SignaturePad(this.$refs.canvas, { penColor: '#173348', minWidth: 1, maxWidth: 2.3 });
        this.pad.addEventListener('endStroke', () => this.sync());
        const resize = () => {
            const canvas = this.$refs.canvas;
            const strokes = this.pad.toData();
            const ratio = Math.min(Math.max(window.devicePixelRatio || 1, 1), 2);
            canvas.width = canvas.offsetWidth * ratio; canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            this.pad.clear(); if (strokes.length) this.pad.fromData(strokes); this.sync();
        };
        this.observer = new ResizeObserver(resize); this.observer.observe(this.$refs.canvas);
        this.$nextTick(resize);
    },
    sync() { this.empty = this.pad.isEmpty(); this.$refs.value.value = this.empty ? '' : this.pad.toDataURL('image/png'); },
    clear() { this.pad.clear(); this.sync(); },
    destroy() { this.observer?.disconnect(); this.pad?.off(); },
}));

Alpine.data('attendees', (initial = ['']) => ({
    rows: initial.map((name, id) => ({ id, name })), nextId: initial.length,
    add() { this.rows.push({ id: this.nextId++, name: '' }); this.$nextTick(() => { const inputs = this.$el.querySelectorAll('[name="user_name[]"]'); inputs[inputs.length - 1]?.focus(); }); },
    remove(index) { if (this.rows.length > 1) this.rows.splice(index, 1); },
}));

window.Alpine = Alpine;
Alpine.start();
createIcons({ icons });
document.addEventListener('alpine:initialized', () => createIcons({ icons }));
