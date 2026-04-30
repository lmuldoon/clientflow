/**
 * PortalSidebar
 *
 * 260px fixed sidebar. Active item: 3px indigo left-border + pale indigo bg.
 * Collapses to a bottom tab bar on mobile.
 *
 * Props: { page } — active page slug
 */

const { useState } = wp.element;

const apiFetch = ( path, opts = {} ) =>
	fetch( window.cfPortalData.apiUrl + path, {
		headers: {
			'X-WP-Nonce':   window.cfPortalData.nonce,
			'Content-Type': 'application/json',
		},
		...opts,
	} ).then( r => r.json() );

injectStyles( 'cps-s', `
/* ── Sidebar (desktop) ───────────────────────────────── */
.cps-sidebar {
	width: 260px;
	flex-shrink: 0;
	background: #FAFAF8;
	border-right: 1px solid #EEECEA;
	display: flex;
	flex-direction: column;
	min-height: 100vh;
	position: sticky;
	top: 0;
	height: 100vh;
	overflow-y: auto;
}

/* ── Branding ─────────────────────────────────────────── */
.cps-brand {
	padding: 28px 24px 22px;
	display: flex;
	align-items: center;
	gap: 12px;
}

.cps-logo-wrap {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	background: #EEF2FF;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	overflow: hidden;
}

.cps-logo-wrap img {
	width: 100%;
	height: 100%;
	object-fit: contain;
}

.cps-logo-initials {
	font-family: 'Playfair Display', serif;
	font-size: 15px;
	font-weight: 700;
	color: #6366F1;
}

.cps-biz-name {
	font-family: 'DM Sans', sans-serif;
	font-size: 14px;
	font-weight: 600;
	color: #1A1A2E;
	line-height: 1.3;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* ── Divider ──────────────────────────────────────────── */
.cps-divider {
	height: 1px;
	background: #EEECEA;
	margin: 0 0 12px;
}

/* ── Nav ──────────────────────────────────────────────── */
.cps-nav {
	flex: 1;
	padding: 4px 12px 12px;
}

.cps-nav-item {
	display: flex;
	align-items: center;
	gap: 11px;
	padding: 11px 14px;
	border-radius: 9px;
	text-decoration: none;
	font-family: 'DM Sans', sans-serif;
	font-size: 14px;
	font-weight: 500;
	color: #374151;
	margin-bottom: 2px;
	transition: background .12s, color .12s;
	position: relative;
	border-left: 3px solid transparent;
}

.cps-nav-item:hover:not(.cps-active) {
	background: #F3F4FF;
	color: #4F46E5;
}

.cps-nav-item.cps-active {
	background: #EEF2FF;
	color: #6366F1;
	font-weight: 600;
	border-left-color: #6366F1;
}

/* Soft gradient halo behind active icon */
.cps-nav-item.cps-active .cps-nav-icon {
	position: relative;
}
.cps-nav-item.cps-active .cps-nav-icon::before {
	content: '';
	position: absolute;
	inset: -6px;
	background: radial-gradient(circle, rgba(99,102,241,.18) 0%, transparent 70%);
	border-radius: 50%;
	pointer-events: none;
}

.cps-nav-icon {
	width: 18px;
	height: 18px;
	flex-shrink: 0;
}

/* ── Footer ───────────────────────────────────────────── */
.cps-footer {
	padding: 16px 20px 24px;
	border-top: 1px solid #EEECEA;
}

.cps-client-name {
	font-family: 'DM Sans', sans-serif;
	font-size: 13px;
	font-weight: 600;
	color: #374151;
	margin-bottom: 2px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.cps-client-email {
	font-family: 'DM Sans', sans-serif;
	font-size: 12px;
	color: #9CA3AF;
	margin-bottom: 14px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.cps-logout-btn {
	background: none;
	border: none;
	padding: 0;
	font-family: 'DM Sans', sans-serif;
	font-size: 13px;
	font-weight: 500;
	color: #9CA3AF;
	cursor: pointer;
	display: flex;
	align-items: center;
	gap: 6px;
	transition: color .12s;
}

.cps-logout-btn:hover { color: #EF4444; }

/* ── Mobile bottom tab bar ────────────────────────────── */
@media (max-width: 768px) {
	.cps-sidebar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		width: 100%;
		height: auto;
		min-height: auto;
		top: auto;
		border-right: none;
		border-top: 1px solid #EEECEA;
		flex-direction: row;
		z-index: 100;
	}

	.cps-brand  { display: none; }
	.cps-divider { display: none; }
	.cps-footer { display: none; }

	.cps-nav {
		display: flex;
		flex: 1;
		padding: 8px 4px;
		gap: 0;
	}

	.cps-nav-item {
		flex: 1;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 4px;
		padding: 8px 4px;
		margin-bottom: 0;
		border-left: none;
		border-radius: 8px;
		font-size: 11px;
		text-align: center;
	}

	.cps-nav-item.cps-active {
		border-left-color: transparent;
		background: #EEF2FF;
	}

	.cps-nav-icon { width: 20px; height: 20px; }
}
` );

