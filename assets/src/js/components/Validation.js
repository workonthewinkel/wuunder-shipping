/**
 * Validation Component
 * Handles validation for WooCommerce shipping method settings modal
 */
class Validation {
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
                this.initializeValidation();
            }
        });
    }

    initializeValidation() {
        const $saveButton = $('#btn-ok');
        const $carrierSelect = $('#woocommerce_wuunder_shipping_wuunder_carrier');
        const $carrierCheckboxes = $('[name^="woocommerce_wuunder_pickup_available_carriers"]');

        // No carrier fields means no carriers available - hide save button
        if (!$carrierSelect.length && !$carrierCheckboxes.length) {
            $saveButton.hide();
            return;
        }

        const $titleField = $('#woocommerce_wuunder_shipping_title');

        if ($carrierSelect.length && $titleField.length && $saveButton.length) {
            // Initial validation
            this.validateAndUpdateButton($carrierSelect, $titleField, $saveButton);
            
            // Bind validation events
            $carrierSelect.on('change', () => {
                this.validateAndUpdateButton($carrierSelect, $titleField, $saveButton);
            });
            
            $titleField.on('input change', () => {
                this.validateAndUpdateButton($carrierSelect, $titleField, $saveButton);
            });
        }
    }

    validateAndUpdateButton($carrierSelect, $titleField, $saveButton) {
        const isValid = this.isValid($carrierSelect, $titleField);
        
        // Update save button state
        $saveButton.prop('disabled', !isValid);
        
        // Show/hide error messages
        this.toggleErrorMessages($carrierSelect, $titleField, isValid);
        
        return isValid;
    }

    isValid($carrierSelect, $titleField) {
        const hasTitle = this.hasValidTitle($titleField);
        const hasCarrier = this.hasValidCarrier($carrierSelect);
        
        return hasTitle && hasCarrier;
    }

    hasValidTitle($titleField) {
        const title = $titleField.val();
        return title && title.trim() !== '';
    }

    hasValidCarrier($carrierSelect) {
        const carrier = $carrierSelect.val();
        return carrier && carrier !== '';
    }

    toggleErrorMessages($carrierSelect, $titleField, isValid) {
        // Remove existing error messages
        this.removeErrorMessages();
        
        if (isValid) {
            return; // No errors to show
        }
        
        // Show appropriate error messages
        if (!this.hasValidTitle($titleField)) {
            this.showFieldError($titleField, wuunder_admin.i18n.title_required || 'Title is required');
        }
        
        if (!this.hasValidCarrier($carrierSelect)) {
            this.showFieldError($carrierSelect, wuunder_admin.i18n.carrier_required || 'Carrier selection is required');
        }
    }

    showFieldError($field, message) {
        // Create error message element
        const $error = $(`<div class="woocommerce-error" style="color: #dc3232; font-size: 12px; margin-top: 5px;">${message}</div>`);
        
        // Add error class to field
        $field.addClass('woocommerce-invalid');
        
        // Insert error message after the field
        $field.after($error);
    }

    removeErrorMessages() {
        // Remove error messages
        $('.woocommerce-error').remove();
        
        // Remove error classes from fields
        $('.woocommerce-invalid').removeClass('woocommerce-invalid');
    }
}

export default Validation;
