/**
 * Pickup Location Selector Component for Block Checkout
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { dispatch, select } from '@wordpress/data';

const PickupLocationSelector = ( { shippingRate, pickupPoint, onPickupPointSelected } ) => {
    const [ isModalOpen, setIsModalOpen ] = useState( false );
    const [ selectedPickup, setSelectedPickup ] = useState( pickupPoint );
    const iframeRef = useRef( null );

    // Get metadata from shipping rate
    const metadata = shippingRate?.meta_data || {};
    const carriers = metadata.available_carriers || ['dhl', 'postnl', 'ups'];
    const primaryColor = metadata.primary_color || '#52ba69';
    const language = metadata.language || 'nl';

    useEffect( () => {
        // Listen for messages from iframe
        const handleMessage = ( event ) => {
            if ( event.origin !== 'https://my.wearewuunder.com' ) {
                return;
            }

            console.log( 'Block checkout received message:', event.data );

            if ( event.data && event.data.type === 'servicePointPickerSelected' ) {
                const transformedPoint = transformPickupPointData( event.data );
                setSelectedPickup( transformedPoint );
                onPickupPointSelected( transformedPoint );
                
                // Store in session/meta
                storePickupPoint( transformedPoint );
                
                // Close modal
                setIsModalOpen( false );
                
                // Update checkout
                dispatch( 'wc/store/cart' ).updateCustomerData( {
                    extensions: {
                        'wuunder-pickup': {
                            pickup_point: transformedPoint
                        }
                    }
                } );
            }
        };

        window.addEventListener( 'message', handleMessage );
        return () => {
            window.removeEventListener( 'message', handleMessage );
        };
    }, [] );

    const transformPickupPointData = ( iframeData ) => {
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

    const storePickupPoint = ( point ) => {
        // Store in extension data for the order
        const extensionData = {
            'wuunder-pickup': {
                pickup_point: point
            }
        };

        // This will be sent with the checkout submission
        dispatch( 'wc/store/checkout' ).__internalSetExtensionData( 
            'wuunder-pickup', 
            { pickup_point: point }
        );
    };

    const getCustomerAddress = () => {
        const store = select( 'wc/store/cart' );
        const customer = store.getCustomerData();
        const shippingAddress = customer.shippingAddress;
        
        return {
            street: shippingAddress.address_1 || '',
            city: shippingAddress.city || '',
            postcode: shippingAddress.postcode || '',
            country: shippingAddress.country || 'NL'
        };
    };

    const buildIframeUrl = () => {
        const address = getCustomerAddress();
        const addressString = [
            address.street,
            address.postcode,
            address.city,
            address.country
        ].filter( Boolean ).join( ', ' );

        const params = new URLSearchParams( {
            address: addressString || 'Netherlands',
            availableCarriers: carriers.join( ',' ),
            primary_color: primaryColor.replace( '#', '' ),
            language: language
        } );

        return `https://my.wearewuunder.com/parcelshop_locator/iframe?${ params.toString() }`;
    };

    const openModal = () => {
        setIsModalOpen( true );
    };

    const closeModal = () => {
        setIsModalOpen( false );
    };

    return (
        <div className="wuunder-pickup-block-container">
            { ! selectedPickup ? (
                <Button 
                    variant="primary"
                    onClick={ openModal }
                    className="wuunder-select-pickup-button"
                >
                    { __( 'Select pick-up location', 'wuunder-shipping' ) }
                </Button>
            ) : (
                <div className="wuunder-selected-pickup-block">
                    <div className="pickup-info">
                        <strong>{ selectedPickup.name }</strong>
                        <span>{ selectedPickup.street }</span>
                        <span>{ selectedPickup.postcode } { selectedPickup.city }</span>
                    </div>
                    <Button 
                        variant="link"
                        onClick={ openModal }
                        className="wuunder-change-pickup"
                    >
                        { __( 'Change', 'wuunder-shipping' ) }
                    </Button>
                </div>
            ) }

            { isModalOpen && (
                <div className="wuunder-pickup-modal-overlay" onClick={ closeModal }>
                    <div className="wuunder-pickup-modal" onClick={ ( e ) => e.stopPropagation() }>
                        <div className="wuunder-modal-header">
                            <h3>{ __( 'Select pick-up location', 'wuunder-shipping' ) }</h3>
                            <button 
                                className="wuunder-modal-close"
                                onClick={ closeModal }
                                aria-label={ __( 'Close', 'wuunder-shipping' ) }
                            >
                                ×
                            </button>
                        </div>
                        <div className="wuunder-modal-body">
                            <iframe
                                ref={ iframeRef }
                                src={ buildIframeUrl() }
                                className="wuunder-pickup-iframe"
                                title={ __( 'Select pick-up location', 'wuunder-shipping' ) }
                            />
                        </div>
                    </div>
                </div>
            ) }
        </div>
    );
};

export default PickupLocationSelector;