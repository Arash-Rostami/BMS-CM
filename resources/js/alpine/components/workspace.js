const STORAGE_KEY = 'user_shortcuts';

export default function workspace(config = {}) {
    return {
        modules: Array.isArray(config.modules) ? config.modules : [],
        stats: config.stats || {},
        recordsUrl: config.recordsUrl || '',

        pinnedModuleIds: [],
        recordPins: [],

        modulesOpen: false,
        recordsOpen: false,

        pickerResource: '',
        pickerTheme: 'from-slate-500 to-slate-600',
        recordQuery: '',
        recordResults: [],
        recordLoading: false,
        recordError: false,
        recordReqId: 0,

        editingKey: null,
        editingLabel: '',

        init() {
            const {modules, records} = this.readStorage();
            const valid = new Set(this.modules.map(m => m.id));
            this.pinnedModuleIds = modules.filter(id => valid.has(id));
            this.recordPins = records.map(p => this.decorateRecord(p));

            this.modulesOpen = this.pinnedModuleIds.length > 0;
            this.recordsOpen = this.recordPins.length > 0;

            const first = this.modules.find(m => m.searchable);
            if (first) {
                this.pickerResource = first.id;
                this.pickerTheme = first.theme;
            }

            this.$watch('recordsOpen', open => {
                if (open && this.pickerResource && this.recordResults.length === 0) this.searchRecords();
            });
        },

        readStorage() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return {modules: [], records: []};
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    return {modules: parsed.map(s => s && s.id).filter(Boolean), records: []};
                }
                return {
                    modules: Array.isArray(parsed.modules) ? parsed.modules : [],
                    records: Array.isArray(parsed.records) ? parsed.records : [],
                };
            } catch (e) {
                return {modules: [], records: []};
            }
        },

        persist() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    modules: this.pinnedModuleIds,
                    records: this.recordPins
                }));
            } catch (e) {
            }
        },

        searchableModules() {
            return this.modules.filter(m => m.searchable);
        },
        pinnedModules() {
            return this.modules.filter(m => this.pinnedModuleIds.includes(m.id));
        },
        unpinnedModules() {
            return this.modules.filter(m => !this.pinnedModuleIds.includes(m.id));
        },
        pinModule(id) {
            if (!this.pinnedModuleIds.includes(id)) {
                this.pinnedModuleIds.push(id);
                this.persist();
            }
        },
        unpinModule(id) {
            this.pinnedModuleIds = this.pinnedModuleIds.filter(x => x !== id);
            this.persist();
        },
        moduleStat(id) {
            return this.stats[id] || 0;
        },

        decorateRecord(p) {
            const parent = this.modules.find(m => m.id === p.resourceId) || {};
            return {
                key: p.key, resourceId: p.resourceId, recordId: p.recordId,
                label: p.label, subtitle: p.subtitle, url: p.url,
                icon: parent.icon || p.icon || '',
                theme: parent.theme || p.theme || 'from-slate-500 to-slate-600',
            };
        },
        selectResource(id) {
            const m = this.modules.find(x => x.id === id) || {};
            this.pickerResource = id;
            this.pickerTheme = m.theme || 'from-slate-500 to-slate-600';
            this.recordQuery = '';
            this.recordResults = [];
            this.searchRecords();
        },
        async searchRecords() {
            if (!this.pickerResource) {
                this.recordResults = [];
                return;
            }
            const reqId = ++this.recordReqId;
            this.recordLoading = true;
            this.recordError = false;
            const url = this.recordsUrl.replace('__RES__', this.pickerResource) + '?q=' + encodeURIComponent(this.recordQuery || '');
            try {
                const r = await fetch(url, {
                    headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    credentials: 'same-origin'
                });
                if (!r.ok) throw r;
                const json = await r.json();
                if (reqId === this.recordReqId) this.recordResults = Array.isArray(json?.data) ? json.data : [];
            } catch (e) {
                if (reqId === this.recordReqId) {
                    this.recordError = true;
                    this.recordResults = [];
                }
            } finally {
                if (reqId === this.recordReqId) this.recordLoading = false;
            }
        },
        isRecordPinned(key) {
            return this.recordPins.some(p => p.key === key);
        },
        addRecord(rec) {
            if (this.recordPins.some(p => p.key === rec.key)) return;
            this.recordPins.push(this.decorateRecord(rec));
            this.persist();
        },
        removeRecord(key) {
            this.recordPins = this.recordPins.filter(p => p.key !== key);
            this.persist();
        },
        renameRecord(key, newLabel) {
            const trimmed = (newLabel || '').trim();
            const pin = this.recordPins.find(p => p.key === key);
            if (pin && trimmed) {
                pin.label = trimmed;
                this.persist();
            }
            this.editingKey = null;
        },

        initials(value) {
            const text = (value || '').toString().trim();
            if (!text) return '#';
            const parts = text.split(/[\s\-_/.]+/).filter(Boolean);
            if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
            const alnum = text.replace(/[^a-zA-Z0-9]/g, '');
            return (alnum.slice(0, 2) || '#').toUpperCase();
        },
    };
}
