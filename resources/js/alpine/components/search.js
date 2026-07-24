const DEFAULT_BREADCRUMB = {
    purchaseRequest: {state: 'upcoming', label: 'Purchase Request'},
    proformaInvoice: {state: 'upcoming', label: 'Proforma Invoice'},
    purchaseOrder: {state: 'upcoming', label: 'Purchase Order'},
    registeredOrder: {state: 'upcoming', label: 'Registered Order'},
    bankProfile: {state: 'upcoming', label: 'Bank Profile'},
    payment: {state: 'upcoming', label: 'Payment'},
    shipment: {state: 'upcoming', label: 'Shipment'},
    custom: {state: 'upcoming', label: 'Custom'}
};
const CIRCUMFERENCE = 2 * Math.PI * 16;
const CIRCUMFERENCE_L = 2 * Math.PI * 22;

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

        async performSearch() {
            if (this.searchQuery.length < 2) {
                this.results = [];
                this.selectedResult = null;
                this.byUser = null;
                return;
            }

            this.isSearching = true;
            this.selectedResult = null;
            this.chain = [];
            this.chainError = false;

            try {
                const r = await axios.get('/api/search/spotlight?q=' + encodeURIComponent(this.searchQuery));
                this.results = r.data.results || [];
                this.byUser = r.data.by_user || null;
            } catch {
                this.results = [];
            } finally {
                this.isSearching = false;
            }
        },

        async selectResult(result) {
            this.selectedResult = result;
            this.chain = [];
            this.chainError = false;
            if (!result || !result.type || !result.id) return;

            this.chainLoading = true;
            try {
                const r = await axios.get(
                    '/api/search/chain?type=' + encodeURIComponent(result.type) + '&id=' + encodeURIComponent(result.id)
                );
                this.chain = r.data.chain || [];
                if (r.data.breadcrumb) this.breadcrumb = r.data.breadcrumb;
            } catch {
                this.chain = [];
                this.chainError = true;
            } finally {
                this.chainLoading = false;
            }
        },

        clearSelected() {
            this.selectedResult = null;
            this.chain = [];
            this.chainError = false;
        },

        _offset(p, circumference) {
            return circumference - (p / 100) * circumference;
        },
        getOffset(p) {
            return this._offset(p, this.C);
        },
        getOffsetL(p) {
            return this._offset(p, this.Cl);
        },

        breadcrumbStages() {
            const entries = Object.entries(this.breadcrumb);
            return entries.map(([key, {state, label}], i) => ({
                key, state, label, isLast: i === entries.length - 1
            }));
        }
    };
}
