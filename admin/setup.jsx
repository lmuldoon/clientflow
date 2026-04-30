import { render } from '@wordpress/element';
import SetupWizard from './components/SetupWizard';

const root = document.getElementById( 'cf-setup-root' );
if ( root ) {
	render( <SetupWizard />, root );
}
