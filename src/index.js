/**
 * WooCommerce Block Checkout Integration for Wuunder Pickup
 */
import { useEffect, useState } from '@wordpress/element';
import { select, subscribe } from '@wordpress/data';
import PickupLocationSelector from './pickup-location-selector';

// Simple approach - inject UI after shipping methods load
const injectPickupSelector = () => {
    // Find the shipping methods container
    const shippingContainer = document.querySelector( '.wc-block-components-shipping-rates-control' );
    
    if ( ! shippingContainer ) {
        return;
    }
    
    // Check if pickup method is selected
    const pickupRadio = shippingContainer.querySelector( 'input[value*="wuunder_pickup"]:checked' );
    
    if ( ! pickupRadio ) {
        // Remove any existing selector
        const existingSelector = document.querySelector( '.wuunder-pickup-block-container' );
        if ( existingSelector ) {
            existingSelector.remove();
        }
        return;
    }
    
    // Check if we already added the selector
    if ( document.querySelector( '.wuunder-pickup-block-container' ) ) {
        return;
    }
    
    // Create the pickup selector UI
    const selectorDiv = document.createElement( 'div' );
    selectorDiv.className = 'wuunder-pickup-block-container';
    selectorDiv.innerHTML = `
        <div style="margin-top: 16px; padding: 12px; background: #f7f7f7; border-radius: 4px;">
            <button type="button" class="wp-block-button__link wp-element-button" id="wuunder-select-pickup">
                Select pick-up location
            </button>
        </div>
    `;
    
    // Insert after the shipping methods
    shippingContainer.appendChild( selectorDiv );
    
    // Add click handler
    const button = selectorDiv.querySelector( '#wuunder-select-pickup' );
    button.addEventListener( 'click', () => {
        openPickupModal();
    } );
};

// Get customer address from checkout form
const getCustomerAddress = () => {
    // Try to get address from various input fields in checkout
    let address = {
        street: '',
        city: '',
        postcode: '',
        country: 'NL'
    };
    
    // Enhanced selectors for WooCommerce Blocks
    const fieldSelectors = {
        street: [
            'input[name="billing_address_1"]',
            'input[id*="billing-address_1"]',
            'input[id*="address_1"]',
            'input[id*="address-1"]',
            '.wp-block-woocommerce-checkout-billing-address-block input[placeholder*="address" i]',
            '.wp-block-woocommerce-checkout-contact-information-block input[placeholder*="address" i]'
        ],
        city: [
            'input[name="billing_city"]',
            'input[id*="billing-city"]', 
            'input[id*="city"]',
            '.wp-block-woocommerce-checkout-billing-address-block input[placeholder*="city" i]',
            '.wp-block-woocommerce-checkout-contact-information-block input[placeholder*="city" i]'
        ],
        postcode: [
            'input[name="billing_postcode"]',
            'input[id*="billing-postcode"]',
            'input[id*="postcode"]',
            'input[id*="postal"]',
            '.wp-block-woocommerce-checkout-billing-address-block input[placeholder*="postal" i]',
            '.wp-block-woocommerce-checkout-billing-address-block input[placeholder*="zip" i]',
            '.wp-block-woocommerce-checkout-contact-information-block input[placeholder*="postal" i]',
            '.wp-block-woocommerce-checkout-contact-information-block input[placeholder*="zip" i]'
        ],
        country: [
            'select[name="billing_country"]',
            'select[id*="billing-country"]',
            'select[id*="country"]',
            '.wp-block-woocommerce-checkout-billing-address-block select',
            '.wp-block-woocommerce-checkout-contact-information-block select'
        ]
    };
    
    // Try each selector until we find a field with a value
    Object.keys( fieldSelectors ).forEach( fieldType => {
        for ( const selector of fieldSelectors[ fieldType ] ) {
            const element = document.querySelector( selector );
            if ( element && element.value && element.value.trim() !== '' ) {
                address[ fieldType ] = element.value.trim();
                break; // Stop at first match with value
            }
        }
    } );
    
    return address;
};

// Build iframe URL with customer address
const buildIframeUrl = ( address ) => {
    const baseUrl = 'https://my.wearewuunder.com/parcelshop_locator/iframe';
    
    // Format address for iframe
    const addressParts = [ address.street, address.postcode, address.city, address.country ].filter( Boolean );
    const addressString = addressParts.length > 0 ? addressParts.join( ', ' ) : 'Netherlands';
    
    const params = new URLSearchParams( {
        address: addressString,
        availableCarriers: 'dhl,postnl,ups',
        primary_color: '52ba69',
        language: 'nl'
    } );
    
    return `${ baseUrl }?${ params.toString() }`;
};

