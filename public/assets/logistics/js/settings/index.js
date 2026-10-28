function settingsSearch() {
    return {
        query: '',
        filter() {
            // handled reactively via x-show bindings
        },
        _matches(keywords) {
            if (!this.query.trim()) return true;
            const q = this.query.toLowerCase();
            return keywords.toLowerCase().split(' ').some(k => k.includes(q)) || q.split(' ').some(w => keywords.toLowerCase().includes(w));
        },
        sectionVisible(keywords) {
            return this._matches(keywords);
        },
        groupVisible(keywords) {
            return this._matches(keywords);
        },
        noResults() {
            const sections = document.querySelectorAll('[data-section]');
            return Array.from(sections).every(el => {
                const kw = el.getAttribute('data-section') || '';
                return !this._matches(kw);
            });
        }
    };
}
