/**
 * ClientFlow Analytics — React Entry Point
 *
 * Mounts the AnalyticsApp component into #cf-analytics-root.
 */
import { render } from '@wordpress/element';
import AnalyticsApp from './components/AnalyticsApp';

const root = document.getElementById( 'cf-analytics-root' );

if ( root ) {
	render( <AnalyticsApp />, root );
}
