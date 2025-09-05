/**
 * WooCommerce Blocks Integration for Wuunder Pickup Points
 * 
 * This follows WooCommerce Blocks standards for extending the checkout
 */
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { select, dispatch, subscribe } from '@wordpress/data';
import { createIframeMessageHandler, transformPickupPointData, buildIframeUrl, formatPickupPointDisplay, createModalHTML, renderPickupFromTemplate } from './shared/pickup-utils.js';

/**
 * Component for pickup point selection in block checkout
 */
const WuunderPickupIntegration = ({ cart, extensions, components }) => {
    const [ selectedPickupPoint, setSelectedPickupPoint ] = useState( null );
    const [ isModalOpen, setIsModalOpen ] = useState( false );
    const [ isInitialized, setIsInitialized ] = useState( false );
    
    // Check if Wuunder pickup method is selected
    const isPickupMethodSelected = () => {
        const shippingRates = select( 'wc/store/cart' )?.getShippingRates() || [];
        return shippingRates.some( 
            packageRates => packageRates.shipping_rates?.some( 
                rate => rate.method_id === 'wuunder_pickup' && rate.selected 
            )
        );
    };

    // Load saved pickup point from localized script data (no AJAX needed!)
    const loadSavedPickupPoint = () => {
        if ( typeof wuunderPickupBlock !== 'undefined' && wuunderPickupBlock.savedPickupPoint ) {
            setSelectedPickupPoint( wuunderPickupBlock.savedPickupPoint );
            return wuunderPickupBlock.savedPickupPoint;
        }
        return null;
    };

    // Initialize component and load saved pickup point
    useEffect(() => {
        if ( ! isInitialized ) {
            setIsInitialized( true );
            
            const savedPickupPoint = loadSavedPickupPoint();
            
            // If we have a saved pickup point and pickup method is selected, show it
            if ( savedPickupPoint && isPickupMethodSelected() ) {
                setTimeout(() => {
                    injectPickupSelector();
                    updatePickupDisplay( savedPickupPoint );
                }, 100); // Small delay to ensure DOM is ready
            }
        }
    }, [ isInitialized ]);

    // Listen for shipping method changes
    useEffect(() => {
        const unsubscribe = subscribe(() => {
            if ( isPickupMethodSelected() ) {
                if ( ! selectedPickupPoint ) {
                    // Try to load saved pickup point if we don't have one
                    const savedPickupPoint = loadSavedPickupPoint();
                    if ( savedPickupPoint ) {
                        // Show selector with saved pickup point
                        injectPickupSelector();
                        updatePickupDisplay( savedPickupPoint );
                    } else {
                        // Show pickup selector when method is selected
                        injectPickupSelector();
                    }
                } else {
                    // Already have pickup point, just ensure selector is shown with display
                    injectPickupSelector();
                    updatePickupDisplay( selectedPickupPoint );
                }
            } else if ( ! isPickupMethodSelected() ) {
                // Hide selector when method is deselected
                removePickupSelector();
            }
        });

        return unsubscribe;
    }, [ selectedPickupPoint, isInitialized ]);

    // Handle iframe messages for pickup point selection using shared handler
    useEffect(() => {
        const messageHandler = createIframeMessageHandler({
            onPickupSelected: ( pickupPoint ) => {
                setSelectedPickupPoint( pickupPoint );
                
                // Store pickup point in Store API extension data
                storePickupPointInStoreAPI( pickupPoint );
                
                closeModal();
                updatePickupDisplay( pickupPoint );
            },
            onModalClose: () => {
                closeModal();
            }
        });

        window.addEventListener( 'message', messageHandler );
        return () => window.removeEventListener( 'message', messageHandler );
    }, []);


    const injectPickupSelector = () => {
        // Find the selected wuunder_pickup radio button
        const pickupRadio = document.querySelector( 'input[value*="wuunder_pickup"]:checked' );
        
        if ( ! pickupRadio ) {
            removePickupSelector();
            return;
        }
        
        // Check if selector already exists
        if ( document.querySelector( '.wuunder-pickup-container' ) ) {
            return;
        }

        // Find the parent li or label element of the radio button
        const radioParent = pickupRadio.closest( 'li' ) || pickupRadio.closest( 'label' )?.parentElement;
        
        if ( ! radioParent ) {
            return;
        }

        const selectorDiv = document.createElement( 'div' );
        selectorDiv.className = 'wuunder-pickup-container';
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
        const selector = document.querySelector( '.wuunder-pickup-container' );
        selector?.remove();
    };

    const updatePickupDisplay = ( pickupPoint ) => {
        let container = document.querySelector( '.wuunder-pickup-container' );
        
        // If container doesn't exist, inject it first
        if ( ! container ) {
            injectPickupSelector();
            container = document.querySelector( '.wuunder-pickup-container' );
        }
        
        if ( container ) {
            const renderedHtml = renderPickupFromTemplate( pickupPoint );
            container.innerHTML = `
                <div class="wuunder-pickup-selector selected">
                    ${ renderedHtml }
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
        const settings = getSelectedPickupMethodSettings();
        const iframeUrl = buildIframeUrl( customerAddress, {
            carriers: settings.carriers.join( ',' ),
            primaryColor: settings.color,
            language: settings.language
        } );
        
        const modalHtml = `
            <div class="wuunder-pickup-modal-overlay" id="wuunder-pickup-modal">
                <div class="wuunder-pickup-modal">
                    <iframe src="${ iframeUrl }" class="wuunder-pickup-iframe" 
                            sandbox="allow-scripts allow-same-origin allow-forms allow-popups"></iframe>
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

    const storePickupPointInStoreAPI = ( pickupPoint ) => {
        // Try Store API integration with better error handling
        const { dispatch } = wp.data;
        
        try {
            // Set extension data using the WooCommerce Store API
            dispatch( 'wc/store/checkout' ).setExtensionData( 'wuunder-pickup', {
                pickup_point: pickupPoint
            });
            
            // Try applyExtensionCartUpdate method to trigger actual API call
            dispatch( 'wc/store/cart' ).applyExtensionCartUpdate( {
                namespace: 'wuunder-pickup',
                data: { pickup_point: pickupPoint }
            })
            .then( ( response ) => {
                // Store API update successful
            })
            .catch( ( error ) => {
                // Fallback to AJAX method
                storePickupPointInSession( pickupPoint );
            });
            
        } catch ( error ) {
            // Store API integration failed, fallback to AJAX method
            storePickupPointInSession( pickupPoint );
        }
    };

    const storePickupPointInSession = ( pickupPoint ) => {
        // Fallback: Store pickup point in WooCommerce session via AJAX
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
            .then( response => response.json())
            .then( data => {
                if ( data.success ) {
                    // Trigger checkout update to refresh shipping rates
                    const { dispatch } = wp.data;
                    if ( dispatch && dispatch( 'wc/store/cart' ) ) {
                        dispatch( 'wc/store/cart' ).invalidateResolutionForStore();
                    }
                }
            })
            .catch( error => {
                // Handle AJAX error silently
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