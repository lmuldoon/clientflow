/**
 * ProposalClientView
 *
 * Root orchestrator for the client-facing proposal page.
 * Fetches proposal by token, tracks the view, and wires up
 * accept / decline actions with toast feedback.
 *
 * Expects window.cfClientData = { apiUrl, token, businessName, businessLogo }
 */

const { useState, useEffect, useCallback, useRef } = wp.element;

import ClientProposalHeader  from '../ClientProposalHeader';
import ClientProposalSection from '../ClientProposalSection';
import ClientPricingTable    from '../ClientPricingTable';
import ClientActionButtons   from '../ClientActionButtons';
import PaymentModal          from '../PaymentModal';

/* ── Style injection ──────────────────────────────────────────── */
const injectStyles = ( id, css ) => {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
};

/* Fonts + global reset — injected once at root level */
const GLOBAL_CSS = `
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap');

*, *::before, *::after { box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
	background: #F8F7F5;
	margin: 0;
	padding: 0;
	min-height: 100vh;
	font-family: 'DM Sans', sans-serif;
	-webkit-font-smoothing: antialiased;
}

#cf-client-root { min-height: 100vh; }
`;

const PAGE_CSS = `
/* ── Page shell ───────────────────────────────────────────────── */
.cfv-page {
	min-height: 100vh;
	background: #F8F7F5;
	padding-bottom: 100px;
}

/* ── Document card ────────────────────────────────────────────── */
.cfv-doc {
	max-width: 780px;
	margin: 0 auto;
	background: #fff;
	box-shadow:
		0 1px 3px rgba(26,26,46,.04),
		0 8px 32px rgba(26,26,46,.07);
	min-height: calc(100vh - 100px);
	animation: cfv-rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes cfv-rise {
	from { opacity: 0; transform: translateY(14px); }
	to   { opacity: 1; transform: translateY(0); }
}

.cfv-body {
	padding: 52px 56px 72px;
}

/* Section stagger */
.cfv-body > * {
	animation: cfv-fade 0.4s ease both;
}
.cfv-body > *:nth-child(1) { animation-delay: 0.1s; }
.cfv-body > *:nth-child(2) { animation-delay: 0.17s; }
.cfv-body > *:nth-child(3) { animation-delay: 0.24s; }
.cfv-body > *:nth-child(4) { animation-delay: 0.31s; }
.cfv-body > *:nth-child(5) { animation-delay: 0.38s; }
.cfv-body > *:nth-child(n+6) { animation-delay: 0.44s; }

@keyframes cfv-fade {
	from { opacity: 0; transform: translateY(8px); }
	to   { opacity: 1; transform: translateY(0); }
}

/* ── Divider ─────────────────────────────────────────────────── */
.cfv-divider {
	height: 1px;
	background: linear-gradient(to right, transparent, #E5E7EB 15%, #E5E7EB 85%, transparent);
	margin: 44px 0;
}

/* ── Loading skeleton ────────────────────────────────────────── */
@keyframes cfv-shimmer {
	0%   { background-position: -700px 0; }
	100% { background-position: 700px 0; }
}

.cfv-skel {
	border-radius: 6px;
	background: linear-gradient(90deg, #EFEFEF 25%, #E4E4E4 50%, #EFEFEF 75%);
	background-size: 700px 100%;
	animation: cfv-shimmer 1.5s infinite;
}

.cfv-loading {
	max-width: 780px;
	margin: 0 auto;
	background: #fff;
	min-height: 100vh;
}

.cfv-loading__head {
	padding: 44px 56px 32px;
	border-bottom: 1px solid #F3F4F6;
	display: flex;
	gap: 28px;
	align-items: flex-start;
}

.cfv-loading__body {
	padding: 52px 56px;
}

.cfv-loading__block {
	margin-bottom: 36px;
}

/* ── Error state ─────────────────────────────────────────────── */
.cfv-error {
	max-width: 460px;
	margin: 80px auto;
	padding: 48px 36px;
	background: #fff;
	border-radius: 16px;
	box-shadow: 0 4px 28px rgba(26,26,46,.08);
	text-align: center;
}

.cfv-error__icon {
	width: 68px;
	height: 68px;
	background: #FEF2F2;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 24px;
	color: #EF4444;
}

.cfv-error__title {
	font-family: 'Playfair Display', serif;
	font-size: 26px;
	font-weight: 700;
	color: #1A1A2E;
	margin: 0 0 12px;
}

.cfv-error__msg {
	font-family: 'DM Sans', sans-serif;
	font-size: 15px;
	color: #6B7280;
	line-height: 1.65;
	margin: 0;
}

/* ── Toast ───────────────────────────────────────────────────── */
.cfv-toasts {
	position: fixed;
	top: 22px;
	right: 22px;
	z-index: 999;
	display: flex;
	flex-direction: column;
	gap: 10px;
	pointer-events: none;
}

.cfv-toast {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 13px 18px;
	border-radius: 10px;
	font-family: 'DM Sans', sans-serif;
	font-size: 14px;
	font-weight: 500;
	min-width: 260px;
	max-width: 360px;
	box-shadow: 0 4px 22px rgba(0,0,0,.12);
	animation: cfv-toast-in 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
	pointer-events: all;
	line-height: 1.4;
}

@keyframes cfv-toast-in {
	from { transform: translateX(40px); opacity: 0; }
	to   { transform: translateX(0);    opacity: 1; }
}

.cfv-toast--success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.cfv-toast--error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 840px) {
	.cfv-doc, .cfv-loading { margin: 0; box-shadow: none; }
}

@media (max-width: 600px) {
	.cfv-body, .cfv-loading__body { padding: 32px 24px 60px; }
	.cfv-loading__head { padding: 28px 24px; }
}

/* ── Print ───────────────────────────────────────────────────── */
@media print {
	.cfv-page  { background: #fff; padding: 0; }
	.cfv-doc   { box-shadow: none; min-height: auto; }
	.cfv-body  { padding: 24px 32px 40px; }
	.cfv-toasts { display: none; }
}
`;