// Show pickup iframe directly
const openPickupModal = () => {
    // Get customer address and build iframe URL
    const customerAddress = getCustomerAddress();
    const iframeUrl = buildIframeUrl( customerAddress );
    
    // Create fullscreen iframe container
    const iframeContainer = document.createElement( 'div' );
    iframeContainer.className = 'wuunder-pickup-modal-overlay';
    iframeContainer.innerHTML = `
        <div class="wuunder-pickup-modal">
            <iframe src="${ iframeUrl }" class="wuunder-pickup-iframe"></iframe>
        </div>
    `;
    
    iframeContainer.id = 'wuunder-pickup-iframe-container';
    document.body.appendChild( iframeContainer );
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    
    // Listen for iframe messages
    window.addEventListener( 'message', handlePickupMessage );
};

// Handle pickup point selection and close events
const handlePickupMessage = ( event ) => {
    if ( event.origin !== 'https://my.wearewuunder.com' ) {
        return;
    }
    
    if ( event.data && event.data.type === 'servicePointPickerSelected' ) {
        // Transform and store the data
        const pickupPoint = transformPickupData( event.data );
        storePickupPoint( pickupPoint );
        
        // Update UI
        updatePickupDisplay( pickupPoint );
        
        // Close iframe
        closePickupModal();
    } else if ( event.data && event.data.type === 'servicePointPickerClose' ) {
        // User closed the iframe modal
        closePickupModal();
    }
};

// Transform pickup point data
const transformPickupData = ( data ) => {
    let carrier = '';
    if ( data.parcelshopId ) {
        const parts = data.parcelshopId.split( ':' );
        if ( parts[0] ) {
            carrier = parts[0].toLowerCase().replace( '_', '' );
        }
    }
    
    return {
        id: data.parcelshopId || '',
        name: data.address?.business || '',
        street: ( data.address?.street_name || '' ) + ' ' + ( data.address?.house_number || '' ),
        postcode: data.address?.zip_code || '',
        city: data.address?.locality || '',
        country: data.address?.country_code || '',
        carrier: carrier,
        opening_hours: data.openingHours || []
    };
};

// Store pickup point
const storePickupPoint = ( pickupPoint ) => {
    // Store in a hidden field for the checkout
    let hiddenField = document.querySelector( '#wuunder_selected_pickup_point' );
    if ( ! hiddenField ) {
        hiddenField = document.createElement( 'input' );
        hiddenField.type = 'hidden';
        hiddenField.id = 'wuunder_selected_pickup_point';
        hiddenField.name = 'wuunder_selected_pickup_point';
        document.querySelector( 'form' ).appendChild( hiddenField );
    }
    
    hiddenField.value = JSON.stringify( pickupPoint );
};

// Update pickup display
const updatePickupDisplay = ( pickupPoint ) => {
    const container = document.querySelector( '.wuunder-pickup-block-container' );
    if ( container ) {
        container.innerHTML = `
            <div style="margin-top: 16px; padding: 12px; background: #f7f7f7; border-radius: 4px;">
                <div style="margin-bottom: 8px;">
                    <strong>${ pickupPoint.name }</strong><br>
                    ${ pickupPoint.street }<br>
                    ${ pickupPoint.postcode } ${ pickupPoint.city }
                </div>
                <button type="button" class="wp-block-button__link wp-element-button" onclick="openPickupModal()">
                    Change
                </button>
            </div>
        `;
    }
};

// Close iframe
window.closePickupModal = () => {
    const container = document.querySelector( '#wuunder-pickup-iframe-container' );
    if ( container ) {
        container.remove();
    }
    // Restore body scroll
    document.body.style.overflow = '';
    window.removeEventListener( 'message', handlePickupMessage );
};

// Make functions globally available
window.openPickupModal = openPickupModal;

// Watch for changes in the checkout
const watchCheckout = () => {
    // Check immediately
    setTimeout( injectPickupSelector, 1000 );
    
    // Watch for changes with MutationObserver
    const observer = new MutationObserver( () => {
        injectPickupSelector();
        attachRadioListeners(); // Re-attach listeners when DOM changes
    } );
    
    // Watch the entire checkout area
    const checkoutContainer = document.querySelector( '.wc-block-checkout' );
    if ( checkoutContainer ) {
        observer.observe( checkoutContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['checked']
        } );
    } else {
        setTimeout( watchCheckout, 2000 );
    }
    
    // Also attach radio listeners
    attachRadioListeners();
};

// Attach event listeners to shipping method radio buttons
const attachRadioListeners = () => {
    const shippingRadios = document.querySelectorAll( '.wc-block-components-shipping-rates-control input[type="radio"]' );
    
    shippingRadios.forEach( radio => {
        // Remove existing listener to avoid duplicates
        radio.removeEventListener( 'change', handleShippingMethodChange );
        // Add new listener
        radio.addEventListener( 'change', handleShippingMethodChange );
    } );
};

// Handle shipping method radio button changes
const handleShippingMethodChange = ( event ) => {
    // Small delay to let the UI update
    setTimeout( () => {
        injectPickupSelector();
    }, 100 );
};

// Start watching when page loads
if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', watchCheckout );
} else {
    watchCheckout();
}