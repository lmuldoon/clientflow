import { useState, useEffect } from '@wordpress/element';
import { cfFetch } from '../../App';

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

const CSS = `
/* ── Section shell ─────────────────────────────────────────────── */
.cf-pa {
	margin-top: 36px;
}

.cf-pa-header {
	display: flex;
	align-items: center;
	gap: 10px;
	padding-bottom: 14px;
	border-bottom: 1.5px solid var(--cf-slate-100);
	margin-bottom: 20px;
}

.cf-pa-header-icon {
	width: 32px;
	height: 32px;
	background: var(--cf-slate-100);
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.cf-pa-header-icon svg {
	width: 15px;
	height: 15px;
	stroke: var(--cf-slate-500);
	stroke-width: 2;
}

.cf-pa-title {
	font-family: var(--cf-font-display);
	font-size: 17px;
	font-weight: 600;
	color: var(--cf-navy);
	letter-spacing: -.2px;
}

.cf-pa-new-btn {
	margin-left: auto;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 7px 14px;
	background: var(--cf-indigo);
	color: white;
	border: none;
	border-radius: var(--cf-radius-sm);
	font-size: 12.5px;
	font-weight: 600;
	font-family: var(--cf-font);
	cursor: pointer;
	transition: background .15s, box-shadow .15s, transform .12s;
	box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.cf-pa-new-btn:hover { background: #4F46E5; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99,102,241,.4); }
.cf-pa-new-btn svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 2.5; }

/* ── Create form ───────────────────────────────────────────────── */
.cf-pa-form {
	background: var(--cf-slate-50);
	border: 1px solid var(--cf-slate-200);
	border-radius: var(--cf-radius);
	padding: 20px;
	margin-bottom: 20px;
	animation: cf-pa-fade-in .2s ease both;
}

.cf-pa-form-title {
	font-size: 13.5px;
	font-weight: 700;
	color: var(--cf-navy);
	margin-bottom: 16px;
}

.cf-pa-form-row {
	display: grid;
	grid-template-columns: 200px 1fr;
	gap: 12px;
	margin-bottom: 12px;
}

.cf-pa-label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	color: var(--cf-slate-600);
	margin-bottom: 6px;
	text-transform: uppercase;
	letter-spacing: .04em;
}

.cf-pa-select,
.cf-pa-textarea {
	width: 100%;
	font-family: var(--cf-font);
	font-size: 13.5px;
	color: var(--cf-slate-800);
	background: var(--cf-white);
	border: var(--cf-input-border);
	border-radius: var(--cf-radius-sm);
	padding: 9px 12px;
	outline: none;
	transition: border-color .15s, box-shadow .15s;
	box-sizing: border-box;
}
.cf-pa-select:focus,
.cf-pa-textarea:focus {
	border-color: var(--cf-indigo);
	box-shadow: var(--cf-input-focus);
}
.cf-pa-textarea {
	resize: vertical;
	min-height: 80px;
	line-height: 1.55;
}
.cf-pa-textarea::placeholder { color: var(--cf-slate-300); }

.cf-pa-form-actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
	margin-top: 4px;
}

.cf-pa-submit-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 18px;
	background: var(--cf-indigo);
	color: white;
	border: none;
	border-radius: var(--cf-radius-sm);
	font-size: 13px;
	font-weight: 600;
	font-family: var(--cf-font);
	cursor: pointer;
	transition: background .15s;
}
.cf-pa-submit-btn:hover:not(:disabled) { background: #4F46E5; }
.cf-pa-submit-btn:disabled { opacity: .55; cursor: not-allowed; }
.cf-pa-submit-spinner {
	width: 12px;
	height: 12px;
	border: 2px solid rgba(255,255,255,.35);
	border-top-color: #fff;
	border-radius: 50%;
	animation: cf-pa-spin .65s linear infinite;
}

.cf-pa-cancel-btn {
	padding: 8px 16px;
	background: transparent;
	color: var(--cf-slate-500);
	border: 1.5px solid var(--cf-slate-200);
	border-radius: var(--cf-radius-sm);
	font-size: 13px;
	font-weight: 500;
	font-family: var(--cf-font);
	cursor: pointer;
	transition: background .12s, border-color .12s, color .12s;
}
.cf-pa-cancel-btn:hover { background: var(--cf-slate-50); border-color: var(--cf-slate-300); color: var(--cf-slate-700); }

/* ── Approval cards ────────────────────────────────────────────── */
.cf-pa-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.cf-pa-card {
	background: var(--cf-white);
	border: 1px solid var(--cf-slate-200);
	border-radius: var(--cf-radius-sm);
	padding: 14px 16px;
	display: grid;
	grid-template-columns: 1fr auto;
	gap: 12px;
	align-items: start;
	transition: border-color .15s, box-shadow .15s;
	animation: cf-pa-fade-in .3s ease both;
}
.cf-pa-card:hover {
	border-color: var(--cf-slate-300);
	box-shadow: var(--cf-shadow);
}
.cf-pa-card:hover .cf-pa-card-delete { opacity: 1; }

.cf-pa-card-left {}

.cf-pa-card-top {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
	flex-wrap: wrap;
}

.cf-pa-type-badge {
	display: inline-flex;
	padding: 3px 9px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .05em;
}
.cf-pa-type-badge.design      { background: var(--cf-indigo-bg);  color: var(--cf-indigo);  }
.cf-pa-type-badge.content     { background: var(--cf-amber-bg);   color: var(--cf-amber);   }
.cf-pa-type-badge.deliverable { background: var(--cf-emerald-bg); color: var(--cf-emerald); }
.cf-pa-type-badge.other       { background: var(--cf-slate-100);  color: var(--cf-slate-500); }

.cf-pa-status-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 9px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
}
.cf-pa-status-badge.pending  { background: var(--cf-amber-bg);   color: var(--cf-amber);   }
.cf-pa-status-badge.approved { background: var(--cf-emerald-bg); color: var(--cf-emerald); }
.cf-pa-status-badge.rejected { background: var(--cf-red-bg);     color: var(--cf-red);     }
.cf-pa-status-dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: currentColor;
	flex-shrink: 0;
}

.cf-pa-description {
	font-size: 13.5px;
	color: var(--cf-slate-700);
	line-height: 1.55;
	margin-bottom: 6px;
}

.cf-pa-meta {
	font-size: 11.5px;
	color: var(--cf-slate-400);
}

.cf-pa-client-comment {
	margin-top: 10px;
	padding: 10px 13px;
	background: var(--cf-slate-50);
	border-left: 3px solid var(--cf-slate-200);
	border-radius: 0 var(--cf-radius-sm) var(--cf-radius-sm) 0;
	font-size: 12.5px;
	color: var(--cf-slate-600);
	font-style: italic;
	line-height: 1.5;
}
.cf-pa-client-comment strong {
	font-style: normal;
	font-weight: 600;
	color: var(--cf-slate-500);
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: .04em;
	display: block;
	margin-bottom: 4px;
}

.cf-pa-card-delete {
	width: 28px;
	height: 28px;
	border: none;
	background: transparent;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	color: var(--cf-slate-300);
	opacity: 0;
	transition: opacity .15s, background .12s, color .12s;
	flex-shrink: 0;
}
.cf-pa-card-delete:hover { background: var(--cf-red-bg); color: var(--cf-red); }
.cf-pa-card-delete svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 2; }

/* ── Empty state ───────────────────────────────────────────────── */
.cf-pa-empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	padding: 40px 20px;
	text-align: center;
}
.cf-pa-empty-icon {
	width: 64px;
	height: 64px;
	background: var(--cf-slate-100);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 4px;
}
.cf-pa-empty-icon svg { width: 28px; height: 28px; stroke: var(--cf-slate-300); stroke-width: 1.5; }
.cf-pa-empty h4 { font-size: 14.5px; font-weight: 700; color: var(--cf-slate-600); margin: 0; }
.cf-pa-empty p  { font-size: 13px; color: var(--cf-slate-400); margin: 0; }

/* ── Error ─────────────────────────────────────────────────────── */
.cf-pa-error {
	display: flex;
	align-items: center;
	gap: 10px;
	background: var(--cf-red-bg);
	border: 1px solid rgba(239,68,68,.2);
	color: var(--cf-red);
	border-radius: var(--cf-radius-sm);
	padding: 11px 14px;
	font-size: 13px;
	font-weight: 500;
	margin-bottom: 14px;
}
.cf-pa-error svg { width: 14px; height: 14px; stroke: currentColor; flex-shrink: 0; }

/* ── Animations ────────────────────────────────────────────────── */
@keyframes cf-pa-fade-in {
	from { opacity: 0; transform: translateY(5px); }
	to   { opacity: 1; transform: translateY(0); }
}
@keyframes cf-pa-spin { to { transform: rotate(360deg); } }

/* ── Mobile ────────────────────────────────────────────────────── */
@media (max-width: 600px) {
	.cf-pa-form-row { grid-template-columns: 1fr; }
}
`;