// ── Icons ─────────────────────────────────────────────────────────────────────

function IconDashboard( { active } ) {
	const c = active ? '#6366F1' : '#6B7280';
	return (
		<svg className="cps-nav-icon" viewBox="0 0 20 20" fill="none"
			stroke={ c } strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
			<rect x="2" y="2"  width="6" height="6"  rx="1.5"/>
			<rect x="12" y="2" width="6" height="6"  rx="1.5"/>
			<rect x="2" y="12" width="6" height="6"  rx="1.5"/>
			<rect x="12" y="12" width="6" height="6" rx="1.5"/>
		</svg>
	);
}

function IconProposals( { active } ) {
	const c = active ? '#6366F1' : '#6B7280';
	return (
		<svg className="cps-nav-icon" viewBox="0 0 20 20" fill="none"
			stroke={ c } strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
			<path d="M4 3h8l4 4v10a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
			<polyline points="12 3 12 7 16 7"/>
			<line x1="7" y1="11" x2="13" y2="11"/>
			<line x1="7" y1="14" x2="11" y2="14"/>
		</svg>
	);
}

function IconProjects( { active } ) {
	const c = active ? '#6366F1' : '#6B7280';
	return (
		<svg className="cps-nav-icon" viewBox="0 0 20 20" fill="none"
			stroke={ c } strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
			<rect x="2" y="7" width="16" height="11" rx="1.5"/>
			<path d="M13 7V5a1 1 0 00-1-1H8a1 1 0 00-1 1v2"/>
			<line x1="7" y1="11" x2="13" y2="11"/>
			<line x1="7" y1="14" x2="10" y2="14"/>
		</svg>
	);
}

function IconPayments( { active } ) {
	const c = active ? '#6366F1' : '#6B7280';
	return (
		<svg className="cps-nav-icon" viewBox="0 0 20 20" fill="none"
			stroke={ c } strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
			<rect x="2" y="5" width="16" height="12" rx="2"/>
			<line x1="2" y1="9" x2="18" y2="9"/>
			<line x1="6" y1="13" x2="8" y2="13"/>
		</svg>
	);
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function PortalSidebar( { page } ) {
	const { businessName, businessLogo, clientData } = window.cfPortalData || {};
	const [ loggingOut, setLoggingOut ] = useState( false );

	const initials = ( businessName || 'CF' )
		.split( ' ' ).slice( 0, 2 ).map( w => w[0] ).join( '' ).toUpperCase();

	const nav = [
		{ slug: 'dashboard', label: 'Dashboard', Icon: IconDashboard },
		{ slug: 'proposals', label: 'Proposals', Icon: IconProposals },
		{ slug: 'projects',  label: 'Projects',  Icon: IconProjects  },
		{ slug: 'payments',  label: 'Payments',  Icon: IconPayments  },
	];

	async function handleLogout() {
		setLoggingOut( true );
		try {
			const res = await apiFetch( '/portal/logout', { method: 'POST' } );
			window.location.href = res.redirect_url || '/portal/login';
		} catch {
			window.location.href = '/portal/login';
		}
	}

	return (
		<aside className="cps-sidebar">

			{ /* ── Branding ── */ }
			<div className="cps-brand">
				<div className="cps-logo-wrap">
					{ businessLogo
						? <img src={ businessLogo } alt={ businessName } />
						: <span className="cps-logo-initials">{ initials }</span>
					}
				</div>
				<span className="cps-biz-name">{ businessName || 'ClientFlow' }</span>
			</div>

			<div className="cps-divider" />

			{ /* ── Navigation ── */ }
			<nav className="cps-nav">
				{ nav.map( ( { slug, label, Icon } ) => (
					<a
						key={ slug }
						href={ `/portal/${ slug }` }
						className={ `cps-nav-item${ page === slug ? ' cps-active' : '' }` }
					>
						<span className="cps-nav-icon">
							<Icon active={ page === slug } />
						</span>
						{ label }
					</a>
				) ) }
			</nav>

			{ /* ── Footer ── */ }
			<div className="cps-footer">
				{ clientData && (
					<>
						<p className="cps-client-name">{ clientData.name || 'Client' }</p>
						<p className="cps-client-email">{ clientData.email }</p>
					</>
				) }
				<button
					className="cps-logout-btn"
					onClick={ handleLogout }
					disabled={ loggingOut }
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
						<polyline points="16 17 21 12 16 7"/>
						<line x1="21" y1="12" x2="9" y2="12"/>
					</svg>
					{ loggingOut ? 'Signing out…' : 'Sign out' }
				</button>
			</div>

		</aside>
	);
}
