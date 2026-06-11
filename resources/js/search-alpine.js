export default function search() {
    return {
        searchQuery: '',
        isSearching: false,
        results: [],
        selectedResult: null,
        byUser: null,
        breadcrumb: {
            purchaseRequest: 'upcoming',
            proformaInvoice: 'upcoming',
            purchaseOrder: 'upcoming',
            registeredOrder: 'upcoming',
            bankProfile: 'upcoming',
            payment: 'upcoming',
            shipment: 'upcoming',
            custom: 'upcoming'
        },
        C: 2 * Math.PI * 16,
        Cl: 2 * Math.PI * 22,

        async performSearch() {
            if (this.searchQuery.length < 2) {
                this.results = [];
                this.selectedResult = null;
                this.byUser = null;
                return;
            }

            this.isSearching = true;
            this.selectedResult = null;

            try {
                const r = await axios.get('/api/search/spotlight?q=' + encodeURIComponent(this.searchQuery));
                this.results = r.data.results || [];
                this.byUser = r.data.by_user || null;
                if (r.data.breadcrumb) this.breadcrumb = r.data.breadcrumb;
            } catch {
                this.results = [];
            } finally {
                this.isSearching = false;
            }
        },

        getOffset(p) {
            return this.C - (p / 100) * this.C;
        },

        getOffsetL(p) {
            return this.Cl - (p / 100) * this.Cl;
        },

        breadcrumbStages() {
            const keys = Object.keys(this.breadcrumb);
            return keys.map((key, index) => ({
                key,
                state: this.breadcrumb[key],
                label: key.replace(/([A-Z])/g, ' $1').trim(),
                isLast: index === keys.length - 1
            }));
        }
    };
}