const TYPE_LABELS = {
	design:      'Design',
	content:     'Content',
	deliverable: 'Deliverable',
	other:       'Other',
};

const STATUS_LABELS = {
	pending:  'Awaiting review',
	approved: 'Approved',
	rejected: 'Changes requested',
};

function formatDate( dateStr ) {
	if ( ! dateStr ) return '';
	try {
		return new Date( dateStr ).toLocaleDateString( 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' } );
	} catch {
		return dateStr;
	}
}

export default function ProjectApprovals( { projectId } ) {
	injectStyles( 'cf-pa-styles', CSS );

	const [ approvals,   setApprovals  ] = useState( [] );
	const [ loading,     setLoading    ] = useState( true );
	const [ showForm,    setShowForm   ] = useState( false );
	const [ creating,    setCreating   ] = useState( false );
	const [ error,       setError      ] = useState( null );
	const [ form,        setForm       ] = useState( { type: 'design', description: '' } );

	// ── Fetch on mount ────────────────────────────────────────────
	useEffect( () => {
		cfFetch( `projects/${ projectId }/approvals` )
			.then( data => setApprovals( data.approvals || [] ) )
			.catch( () => setError( 'Failed to load approval requests.' ) )
			.finally( () => setLoading( false ) );
	}, [ projectId ] );

	// ── Create ────────────────────────────────────────────────────
	async function handleCreate( e ) {
		e.preventDefault();
		setCreating( true );
		setError( null );
		try {
			const data = await cfFetch( `projects/${ projectId }/approvals`, {
				method: 'POST',
				body:   JSON.stringify( form ),
			} );
			setApprovals( data.approvals || [] );
			setShowForm( false );
			setForm( { type: 'design', description: '' } );
		} catch ( err ) {
			setError( err.message || 'Failed to create approval request.' );
		} finally {
			setCreating( false );
		}
	}

	// ── Delete ────────────────────────────────────────────────────
	async function handleDelete( approvalId ) {
		try {
			await cfFetch( `projects/${ projectId }/approvals/${ approvalId }`, { method: 'DELETE' } );
			setApprovals( prev => prev.filter( a => a.id !== approvalId ) );
		} catch ( err ) {
			setError( err.message || 'Delete failed.' );
		}
	}

	return (
		<div className="cf-pa">
			<div className="cf-pa-header">
				<div className="cf-pa-header-icon">
					<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
						<polyline points="9 11 12 14 22 4"/>
						<path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
					</svg>
				</div>
				<span className="cf-pa-title">Approvals</span>
				{ ! showForm && (
					<button type="button" className="cf-pa-new-btn" onClick={ () => setShowForm( true ) }>
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
						</svg>
						New Request
					</button>
				) }
			</div>

			{ error && (
				<div className="cf-pa-error">
					<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
						<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
					</svg>
					{ error }
				</div>
			) }

			{ showForm && (
				<form className="cf-pa-form" onSubmit={ handleCreate }>
					<div className="cf-pa-form-title">New Approval Request</div>
					<div className="cf-pa-form-row">
						<div>
							<label className="cf-pa-label" htmlFor="cf-pa-type">Type</label>
							<select
								id="cf-pa-type"
								className="cf-pa-select"
								value={ form.type }
								onChange={ e => setForm( f => ( { ...f, type: e.target.value } ) ) }
							>
								<option value="design">Design</option>
								<option value="content">Content</option>
								<option value="deliverable">Deliverable</option>
								<option value="other">Other</option>
							</select>
						</div>
						<div>
							<label className="cf-pa-label" htmlFor="cf-pa-desc">Description</label>
							<textarea
								id="cf-pa-desc"
								className="cf-pa-textarea"
								placeholder="Describe what needs reviewing…"
								value={ form.description }
								onChange={ e => setForm( f => ( { ...f, description: e.target.value } ) ) }
								rows={ 3 }
							/>
						</div>
					</div>
					<div className="cf-pa-form-actions">
						<button
							type="button"
							className="cf-pa-cancel-btn"
							onClick={ () => { setShowForm( false ); setError( null ); } }
						>
							Cancel
						</button>
						<button type="submit" className="cf-pa-submit-btn" disabled={ creating }>
							{ creating ? <><div className="cf-pa-submit-spinner" /> Sending…</> : 'Send Request' }
						</button>
					</div>
				</form>
			) }

			{ ! loading && approvals.length === 0 ? (
				<div className="cf-pa-empty">
					<div className="cf-pa-empty-icon">
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<polyline points="9 11 12 14 22 4"/>
							<path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
						</svg>
					</div>
					<h4>No approval requests yet</h4>
					<p>Create a request to get client sign-off on deliverables</p>
				</div>
			) : (
				<div className="cf-pa-list">
					{ approvals.map( ( approval, idx ) => (
						<div
							key={ approval.id }
							className="cf-pa-card"
							style={ { animationDelay: `${ idx * 0.04 }s` } }
						>
							<div className="cf-pa-card-left">
								<div className="cf-pa-card-top">
									<span className={ `cf-pa-type-badge ${ approval.type }` }>
										{ TYPE_LABELS[ approval.type ] || approval.type }
									</span>
									<span className={ `cf-pa-status-badge ${ approval.status }` }>
										<span className="cf-pa-status-dot" />
										{ STATUS_LABELS[ approval.status ] || approval.status }
									</span>
								</div>
								{ approval.description && (
									<div className="cf-pa-description">{ approval.description }</div>
								) }
								<div className="cf-pa-meta">
									Requested { formatDate( approval.created_at ) }
									{ approval.responded_at && ` · Responded ${ formatDate( approval.responded_at ) }` }
								</div>
								{ approval.client_comment && (
									<div className="cf-pa-client-comment">
										<strong>Client note</strong>
										{ approval.client_comment }
									</div>
								) }
							</div>

							<button
								type="button"
								className="cf-pa-card-delete"
								title="Delete request"
								onClick={ () => handleDelete( approval.id ) }
								aria-label="Delete approval request"
							>
								<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
									<polyline points="3 6 5 6 21 6"/>
									<path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
									<path d="M10 11v6M14 11v6"/>
								</svg>
							</button>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
