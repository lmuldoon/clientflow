/**
 * ProposalWizard
 *
 * 5-step guided wizard for creating a new proposal.
 *   1 — Template Selection
 *   2 — Client Details
 *   3 — Proposal Settings
 *   4 — Pricing
 *   5 — Review & Create
 *
 * Props:
 *   onComplete {fn}  — called with the created proposal object
 *   onCancel   {fn}  — called when user cancels
 */
import { useState, useRef } from '@wordpress/element';
import TemplateSelector from '../TemplateSelector';
import ClientDetailsForm from '../ClientDetailsForm';
import ProposalSettings from '../ProposalSettings';
import PricingSetup from '../PricingSetup';
import { cfFetch } from '../../App';

const CURRENCY_SYMBOLS = { GBP: '£', USD: '$', EUR: '€', CAD: '$', AUD: '$' };

const STEPS = [
	{ num: 1, label: 'Template'  },
	{ num: 2, label: 'Client'    },
	{ num: 3, label: 'Settings'  },
	{ num: 4, label: 'Pricing'   },
	{ num: 5, label: 'Review'    },
];

const TEMPLATE_NAMES = {
	'web-design': 'Web Design',
	'retainer':   'Retainer',
	'marketing':  'Marketing Campaign',
	'blank':      'Blank Proposal',
};

