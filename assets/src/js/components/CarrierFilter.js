/**
 * CarrierFilter Component
 * Handles filtering wuunder-carriers-table rows by multiple criteria
 */
class CarrierFilter {
    constructor() {
        this.searchInput = '#wuunder_carrier_search';
        this.carrierFilterSelect = '#wuunder_carrier_filter';
        this.selectAllCheckbox = '#wuunder-select-all-carriers';
        this.carrierCheckboxes = '.wuunder-carrier-checkbox';
        this.tableRows = '.wuunder-carriers-table tbody tr';
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
            this.applyFilters();
            this.updateSelectAllCheckbox();
        });
    }

    updateUrl() {
        const selectedCarrier = $(this.carrierFilterSelect).val();
        const url = new URL(window.location);
        if (selectedCarrier) {
            url.searchParams.set('carrier_filter', selectedCarrier);
        } else {
            url.searchParams.delete('carrier_filter');
        }
        window.history.replaceState({}, '', url);
    }

    bindEvents() {
        // Search input
        $(this.searchInput).on('input', () => this.applyFilters());

        // Carrier filter dropdown
        $(this.carrierFilterSelect).on('change', () => {
            this.applyFilters();
            this.uncheckSelectAll();
            this.updateUrl();
        });

        // Select all checkbox
        $(this.selectAllCheckbox).on('change', (e) => this.handleSelectAll(e));

        // Individual carrier checkboxes
        $(document).on('change', this.carrierCheckboxes, () => {
            this.updateSelectAllCheckbox();
        });

        // Row click to toggle checkbox
        $(document).on('click', this.tableRows, (e) => this.handleRowClick(e));
    }

    applyFilters() {
        const searchQuery = $(this.searchInput).val().toLowerCase();
        const selectedCarrier = $(this.carrierFilterSelect).val();
        const $rows = $(this.tableRows);

        $rows.each(function() {
            const $row = $(this);
            let showRow = true;

            // Filter by carrier or enabled status
            if (selectedCarrier && selectedCarrier !== '') {
                if (selectedCarrier === 'enabled') {
                    const isEnabled = $row.find('.wuunder-carrier-checkbox').is(':checked');
                    if (!isEnabled) {
                        showRow = false;
                    }
                } else {
                    const carrierCode = $row.data('carrier-code');
                    if (carrierCode !== selectedCarrier) {
                        showRow = false;
                    }
                }
            }

            // Filter by search query
            if (searchQuery && searchQuery !== '') {
                const carrierName = $row.data('carrier-name') || '';
                const productName = $row.data('product-name') || '';
                const description = $row.data('description') || '';
                const tags = ($row.data('tags') || '').toLowerCase();

                const searchableText = `${carrierName} ${productName} ${description} ${tags}`;
                if (!searchableText.includes(searchQuery)) {
                    showRow = false;
                }
            }

            if (showRow) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }

    handleSelectAll(e) {
        const isChecked = $(e.target).is(':checked');
        const $visibleCheckboxes = $(this.tableRows).filter(':visible').find(this.carrierCheckboxes);

        $visibleCheckboxes.prop('checked', isChecked);
    }

    updateSelectAllCheckbox() {
        const $visibleCheckboxes = $(this.tableRows).filter(':visible').find(this.carrierCheckboxes);
        const totalVisible = $visibleCheckboxes.length;
        const totalChecked = $visibleCheckboxes.filter(':checked').length;

        if (totalVisible === 0) {
            $(this.selectAllCheckbox).prop('checked', false).prop('indeterminate', false);
        } else if (totalChecked === 0) {
            $(this.selectAllCheckbox).prop('checked', false).prop('indeterminate', false);
        } else if (totalChecked === totalVisible) {
            $(this.selectAllCheckbox).prop('checked', true).prop('indeterminate', false);
        } else {
            $(this.selectAllCheckbox).prop('checked', false).prop('indeterminate', true);
        }
    }

    uncheckSelectAll() {
        $(this.selectAllCheckbox).prop('checked', false).prop('indeterminate', false);
    }

    handleRowClick(e) {
        // Don't toggle if clicking on the checkbox itself or a label
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).is('label') || $(e.target).closest('label').length > 0) {
            return;
        }

        // Find the checkbox in this row
        const $checkbox = $(e.currentTarget).find(this.carrierCheckboxes);
        if ($checkbox.length) {
            // Toggle the checkbox
            $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
        }
    }
}

export default CarrierFilter;