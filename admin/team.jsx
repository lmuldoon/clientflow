import { render } from '@wordpress/element';
import TeamApp from './components/TeamApp';

const root = document.getElementById( 'cf-team-root' );
if ( root ) {
	render( <TeamApp />, root );
}