// ─── Styles ───────────────────────────────────────────────────────────────────
const CSS = `
/* Wrapper */
.cf-wiz {
  background: var(--cf-white);
  border-radius: var(--cf-radius);
  box-shadow: var(--cf-shadow-lg);
  border: 1px solid var(--cf-slate-200);
  overflow: hidden;
}

/* Header bar */
.cf-wiz-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 28px;
  border-bottom: 1px solid var(--cf-slate-100);
  background: var(--cf-white);
}
.cf-wiz-title {
  font-family: var(--cf-font-display);
  font-size: 20px;
  color: var(--cf-navy);
  letter-spacing: -.3px;
}
.cf-wiz-cancel {
  display: flex; align-items: center; gap: 6px;
  background: none; border: none;
  font-size: 13px; font-weight: 500;
  color: var(--cf-slate-400);
  cursor: pointer;
  padding: 6px 10px;
  border-radius: var(--cf-radius-sm);
  font-family: var(--cf-font);
  transition: color .12s, background .12s;
}
.cf-wiz-cancel:hover { color: var(--cf-red); background: var(--cf-red-bg); }
.cf-wiz-cancel svg { width: 14px; height: 14px; stroke: currentColor; }

/* Step indicator */
.cf-wiz-steps {
  display: flex;
  align-items: center;
  padding: 24px 36px;
  background: var(--cf-slate-50);
  border-bottom: 1px solid var(--cf-slate-100);
  gap: 0;
}
.cf-wiz-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  flex: 1;
  position: relative;
}
/* Connecting line */
.cf-wiz-step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 15px;
  left: calc(50% + 15px);
  right: calc(-50% + 15px);
  height: 2px;
  background: var(--cf-slate-200);
  z-index: 0;
  transition: background .3s;
}
.cf-wiz-step.done:not(:last-child)::after {
  background: var(--cf-emerald);
}
.cf-wiz-step.active:not(:last-child)::after {
  background: linear-gradient(90deg, var(--cf-indigo), var(--cf-slate-200));
}

/* Circle */
.cf-wiz-step-circle {
  width: 30px; height: 30px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px;
  font-weight: 700;
  position: relative;
  z-index: 1;
  transition: background .25s, box-shadow .25s, color .25s;
  flex-shrink: 0;
}
.cf-wiz-step.upcoming .cf-wiz-step-circle {
  background: var(--cf-slate-100);
  color: var(--cf-slate-400);
  border: 2px solid var(--cf-slate-200);
}
.cf-wiz-step.active .cf-wiz-step-circle {
  background: var(--cf-indigo);
  color: white;
  box-shadow: 0 0 0 4px rgba(99,102,241,.18);
}
.cf-wiz-step.done .cf-wiz-step-circle {
  background: var(--cf-emerald);
  color: white;
}
.cf-wiz-step-circle svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2.5; }

/* Label */
.cf-wiz-step-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .04em;
  text-transform: uppercase;
  transition: color .2s;
}
.cf-wiz-step.upcoming .cf-wiz-step-label { color: var(--cf-slate-300); }
.cf-wiz-step.active   .cf-wiz-step-label { color: var(--cf-indigo); }
.cf-wiz-step.done     .cf-wiz-step-label { color: var(--cf-emerald); }

/* Content area */
.cf-wiz-body {
  padding: 32px 36px;
  min-height: 340px;
}

/* Step content animation */
.cf-wiz-content {
  animation-duration: .28s;
  animation-fill-mode: both;
  animation-timing-function: cubic-bezier(.25,.46,.45,.94);
}
.cf-wiz-content.slide-right { animation-name: cf-slide-in-right; }
.cf-wiz-content.slide-left  { animation-name: cf-slide-in-left; }

/* Step heading */
.cf-wiz-step-heading { margin-bottom: 24px; }
.cf-wiz-step-heading h2 {
  font-family: var(--cf-font-display);
  font-size: 22px;
  color: var(--cf-navy);
  letter-spacing: -.3px;
  margin-bottom: 4px;
}
.cf-wiz-step-heading p { font-size: 14px; color: var(--cf-slate-400); }

/* Footer */
.cf-wiz-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 36px;
  border-top: 1px solid var(--cf-slate-100);
  background: var(--cf-slate-50);
}
.cf-wiz-footer-left { display: flex; gap: 10px; }
.cf-wiz-footer-right { display: flex; gap: 10px; align-items: center; }

.cf-wiz-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 22px;
  border-radius: var(--cf-radius-sm);
  font-size: 13.5px;
  font-weight: 600;
  font-family: var(--cf-font);
  cursor: pointer;
  border: none;
  transition: background .15s, box-shadow .15s, transform .12s, opacity .15s;
  white-space: nowrap;
}
.cf-wiz-btn svg { width: 15px; height: 15px; stroke: currentColor; stroke-width: 2; }
.cf-wiz-btn.primary {
  background: var(--cf-indigo);
  color: white;
  box-shadow: 0 2px 8px rgba(99,102,241,.35);
}
.cf-wiz-btn.primary:hover {
  background: #4F46E5;
  box-shadow: 0 4px 16px rgba(99,102,241,.4);
  transform: translateY(-1px);
}
.cf-wiz-btn.primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
.cf-wiz-btn.ghost {
  background: transparent;
  color: var(--cf-slate-600);
  border: 1.5px solid var(--cf-slate-200);
}
.cf-wiz-btn.ghost:hover { border-color: var(--cf-slate-400); color: var(--cf-slate-800); }
.cf-wiz-btn.success {
  background: var(--cf-emerald);
  color: white;
  box-shadow: 0 2px 8px rgba(16,185,129,.35);
}
.cf-wiz-btn.success:hover { background: #059669; transform: translateY(-1px); }
.cf-wiz-btn.success:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* Spinner */
@keyframes cf-spin { to { transform: rotate(360deg); } }
.cf-spin { animation: cf-spin .7s linear infinite; }

/* Error banner */
.cf-wiz-error {
  display: flex; align-items: center; gap: 10px;
  background: var(--cf-red-bg);
  border: 1px solid rgba(239,68,68,.25);
  color: var(--cf-red);
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 13px;
  margin-bottom: 20px;
}
.cf-wiz-error svg { width: 16px; height: 16px; stroke: currentColor; flex-shrink: 0; }

/* ── Review step ──────────────────────────────────────────────── */
.cf-review-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}
.cf-review-section {
  background: var(--cf-slate-50);
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm);
  padding: 16px 20px;
}
.cf-review-section-title {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--cf-slate-400);
  margin-bottom: 10px;
}
.cf-review-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
.cf-review-row .k { color: var(--cf-slate-500); }
.cf-review-row .v { font-weight: 600; color: var(--cf-slate-800); }
.cf-review-total {
  background: linear-gradient(135deg, var(--cf-navy) 0%, var(--cf-navy-mid) 100%);
  border-radius: var(--cf-radius-sm);
  padding: 18px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  grid-column: span 2;
}
.cf-review-total .label { font-size: 13px; color: rgba(255,255,255,.6); }
.cf-review-total .amount {
  font-family: var(--cf-font-display);
  font-size: 28px;
  color: white;
  letter-spacing: -.5px;
}
.cf-review-items { grid-column: span 2; }
.cf-review-item-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  padding: 5px 0;
  border-bottom: 1px solid var(--cf-slate-100);
  color: var(--cf-slate-700);
}
.cf-review-item-row:last-child { border-bottom: none; }
`;

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