/* ── Sub-components ───────────────────────────────────────────── */

function LoadingSkeleton() {
	return (
		<div className="cfv-loading">
			<div className="cfv-loading__head">
				<div className="cfv-skel" style={ { width: 58, height: 58, borderRadius: 13, flexShrink: 0 } } />
				<div style={ { flex: 1 } }>
					<div className="cfv-skel" style={ { height: 12, width: '22%', marginBottom: 14 } } />
					<div className="cfv-skel" style={ { height: 36, width: '65%', marginBottom: 14 } } />
					<div className="cfv-skel" style={ { height: 16, width: '42%', marginBottom: 22 } } />
					<div style={ { display: 'flex', gap: 10 } }>
						<div className="cfv-skel" style={ { height: 26, width: 80, borderRadius: 100 } } />
						<div className="cfv-skel" style={ { height: 26, width: 130 } } />
					</div>
				</div>
			</div>
			<div className="cfv-loading__body">
				{ [ 0.9, 0.7, 0.85 ].map( ( w, i ) => (
					<div key={ i } className="cfv-loading__block">
						<div className="cfv-skel" style={ { height: 24, width: '35%', marginBottom: 14 } } />
						<div className="cfv-skel" style={ { height: 14, marginBottom: 8 } } />
						<div className="cfv-skel" style={ { height: 14, width: `${ w * 100 }%`, marginBottom: 8 } } />
						<div className="cfv-skel" style={ { height: 14, width: '72%' } } />
					</div>
				) ) }
			</div>
		</div>
	);
}

function Toasts( { toasts } ) {
	return (
		<div className="cfv-toasts">
			{ toasts.map( t => (
				<div key={ t.id } className={ `cfv-toast cfv-toast--${ t.type }` }>
					{ t.type === 'success'
						? <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
						: <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					}
					{ t.message }
				</div>
			) ) }
		</div>
	);
}

/* ── API helper ───────────────────────────────────────────────── */
const BASE = ( window.cfClientData || {} ).apiUrl || '/wp-json/clientflow/v1/';

async function apiFetch( path, opts = {} ) {
	const res  = await fetch( BASE + path, { headers: { 'Content-Type': 'application/json' }, ...opts } );
	const json = await res.json().catch( () => ( {} ) );
	if ( ! res.ok ) throw new Error( json.message || `HTTP ${ res.status }` );
	return json;
}

