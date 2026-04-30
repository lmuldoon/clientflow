/**
 * ProposalList
 *
 * Full proposals list view with status tabs, search, CSS grid rows,
 * quick actions, empty state, and pagination.
 *
 * Props:
 *   proposals       {array}   — proposal objects from API
 *   loading         {bool}
 *   error           {string|null}
 *   onNewProposal   {fn}
 *   onEditProposal  {fn}      — onEditProposal(id)
 *   onRefresh       {fn}
 */
import { useState, useMemo } from '@wordpress/element';
import { cfFetch } from '../../App';

const TABS = [
	{ id: 'all',      label: 'All'      },
	{ id: 'draft',    label: 'Draft'    },
	{ id: 'sent',     label: 'Sent'     },
	{ id: 'viewed',   label: 'Viewed'   },
	{ id: 'accepted', label: 'Accepted' },
	{ id: 'declined', label: 'Declined' },
];

const STATUS_CONFIG = {
	draft:    { bg: 'var(--cf-slate-100)',   color: 'var(--cf-slate-600)',   label: 'Draft'    },
	sent:     { bg: 'var(--cf-indigo-bg)',   color: 'var(--cf-indigo)',      label: 'Sent'     },
	viewed:   { bg: 'var(--cf-amber-bg)',    color: 'var(--cf-amber)',       label: 'Viewed'   },
	accepted: { bg: 'var(--cf-emerald-bg)',  color: 'var(--cf-emerald)',     label: 'Accepted' },
	declined: { bg: 'var(--cf-red-bg)',      color: 'var(--cf-red)',         label: 'Declined' },
	expired:  { bg: 'var(--cf-slate-100)',   color: 'var(--cf-slate-400)',   label: 'Expired'  },
};

const CURRENCY_SYMBOLS = { GBP: '£', USD: '$', EUR: '€', CAD: '$', AUD: '$' };

const PER_PAGE = 20;

