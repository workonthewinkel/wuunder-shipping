/**
 * ShippingMethods Component
 * Handles WooCommerce shipping method settings
 */
class ShippingMethods {
    constructor() {
        this.init();
    }

    init() {
        $(document).ready(() => {
            this.bindEvents();
        });
    }

    bindEvents() {
        // Listen for WooCommerce modal loading
        $(document.body).on('wc_backbone_modal_loaded', (e, target) => {
            if (target === 'wc-modal-shipping-method-settings') {
                this.initializeShippingMethodSettings();
            }
        });
    }

    initializeShippingMethodSettings() {
        const $carrierSelect = $('#woocommerce_wuunder_shipping_wuunder_carrier');
        const $titleField = $('#woocommerce_wuunder_shipping_title');
        
        if ($carrierSelect.length && $titleField.length) {
            $carrierSelect.on('change', () => {
                this.handleCarrierChange($carrierSelect, $titleField);
            });
        }
    }

    handleCarrierChange($carrierSelect, $titleField) {
        const selectedText = $carrierSelect.find('option:selected').text();
        const currentTitle = $titleField.val();
        
        // Get all carrier option values
        const carrierValues = this.getCarrierValues($carrierSelect);
        
        // Update title if empty or if current value matches one of the carrier options
        if (this.shouldUpdateTitle(currentTitle, carrierValues)) {
            $titleField.val(selectedText);
        }
    }

    getCarrierValues($carrierSelect) {
        const carrierValues = [];
        $carrierSelect.find('option').each(function() {
            if ($(this).val()) {
                carrierValues.push($(this).text());
            }
        });
        return carrierValues;
    }

    shouldUpdateTitle(currentTitle, carrierValues) {
        return !currentTitle || 
               currentTitle.trim() === '' || 
               carrierValues.includes(currentTitle);
    }
}

export default ShippingMethods;
