/**
 * PortalLogin
 *
 * Full-page magic link request form. Split layout: indigo brand panel (left)
 * + warm paper form panel (right). Collapses to single column on mobile.
 *
 * Reads: window.cfPortalData.businessName, .businessLogo
 */

const { useState } = wp.element;

const apiFetch = ( path, opts = {} ) =>
	fetch( window.cfPortalData.apiUrl + path, {
		headers: {
			'X-WP-Nonce':    window.cfPortalData.nonce,
			'Content-Type':  'application/json',
		},
		...opts,
	} ).then( r => r.json() );

injectStyles( 'cf-global-s', `@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap');
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: 'DM Sans', sans-serif; -webkit-font-smoothing: antialiased; }` );

injectStyles( 'cpl-s', `
/* ── Shell ─────────────────────────────────────────────── */
.cpl-shell {
	display: flex;
	min-height: 100vh;
}

/* ── Brand panel ────────────────────────────────────────── */
.cpl-brand {
	flex: 0 0 44%;
	background: linear-gradient(160deg, #312E81 0%, #4F46E5 45%, #6366F1 75%, #8B5CF6 100%);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 60px 48px;
	position: relative;
	overflow: hidden;
}

.cpl-brand::before {
	content: '';
	position: absolute;
	inset: 0;
	background:
		radial-gradient(ellipse 70% 60% at 30% 40%, rgba(255,255,255,.07) 0%, transparent 70%),
		radial-gradient(ellipse 50% 40% at 80% 70%, rgba(139,92,246,.4) 0%, transparent 60%);
	pointer-events: none;
}

.cpl-brand-inner {
	position: relative;
	z-index: 1;
	text-align: center;
}

.cpl-logo-wrap {
	width: 80px;
	height: 80px;
	background: rgba(255,255,255,.15);
	border-radius: 20px;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 28px;
	backdrop-filter: blur(8px);
	border: 1px solid rgba(255,255,255,.2);
}

.cpl-logo-wrap img {
	max-width: 52px;
	max-height: 52px;
	object-fit: contain;
}

.cpl-logo-initials {
	font-family: 'Playfair Display', serif;
	font-size: 28px;
	font-weight: 700;
	color: #fff;
	letter-spacing: -0.02em;
}

.cpl-brand-name {
	font-family: 'DM Sans', sans-serif;
	font-size: 22px;
	font-weight: 600;
	color: #fff;
	margin: 0 0 12px;
	letter-spacing: -0.01em;
}

.cpl-brand-tagline {
	font-family: 'DM Sans', sans-serif;
	font-size: 15px;
	color: rgba(255,255,255,.65);
	margin: 0;
	line-height: 1.5;
}

/* Decorative circles */
.cpl-brand-deco {
	position: absolute;
	border-radius: 50%;
	border: 1px solid rgba(255,255,255,.08);
	pointer-events: none;
}
.cpl-brand-deco-1 { width: 340px; height: 340px; bottom: -80px; right: -80px; }
.cpl-brand-deco-2 { width: 200px; height: 200px; top: 40px; left: -60px; }

/* ── Form panel ─────────────────────────────────────────── */
.cpl-form-panel {
	flex: 1;
	background: #F8F7F5;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 60px 40px;
}

.cpl-card {
	background: #fff;
	border-radius: 20px;
	padding: 52px 48px 44px;
	width: 100%;
	max-width: 440px;
	box-shadow:
		0 2px 4px rgba(26,26,46,.04),
		0 12px 40px rgba(26,26,46,.08);
}

/* ── Typography ─────────────────────────────────────────── */
.cpl-heading {
	font-family: 'Playfair Display', serif;
	font-size: 36px;
	font-weight: 700;
	color: #1A1A2E;
	margin: 0 0 10px;
	letter-spacing: -0.02em;
	line-height: 1.15;
	animation: cpl-fade-up .5s ease both;
}

.cpl-sub {
	font-family: 'DM Sans', sans-serif;
	font-size: 15px;
	color: #6B7280;
	line-height: 1.65;
	margin: 0 0 32px;
	animation: cpl-fade-up .5s ease .08s both;
}

@keyframes cpl-fade-up {
	from { opacity: 0; transform: translateY(8px); }
	to   { opacity: 1; transform: translateY(0); }
}

/* ── Form ───────────────────────────────────────────────── */
.cpl-label {
	display: block;
	font-family: 'DM Sans', sans-serif;
	font-size: 13px;
	font-weight: 600;
	color: #374151;
	margin-bottom: 8px;
	letter-spacing: 0.02em;
	animation: cpl-fade-up .5s ease .14s both;
}

.cpl-input {
	display: block;
	width: 100%;
	height: 52px;
	padding: 0 16px;
	background: #F8F7F5;
	border: 1.5px solid #E5E7EB;
	border-radius: 10px;
	font-family: 'DM Sans', sans-serif;
	font-size: 15px;
	color: #1A1A2E;
	outline: none;
	transition: border-color .15s, box-shadow .15s;
	animation: cpl-fade-up .5s ease .18s both;
}

.cpl-input:focus {
	border-color: #6366F1;
	box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}

.cpl-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	width: 100%;
	height: 52px;
	margin-top: 20px;
	background: #6366F1;
	color: #fff;
	border: none;
	border-radius: 10px;
	font-family: 'DM Sans', sans-serif;
	font-size: 15px;
	font-weight: 600;
	cursor: pointer;
	transition: background .15s, transform .15s, box-shadow .15s;
	box-shadow: 0 3px 12px rgba(99,102,241,.3);
	letter-spacing: 0.01em;
	animation: cpl-fade-up .5s ease .24s both;
}

.cpl-btn:hover:not(:disabled) {
	background: #4F46E5;
	transform: translateY(-1px);
	box-shadow: 0 5px 18px rgba(99,102,241,.4);
}

.cpl-btn:disabled {
	opacity: .7;
	cursor: not-allowed;
}

/* ── Spinner ─────────────────────────────────────────────── */
.cpl-spinner {
	width: 18px;
	height: 18px;
	border: 2.5px solid rgba(255,255,255,.4);
	border-top-color: #fff;
	border-radius: 50%;
	animation: cpl-spin .7s linear infinite;
	flex-shrink: 0;
}
@keyframes cpl-spin { to { transform: rotate(360deg); } }

/* ── Success state ──────────────────────────────────────── */
.cpl-success {
	text-align: center;
	animation: cpl-fade-up .4s ease both;
}

.cpl-success-icon {
	width: 64px;
	height: 64px;
	background: #D1FAE5;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 20px;
}

.cpl-success-title {
	font-family: 'Playfair Display', serif;
	font-size: 22px;
	font-weight: 700;
	color: #1A1A2E;
	margin: 0 0 10px;
}

.cpl-success-msg {
	font-family: 'DM Sans', sans-serif;
	font-size: 15px;
	color: #6B7280;
	line-height: 1.65;
	margin: 0 0 20px;
}

.cpl-retry-link {
	font-family: 'DM Sans', sans-serif;
	font-size: 13px;
	color: #9CA3AF;
	background: none;
	border: none;
	cursor: pointer;
	text-decoration: underline;
	padding: 0;
}

.cpl-retry-link:hover { color: #6366F1; }

/* ── Error notice ────────────────────────────────────────── */
.cpl-error {
	background: #FEF2F2;
	border: 1px solid #FECACA;
	border-radius: 8px;
	padding: 12px 14px;
	font-family: 'DM Sans', sans-serif;
	font-size: 13px;
	color: #B91C1C;
	margin-top: 16px;
	animation: cpl-fade-up .3s ease both;
}

/* ── Small print ─────────────────────────────────────────── */
.cpl-fine-print {
	margin-top: 28px;
	font-family: 'DM Sans', sans-serif;
	font-size: 12px;
	color: #C0C0C8;
	text-align: center;
	line-height: 1.6;
	animation: cpl-fade-up .5s ease .3s both;
}

/* ── Mobile ──────────────────────────────────────────────── */
@media (max-width: 768px) {
	.cpl-shell { flex-direction: column; }

	.cpl-brand {
		flex: 0 0 auto;
		padding: 28px 24px;
		flex-direction: row;
		gap: 16px;
		justify-content: flex-start;
	}

	.cpl-brand-inner {
		display: flex;
		align-items: center;
		gap: 14px;
		text-align: left;
	}

	.cpl-logo-wrap { margin: 0; width: 44px; height: 44px; border-radius: 10px; }
	.cpl-logo-initials { font-size: 16px; }
	.cpl-brand-name { font-size: 16px; margin: 0; }
	.cpl-brand-tagline { display: none; }
	.cpl-brand-deco { display: none; }

	.cpl-form-panel { padding: 32px 20px; }
	.cpl-card { padding: 36px 28px 32px; }
	.cpl-heading { font-size: 28px; }
}

@media print {
	.cpl-brand { display: none; }
}
` );

