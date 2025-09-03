/**
 * Shared utilities for pickup point functionality
 * Used by both classic and block checkout integrations
 */

/**
 * Transform pickup point data from iframe response
 * @param {Object} iframeData - Raw data from Wuunder iframe
 * @returns {Object} Normalized pickup point data
 */
export const transformPickupPointData = ( iframeData ) => {
    // Extract carrier from parcelshopId (e.g., "POST_NL:218167" -> "postnl")
    let carrier = '';
    if ( iframeData.parcelshopId ) {
        const parts = iframeData.parcelshopId.split( ':' );
        if ( parts[0] ) {
            carrier = parts[0].toLowerCase().replace( '_', '' );
        }
    }
    
    return {
        id: iframeData.parcelshopId || '',
        name: iframeData.address?.business || '',
        street: ( iframeData.address?.street_name || '' ) + ' ' + ( iframeData.address?.house_number || '' ),
        postcode: iframeData.address?.zip_code || '',
        city: iframeData.address?.locality || '',
        country: iframeData.address?.country_code || '',
        carrier: carrier,
        opening_hours: iframeData.openingHours || []
    };
};

/**
 * Build Wuunder iframe URL with parameters
 * @param {Object} address - Customer address object
 * @param {Object} config - Configuration for carriers, color, language
 * @returns {string} Complete iframe URL
 */
export const buildIframeUrl = ( address, config = {} ) => {
    const baseUrl = 'https://my.wearewuunder.com/parcelshop_locator/iframe';
    
    // Default configuration
    const defaultConfig = {
        carriers: 'dhl,postnl,ups',
        primaryColor: '52ba69',
        language: 'nl'
    };
    
    const finalConfig = { ...defaultConfig, ...config };
    
    // Format address for iframe
    const addressParts = [
        address.street,
        address.postcode,
        address.city,
        address.country
    ].filter( Boolean );
    
    const addressString = addressParts.length > 0 ? addressParts.join( ', ' ) : 'Netherlands';
    
    const params = new URLSearchParams({
        address: addressString,
        availableCarriers: finalConfig.carriers,
        primary_color: finalConfig.primaryColor.replace( '#', '' ),
        language: finalConfig.language
    });
    
    return `${ baseUrl }?${ params.toString() }`;
};

/**
 * Verify if message is from trusted Wuunder origin
 * @param {MessageEvent} event - Browser message event
 * @returns {boolean} True if from Wuunder
 */
export const isWuunderMessage = ( event ) => {
    return event.origin === 'https://my.wearewuunder.com';
};

/**
 * Check if message is pickup point selection
 * @param {Object} data - Message data
 * @returns {boolean} True if pickup point was selected
 */
export const isPickupPointSelected = ( data ) => {
    return data?.type === 'servicePointPickerSelected';
};

/**
 * Check if message is modal close event
 * @param {Object} data - Message data
 * @returns {boolean} True if modal should close
 */
export const isModalCloseEvent = ( data ) => {
    return data?.type === 'servicePointPickerClose';
};

/**
 * Format pickup point display text
 * @param {Object} pickupPoint - Pickup point data
 * @returns {string} Formatted display text
 */
export const formatPickupPointDisplay = ( pickupPoint ) => {
    const street = pickupPoint.street?.trim() || '';
    const postcode = pickupPoint.postcode?.trim() || '';
    const city = pickupPoint.city?.trim() || '';
    
    const parts = [
        pickupPoint.name,
        street,
        postcode && city ? `${ postcode } ${ city }` : ( postcode || city )
    ].filter( Boolean );
    
    return parts.join( ', ' );
};

/**
 * Create modal HTML structure
 * @param {string} iframeUrl - URL for the iframe
 * @param {string} title - Modal title for accessibility
 * @returns {string} HTML string for modal
 */
export const createModalHTML = ( iframeUrl, title = 'Select pick-up location' ) => {
    return `
        <div class="wuunder-pickup-modal-overlay" id="wuunder-pickup-modal">
            <div class="wuunder-pickup-modal">
                <iframe 
                    src="${ iframeUrl }"
                    class="wuunder-pickup-iframe"
                    title="${ title }">
                </iframe>
            </div>
        </div>
    `;
};

/**
 * Constants for Wuunder integration
 */
export const WUUNDER_CONSTANTS = {
    IFRAME_ORIGIN: 'https://my.wearewuunder.com',
    IFRAME_BASE_URL: 'https://my.wearewuunder.com/parcelshop_locator/iframe',
    DEFAULT_CARRIERS: 'dhl,postnl,ups',
    DEFAULT_COLOR: '52ba69',
    DEFAULT_LANGUAGE: 'nl',
    DEFAULT_COUNTRY: 'NL'
};