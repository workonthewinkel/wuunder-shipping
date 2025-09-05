/**
 * Wuunder Pick-up Point Checkout Integration
 */
import { createIframeMessageHandler, transformPickupPointData, buildIframeUrl, formatPickupPointDisplay, createModalHTML, renderPickupFromTemplate } from './shared/pickup-utils.js';

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
            
            // Listen for messages from iframe using shared handler
            const messageHandler = createIframeMessageHandler({
                onPickupSelected: (pickupPoint) => {
                    this.selectedPickupPoint = pickupPoint;
                    this.storePickupPoint(pickupPoint);
                    this.displaySelectedPickupPoint();
                    this.closeModal();
                },
                onModalClose: () => {
                    this.closeModal();
                }
            });
            window.addEventListener('message', messageHandler, false);
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
                    <div class="wuunder-pickup-selector">
                        <button type="button" class="button wuunder-select-pickup-point">
                            ${wuunder_checkout.i18n.select_pickup_point}
                        </button>
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
            
            // Extract instance ID from method ID (e.g., "wuunder_pickup:5" -> instance ID 5)
            const methodParts = methodId.split(':');
            const instanceId = methodParts.length > 1 ? methodParts[1] : null;
            
            // Default settings
            const defaults = {
                carriers: 'dhl,postnl,ups',
                primaryColor: '52ba69',
                language: 'nl'
            };
            
            // Check if method settings are available
            if (typeof wuunder_checkout === 'undefined' || !wuunder_checkout.pickup_settings) {
                return defaults;
            }
            
            // Get settings for the selected instance
            const methodSettings = wuunder_checkout.pickup_settings[instanceId];
            if (!methodSettings) {
                return defaults;
            }
            
            return {
                carriers: (methodSettings.available_carriers || defaults.carriers.split(',')).join(','),
                primaryColor: (methodSettings.primary_color || '#' + defaults.primaryColor).replace('#', ''),
                language: methodSettings.language || defaults.language
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
            const iframeUrl = buildIframeUrl(address, {
                carriers: methodData.carriers,
                primaryColor: methodData.primaryColor,
                language: methodData.language
            });
            
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
            
            // Render pickup point info using template renderer
            const renderedHtml = renderPickupFromTemplate(this.selectedPickupPoint);
            
            $container.html(`
                <div class="wuunder-pickup-selector selected">
                    ${renderedHtml}
                    <a href="#" class="wuunder-change-pickup-point">${wuunder_checkout.i18n.change}</a>
                </div>
            `);
            
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