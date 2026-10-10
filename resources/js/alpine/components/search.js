const DEFAULT_BREADCRUMB = {
    purchaseRequest: { state: 'upcoming', label: 'Purchase Request' },
    proformaInvoice: { state: 'upcoming', label: 'Proforma Invoice' },
    purchaseOrder: { state: 'upcoming', label: 'Purchase Order' },
    registeredOrder: { state: 'upcoming', label: 'Registered Order' },
    bankProfile: { state: 'upcoming', label: 'Bank Profile' },
    payment: { state: 'upcoming', label: 'Payment' },
    shipment: { state: 'upcoming', label: 'Shipment' },
    custom: { state: 'upcoming', label: 'Custom' }
};

const CIRCUMFERENCE = 2 * Math.PI * 16;
const CIRCUMFERENCE_L = 2 * Math.PI * 22;

const buildStages = (b) => {
    const entries = Object.entries(b);
    const max = entries.length - 1;
    return entries.map(([key, { state, label }], i) => ({
        key, state, label, isLast: i === max
    }));
};

const DEFAULT_STAGES = buildStages(DEFAULT_BREADCRUMB);

export default function search() {
    return {
        searchQuery: '',
        isSearching: false,
        results: [],
        selectedResult: null,
        byUser: null,
        chain: [],
        chainLoading: false,
        chainError: false,
        breadcrumb: DEFAULT_BREADCRUMB,
        C: CIRCUMFERENCE,
        Cl: CIRCUMFERENCE_L,

        _cachedStages: DEFAULT_STAGES,
        _searchCtrl: null,
        _chainCtrl: null,

        async performSearch() {
            if (this._searchCtrl) this._searchCtrl.abort();

            if (this.searchQuery.length < 2) {
                this.results = [];
                this.selectedResult = null;
                this.byUser = null;
                this.isSearching = false;
                return;
            }

            this.isSearching = true;
            this.selectedResult = null;
            this.chain = [];
            this.chainError = false;
            this._searchCtrl = new AbortController();
            const ctrl = this._searchCtrl;

            try {
                const r = await axios.get('/api/search/spotlight', {
                    params: { q: this.searchQuery },
                    signal: ctrl.signal
                });

                this.results = r.data?.results || [];
                this.byUser = r.data?.by_user || null;
            } catch (e) {
                if (ctrl.signal.aborted) return;
                this.results = [];
            } finally {
                if (!ctrl.signal.aborted) {
                    this.isSearching = false;
                }
            }
        },

        async selectResult(result) {
            if (this._chainCtrl) this._chainCtrl.abort();

            this.selectedResult = result;
            this.chain = [];
            this.chainError = false;

            if (!result || !result.type || !result.id) return;

            this.chainLoading = true;
            this._chainCtrl = new AbortController();
            const ctrl = this._chainCtrl;

            try {
                const r = await axios.get('/api/search/chain', {
                    params: { type: result.type, id: result.id },
                    signal: ctrl.signal
                });

                this.chain = r.data?.chain || [];

                if (r.data?.breadcrumb) {
                    this.breadcrumb = r.data.breadcrumb;
                    this._cachedStages = buildStages(this.breadcrumb);
                }
            } catch (e) {
                if (ctrl.signal.aborted) return;
                this.chain = [];
                this.chainError = true;
            } finally {
                if (!ctrl.signal.aborted) {
                    this.chainLoading = false;
                }
            }
        },

        clearSelected() {
            if (this._chainCtrl) this._chainCtrl.abort();
            this.selectedResult = null;
            this.chain = [];
            this.chainError = false;
        },

        getOffset(p) {
            return this.C * (1 - p / 100);
        },

        getOffsetL(p) {
            return this.Cl * (1 - p / 100);
        },

        breadcrumbStages() {
            return this._cachedStages;
        }
    };
}
