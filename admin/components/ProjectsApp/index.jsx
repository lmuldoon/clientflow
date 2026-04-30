/**
 * ProjectsApp
 *
 * Root component for the Projects admin page.
 * Manages list ↔ detail view state.
 *
 * Injects global CSS variables (same as admin App.jsx) once on mount.
 */
import { useState } from '@wordpress/element';
import ProjectList   from '../ProjectList';
import ProjectDetail from '../ProjectDetail';

const CF_GLOBAL_CSS = `
@import url('https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap');

:root {
  --cf-navy:       #0F172A;
  --cf-navy-mid:   #1E293B;
  --cf-navy-dim:   #334155;
  --cf-indigo:     #6366F1;
  --cf-indigo-lt:  #818CF8;
  --cf-indigo-bg:  #EEF2FF;
  --cf-emerald:    #10B981;
  --cf-emerald-bg: #ECFDF5;
  --cf-amber:      #F59E0B;
  --cf-amber-bg:   #FFFBEB;
  --cf-red:        #EF4444;
  --cf-red-bg:     #FEF2F2;
  --cf-slate-50:   #F8FAFC;
  --cf-slate-100:  #F1F5F9;
  --cf-slate-200:  #E2E8F0;
  --cf-slate-300:  #CBD5E1;
  --cf-slate-400:  #94A3B8;
  --cf-slate-500:  #64748B;
  --cf-slate-600:  #475569;
  --cf-slate-700:  #334155;
  --cf-slate-800:  #1E293B;
  --cf-white:      #FFFFFF;
  --cf-radius:     12px;
  --cf-radius-sm:  8px;
  --cf-shadow:     0 1px 3px rgba(15,23,42,.06), 0 4px 16px rgba(15,23,42,.08);
  --cf-shadow-lg:  0 4px 6px rgba(15,23,42,.05), 0 10px 40px rgba(15,23,42,.12);
  --cf-font:         'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
  --cf-font-display: 'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
  --cf-input-border: 1.5px solid var(--cf-slate-200);
  --cf-input-focus: 0 0 0 3px rgba(99,102,241,.12);
}

#cf-projects-root, #cf-projects-root * {
  box-sizing: border-box;
  font-family: var(--cf-font);
  -webkit-font-smoothing: antialiased;
}

#cf-projects-root a { text-decoration: none; }
`;

function injectGlobalStyles() {
	if ( document.getElementById( 'cf-projects-global-styles' ) ) return;
	const el = document.createElement( 'style' );
	el.id = 'cf-projects-global-styles';
	el.textContent = CF_GLOBAL_CSS;
	document.head.appendChild( el );
}

export async function cfFetch( path, options = {} ) {
	const { apiUrl, nonce } = window.cfData || {};
	const url = ( apiUrl || '/wp-json/clientflow/v1/' ) + path;

	const res = await fetch( url, {
		...options,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce || '',
			...( options.headers || {} ),
		},
	} );

	if ( ! res.ok ) {
		const err = await res.json().catch( () => ( {} ) );
		throw new Error( err.message || `Request failed: ${ res.status }` );
	}

	return res.json();
}

export default function ProjectsApp() {
	injectGlobalStyles();

	const [ view, setView ]               = useState( 'list' );
	const [ activeProjectId, setActiveProjectId ] = useState( null );

	function handleViewProject( id ) {
		setActiveProjectId( id );
		setView( 'detail' );
	}

	function handleBack() {
		setActiveProjectId( null );
		setView( 'list' );
	}

	return (
		<div style={ { maxWidth: 1100, padding: '0 0 48px' } }>
			{ view === 'list' ? (
				<ProjectList onViewProject={ handleViewProject } />
			) : (
				<ProjectDetail
					projectId={ activeProjectId }
					onBack={ handleBack }
				/>
			) }
		</div>
	);
}
