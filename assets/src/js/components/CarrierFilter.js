/**
 * CarrierFilter Component
 * Handles filtering wuunder-carriers-table rows by data-carrier-code
 */
class CarrierFilter {
    constructor() {
        this.filterSelect = '#wuunder_carrier_filter';
        this.tableRows = '.wuunder-carriers-table tbody tr';
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
        });
    }

    bindEvents() {
        $(this.filterSelect).on('change', (e) => this.handleFilterChange(e));
    }

    handleFilterChange(e) {
        const selectedValue = $(e.target).find('option:selected').val();
        const $rows = $(this.tableRows);

        if (!selectedValue || selectedValue === '') {
            // Show all rows if nothing selected
            this.showAllRows($rows);
        } else {
            this.filterRowsByCarrier($rows, selectedValue);
        }
    }

    showAllRows($rows) {
        $rows.show();
    }

    filterRowsByCarrier($rows, selectedCarrierCode) {
        $rows.each(function() {
            const carrierCode = $(this).data('carrier-code');
            if (carrierCode == selectedCarrierCode) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
}

export default CarrierFilter;