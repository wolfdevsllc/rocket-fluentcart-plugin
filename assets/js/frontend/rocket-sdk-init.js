/**
 * Rocket SDK Initialization
 * Initializes the Rocket control panel in the container
 */
jQuery(function($) {
    if (typeof rfcSiteData === 'object' && rfcSiteData.accessToken && rfcSiteData.siteId) {
        // Show loader
        $('.rfc-loader').removeClass('hide');
        
        // Initialize Rocket SDK
        window.Rocket('init', {
            token: rfcSiteData.accessToken,
            siteId: rfcSiteData.siteId,
            header: 'hide',
            elementId: 'rfc-rocket-container',
            colors: {
                bodyBackground: '#ffffff',
                iconPrimary: '#0073aa',
                iconSecondary: '#005177',
                primary: '#0073aa',
                primaryHover: '#005177',
                primaryActive: '#004155',
                primaryMenuHover: '#005177',
                primaryMenuActive: '#004155'
            }
        });
        
        // Hide loader after SDK loads
        setTimeout(function() {
            $('.rfc-loader').addClass('hide');
        }, 4000);
    } else {
        console.error('Rocket FluentCart: Missing site data or access token');
    }
});
