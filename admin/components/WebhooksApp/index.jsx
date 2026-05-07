/**
 * WebhooksApp
 *
 * Outbound webhook management for ClientFlow Pro/Agency accounts.
 * Add, edit, enable/disable, test, and delete webhook endpoints.
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { cfFetch } from '../../App.jsx';

// ── Styles ─────────────────────────────────────────────────────────────────────

const WH_CSS = `
@import url('https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap');

:root {
  --cf-navy:       #0F172A;
  --cf-indigo:     #6366F1;
  --cf-indigo-lt:  #818CF8;
  --cf-indigo-bg:  #EEF2FF;
  --cf-emerald:    #10B981;
  --cf-emerald-bg: #ECFDF5;
  --cf-amber:      #F59E0B;
  --cf-red:        #EF4444;
  --cf-red-bg:     #FEF2F2;
  --cf-slate-50:   #F8FAFC;
  --cf-slate-100:  #F1F5F9;
  --cf-slate-200:  #E2E8F0;
  --cf-slate-400:  #94A3B8;
  --cf-slate-500:  #64748B;
  --cf-white:      #FFFFFF;
  --cf-radius:     12px;
  --cf-radius-sm:  8px;
  --cf-shadow:     0 1px 3px rgba(15,23,42,.06), 0 4px 16px rgba(15,23,42,.08);
  --cf-font:       'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
}

.cf-wh * { box-sizing: border-box; }

.cf-wh {
  font-family: var(--cf-font);
  min-height: 100vh;
  padding: 32px 28px 64px;
  color: var(--cf-navy);
  -webkit-font-smoothing: antialiased;
}

/* Header */
.cf-wh-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 28px;
}
.cf-wh-title { font-size: 28px; font-weight: 800; letter-spacing: -.5px; margin: 0; line-height: 1.1; }
.cf-wh-sub   { font-size: 14px; color: var(--cf-slate-500); margin: 6px 0 0; line-height: 1.5; }