const CSS = `
/* Layout */
.cf-list-wrap { display: flex; flex-direction: column; gap: 0; padding: 32px 28px 64px; }

/* Header */
.cf-list-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 28px;
  gap: 16px;
}
.cf-list-title {
  font-family: var(--cf-font);
  font-size: 26px;
  font-weight: 800;
  color: var(--cf-navy);
  letter-spacing: -.5px;
  margin: 0 0 4px;
}
.cf-list-subtitle {
  font-size: 13.5px;
  color: var(--cf-slate-500);
  margin: 0;
}
.cf-list-new-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px;
  background: var(--cf-indigo);
  color: white;
  border-radius: var(--cf-radius-sm);
  font-size: 13.5px;
  font-weight: 600;
  font-family: var(--cf-font);
  border: none;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(99,102,241,.35);
  transition: background .15s, box-shadow .15s, transform .12s;
  flex-shrink: 0;
}
.cf-list-new-btn:hover { background: #4F46E5; box-shadow: 0 4px 16px rgba(99,102,241,.4); transform: translateY(-1px); }
.cf-list-new-btn svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 2.5; }

.cf-list-refresh-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 16px;
  background: var(--cf-white);
  color: var(--cf-slate-600);
  border-radius: var(--cf-radius-sm);
  font-size: 13px;
  font-weight: 500;
  font-family: var(--cf-font);
  border: 1.5px solid var(--cf-slate-200);
  cursor: pointer;
  transition: border-color .15s, color .15s, background .15s;
  flex-shrink: 0;
}
.cf-list-refresh-btn:hover:not(:disabled) { border-color: var(--cf-indigo); color: var(--cf-indigo); background: var(--cf-indigo-bg); }
.cf-list-refresh-btn:disabled { opacity: .5; cursor: not-allowed; }
.cf-list-refresh-btn svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; }
.cf-list-refresh-btn.spinning svg { animation: cf-spin 0.7s linear infinite; }

/* Tabs + search bar */
.cf-list-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.cf-list-tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid var(--cf-slate-200);
}
.cf-list-tab {
  display: flex; align-items: center; gap: 7px;
  padding: 8px 14px 10px;
  background: none; border: none;
  font-size: 13px; font-weight: 500;
  font-family: var(--cf-font);
  color: var(--cf-slate-500);
  cursor: pointer;
  position: relative;
  bottom: -2px;
  border-bottom: 2px solid transparent;
  transition: color .15s, border-color .15s;
  white-space: nowrap;
}
.cf-list-tab:hover { color: var(--cf-slate-800); }
.cf-list-tab.active {
  color: var(--cf-indigo);
  border-bottom-color: var(--cf-indigo);
  font-weight: 600;
}
.cf-list-tab-count {
  font-size: 11px;
  font-weight: 700;
  background: var(--cf-slate-100);
  color: var(--cf-slate-500);
  border-radius: 999px;
  padding: 1px 7px;
  min-width: 20px;
  text-align: center;
}
.cf-list-tab.active .cf-list-tab-count {
  background: var(--cf-indigo-bg);
  color: var(--cf-indigo);
}

/* Search */
.cf-list-search-wrap {
  position: relative;
  flex-shrink: 0;
}
.cf-list-search-icon {
  position: absolute;
  left: 12px; top: 50%;
  transform: translateY(-50%);
  width: 15px; height: 15px;
  stroke: var(--cf-slate-400);
  stroke-width: 2;
  pointer-events: none;
}
.cf-list-search {
  padding: 9px 14px 9px 36px;
  border: var(--cf-input-border);
  border-radius: var(--cf-radius-sm);
  font-size: 13.5px;
  font-family: var(--cf-font);
  color: var(--cf-slate-800);
  background: var(--cf-white);
  outline: none;
  width: 220px;
  transition: border-color .15s, box-shadow .15s;
}
.cf-list-search::placeholder { color: var(--cf-slate-300); }
.cf-list-search:focus { border-color: var(--cf-indigo); box-shadow: var(--cf-input-focus); }

/* Table header row */
.cf-list-col-headers {
  display: grid;
  grid-template-columns: 2fr 2fr 120px 100px 120px 100px;
  gap: 12px;
  padding: 8px 16px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--cf-slate-400);
  border-bottom: 1px solid var(--cf-slate-100);
  margin-bottom: 4px;
}

/* Row card */
.cf-list-row {
  display: grid;
  grid-template-columns: 2fr 2fr 120px 100px 120px 100px;
  gap: 12px;
  align-items: center;
  padding: 14px 16px;
  background: var(--cf-white);
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm);
  margin-bottom: 6px;
  position: relative;
  transition: border-color .15s, box-shadow .15s, transform .12s;
  cursor: default;
}
.cf-list-row:hover {
  border-color: var(--cf-slate-300);
  box-shadow: var(--cf-shadow);
  transform: translateY(-1px);
}
.cf-list-row:hover .cf-list-actions { opacity: 1; }

/* Left accent bar by status */
.cf-list-row::before {
  content: '';
  position: absolute;
  left: 0; top: 8px; bottom: 8px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--cf-slate-200);
  transition: background .15s;
}
.cf-list-row[data-status="accepted"]::before  { background: var(--cf-emerald); }
.cf-list-row[data-status="sent"]::before      { background: var(--cf-indigo); }
.cf-list-row[data-status="viewed"]::before    { background: var(--cf-amber); }
.cf-list-row[data-status="declined"]::before  { background: var(--cf-red); }

/* Client cell */
.cf-list-client-name { font-size: 13.5px; font-weight: 600; color: var(--cf-slate-800); }
.cf-list-client-company { font-size: 12px; color: var(--cf-slate-400); margin-top: 2px; }

/* Proposal title */
.cf-list-proposal-title {
  font-size: 13px;
  color: var(--cf-slate-600);
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

/* Amount */
.cf-list-amount {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--cf-slate-800);
  font-variant-numeric: tabular-nums;
}

/* Status badge */
.cf-list-badge {
  display: inline-flex;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}

/* Decline reason modal */
.cf-decline-overlay {
  position: fixed;
  inset: 0;
  z-index: 9000;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  animation: cf-fade-overlay 0.18s ease both;
}
@keyframes cf-fade-overlay {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.cf-decline-modal {
  background: var(--cf-white);
  border-radius: var(--cf-radius);
  border-left: 3px solid var(--cf-red);
  padding: 28px 32px 32px;
  width: 100%;
  max-width: 460px;
  box-shadow: var(--cf-shadow-lg);
  animation: cf-modal-in 0.22s cubic-bezier(0.22, 1, 0.36, 1) both;
}
@keyframes cf-modal-in {
  from { opacity: 0; transform: scale(0.96) translateY(6px); }
  to   { opacity: 1; transform: scale(1)    translateY(0);   }
}
.cf-decline-modal-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}
.cf-decline-modal-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--cf-red-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}
.cf-decline-modal-icon svg {
  width: 16px;
  height: 16px;
  stroke: var(--cf-red);
  stroke-width: 2;
}
.cf-decline-modal-titles { flex: 1; }
.cf-decline-modal-title {
  font-family: var(--cf-font-display);
  font-size: 17px;
  font-weight: 600;
  color: var(--cf-navy);
  margin-bottom: 3px;
  line-height: 1.3;
}
.cf-decline-modal-subtitle {
  font-size: 12.5px;
  color: var(--cf-slate-400);
}
.cf-decline-modal-body {
  background: var(--cf-slate-50);
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm);
  padding: 14px 16px;
  font-size: 14px;
  line-height: 1.65;
  color: var(--cf-slate-700);
  font-style: italic;
  white-space: pre-wrap;
  word-break: break-word;
  margin-bottom: 20px;
}
.cf-decline-modal-body::before { content: '\u201C'; color: var(--cf-red); font-style: normal; font-size: 18px; line-height: 0; vertical-align: -3px; margin-right: 2px; }
.cf-decline-modal-body::after  { content: '\u201D'; color: var(--cf-red); font-style: normal; font-size: 18px; line-height: 0; vertical-align: -3px; margin-left: 2px;  }
.cf-decline-modal-close {
  display: flex;
  justify-content: flex-end;
}
.cf-decline-modal-close-btn {
  padding: 8px 18px;
  border-radius: var(--cf-radius-sm);
  border: 1.5px solid var(--cf-slate-200);
  background: var(--cf-white);
  font-family: var(--cf-font);
  font-size: 13px;
  font-weight: 500;
  color: var(--cf-slate-600);
  cursor: pointer;
  transition: background .12s, border-color .12s, color .12s;
}
.cf-decline-modal-close-btn:hover { background: var(--cf-slate-50); border-color: var(--cf-slate-300); color: var(--cf-slate-800); }

/* Date */
.cf-list-date {
  font-size: 12px;
  color: var(--cf-slate-400);
}

/* Actions */
.cf-list-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity .15s;
  justify-content: flex-end;
}
.cf-list-action-btn {
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  background: var(--cf-slate-100);
  border: none;
  border-radius: 6px;
  cursor: pointer;
  color: var(--cf-slate-500);
  transition: background .12s, color .12s;
}
.cf-list-action-btn:hover { background: var(--cf-indigo-bg); color: var(--cf-indigo); }
.cf-list-action-btn.danger:hover { background: var(--cf-red-bg); color: var(--cf-red); }
.cf-list-action-btn svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 2; }

/* Skeleton */
.cf-list-skeleton {
  height: 64px;
  background: linear-gradient(90deg, var(--cf-slate-100) 25%, var(--cf-slate-50) 50%, var(--cf-slate-100) 75%);
  background-size: 200% 100%;
  border-radius: var(--cf-radius-sm);
  animation: cf-shimmer 1.4s ease infinite;
  margin-bottom: 6px;
}
@keyframes cf-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Empty state */
.cf-list-empty {
  display: flex; flex-direction: column; align-items: center;
  padding: 60px 20px;
  text-align: center;
  gap: 12px;
  animation: cf-fade-up .3s ease both;
}
.cf-list-empty-icon {
  width: 72px; height: 72px;
  background: var(--cf-slate-100);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 4px;
}
.cf-list-empty-icon svg { width: 34px; height: 34px; stroke: var(--cf-slate-300); stroke-width: 1.5; }
.cf-list-empty h3 { font-size: 18px; font-weight: 700; color: var(--cf-slate-700); }
.cf-list-empty p { font-size: 14px; color: var(--cf-slate-400); max-width: 320px; line-height: 1.6; }

/* Error banner */
.cf-list-error {
  display: flex; align-items: center; gap: 10px;
  background: var(--cf-red-bg); border: 1px solid rgba(239,68,68,.2);
  color: var(--cf-red); border-radius: 8px;
  padding: 12px 16px; font-size: 13px; margin-bottom: 16px;
}
.cf-list-error svg { width: 16px; height: 16px; stroke: currentColor; flex-shrink: 0; }

/* Pagination */
.cf-list-pager {
  display: flex; align-items: center; justify-content: center;
  gap: 6px; margin-top: 20px;
}
.cf-list-page-btn {
  min-width: 34px; height: 34px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 8px;
  border: 1.5px solid var(--cf-slate-200);
  background: var(--cf-white);
  font-size: 13px; font-weight: 600; font-family: var(--cf-font);
  color: var(--cf-slate-600);
  cursor: pointer;
  transition: border-color .12s, background .12s, color .12s;
}
.cf-list-page-btn:hover:not(:disabled) { border-color: var(--cf-indigo); color: var(--cf-indigo); background: var(--cf-indigo-bg); }
.cf-list-page-btn.active { background: var(--cf-indigo); color: white; border-color: var(--cf-indigo); }
.cf-list-page-btn:disabled { opacity: .4; cursor: not-allowed; }
.cf-list-page-btn svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; }
`;

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