export default function PortalLogin() {
	const { businessName, businessLogo } = window.cfPortalData || {};

	const initials = ( businessName || 'CF' )
		.split( ' ' )
		.slice( 0, 2 )
		.map( w => w[0] )
		.join( '' )
		.toUpperCase();

	const [ email,   setEmail   ] = useState( '' );
	const [ phase,   setPhase   ] = useState( 'idle' ); // idle | loading | success | error
	const [ errMsg,  setErrMsg  ] = useState( '' );

	async function handleSubmit( e ) {
		e.preventDefault();
		if ( ! email ) return;

		setPhase( 'loading' );
		setErrMsg( '' );

		try {
			const res = await apiFetch( '/portal/send-magic-link', {
				method: 'POST',
				body:   JSON.stringify( { email } ),
			} );

			if ( res.success ) {
				setPhase( 'success' );
			} else {
				setErrMsg( res.message || 'Something went wrong. Please try again.' );
				setPhase( 'error' );
			}
		} catch {
			setErrMsg( 'Network error. Please check your connection and try again.' );
			setPhase( 'error' );
		}
	}

	return (
		<div className="cpl-shell">

			{ /* ── Brand panel ── */ }
			<div className="cpl-brand">
				<div className="cpl-brand-deco cpl-brand-deco-1" />
				<div className="cpl-brand-deco cpl-brand-deco-2" />
				<div className="cpl-brand-inner">
					<div className="cpl-logo-wrap">
						{ businessLogo
							? <img src={ businessLogo } alt={ businessName } />
							: <span className="cpl-logo-initials">{ initials }</span>
						}
					</div>
					{ businessName && <p className="cpl-brand-name">{ businessName }</p> }
					<p className="cpl-brand-tagline">Your dedicated client space</p>
				</div>
			</div>

			{ /* ── Form panel ── */ }
			<div className="cpl-form-panel">
				<div className="cpl-card">

					{ 'success' === phase ? (
						<div className="cpl-success">
							<div className="cpl-success-icon">
								<svg width="28" height="28" viewBox="0 0 24 24" fill="none"
									stroke="#10B981" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
									<polyline points="20 6 9 17 4 12"/>
								</svg>
							</div>
							<h1 className="cpl-success-title">Check your email!</h1>
							<p className="cpl-success-msg">
								A login link is on its way to <strong>{ email }</strong>.
								It&rsquo;ll arrive within a minute.
							</p>
							<button
								className="cpl-retry-link"
								onClick={ () => { setPhase( 'idle' ); setEmail( '' ); } }
							>
								Didn&rsquo;t receive it? Try again
							</button>
						</div>
					) : (
						<form onSubmit={ handleSubmit }>
							<h1 className="cpl-heading">Welcome back</h1>
							<p className="cpl-sub">
								Enter your email address and we&rsquo;ll send you a secure login link.
							</p>

							<label className="cpl-label" htmlFor="cpl-email">Email address</label>
							<input
								id="cpl-email"
								className="cpl-input"
								type="email"
								placeholder="you@example.com"
								value={ email }
								onChange={ e => setEmail( e.target.value ) }
								required
								autoFocus
							/>

							{ 'error' === phase && (
								<div className="cpl-error">{ errMsg }</div>
							) }

							<button
								className="cpl-btn"
								type="submit"
								disabled={ 'loading' === phase }
							>
								{ 'loading' === phase
									? <>
										<span className="cpl-spinner" />
										Sending your link&hellip;
									</>
									: 'Send Login Link'
								}
							</button>

							<p className="cpl-fine-print">
								No password required. Links expire in 24 hours.
							</p>
						</form>
					) }

				</div>
			</div>

		</div>
	);
}
