/**
 * WooCommerce Blocks Integration for Wuunder Pickup Points
 * 
 * This follows WooCommerce Blocks standards for extending the checkout
 */
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { select, dispatch, subscribe } from '@wordpress/data';

/**
 * Component for pickup point selection in block checkout
 */
const WuunderPickupIntegration = ({ cart, extensions, components }) => {
    const [ selectedPickupPoint, setSelectedPickupPoint ] = useState( null );
    const [ isModalOpen, setIsModalOpen ] = useState( false );
    
    // Check if Wuunder pickup method is selected
    const isPickupMethodSelected = () => {
        const shippingRates = select( 'wc/store/cart' )?.getShippingRates() || [];
        return shippingRates.some( 
            packageRates => packageRates.shipping_rates?.some( 
                rate => rate.method_id === 'wuunder_pickup' && rate.selected 
            )
        );
    };

    // Listen for shipping method changes
    useEffect(() => {
        const unsubscribe = subscribe(() => {
            if ( isPickupMethodSelected() && ! selectedPickupPoint ) {
                // Show pickup selector when method is selected
                injectPickupSelector();
            } else if ( ! isPickupMethodSelected() ) {
                // Hide selector when method is deselected
                removePickupSelector();
            }
        });

        return unsubscribe;
    }, [ selectedPickupPoint ]);

    // Handle iframe messages for pickup point selection
    useEffect(() => {
        const handleMessage = ( event ) => {
            if ( event.origin !== 'https://my.wearewuunder.com' ) {
                return;
            }

            if ( event.data?.type === 'servicePointPickerSelected' ) {
                const pickupPoint = transformPickupData( event.data );
                setSelectedPickupPoint( pickupPoint );
                
                // Store pickup point in WooCommerce session via AJAX
                storePickupPointInSession( pickupPoint );
                
                closeModal();
                updatePickupDisplay( pickupPoint );
            } else if ( event.data?.type === 'servicePointPickerClose' ) {
                closeModal();
            }
        };

        window.addEventListener( 'message', handleMessage );
        return () => window.removeEventListener( 'message', handleMessage );
    }, []);

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
            street_name: data.address?.street_name || '',
            house_number: data.address?.house_number || '',
            postcode: data.address?.zip_code || '',
            city: data.address?.locality || '',
            country: data.address?.country_code || '',
            carrier: carrier,
            opening_hours: data.openingHours || [],
        };
    };

    const injectPickupSelector = () => {
        // Find the selected wuunder_pickup radio button
        const pickupRadio = document.querySelector( 'input[value*="wuunder_pickup"]:checked' );
        
        if ( ! pickupRadio ) {
            removePickupSelector();
            return;
        }
        
        // Check if selector already exists
        if ( document.querySelector( '.wuunder-pickup-block-container' ) ) {
            return;
        }

        // Find the parent li or label element of the radio button
        const radioParent = pickupRadio.closest( 'li' ) || pickupRadio.closest( 'label' )?.parentElement;
        
        if ( ! radioParent ) {
            return;
        }

        const selectorDiv = document.createElement( 'div' );
        selectorDiv.className = 'wuunder-pickup-block-container';
        selectorDiv.innerHTML = `
            <div class="wuunder-pickup-selector">
                <button type="button" class="wp-block-button__link wp-element-button" id="wuunder-select-pickup">
                    ${ __( 'Select pick-up location', 'wuunder-shipping' ) }
                </button>
            </div>
        `;
        
        // Insert after the shipping method radio button's parent element
        radioParent.insertAdjacentElement( 'afterend', selectorDiv );
        
        const button = selectorDiv.querySelector( '#wuunder-select-pickup' );
        button?.addEventListener( 'click', openModal );
    };

    const removePickupSelector = () => {
        const selector = document.querySelector( '.wuunder-pickup-block-container' );
        selector?.remove();
    };

    const updatePickupDisplay = ( pickupPoint ) => {
        let container = document.querySelector( '.wuunder-pickup-block-container' );
        
        // If container doesn't exist, inject it first
        if ( ! container ) {
            injectPickupSelector();
            container = document.querySelector( '.wuunder-pickup-block-container' );
        }
        
        if ( container ) {
            container.innerHTML = `
                <div class="wuunder-pickup-selector selected">
                    <div class="pickup-details">
                        <strong>${ pickupPoint.name }</strong><br>
                        ${ pickupPoint.street }<br>
                        ${ pickupPoint.postcode } ${ pickupPoint.city }
                    </div>
                    <button type="button" class="wp-block-button__link wp-element-button" id="wuunder-change-pickup">
                        ${ __( 'Change', 'wuunder-shipping' ) }
                    </button>
                </div>
            `;
            
            const button = container.querySelector( '#wuunder-change-pickup' );
            button?.addEventListener( 'click', openModal );
        }
    };

    const openModal = () => {
        const customerAddress = getCustomerAddress();
        const iframeUrl = buildIframeUrl( customerAddress );
        
        const modalHtml = `
            <div class="wuunder-pickup-modal-overlay" id="wuunder-pickup-modal">
                <div class="wuunder-pickup-modal">
                    <iframe src="${ iframeUrl }" class="wuunder-pickup-iframe"></iframe>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML( 'beforeend', modalHtml );
        document.body.style.overflow = 'hidden';
        
        // Add click handler to close on overlay click
        const overlay = document.getElementById( 'wuunder-pickup-modal' );
        overlay?.addEventListener( 'click', ( e ) => {
            if ( e.target === overlay ) {
                closeModal();
            }
        });
    };

    const closeModal = () => {
        const modal = document.getElementById( 'wuunder-pickup-modal' );
        modal?.remove();
        document.body.style.overflow = '';
    };

    const getCustomerAddress = () => {
        const customer = select( 'wc/store/cart' )?.getCustomerData();
        const address = customer?.shippingAddress || customer?.billingAddress || {};
        
        return {
            street: address.address_1 || '',
            city: address.city || '',
            postcode: address.postcode || '',
            country: address.country || 'NL'
        };
    };

    const storePickupPointInSession = ( pickupPoint ) => {
        // Store pickup point in WooCommerce session via AJAX
        if ( typeof wuunderPickupBlock !== 'undefined' && wuunderPickupBlock.ajaxUrl ) {
            fetch( wuunderPickupBlock.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wuunder_store_pickup_point',
                    nonce: wuunderPickupBlock.nonce,
                    pickup_point: JSON.stringify( pickupPoint )
                })
            })
            .then( response => response.json() )
            .then( data => {
                if ( data.success ) {
                    // Trigger checkout update to refresh shipping rates
                    const { dispatch } = wp.data;
                    if ( dispatch && dispatch( 'wc/store/cart' ) ) {
                        dispatch( 'wc/store/cart' ).invalidateResolutionForStore();
                    }
                }
            });
        }
    };

    const storePickupPointInHiddenField = ( pickupPoint ) => {
        // Remove any existing hidden field
        let hiddenField = document.querySelector( '#wuunder_selected_pickup_point' );
        if ( hiddenField ) {
            hiddenField.remove();
        }
        
        // Create new hidden field
        hiddenField = document.createElement( 'input' );
        hiddenField.type = 'hidden';
        hiddenField.id = 'wuunder_selected_pickup_point';
        hiddenField.name = 'wuunder_selected_pickup_point';
        hiddenField.value = JSON.stringify( pickupPoint );
        
        // Find the checkout form or a suitable container
        const form = document.querySelector( 'form.wc-block-checkout__form' ) || 
                     document.querySelector( '.wc-block-checkout' ) ||
                     document.body;
        form.appendChild( hiddenField );
    };

    const getSelectedPickupMethodSettings = () => {
        // Default settings in case nothing is found
        const defaults = {
            color: '52ba69',
            carriers: [ 'dhl', 'postnl', 'ups' ],
            language: 'nl'
        };
        
        // Check if method settings are available
        if ( typeof wuunderPickupBlock === 'undefined' || ! wuunderPickupBlock.methodSettings ) {
            return defaults;
        }
        
        // Get the selected shipping method
        const shippingRates = select( 'wc/store/cart' )?.getShippingRates() || [];
        let selectedMethod = null;
        
        for ( const packageRates of shippingRates ) {
            const selectedRate = packageRates.shipping_rates?.find( 
                rate => rate.method_id === 'wuunder_pickup' && rate.selected 
            );
            if ( selectedRate ) {
                selectedMethod = selectedRate;
                break;
            }
        }
        
        if ( ! selectedMethod || ! selectedMethod.instance_id ) {
            return defaults;
        }
        
        // Get settings for the selected instance
        const methodSettings = wuunderPickupBlock.methodSettings[ selectedMethod.instance_id ];
        if ( ! methodSettings ) {
            return defaults;
        }
        
        return {
            color: ( methodSettings.primary_color || defaults.color ).replace( '#', '' ),
            carriers: methodSettings.available_carriers || defaults.carriers,
            language: methodSettings.language || defaults.language
        };
    };

    const buildIframeUrl = ( address ) => {
        const addressParts = [ 
            address.street, 
            address.postcode, 
            address.city, 
            address.country 
        ].filter( Boolean );
        
        // Get settings for the selected pickup method
        const settings = getSelectedPickupMethodSettings();
        
        const params = new URLSearchParams({
            address: addressParts.join( ', ' ) || 'Netherlands',
            availableCarriers: settings.carriers.join( ',' ),
            primary_color: settings.color,
            language: settings.language
        });
        
        return `https://my.wearewuunder.com/parcelshop_locator/iframe?${ params.toString() }`;
    };

    // Return null as we're injecting DOM directly for now
    // In a full implementation, this would return React components
    return null;
};

// Register the integration with WooCommerce Blocks
const render = () => {
    return <WuunderPickupIntegration />;
};

// Register as a plugin to integrate with checkout
registerPlugin( 'wuunder-pickup-integration', {
    render,
    scope: 'woocommerce-checkout'
});

// Also handle classic DOM manipulation for backwards compatibility
if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', initClassicIntegration );
} else {
    initClassicIntegration();
}

function initClassicIntegration() {
    // Watch for classic checkout changes
    const checkoutForm = document.querySelector( 'form.woocommerce-checkout' );
    if ( ! checkoutForm ) {
        // We're in blocks checkout, plugin will handle it
        return;
    }
    
    // Classic checkout integration code would go here if needed
}