// ─── Review Step ──────────────────────────────────────────────────────────────
function ReviewStep( { data } ) {
	const { client, settings, lineItems, discountPct, vatPct, templateId, currency } = data;
	const sym = CURRENCY_SYMBOLS[ currency ] || '£';

	const subtotal     = lineItems.reduce( ( s, r ) => s + ( parseFloat( r.qty ) || 0 ) * ( parseFloat( r.unit_price ) || 0 ), 0 );
	const discountAmt  = subtotal * ( discountPct / 100 );
	const vatAmt       = ( subtotal - discountAmt ) * ( vatPct / 100 );
	const grand        = subtotal - discountAmt + vatAmt;

	return (
		<div>
			<div className="cf-review-grid">
				{/* Template */ }
				<div className="cf-review-section">
					<div className="cf-review-section-title">Template</div>
					<div className="cf-review-row"><span className="k">Type</span><span className="v">{ TEMPLATE_NAMES[ templateId ] || templateId }</span></div>
				</div>
				{/* Settings */ }
				<div className="cf-review-section">
					<div className="cf-review-section-title">Proposal</div>
					<div className="cf-review-row"><span className="k">Title</span><span className="v">{ settings.title || '—' }</span></div>
					<div className="cf-review-row"><span className="k">Expiry</span><span className="v">{ settings.expiry_date || '—' }</span></div>
					<div className="cf-review-row"><span className="k">Currency</span><span className="v">{ settings.currency || 'GBP' }</span></div>
					{ settings.require_deposit && (
						<div className="cf-review-row"><span className="k">Deposit</span><span className="v">{ settings.deposit_pct }%</span></div>
					) }
				</div>
				{/* Client */ }
				<div className="cf-review-section">
					<div className="cf-review-section-title">Client</div>
					<div className="cf-review-row"><span className="k">Name</span><span className="v">{ client.name || '—' }</span></div>
					<div className="cf-review-row"><span className="k">Email</span><span className="v">{ client.email || '—' }</span></div>
					{ client.company && <div className="cf-review-row"><span className="k">Company</span><span className="v">{ client.company }</span></div> }
				</div>
				{/* Line items */ }
				{ lineItems.length > 0 && (
					<div className="cf-review-section cf-review-items">
						<div className="cf-review-section-title">Line Items</div>
						{ lineItems.map( ( r ) => (
							<div key={ r.id } className="cf-review-item-row">
								<span>{ r.description || 'Unnamed item' } × { r.qty }</span>
								<span style={ { fontWeight: 600 } }>
									{ sym }{ ( ( parseFloat( r.qty ) || 0 ) * ( parseFloat( r.unit_price ) || 0 ) ).toFixed( 2 ) }
								</span>
							</div>
						) ) }
					</div>
				) }
				{/* Grand total */ }
				<div className="cf-review-total">
					<div className="label">Grand Total</div>
					<div className="amount">{ sym }{ grand.toFixed( 2 ) }</div>
				</div>
			</div>
		</div>
	);
}

