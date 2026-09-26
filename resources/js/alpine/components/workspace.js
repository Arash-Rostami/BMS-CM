const STORAGE_KEY = 'user_shortcuts';
const DEFAULT_THEME = 'from-slate-500 to-slate-600';
const REGEX_SPLIT = /[\s\-_/.]+/;
const REGEX_ALNUM = /[^a-zA-Z0-9]/g;

export default function workspace(config = {}) {
    const rawModules = Array.isArray(config.modules) ? config.modules : [];
    const rawStats = config.stats || {};
    const rawRecordsUrl = config.recordsUrl || '';

    const moduleMap = new Map();
    const searchableMods = [];

    for (let i = 0; i < rawModules.length; i++) {
        const m = rawModules[i];
        moduleMap.set(m.id, m);
        if (m.searchable) searchableMods.push(m);
    }

    return {
        pinnedModuleIds: [],
        recordPins: [],

        modulesOpen: false,
        recordsOpen: false,

        pickerResource: '',
        pickerTheme: DEFAULT_THEME,
        recordQuery: '',
        recordResults: [],
        recordLoading: false,
        recordError: false,

        editingKey: null,
        editingLabel: '',

        _abortCtrl: null,

        get modules() { return rawModules; },
        get stats() { return rawStats; },
        get recordsUrl() { return rawRecordsUrl; },

        init() {
            const { modules: savedMods, records: savedRecs } = this.readStorage();

            const validPins = [];
            for (let i = 0; i < savedMods.length; i++) {
                if (moduleMap.has(savedMods[i])) validPins.push(savedMods[i]);
            }
            this.pinnedModuleIds = validPins;

            const validRecords = [];
            for (let i = 0; i < savedRecs.length; i++) {
                validRecords.push(this.decorateRecord(savedRecs[i]));
            }
            this.recordPins = validRecords;

            this.modulesOpen = this.pinnedModuleIds.length > 0;
            this.recordsOpen = this.recordPins.length > 0;

            if (searchableMods.length > 0) {
                const first = searchableMods[0];
                this.pickerResource = first.id;
                this.pickerTheme = first.theme || DEFAULT_THEME;
            }

            this.$watch('recordsOpen', open => {
                if (open && this.pickerResource && this.recordResults.length === 0) {
                    this.searchRecords();
                }
            });
        },

        readStorage() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return { modules: [], records: [] };

                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    const validMods = [];
                    for (let i = 0; i < parsed.length; i++) {
                        if (parsed[i] && parsed[i].id) validMods.push(parsed[i].id);
                    }
                    return { modules: validMods, records: [] };
                }

                return {
                    modules: Array.isArray(parsed.modules) ? parsed.modules : [],
                    records: Array.isArray(parsed.records) ? parsed.records : [],
                };
            } catch (e) {
                return { modules: [], records: [] };
            }
        },

        persist() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    modules: this.pinnedModuleIds,
                    records: this.recordPins
                }));
            } catch (e) {}
        },

        searchableModules() {
            return searchableMods;
        },

        pinnedModules() {
            const out = [];
            for (let i = 0; i < this.pinnedModuleIds.length; i++) {
                const m = moduleMap.get(this.pinnedModuleIds[i]);
                if (m) out.push(m);
            }
            return out;
        },

        unpinnedModules() {
            const pinned = new Set(this.pinnedModuleIds);
            const out = [];
            for (let i = 0; i < rawModules.length; i++) {
                if (!pinned.has(rawModules[i].id)) out.push(rawModules[i]);
            }
            return out;
        },

        pinModule(id) {
            if (this.pinnedModuleIds.indexOf(id) === -1) {
                this.pinnedModuleIds.push(id);
                this.persist();
            }
        },

        unpinModule(id) {
            const idx = this.pinnedModuleIds.indexOf(id);
            if (idx !== -1) {
                this.pinnedModuleIds.splice(idx, 1);
                this.persist();
            }
        },

        moduleStat(id) {
            return rawStats[id] || 0;
        },

        decorateRecord(p) {
            const parent = moduleMap.get(p.resourceId) || {};
            return {
                key: p.key,
                resourceId: p.resourceId,
                recordId: p.recordId,
                label: p.label,
                subtitle: p.subtitle,
                url: p.url,
                icon: parent.icon || p.icon || '',
                theme: parent.theme || p.theme || DEFAULT_THEME,
            };
        },

        selectResource(id) {
            const m = moduleMap.get(id) || {};
            this.pickerResource = id;
            this.pickerTheme = m.theme || DEFAULT_THEME;
            this.recordQuery = '';
            this.recordResults = [];
            this.searchRecords();
        },

        async searchRecords() {
            if (this._abortCtrl) this._abortCtrl.abort();

            if (!this.pickerResource) {
                this.recordResults = [];
                return;
            }

            this._abortCtrl = new AbortController();
            this.recordLoading = true;
            this.recordError = false;

            const url = rawRecordsUrl.replace('__RES__', this.pickerResource) + '?q=' + encodeURIComponent(this.recordQuery || '');

            try {
                const r = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: this._abortCtrl.signal
                });

                if (!r.ok) throw r;
                const json = await r.json();

                this.recordResults = Array.isArray(json?.data) ? json.data : [];
            } catch (e) {
                if (e.name === 'AbortError') return;
                this.recordError = true;
                this.recordResults = [];
            } finally {
                if (this._abortCtrl && !this._abortCtrl.signal.aborted) {
                    this.recordLoading = false;
                }
            }
        },

        isRecordPinned(key) {
            return this.recordPins.findIndex(p => p.key === key) !== -1;
        },

        addRecord(rec) {
            if (this.isRecordPinned(rec.key)) return;
            this.recordPins.push(this.decorateRecord(rec));
            this.persist();
        },

        removeRecord(key) {
            const idx = this.recordPins.findIndex(p => p.key === key);
            if (idx !== -1) {
                this.recordPins.splice(idx, 1);
                this.persist();
            }
        },

        renameRecord(key, newLabel) {
            const trimmed = (newLabel || '').trim();
            if (trimmed) {
                const pin = this.recordPins.find(p => p.key === key);
                if (pin) {
                    pin.label = trimmed;
                    this.persist();
                }
            }
            this.editingKey = null;
        },

        initials(value) {
            const text = (value || '').toString().trim();
            if (!text) return '#';

            const parts = text.split(REGEX_SPLIT).filter(Boolean);
            if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();

            const alnum = text.replace(REGEX_ALNUM, '');
            return (alnum.slice(0, 2) || '#').toUpperCase();
        },
    };
}