/* Buttons */
.cf-wh-btn {
  display: inline-flex; align-items: center; gap: 7px;
  height: 40px; padding: 0 18px;
  border-radius: var(--cf-radius-sm);
  font-size: 13px; font-weight: 600; font-family: var(--cf-font);
  cursor: pointer; transition: background .15s, box-shadow .15s, transform .1s;
  border: none; outline: none;
}
.cf-wh-btn--primary {
  background: var(--cf-indigo); color: #fff;
  box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.cf-wh-btn--primary:hover { background: #4F46E5; transform: translateY(-1px); }
.cf-wh-btn--primary:disabled { opacity: .5; cursor: default; transform: none; }
.cf-wh-btn--ghost {
  background: var(--cf-white); color: var(--cf-slate-500);
  border: 1.5px solid var(--cf-slate-200);
}
.cf-wh-btn--ghost:hover { border-color: var(--cf-slate-400); color: var(--cf-navy); }
.cf-wh-btn--danger { background: var(--cf-red-bg); color: var(--cf-red); border: 1.5px solid #FECACA; }
.cf-wh-btn--danger:hover { background: #FEE2E2; }
.cf-wh-btn--sm { height: 32px; padding: 0 12px; font-size: 12px; }

/* Form card */
.cf-wh-form-card {
  background: var(--cf-white);
  border: 1.5px solid var(--cf-indigo);
  border-radius: var(--cf-radius);
  padding: 24px 28px 28px;
  margin-bottom: 24px;
  box-shadow: var(--cf-shadow);
}
.cf-wh-form-title { font-size: 15px; font-weight: 700; margin: 0 0 20px; }
.cf-wh-field { margin-bottom: 18px; }
.cf-wh-label {
  display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px;
}
.cf-wh-input {
  width: 100%; height: 42px; border: 1.5px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm); padding: 0 14px;
  font-size: 13px; font-family: var(--cf-font); color: var(--cf-navy);
  background: #FAFAFA; transition: border-color .15s, box-shadow .15s; outline: none;
}
.cf-wh-input:focus { border-color: var(--cf-indigo); box-shadow: 0 0 0 3px rgba(99,102,241,.12); background: #fff; }
.cf-wh-input::placeholder { color: var(--cf-slate-400); }
.cf-wh-error-text { font-size: 12px; color: var(--cf-red); margin-top: 5px; }

/* Event checkboxes */
.cf-wh-events { display: flex; flex-wrap: wrap; gap: 8px; }
.cf-wh-event-label {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 12px; border: 1.5px solid var(--cf-slate-200);
  border-radius: 100px; font-size: 12px; font-weight: 500; cursor: pointer;
  transition: border-color .15s, background .15s;
  user-select: none;
}
.cf-wh-event-label:hover { border-color: var(--cf-indigo-lt); }
.cf-wh-event-label--on { border-color: var(--cf-indigo); background: var(--cf-indigo-bg); color: var(--cf-indigo); font-weight: 600; }
.cf-wh-event-label input { display: none; }

/* Form row */
.cf-wh-form-actions { display: flex; gap: 10px; margin-top: 24px; }

/* Webhook card */
.cf-wh-card {
  background: var(--cf-white);
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius);
  padding: 20px 24px;
  margin-bottom: 14px;
  box-shadow: var(--cf-shadow);
  transition: border-color .15s;
}
.cf-wh-card:hover { border-color: var(--cf-slate-400); }
.cf-wh-card--disabled { opacity: .65; }

.cf-wh-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.cf-wh-card-url { font-size: 14px; font-weight: 600; word-break: break-all; flex: 1; }
.cf-wh-card-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

/* Toggle */
.cf-wh-toggle { display: inline-flex; align-items: center; gap: 7px; cursor: pointer; user-select: none; }
.cf-wh-toggle-track {
  width: 36px; height: 20px; border-radius: 10px; background: var(--cf-slate-200);
  position: relative; transition: background .2s;
}
.cf-wh-toggle-track--on { background: var(--cf-emerald); }
.cf-wh-toggle-thumb {
  position: absolute; top: 2px; left: 2px; width: 16px; height: 16px;
  border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
  transition: left .2s;
}
.cf-wh-toggle-track--on .cf-wh-toggle-thumb { left: 18px; }
.cf-wh-toggle-label { font-size: 12px; font-weight: 500; color: var(--cf-slate-500); }

/* Event pills */
.cf-wh-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.cf-wh-pill {
  display: inline-block; padding: 3px 10px; border-radius: 100px;
  font-size: 11px; font-weight: 600; letter-spacing: .02em;
  background: var(--cf-indigo-bg); color: var(--cf-indigo);
}

/* Delivery log */
.cf-wh-log { margin-top: 14px; border-top: 1px solid var(--cf-slate-100); padding-top: 12px; }
.cf-wh-log-title { font-size: 11px; font-weight: 700; color: var(--cf-slate-400); letter-spacing: .06em; text-transform: uppercase; margin-bottom: 8px; }
.cf-wh-log-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--cf-slate-500); padding: 3px 0; }
.cf-wh-log-code {
  display: inline-block; padding: 2px 7px; border-radius: 5px;
  font-size: 11px; font-weight: 700; font-family: 'DM Mono', monospace;
}
.cf-wh-log-code--ok  { background: var(--cf-emerald-bg); color: #065F46; }
.cf-wh-log-code--err { background: var(--cf-red-bg); color: var(--cf-red); }

/* Test result inline */
.cf-wh-test-result { font-size: 12px; font-weight: 600; }
.cf-wh-test-result--ok  { color: var(--cf-emerald); }
.cf-wh-test-result--err { color: var(--cf-red); }

/* Secret banner */
.cf-wh-secret-box {
  background: var(--cf-slate-50); border: 1.5px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm); padding: 12px 16px; margin-top: 16px;
}
.cf-wh-secret-label { font-size: 11px; font-weight: 700; color: var(--cf-slate-400); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.cf-wh-secret-row { display: flex; gap: 8px; align-items: center; }
.cf-wh-secret-val {
  flex: 1; font-family: 'DM Mono', 'Courier New', monospace;
  font-size: 12px; color: var(--cf-slate-500); word-break: break-all;
  background: #fff; border: 1px solid var(--cf-slate-200); border-radius: 6px;
  padding: 6px 10px;
}
.cf-wh-secret-note { font-size: 11px; color: var(--cf-slate-400); margin-top: 6px; }

/* Upgrade banner */
.cf-wh-upgrade {
  background: linear-gradient(135deg, #EEF2FF 0%, #FAF5FF 100%);
  border: 1.5px solid var(--cf-indigo);
  border-radius: var(--cf-radius);
  padding: 28px 32px; text-align: center; margin-bottom: 24px;
}
.cf-wh-upgrade-title { font-size: 18px; font-weight: 800; margin: 0 0 8px; }
.cf-wh-upgrade-sub { font-size: 14px; color: var(--cf-slate-500); margin: 0 0 20px; }

/* Empty state */
.cf-wh-empty {
  text-align: center; padding: 60px 24px; color: var(--cf-slate-500);
}
.cf-wh-empty-title { font-size: 16px; font-weight: 700; color: var(--cf-navy); margin: 16px 0 8px; }
.cf-wh-empty-sub   { font-size: 14px; margin: 0; }

/* Notice */
.cf-wh-notice {
  padding: 12px 18px; border-radius: var(--cf-radius-sm);
  font-size: 13px; font-weight: 500;
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 20px;
}
.cf-wh-notice--error   { background: var(--cf-red-bg); color: #991B1B; border: 1px solid #FECACA; }
`;

// ── Constants ───────────────────────────────────────────────────────────────────

const ALL_EVENTS = [
	{ value: 'proposal.sent',      label: 'Proposal Sent' },
	{ value: 'proposal.accepted',  label: 'Proposal Accepted' },
	{ value: 'proposal.declined',  label: 'Proposal Declined' },
	{ value: 'payment.completed',  label: 'Payment Completed' },
	{ value: 'project.created',    label: 'Project Created' },
	{ value: 'project.completed',  label: 'Project Completed' },
];

// ── Sub-components ──────────────────────────────────────────────────────────────

function Toggle( { on, onChange, label } ) {
	return (
		<label className="cf-wh-toggle" onClick={ () => onChange( !on ) }>
			<span className={ `cf-wh-toggle-track${ on ? ' cf-wh-toggle-track--on' : '' }` }>
				<span className="cf-wh-toggle-thumb" />
			</span>
			{ label && <span className="cf-wh-toggle-label">{ label }</span> }
		</label>
	);
}

function EventCheckboxes( { selected, onChange } ) {
	const toggle = ( value ) => {
		onChange( selected.includes( value )
			? selected.filter( v => v !== value )
			: [ ...selected, value ]
		);
	};
	return (
		<div className="cf-wh-events">
			{ ALL_EVENTS.map( ev => (
				<label key={ ev.value } className={ `cf-wh-event-label${ selected.includes( ev.value ) ? ' cf-wh-event-label--on' : '' }` }>
					<input type="checkbox" checked={ selected.includes( ev.value ) } onChange={ () => toggle( ev.value ) } />
					{ ev.label }
				</label>
			) ) }
		</div>
	);
}

function LogRow( { log } ) {
	const codeClass = log.success ? 'cf-wh-log-code--ok' : 'cf-wh-log-code--err';
	const code      = log.response_code === 0 ? 'ERR' : String( log.response_code );
	const ts        = log.delivered_at ? new Date( log.delivered_at.replace( ' ', 'T' ) + 'Z' ).toLocaleString() : '';
	return (
		<div className="cf-wh-log-row">
			<span className={ `cf-wh-log-code ${ codeClass }` }>{ code }</span>
			<span>{ log.event }</span>
			<span style={{ marginLeft: 'auto', color: '#94A3B8' }}>{ ts }</span>
		</div>
	);
}

function WebhookForm( { initial, onSave, onCancel, saving } ) {
	const [ url, setUrl ]       = useState( initial?.url || '' );
	const [ events, setEvents ] = useState( initial?.events || [] );
	const [ error, setError ]   = useState( '' );

	const handleSave = () => {
		if ( ! url.trim() ) { setError( 'URL is required.' ); return; }
		if ( events.length === 0 ) { setError( 'Select at least one event.' ); return; }
		setError( '' );
		onSave( { url: url.trim(), events } );
	};

	return (
		<div className="cf-wh-form-card">
			<p className="cf-wh-form-title">{ initial ? 'Edit Webhook' : 'Add Webhook' }</p>

			<div className="cf-wh-field">
				<label className="cf-wh-label">Endpoint URL</label>
				<input
					className="cf-wh-input"
					type="url"
					placeholder="https://hooks.zapier.com/hooks/catch/…"
					value={ url }
					onChange={ e => setUrl( e.target.value ) }
					spellCheck="false"
					autoComplete="off"
				/>
			</div>

			<div className="cf-wh-field">
				<label className="cf-wh-label">Events</label>
				<EventCheckboxes selected={ events } onChange={ setEvents } />
			</div>

			{ error && <p className="cf-wh-error-text">{ error }</p> }

			<div className="cf-wh-form-actions">
				<button className="cf-wh-btn cf-wh-btn--primary" onClick={ handleSave } disabled={ saving }>
					{ saving ? 'Saving…' : ( initial ? 'Save Changes' : 'Add Webhook' ) }
				</button>
				<button className="cf-wh-btn cf-wh-btn--ghost" onClick={ onCancel }>Cancel</button>
			</div>
		</div>
	);
}

function WebhookCard( { webhook, onUpdate, onDelete } ) {
	const [ testState, setTestState ]   = useState( null ); // null | 'loading' | {ok, msg}
	const [ toggling, setToggling ]     = useState( false );
	const [ editing, setEditing ]       = useState( false );
	const [ saving, setSaving ]         = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ newSecret, setNewSecret ]   = useState( webhook.secret );

	const handleToggle = async ( enabled ) => {
		setToggling( true );
		try {
			const res = await cfFetch( `webhooks/${ webhook.id }`, {
				method: 'PATCH',
				body: JSON.stringify( { enabled } ),
			} );
			onUpdate( res.webhook );
		} catch {}
		setToggling( false );
	};

	const handleSave = async ( data ) => {
		setSaving( true );
		try {
			const res = await cfFetch( `webhooks/${ webhook.id }`, {
				method: 'PATCH',
				body: JSON.stringify( data ),
			} );
			onUpdate( res.webhook );
			setEditing( false );
		} catch {}
		setSaving( false );
	};

	const handleTest = async () => {
		setTestState( 'loading' );
		try {
			const res = await cfFetch( `webhooks/${ webhook.id }/test`, { method: 'POST' } );
			setTestState( { ok: res.success, msg: res.message } );
		} catch ( e ) {
			setTestState( { ok: false, msg: e.message || 'Request failed.' } );
		}
		setTimeout( () => setTestState( null ), 6000 );
	};

	const handleDelete = async () => {
		if ( ! confirming ) { setConfirming( true ); return; }
		try {
			await cfFetch( `webhooks/${ webhook.id }`, { method: 'DELETE' } );
			onDelete( webhook.id );
		} catch {}
		setConfirming( false );
	};

	if ( editing ) {
		return (
			<WebhookForm
				initial={ webhook }
				onSave={ handleSave }
				onCancel={ () => setEditing( false ) }
				saving={ saving }
			/>
		);
	}

	const logs = webhook.logs || [];

	return (
		<div className={ `cf-wh-card${ !webhook.enabled ? ' cf-wh-card--disabled' : '' }` }>
			<div className="cf-wh-card-top">
				<div style={{ flex: 1 }}>
					<div className="cf-wh-card-url">{ webhook.url }</div>
				</div>
				<div className="cf-wh-card-actions">
					<Toggle on={ webhook.enabled } onChange={ handleToggle } label={ webhook.enabled ? 'Enabled' : 'Disabled' } />

					<button className="cf-wh-btn cf-wh-btn--ghost cf-wh-btn--sm" onClick={ handleTest } disabled={ testState === 'loading' }>
						{ testState === 'loading' ? 'Sending…' : 'Send Test' }
					</button>

					<button className="cf-wh-btn cf-wh-btn--ghost cf-wh-btn--sm" onClick={ () => setEditing( true ) }>Edit</button>

					<button
						className="cf-wh-btn cf-wh-btn--danger cf-wh-btn--sm"
						onClick={ handleDelete }
						onBlur={ () => setTimeout( () => setConfirming( false ), 200 ) }
					>
						{ confirming ? 'Confirm?' : 'Delete' }
					</button>
				</div>
			</div>

			{ testState && testState !== 'loading' && (
				<p className={ `cf-wh-test-result ${ testState.ok ? 'cf-wh-test-result--ok' : 'cf-wh-test-result--err' }` }
					style={{ marginTop: 10, marginBottom: 0 }}>
					{ testState.ok ? '✓' : '✗' } { testState.msg }
				</p>
			) }

			<div className="cf-wh-pills">
				{ ( webhook.events || [] ).map( ev => (
					<span key={ ev } className="cf-wh-pill">{ ev }</span>
				) ) }
			</div>

			{ logs.length > 0 && (
				<div className="cf-wh-log">
					<div className="cf-wh-log-title">Recent Deliveries</div>
					{ logs.map( ( log, i ) => <LogRow key={ i } log={ log } /> ) }
				</div>
			) }
		</div>
	);
}

// ── Main component ──────────────────────────────────────────────────────────────

export default function WebhooksApp() {
	const { featureAccess = {}, userPlan = 'free' } = window.cfData || {};
	const canUse = featureAccess.use_webhooks;

	const [ webhooks, setWebhooks ] = useState( [] );
	const [ loading, setLoading ]   = useState( true );
	const [ error, setError ]       = useState( '' );
	const [ adding, setAdding ]     = useState( false );
	const [ saving, setSaving ]     = useState( false );
	const [ newWebhookSecret, setNewWebhookSecret ] = useState( null );
	const stylesInjected = useRef( false );

	// Inject styles once.
	useEffect( () => {
		if ( stylesInjected.current ) return;
		const el = document.createElement( 'style' );
		el.textContent = WH_CSS;
		document.head.appendChild( el );
		stylesInjected.current = true;
	}, [] );

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const res = await cfFetch( 'webhooks' );
			setWebhooks( res.webhooks || [] );
		} catch ( e ) {
			setError( e.message || 'Failed to load webhooks.' );
		}
		setLoading( false );
	}, [] );

	useEffect( () => { load(); }, [ load ] );

	const handleCreate = async ( data ) => {
		setSaving( true );
		setNewWebhookSecret( null );
		try {
			const res = await cfFetch( 'webhooks', {
				method: 'POST',
				body: JSON.stringify( data ),
			} );
			setWebhooks( prev => [ res.webhook, ...prev ] );
			setNewWebhookSecret( res.webhook.secret );
			setAdding( false );
		} catch ( e ) {
			setError( e.message || 'Failed to create webhook.' );
		}
		setSaving( false );
	};

	const handleUpdate = ( updated ) => {
		setWebhooks( prev => prev.map( w => w.id === updated.id ? { ...updated, logs: w.logs } : w ) );
	};

	const handleDelete = ( id ) => {
		setWebhooks( prev => prev.filter( w => w.id !== id ) );
	};

	return (
		<div className="cf-wh">
			{ /* Header */ }
			<div className="cf-wh-header">
				<div>
					<h1 className="cf-wh-title">Webhooks</h1>
					<p className="cf-wh-sub">Automatically POST to any URL when key events happen — connect Zapier, Make, or your own systems.</p>
				</div>
				{ canUse && ! adding && (
					<button className="cf-wh-btn cf-wh-btn--primary" onClick={ () => { setAdding( true ); setNewWebhookSecret( null ); } }>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						Add Webhook
					</button>
				) }
			</div>

			{ /* Upgrade banner */ }
			{ ! canUse && (
				<div className="cf-wh-upgrade">
					<p className="cf-wh-upgrade-title">Unlock Outbound Webhooks</p>
					<p className="cf-wh-upgrade-sub">Connect ClientFlow to Zapier, Make, Slack, and 7,000+ other tools. Available on Pro and Agency plans.</p>
					<a href="https://wpclientflow.co.uk/pricing" target="_blank" rel="noreferrer" className="cf-wh-btn cf-wh-btn--primary" style={{ textDecoration: 'none' }}>
						Upgrade Plan
					</a>
				</div>
			) }

			{ /* Error notice */ }
			{ error && (
				<div className="cf-wh-notice cf-wh-notice--error">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					{ error }
				</div>
			) }

			{ /* Secret reveal after creation */ }
			{ newWebhookSecret && (
				<div className="cf-wh-secret-box" style={{ marginBottom: 24 }}>
					<p className="cf-wh-secret-label">Signing Secret — copy now</p>
					<div className="cf-wh-secret-row">
						<span className="cf-wh-secret-val">{ newWebhookSecret }</span>
						<button className="cf-wh-btn cf-wh-btn--ghost cf-wh-btn--sm" onClick={ () => { navigator.clipboard.writeText( newWebhookSecret ); } }>Copy</button>
					</div>
					<p className="cf-wh-secret-note">Use this secret to verify the <code>X-ClientFlow-Signature</code> header on incoming requests. It won't be shown again.</p>
				</div>
			) }

			{ /* Add form */ }
			{ adding && canUse && (
				<WebhookForm
					onSave={ handleCreate }
					onCancel={ () => setAdding( false ) }
					saving={ saving }
				/>
			) }

			{ /* List */ }
			{ loading ? (
				<p style={{ color: '#94A3B8', fontSize: 14 }}>Loading…</p>
			) : webhooks.length === 0 && canUse ? (
				<div className="cf-wh-empty">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
						<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>
					</svg>
					<p className="cf-wh-empty-title">No webhooks yet</p>
					<p className="cf-wh-empty-sub">Click "Add Webhook" to start automating your workflow.</p>
				</div>
			) : (
				webhooks.map( wh => (
					<WebhookCard
						key={ wh.id }
						webhook={ wh }
						onUpdate={ handleUpdate }
						onDelete={ handleDelete }
					/>
				) )
			) }
		</div>
	);
}
