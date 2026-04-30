/**
 * ClientFlow App Root
 *
 * Manages top-level view state: list ↔ wizard.
 * Injects global CSS variables and font import once on mount.
 */
import { useState, useEffect } from '@wordpress/element';
import ProposalList    from './components/ProposalList';
import ProposalWizard  from './components/ProposalWizard';
import ContentEditor   from './components/ContentEditor';

// ─── Global styles (injected once) ────────────────────────────────────────────
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

#cf-app, #cf-app * {
  box-sizing: border-box;
  font-family: var(--cf-font);
  -webkit-font-smoothing: antialiased;
}

#cf-app a { text-decoration: none; }

@keyframes cf-fade-up {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes cf-slide-in-right {
  from { opacity: 0; transform: translateX(32px); }
  to   { opacity: 1; transform: translateX(0); }
}
@keyframes cf-slide-in-left {
  from { opacity: 0; transform: translateX(-32px); }
  to   { opacity: 1; transform: translateX(0); }
}
@keyframes cf-spin {
  to { transform: rotate(360deg); }
}
`;

function injectGlobalStyles() {
	if ( document.getElementById( 'cf-global-styles' ) ) return;
	const el = document.createElement( 'style' );
	el.id = 'cf-global-styles';
	el.textContent = CF_GLOBAL_CSS;
	document.head.appendChild( el );
}

// ─── API helper ───────────────────────────────────────────────────────────────
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

// ─── Root Component ───────────────────────────────────────────────────────────
export default function App() {
	const [ view, setView ]                             = useState( 'list' );
	const [ proposals, setProposals ]                   = useState( [] );
	const [ loading, setLoading ]                       = useState( true );
	const [ error, setError ]                           = useState( null );
	const [ editingProposal, setEditingProposal ]       = useState( null );
	const [ editingContentProposal, setEditingContentProposal ] = useState( null );

	useEffect( () => {
		injectGlobalStyles();
		fetchProposals();
	}, [] );

	async function fetchProposals() {
		setLoading( true );
		setError( null );
		try {
			const data = await cfFetch( 'proposals' );
			setProposals( data.proposals || [] );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setLoading( false );
		}
	}

	async function handleEditProposal( id ) {
		try {
			const data = await cfFetch( `proposals/${ id }` );
			setEditingProposal( data.proposal );
			setView( 'wizard' );
		} catch ( e ) {
			alert( e.message || 'Could not load proposal.' );
		}
	}

	function handleWizardComplete( savedProposal ) {
		if ( editingProposal ) {
			// Replace the updated proposal in-place in the list.
			setProposals( prev => prev.map( p => p.id === savedProposal.id ? savedProposal : p ) );
		} else {
			setProposals( prev => [ savedProposal, ...prev ] );
		}
		setEditingProposal( null );
		setView( 'list' );
	}

	function handleWizardCancel() {
		setEditingProposal( null );
		setView( 'list' );
	}

	async function handleEditContent( id ) {
		try {
			const data = await cfFetch( `proposals/${ id }` );
			setEditingContentProposal( data.proposal );
			setView( 'edit-content' );
		} catch ( e ) {
			alert( e.message || 'Could not load proposal.' );
		}
	}

	function handleContentSave( updatedProposal ) {
		setProposals( prev => prev.map( p => p.id === updatedProposal.id ? updatedProposal : p ) );
		setEditingContentProposal( null );
		setView( 'list' );
	}

	function handleContentCancel() {
		setEditingContentProposal( null );
		setView( 'list' );
	}

	return (
		<div id="cf-app" style={ { maxWidth: 1100, padding: '0 0 48px' } }>
			{ view === 'list' && (
				<ProposalList
					proposals={ proposals }
					loading={ loading }
					error={ error }
					onNewProposal={ () => setView( 'wizard' ) }
					onEditProposal={ handleEditProposal }
					onEditContent={ handleEditContent }
					onRefresh={ fetchProposals }
				/>
			) }
			{ view === 'wizard' && (
				<ProposalWizard
					initialProposal={ editingProposal }
					onComplete={ handleWizardComplete }
					onCancel={ handleWizardCancel }
				/>
			) }
			{ view === 'edit-content' && editingContentProposal && (
				<ContentEditor
					proposal={ editingContentProposal }
					onSave={ handleContentSave }
					onCancel={ handleContentCancel }
				/>
			) }
		</div>
	);
}