/* ── Main component ───────────────────────────────────────────── */
export default function ProposalClientView() {
	injectStyles( 'cf-global-s',  GLOBAL_CSS );
	injectStyles( 'cf-page-s',    PAGE_CSS );

	const token        = ( window.cfClientData || {} ).token || '';
	const businessName = ( window.cfClientData || {} ).businessName || '';
	const businessLogo = ( window.cfClientData || {} ).businessLogo || '';

	const [ loadState,      setLoadState     ] = useState( 'loading' ); // 'loading' | 'loaded' | 'error'
	const [ proposal,       setProposal      ] = useState( null );
	const [ errorMsg,       setErrorMsg      ] = useState( '' );
	const [ actionLoading,  setActionLoading ] = useState( false );
	const [ toasts,         setToasts        ] = useState( [] );
	const [ showPayment,    setShowPayment   ] = useState( false );
	const viewTracked = useRef( false );

	/* Toast helper */
	const toast = useCallback( ( message, type = 'success' ) => {
		const id = Date.now();
		setToasts( ts => [ ...ts, { id, message, type } ] );
		setTimeout( () => setToasts( ts => ts.filter( t => t.id !== id ) ), 4500 );
	}, [] );

	/* Fetch proposal */
	useEffect( () => {
		if ( ! token ) {
			setLoadState( 'error' );
			setErrorMsg( 'Invalid proposal link.' );
			return;
		}
		apiFetch( `client/proposals/${ token }` )
			.then( data => { setProposal( data.proposal ); setLoadState( 'loaded' ); } )
			.catch( err => { setLoadState( 'error' ); setErrorMsg( err.message ); } );
	}, [ token ] );

	/* Track view — fires once after proposal loads */
	useEffect( () => {
		if ( loadState !== 'loaded' || viewTracked.current ) return;
		viewTracked.current = true;
		apiFetch( `client/proposals/${ token }/view`, { method: 'POST' } ).catch( () => {} );
	}, [ loadState ] );

	/* Accept */
	const handleAccept = useCallback( async () => {
		setActionLoading( true );
		try {
			await apiFetch( `client/proposals/${ token }/accept`, { method: 'POST' } );
			setProposal( p => ( { ...p, status: 'accepted', accepted_at: new Date().toISOString() } ) );
			toast( 'Proposal accepted! We\'ll be in touch shortly.' );
		} catch ( err ) {
			toast( err.message || 'Could not accept the proposal. Please try again.', 'error' );
		} finally {
			setActionLoading( false );
		}
	}, [ token ] );

	/* Decline */
	const handleDecline = useCallback( async ( reason = '' ) => {
		setActionLoading( true );
		try {
			const body = reason ? JSON.stringify( { reason } ) : undefined;
			await apiFetch( `client/proposals/${ token }/decline`, { method: 'POST', body } );
			setProposal( p => ( { ...p, status: 'declined' } ) );
			toast( 'Proposal declined. Thank you for letting us know.' );
		} catch ( err ) {
			toast( err.message || 'Could not decline the proposal. Please try again.', 'error' );
		} finally {
			setActionLoading( false );
		}
	}, [ token ] );

	/* ── Render states ────────────────────────────────────────── */
	if ( loadState === 'loading' ) {
		return (
			<div className="cfv-page">
				<LoadingSkeleton />
			</div>
		);
	}

	if ( loadState === 'error' ) {
		return (
			<div className="cfv-page">
				<div className="cfv-error">
					<div className="cfv-error__icon">
						<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
							<circle cx="12" cy="12" r="10"/>
							<line x1="15" y1="9" x2="9" y2="15"/>
							<line x1="9" y1="9" x2="15" y2="15"/>
						</svg>
					</div>
					<h1 className="cfv-error__title">Proposal Not Found</h1>
					<p className="cfv-error__msg">
						{ errorMsg || 'This proposal link may be invalid or has been removed. Please contact us for assistance.' }
					</p>
				</div>
			</div>
		);
	}

	/* ── Full document ────────────────────────────────────────── */
	const content   = proposal.content   || {};
	const sections  = content.sections   || [];
	const lineItems = content.line_items || [];

	return (
		<div className="cfv-page">
			<div className="cfv-doc">
				<ClientProposalHeader
					proposal={ proposal }
					businessName={ businessName }
					businessLogo={ businessLogo }
				/>

				<div className="cfv-body">
					{ sections.map( ( section, i ) => (
						<ClientProposalSection key={ i } section={ section } />
					) ) }

					{ lineItems.length > 0 && (
						<>
							<div className="cfv-divider" />
							<ClientPricingTable
								items={ lineItems }
								discountPct={ content.discount_pct || 0 }
								vatPct={ content.vat_pct || 0 }
								currency={ proposal.currency || 'GBP' }
								totalAmount={ proposal.total_amount }
							/>
						</>
					) }
				</div>
			</div>

			<ClientActionButtons
				status={ proposal.status }
				paymentEnabled={ proposal.payment_enabled }
				hasPaid={ !! proposal.has_paid }
				ownerEmail={ proposal.owner_email }
				onAccept={ handleAccept }
				onDecline={ handleDecline }
				onPayment={ () => setShowPayment( true ) }
				loading={ actionLoading }
			/>

			{ showPayment && (
				<PaymentModal
					proposal={ proposal }
					onClose={ () => setShowPayment( false ) }
				/>
			) }

			<Toasts toasts={ toasts } />
		</div>
	);
}
