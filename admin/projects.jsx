/**
 * ClientFlow Projects — React Entry Point
 *
 * Mounts the ProjectsApp component into #cf-projects-root.
 */
import { render } from '@wordpress/element';
import ProjectsApp from './components/ProjectsApp';

const root = document.getElementById( 'cf-projects-root' );

if ( root ) {
	render( <ProjectsApp />, root );
}
