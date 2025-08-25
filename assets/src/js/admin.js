/**
 * Wuunder Admin JavaScript - Main Module
 * Orchestrates all admin functionality through components
 */
import ConnectionTester from './components/ConnectionTester.js';
import CarrierList from './components/CarrierList.js';
import DisconnectManager from './components/DisconnectManager.js';
import ShippingMethods from './components/ShippingMethods.js';
import Validation from './components/Validation.js';
import CarrierFilter from './components/CarrierFilter.js';

(function($) {
    'use strict';
    
    // Make jQuery available globally for ES6 modules
    window.$ = $;
    
    // Initialize all components when DOM is ready
    $(document).ready(function() {
        // Initialize all admin components
        new ConnectionTester();
        new CarrierList();
        new DisconnectManager();
        new ShippingMethods();
        new Validation();
        new CarrierFilter();
    });

})(jQuery);