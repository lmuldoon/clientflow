/**
 * Portal bundle entry point.
 *
 * Reads cfPortalData.page and mounts the correct top-level component.
 *
 *   login     → PortalLogin   (unauthenticated)
 *   verify    → PortalVerify  (unauthenticated, auto-fires token verification)
 *   dashboard | proposals | payments → PortalApp (authenticated shell)
 */

// Must be first import so window.injectStyles is defined before any
// component module evaluates its top-level injectStyles() calls.
import './portal-globals';

import PortalLogin   from './components/PortalLogin';
import PortalVerify  from './components/PortalVerify';
import PortalApp     from './components/PortalApp';

const { render } = wp.element;

const root = document.getElementById( 'cf-portal-root' );
const page = ( window.cfPortalData || {} ).page || 'login';

if ( 'login' === page ) {
	render( <PortalLogin />, root );
} else if ( 'verify' === page ) {
	render( <PortalVerify />, root );
} else {
	// dashboard | proposals | payments
	render( <PortalApp page={ page } />, root );
}
