/**
 * Wuunder Pick-up Point Checkout Integration
 */
(function($) {
    'use strict';

    const WuunderPickup = {
        selectedPickupPoint: null,
        modalOpen: false,

        init: function() {
            this.bindEvents();
            this.checkSelectedShipping();
        },

        bindEvents: function() {
            // Listen for shipping method changes
            $(document.body).on('change', 'input[name="shipping_method[0]"]', this.onShippingMethodChange.bind(this));
            $(document.body).on('updated_checkout', this.onCheckoutUpdated.bind(this));
            
            // Listen for pick-up button clicks
            $(document.body).on('click', '.wuunder-select-pickup-point', this.openPickupModal.bind(this));
            
            // Listen for messages from iframe
            window.addEventListener('message', this.handleIframeMessage.bind(this), false);
        },

        onShippingMethodChange: function() {
            this.checkSelectedShipping();
        },

        onCheckoutUpdated: function() {
            this.checkSelectedShipping();
        },

        checkSelectedShipping: function() {
            const selectedMethod = $('input[name="shipping_method[0]"]:checked').val();
            
            if (selectedMethod && selectedMethod.indexOf('wuunder_pickup') !== -1) {
                this.showPickupButton();
            } else {
                this.hidePickupButton();
            }
        },

        showPickupButton: function() {
            const $shippingMethod = $('input[name="shipping_method[0]"]:checked').closest('li');
            
            // Remove any existing button
            $('.wuunder-pickup-container').remove();
            
            // Get shipping method data
            const methodData = this.getMethodData();
            
            // Create button container
            const buttonHtml = `
                <div class="wuunder-pickup-container">
                    <button type="button" class="button wuunder-select-pickup-point">
                        ${wuunder_checkout.i18n.select_pickup_point}
                    </button>
                    <div class="wuunder-selected-pickup-point" style="display: none;">
                        <span class="pickup-point-info"></span>
                        <a href="#" class="wuunder-change-pickup-point">${wuunder_checkout.i18n.change}</a>
                    </div>
                </div>
            `;
            
            $shippingMethod.append(buttonHtml);
            
            // Restore selected pickup point if exists
            if (this.selectedPickupPoint) {
                this.displaySelectedPickupPoint();
            }
        },

        hidePickupButton: function() {
            $('.wuunder-pickup-container').remove();
        },

        getMethodData: function() {
            const $checkedInput = $('input[name="shipping_method[0]"]:checked');
            const methodId = $checkedInput.val();
            
            // Try to get data from hidden fields or data attributes
            const $methodLi = $checkedInput.closest('li');
            
            return {
                carriers: $methodLi.data('carriers') || 'dhl,postnl,ups',
                primaryColor: $methodLi.data('primary-color') || '52ba69',
                language: $methodLi.data('language') || 'nl'
            };
        },

        openPickupModal: function(e) {
            e.preventDefault();
            
            if (this.modalOpen) {
                return;
            }
            
            this.modalOpen = true;
            
            // Get customer address
            const address = this.getCustomerAddress();
            
            // Get method configuration
            const methodData = this.getMethodData();
            
            // Build iframe URL
            const iframeUrl = this.buildIframeUrl(address, methodData);
            
            // Create and show modal
            this.createModal(iframeUrl);
        },

        getCustomerAddress: function() {
            // Get address from checkout form
            const street = $('#billing_address_1').val() || '';
            const city = $('#billing_city').val() || '';
            const postcode = $('#billing_postcode').val() || '';
            const country = $('#billing_country').val() || 'NL';
            
            // Try shipping address if billing is empty
            if (!city && $('#ship-to-different-address-checkbox').is(':checked')) {
                return {
                    street: $('#shipping_address_1').val() || '',
                    city: $('#shipping_city').val() || '',
                    postcode: $('#shipping_postcode').val() || '',
                    country: $('#shipping_country').val() || 'NL'
                };
            }
            
            return {
                street: street,
                city: city,
                postcode: postcode,
                country: country
            };
        },

        buildIframeUrl: function(address, methodData) {
            const baseUrl = 'https://my.wearewuunder.com/parcelshop_locator/iframe';
            
            // Format address for iframe
            const addressString = [
                address.street,
                address.postcode,
                address.city,
                address.country
            ].filter(Boolean).join(', ');
            
            const params = new URLSearchParams({
                address: addressString || 'Netherlands',
                availableCarriers: methodData.carriers,
                primary_color: methodData.primaryColor.replace('#', ''),
                language: methodData.language
            });
            
            return `${baseUrl}?${params.toString()}`;
        },

        createModal: function(iframeUrl) {
            // Remove any existing modal
            $('#wuunder-pickup-iframe-container').remove();
            
            const modalHtml = `
                <div id="wuunder-pickup-iframe-container" class="wuunder-pickup-modal-overlay">
                    <div class="wuunder-pickup-modal">
                        <iframe 
                            id="wuunder-pickup-iframe" 
                            src="${iframeUrl}"
                            class="wuunder-pickup-iframe"
                            title="${wuunder_checkout.i18n.select_pickup_location}">
                        </iframe>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        },

        closeModal: function() {
            $('#wuunder-pickup-iframe-container').remove();
            document.body.style.overflow = '';
            this.modalOpen = false;
        },

        handleIframeMessage: function(event) {
            // Verify origin
            if (event.origin !== 'https://my.wearewuunder.com') {
                return;
            }
            
            // Handle pickup point selection
            if (event.data && event.data.type === 'servicePointPickerSelected') {
                // Transform the data to our expected format
                const pickupPoint = this.transformPickupPointData(event.data);
                this.selectedPickupPoint = pickupPoint;
                
                // Store in hidden field for form submission
                this.storePickupPoint(pickupPoint);
                
                // Display selected pickup point
                this.displaySelectedPickupPoint();
                
                // Close iframe
                this.closeModal();
                
                // Trigger checkout update
                $(document.body).trigger('update_checkout');
            } else if (event.data && event.data.type === 'servicePointPickerClose') {
                // User closed the iframe modal
                this.closeModal();
            }
        },
        
        transformPickupPointData: function(iframeData) {
            // Extract carrier from parcelshopId (e.g., "POST_NL:218167" -> "postnl")
            let carrier = '';
            if (iframeData.parcelshopId) {
                const parts = iframeData.parcelshopId.split(':');
                if (parts[0]) {
                    carrier = parts[0].toLowerCase().replace('_', '');
                }
            }
            
            // Transform the data structure
            return {
                id: iframeData.parcelshopId || '',
                name: iframeData.address?.business || '',
                street: (iframeData.address?.street_name || '') + ' ' + (iframeData.address?.house_number || ''),
                postcode: iframeData.address?.zip_code || '',
                city: iframeData.address?.locality || '',
                country: iframeData.address?.country_code || '',
                carrier: carrier,
                opening_hours: iframeData.openingHours || []
            };
        },

        storePickupPoint: function(pickupPoint) {
            // Remove any existing hidden field
            $('#wuunder_selected_pickup_point').remove();
            
            // Create JSON string
            const jsonString = JSON.stringify(pickupPoint);
            
            // Create hidden field using jQuery to properly handle special characters
            const hiddenField = $('<input>', {
                type: 'hidden',
                id: 'wuunder_selected_pickup_point',
                name: 'wuunder_selected_pickup_point',
                value: jsonString
            });
            
            $('form.checkout').append(hiddenField);
        },

        displaySelectedPickupPoint: function() {
            if (!this.selectedPickupPoint) {
                return;
            }
            
            const $container = $('.wuunder-pickup-container');
            const $button = $container.find('.wuunder-select-pickup-point');
            const $selectedInfo = $container.find('.wuunder-selected-pickup-point');
            const $infoText = $selectedInfo.find('.pickup-point-info');
            
            // Format pickup point info - trim and filter empty values
            const street = this.selectedPickupPoint.street.trim();
            const postcode = this.selectedPickupPoint.postcode.trim();
            const city = this.selectedPickupPoint.city.trim();
            
            const info = [
                this.selectedPickupPoint.name,
                street,
                postcode && city ? postcode + ' ' + city : (postcode || city)
            ].filter(Boolean).join(', ');
            
            $infoText.text(info);
            
            // Show selected info, hide button
            $button.hide();
            $selectedInfo.show();
            
            // Bind change link
            $container.find('.wuunder-change-pickup-point').off('click').on('click', function(e) {
                e.preventDefault();
                this.openPickupModal(e);
            }.bind(this));
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('form.checkout').length > 0) {
            WuunderPickup.init();
        }
    });

})(jQuery);