// ─── Main Component ───────────────────────────────────────────────────────────
export default function ProposalWizard( { initialProposal = null, onComplete, onCancel } ) {
	injectStyles( 'cf-wiz-styles', CSS );

	const isEdit   = !! initialProposal;
	const userPlan = window.cfData?.userPlan || 'free';

	// `content` is decoded to an object by the PHP API — use it directly.
	// Guard against the rare case where it might still arrive as a string.
	const parsedContent = isEdit
		? ( typeof initialProposal.content === 'object' && initialProposal.content !== null
			? initialProposal.content
			: ( () => { try { return JSON.parse( initialProposal.content || '{}' ); } catch { return {}; } } )() )
		: {};

	const [ step, setStep ]           = useState( isEdit ? 2 : 1 );
	const [ direction, setDirection ] = useState( 'right' );
	const [ templateId, setTemplateId ] = useState( parsedContent.template_id || ( isEdit ? 'blank' : 'web-design' ) );
	const [ client, setClient ]       = useState( isEdit ? {
		name:    initialProposal.client_name    || '',
		email:   initialProposal.client_email   || '',
		company: initialProposal.client_company || '',
		phone:   initialProposal.client_phone   || '',
	} : { name: '', email: '', company: '', phone: '' } );
	const [ settings, setSettings ]   = useState( isEdit ? {
		title:           initialProposal.title        || '',
		currency:        initialProposal.currency      || 'GBP',
		expiry_date:     initialProposal.expiry_date   || '',
		deposit_pct:     parsedContent.deposit_pct     ?? 25,
		require_deposit: parsedContent.require_deposit ?? false,
	} : { title: '', currency: 'GBP', expiry_date: '', deposit_pct: 25, require_deposit: false } );
	const [ lineItems, setLineItems ] = useState( parsedContent.line_items   || [] );
	const [ discountPct, setDiscountPct ] = useState( parsedContent.discount_pct ?? 0 );
	const [ vatPct, setVatPct ]       = useState( parsedContent.vat_pct      ?? 20 );
	const [ errors, setErrors ]       = useState( {} );
	const [ submitting, setSubmitting ] = useState( false );
	const [ submitError, setSubmitError ] = useState( null );

	// Validate current step before advancing
	function validate() {
		const errs = {};

		if ( step === 1 && ! templateId ) {
			errs.template = 'Please choose a template.';
		}

		if ( step === 2 ) {
			if ( ! client.name?.trim() ) errs.name = 'Client name is required.';
			if ( ! client.email?.trim() ) errs.email = 'Email address is required.';
			else if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( client.email ) ) errs.email = 'Enter a valid email address.';
		}

		if ( step === 3 ) {
			if ( ! settings.title?.trim() ) errs.title = 'Proposal title is required.';
		}

		setErrors( errs );
		return Object.keys( errs ).length === 0;
	}

	function goNext() {
		if ( ! validate() ) return;
		setDirection( 'right' );
		setStep( s => Math.min( s + 1, 5 ) );
	}

	function goBack() {
		setDirection( 'left' );
		setErrors( {} );
		setStep( s => Math.max( s - 1, 1 ) );
	}

	function handleClientChange( field, value ) {
		setClient( prev => ( { ...prev, [ field ]: value } ) );
		if ( errors[ field ] ) setErrors( prev => { const e = { ...prev }; delete e[ field ]; return e; } );
	}

	function handleSettingsChange( field, value ) {
		setSettings( prev => ( { ...prev, [ field ]: value } ) );
		if ( errors[ field ] ) setErrors( prev => { const e = { ...prev }; delete e[ field ]; return e; } );
	}

	function handlePricingUpdate( { items, discount_pct, vat_pct } ) {
		setLineItems( items );
		setDiscountPct( discount_pct );
		setVatPct( vat_pct );
	}

	async function handleCreate() {
		if ( ! validate() ) return;
		setSubmitting( true );
		setSubmitError( null );

		const subtotal    = lineItems.reduce( ( s, r ) => s + ( parseFloat( r.qty ) || 0 ) * ( parseFloat( r.unit_price ) || 0 ), 0 );
		const discountAmt = subtotal * ( discountPct / 100 );
		const vatAmt      = ( subtotal - discountAmt ) * ( vatPct / 100 );
		const grand       = subtotal - discountAmt + vatAmt;

		const body = {
			template_id:     templateId,
			title:           settings.title,
			currency:        settings.currency || 'GBP',
			expiry_date:     settings.expiry_date,
			deposit_pct:     settings.deposit_pct,
			require_deposit: settings.require_deposit,
			total_amount:    grand,
			client_name:     client.name,
			client_email:    client.email,
			client_company:  client.company,
			client_phone:    client.phone,
			line_items:      lineItems,
			discount_pct:    discountPct,
			vat_pct:         vatPct,
		};

		try {
			const endpoint = isEdit
				? `proposals/${ initialProposal.id }/update-wizard`
				: 'proposals/create';

			const result = await cfFetch( endpoint, {
				method: 'POST',
				body:   JSON.stringify( body ),
			} );

			onComplete( result.proposal );
		} catch ( e ) {
			setSubmitError( e.message || 'Something went wrong. Please try again.' );
			setSubmitting( false );
		}
	}

	// Step metadata
	const stepState = ( num ) => {
		if ( num < step ) return 'done';
		if ( num === step ) return 'active';
		return 'upcoming';
	};

	const STEP_CONTENT = {
		1: {
			heading: 'Choose a Template',
			sub: 'Pick a starting point — you can customise everything after.',
			content: <TemplateSelector selected={ templateId } onSelect={ setTemplateId } userPlan={ userPlan } />,
		},
		2: {
			heading: 'Client Details',
			sub: "Who is this proposal for? You can also create a client later.",
			content: <ClientDetailsForm values={ client } onChange={ handleClientChange } errors={ errors } />,
		},
		3: {
			heading: 'Proposal Settings',
			sub: 'Configure the title, currency, and payment options.',
			content: <ProposalSettings values={ settings } onChange={ handleSettingsChange } errors={ errors } />,
		},
		4: {
			heading: 'Pricing',
			sub: 'Add your services and fees. Everything is editable after creation.',
			content: (
				<PricingSetup
					items={ lineItems }
					currency={ settings.currency || 'GBP' }
					discountPct={ discountPct }
					vatPct={ vatPct }
					onUpdate={ handlePricingUpdate }
				/>
			),
		},
		5: {
			heading: 'Review & Create',
			sub: 'Check everything looks right before creating your proposal.',
			content: (
				<ReviewStep data={ { client, settings, lineItems, discountPct, vatPct, templateId, currency: settings.currency || 'GBP' } } />
			),
		},
	};

	const current = STEP_CONTENT[ step ];

	return (
		<div className="cf-wiz" style={ { animation: 'cf-fade-up .35s ease both' } }>
			{/* Header */ }
			<div className="cf-wiz-header">
				<div className="cf-wiz-title">{ isEdit ? 'Edit Proposal' : 'New Proposal' }</div>
				<button type="button" className="cf-wiz-cancel" onClick={ onCancel }>
					<svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
					</svg>
					Cancel
				</button>
			</div>

			{/* Step indicator */ }
			<div className="cf-wiz-steps">
				{ STEPS.map( ( s ) => (
					<div key={ s.num } className={ `cf-wiz-step ${ stepState( s.num ) }` }>
						<div className="cf-wiz-step-circle">
							{ stepState( s.num ) === 'done' ? (
								<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
									<polyline points="20 6 9 17 4 12"/>
								</svg>
							) : s.num }
						</div>
						<div className="cf-wiz-step-label">{ s.label }</div>
					</div>
				) ) }
			</div>

			{/* Body */ }
			<div className="cf-wiz-body">
				{ submitError && (
					<div className="cf-wiz-error">
						<svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
							<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
						</svg>
						{ submitError }
					</div>
				) }

				<div className={ `cf-wiz-content slide-${ direction }` } key={ step }>
					<div className="cf-wiz-step-heading">
						<h2>{ current.heading }</h2>
						<p>{ current.sub }</p>
					</div>
					{ current.content }
				</div>
			</div>

			{/* Footer */ }
			<div className="cf-wiz-footer">
				<div className="cf-wiz-footer-left">
					{ step > 1 && (
						<button type="button" className="cf-wiz-btn ghost" onClick={ goBack }>
							<svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
								<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
							</svg>
							Back
						</button>
					) }
				</div>
				<div className="cf-wiz-footer-right">
					<span style={ { fontSize: 12, color: 'var(--cf-slate-400)' } }>
						Step { step } of { STEPS.length }
					</span>
					{ step < 5 ? (
						<button type="button" className="cf-wiz-btn primary" onClick={ goNext }>
							Next
							<svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
								<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
							</svg>
						</button>
					) : (
						<button type="button" className="cf-wiz-btn success" onClick={ handleCreate } disabled={ submitting }>
							{ submitting ? (
								<>
									<svg className="cf-spin" viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
										<circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,.3)"/>
										<path d="M12 2a10 10 0 0110 10" stroke="white"/>
									</svg>
									{ isEdit ? 'Saving…' : 'Creating…' }
								</>
							) : (
								<>
									<svg viewBox="0 0 24 24" fill="none" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
										<polyline points="20 6 9 17 4 12"/>
									</svg>
									{ isEdit ? 'Save Changes' : 'Create Proposal' }
								</>
							) }
						</button>
					) }
				</div>
			</div>
		</div>
	);
}