function formatDate( dateStr ) {
	if ( ! dateStr ) return '—';
	try {
		return new Date( dateStr ).toLocaleDateString( 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' } );
	} catch {
		return dateStr;
	}
}

function formatAmount( amount, currency ) {
	if ( ! amount ) return '—';
	const sym = CURRENCY_SYMBOLS[ currency ] || '£';
	return `${ sym }${ parseFloat( amount ).toLocaleString( 'en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) }`;
}

function StatusBadge( { status } ) {
	const cfg = STATUS_CONFIG[ status ] || STATUS_CONFIG.draft;
	return (
		<span className="cf-list-badge" style={ { background: cfg.bg, color: cfg.color } }>
			{ cfg.label }
		</span>
	);
}

export default function ProposalList( {
	proposals = [],
	loading = false,
	error = null,
	onNewProposal,
	onEditProposal,
	onEditContent,
	onRefresh,
} ) {
	injectStyles( 'cf-list-styles', CSS );

	const [ activeTab, setActiveTab ]         = useState( 'all' );
	const [ search, setSearch ]               = useState( '' );
	const [ page, setPage ]                   = useState( 1 );
	const [ deletingId, setDeletingId ]       = useState( null );
	const [ sendingId, setSendingId ]         = useState( null );
	const [ refreshing, setRefreshing ]       = useState( false );
	const [ declineReason, setDeclineReason ] = useState( null ); // { title, reason }

	async function handleRefresh() {
		setRefreshing( true );
		try {
			await onRefresh();
		} finally {
			setRefreshing( false );
		}
	}

	// Count per tab
	const counts = useMemo( () => {
		const c = { all: proposals.length };
		TABS.slice( 1 ).forEach( t => {
			c[ t.id ] = proposals.filter( p => p.status === t.id ).length;
		} );
		return c;
	}, [ proposals ] );

	// Filter
	const filtered = useMemo( () => {
		let list = proposals;

		if ( activeTab !== 'all' ) {
			list = list.filter( p => p.status === activeTab );
		}

		if ( search.trim() ) {
			const q = search.toLowerCase();
			list = list.filter( p =>
				( p.client_name || '' ).toLowerCase().includes( q ) ||
				( p.title || '' ).toLowerCase().includes( q ) ||
				( p.client_email || '' ).toLowerCase().includes( q )
			);
		}

		return list;
	}, [ proposals, activeTab, search ] );

	// Pagination
	const pageCount = Math.ceil( filtered.length / PER_PAGE );
	const paginated = filtered.slice( ( page - 1 ) * PER_PAGE, page * PER_PAGE );

	function handleTabChange( id ) {
		setActiveTab( id );
		setPage( 1 );
	}

	function handleSearch( e ) {
		setSearch( e.target.value );
		setPage( 1 );
	}

	async function handleDelete( id ) {
		if ( ! window.confirm( 'Delete this proposal? This cannot be undone.' ) ) return;
		setDeletingId( id );
		try {
			await cfFetch( `proposals/${ id }`, { method: 'DELETE' } );
			onRefresh();
		} catch ( e ) {
			alert( e.message || 'Delete failed.' );
		} finally {
			setDeletingId( null );
		}
	}

	async function handleSend( proposal ) {
		const email = proposal.client_email || window.prompt(
			'Enter client email address to send to:',
			''
		);
		if ( ! email ) return;
		setSendingId( proposal.id );
		try {
			await cfFetch( `proposals/${ proposal.id }/send`, {
				method: 'POST',
				body: JSON.stringify( { client_email: email } ),
			} );
			onRefresh();
		} catch ( e ) {
			alert( e.message || 'Send failed.' );
		} finally {
			setSendingId( null );
		}
	}

	async function handleDuplicate( id ) {
		try {
			await cfFetch( `proposals/${ id }/duplicate`, { method: 'POST' } );
			onRefresh();
		} catch ( e ) {
			alert( e.message || 'Duplicate failed.' );
		}
	}

	return (
		<div className="cf-list-wrap">
			{/* Header */ }
			<div className="cf-list-header">
				<div>
					<h1 className="cf-list-title">Proposals</h1>
					<p className="cf-list-subtitle">Manage and track your client proposals</p>
				</div>
				<div style={ { display: 'flex', gap: 8, alignItems: 'center' } }>
					<button
						type="button"
						className={ `cf-list-refresh-btn${ refreshing ? ' spinning' : '' }` }
						onClick={ handleRefresh }
						disabled={ refreshing || loading }
						title="Refresh proposals"
					>
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<polyline points="23 4 23 10 17 10"/>
							<path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
						</svg>
						Refresh
					</button>
					<button type="button" className="cf-list-new-btn" onClick={ onNewProposal }>
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
						</svg>
						New Proposal
					</button>
				</div>
			</div>

			{ error && (
				<div className="cf-list-error">
					<svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
					</svg>
					{ error }
				</div>
			) }

			{/* Tabs + search */ }
			<div className="cf-list-controls">
				<div className="cf-list-tabs">
					{ TABS.map( tab => (
						<button
							key={ tab.id }
							type="button"
							className={ `cf-list-tab${ activeTab === tab.id ? ' active' : '' }` }
							onClick={ () => handleTabChange( tab.id ) }
						>
							{ tab.label }
							<span className="cf-list-tab-count">{ counts[ tab.id ] || 0 }</span>
						</button>
					) ) }
				</div>

				<div className="cf-list-search-wrap">
					<svg className="cf-list-search-icon" viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
						<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
					</svg>
					<input
						type="search"
						className="cf-list-search"
						placeholder="Search proposals…"
						value={ search }
						onChange={ handleSearch }
					/>
				</div>
			</div>

			{/* Column headers */ }
			{ ( loading || paginated.length > 0 ) && (
				<div className="cf-list-col-headers">
					<div>Client</div>
					<div>Proposal</div>
					<div>Amount</div>
					<div>Status</div>
					<div>Created</div>
					<div />
				</div>
			) }

			{/* Loading skeletons */ }
			{ loading && [ 1, 2, 3, 4 ].map( i => (
				<div key={ i } className="cf-list-skeleton" style={ { animationDelay: `${ i * 0.1 }s` } } />
			) ) }

			{/* Rows */ }
			{ ! loading && paginated.map( ( proposal, idx ) => (
				<div
					key={ proposal.id }
					className="cf-list-row"
					data-status={ proposal.status }
					style={ { animation: `cf-fade-up .25s ease ${ idx * 0.04 }s both` } }
				>
					{/* Client */ }
					<div>
						<div className="cf-list-client-name">{ proposal.client_name || 'No client' }</div>
						{ proposal.client_company && (
							<div className="cf-list-client-company">{ proposal.client_company }</div>
						) }
					</div>

					{/* Title */ }
					<div className="cf-list-proposal-title" title={ proposal.title }>
						{ proposal.title || 'Untitled' }
					</div>

					{/* Amount */ }
					<div className="cf-list-amount">
						{ formatAmount( proposal.total_amount, proposal.currency ) }
					</div>

					{/* Status */ }
					<div>
						<StatusBadge status={ proposal.status } />
					</div>

					{/* Date */ }
					<div className="cf-list-date">{ formatDate( proposal.created_at ) }</div>

					{/* Actions */ }
					<div className="cf-list-actions">
						{ proposal.status === 'declined' && proposal.decline_reason && (
							<button
								type="button"
								className="cf-list-action-btn"
								title="View decline reason"
								onClick={ () => setDeclineReason( { title: proposal.title, reason: proposal.decline_reason } ) }
							>
								<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
									<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
								</svg>
							</button>
						) }
						{ [ 'draft', 'declined', 'expired' ].includes( proposal.status ) && (
							<>
								{ proposal.status === 'draft' && (
									<button
										type="button"
										className="cf-list-action-btn"
										title="Send to client"
										disabled={ sendingId === proposal.id }
										onClick={ () => handleSend( proposal ) }
									>
										{ sendingId === proposal.id ? (
											<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round" style={ { animation: 'cf-spin 1s linear infinite' } }>
												<circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2.5" strokeDasharray="40 20"/>
											</svg>
										) : (
											<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
												<line x1="22" y1="2" x2="11" y2="13"/>
												<polygon points="22 2 15 22 11 13 2 9 22 2"/>
											</svg>
										) }
									</button>
								) }
								<button
									type="button"
									className="cf-list-action-btn"
									title="Edit proposal"
									onClick={ () => onEditProposal( proposal.id ) }
								>
									<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
										<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
										<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
									</svg>
								</button>
								<button
									type="button"
									className="cf-list-action-btn"
									title="Edit content"
									onClick={ () => onEditContent( proposal.id ) }
								>
									<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
										<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
										<polyline points="14 2 14 8 20 8"/>
										<line x1="16" y1="13" x2="8" y2="13"/>
										<line x1="16" y1="17" x2="8" y2="17"/>
										<polyline points="10 9 9 9 8 9"/>
									</svg>
								</button>
							</>
						) }
						<button
							type="button"
							className="cf-list-action-btn"
							title="Duplicate"
							onClick={ () => handleDuplicate( proposal.id ) }
						>
							<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
								<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
							</svg>
						</button>
						<button
							type="button"
							className="cf-list-action-btn danger"
							title="Delete"
							disabled={ deletingId === proposal.id }
							onClick={ () => handleDelete( proposal.id ) }
						>
							<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
								<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
							</svg>
						</button>
					</div>
				</div>
			) ) }

			{/* Empty state */ }
			{ ! loading && paginated.length === 0 && (
				<div className="cf-list-empty">
					<div className="cf-list-empty-icon">
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
							<polyline points="14 2 14 8 20 8"/>
							<line x1="12" y1="18" x2="12" y2="12"/>
							<line x1="9" y1="15" x2="15" y2="15"/>
						</svg>
					</div>
					<h3>{ search || activeTab !== 'all' ? 'No proposals found' : 'No proposals yet' }</h3>
					<p>
						{ search
							? `No proposals match "${ search }". Try a different search.`
							: activeTab !== 'all'
							? `You have no ${ activeTab } proposals yet.`
							: window.cfData?.onboardingComplete === false
							? <>New to ClientFlow? <a href="admin.php?page=clientflow-setup" style={ { color: 'var(--cf-indigo)' } }>Complete your setup</a> then create your first proposal.</>
							: 'Create your first proposal to get started.' }
					</p>
					{ ! search && activeTab === 'all' && (
						<button type="button" className="cf-list-new-btn" onClick={ onNewProposal } style={ { marginTop: 8 } }>
							<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round" style={ { width: 15, height: 15, stroke: 'currentColor', strokeWidth: 2.5 } }>
								<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
							</svg>
							Create Proposal
						</button>
					) }
				</div>
			) }

			{/* Pagination */ }
			{ pageCount > 1 && (
				<div className="cf-list-pager">
					<button
						type="button"
						className="cf-list-page-btn"
						disabled={ page === 1 }
						onClick={ () => setPage( p => p - 1 ) }
						aria-label="Previous page"
					>
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
						</svg>
					</button>

					{ Array.from( { length: pageCount }, ( _, i ) => i + 1 )
						.filter( p => p === 1 || p === pageCount || Math.abs( p - page ) <= 1 )
						.reduce( ( acc, p, idx, arr ) => {
							if ( idx > 0 && p - arr[ idx - 1 ] > 1 ) acc.push( '…' );
							acc.push( p );
							return acc;
						}, [] )
						.map( ( p, i ) =>
							p === '…' ? (
								<span key={ `ellipsis-${ i }` } style={ { padding: '0 4px', color: 'var(--cf-slate-400)' } }>…</span>
							) : (
								<button
									key={ p }
									type="button"
									className={ `cf-list-page-btn${ page === p ? ' active' : '' }` }
									onClick={ () => setPage( p ) }
								>
									{ p }
								</button>
							)
						)
					}

					<button
						type="button"
						className="cf-list-page-btn"
						disabled={ page === pageCount }
						onClick={ () => setPage( p => p + 1 ) }
						aria-label="Next page"
					>
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
						</svg>
					</button>
				</div>
			) }

			{ declineReason && (
				<div
					className="cf-decline-overlay"
					onClick={ e => { if ( e.target === e.currentTarget ) setDeclineReason( null ); } }
					role="dialog"
					aria-modal="true"
					aria-labelledby="cf-decline-modal-title"
				>
					<div className="cf-decline-modal">
						<div className="cf-decline-modal-header">
							<div className="cf-decline-modal-icon">
								<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
									<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
								</svg>
							</div>
							<div className="cf-decline-modal-titles">
								<div className="cf-decline-modal-title" id="cf-decline-modal-title">Client's Decline Reason</div>
								<div className="cf-decline-modal-subtitle">{ declineReason.title }</div>
							</div>
						</div>
						<div className="cf-decline-modal-body">{ declineReason.reason }</div>
						<div className="cf-decline-modal-close">
							<button
								type="button"
								className="cf-decline-modal-close-btn"
								onClick={ () => setDeclineReason( null ) }
							>
								Close
							</button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
