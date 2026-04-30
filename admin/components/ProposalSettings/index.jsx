/**
 * ProposalSettings
 *
 * Step 3 of the proposal wizard. Title, currency, expiry date,
 * and optional deposit configuration.
 *
 * Props:
 *   values   {object}  — { title, currency, expiry_date, deposit_pct, require_deposit }
 *   onChange {fn}      — onChange(field, value)
 *   errors   {object}
 */
import { useState } from '@wordpress/element';

const CURRENCIES = [
	{ value: 'GBP', label: '£ GBP — British Pound' },
	{ value: 'USD', label: '$ USD — US Dollar' },
	{ value: 'EUR', label: '€ EUR — Euro' },
	{ value: 'CAD', label: '$ CAD — Canadian Dollar' },
	{ value: 'AUD', label: '$ AUD — Australian Dollar' },
];

const CSS = `
.cf-ps-wrap { display: flex; flex-direction: column; gap: 20px; }

/* Label */
.cf-ps-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--cf-slate-500);
  margin-bottom: 6px;
  letter-spacing: .03em;
  text-transform: uppercase;
}
.cf-ps-req { color: var(--cf-indigo); margin-left: 2px; }

/* Input shared */
.cf-ps-input, .cf-ps-select {
  width: 100%;
  padding: 11px 14px;
  border: var(--cf-input-border);
  border-radius: var(--cf-radius-sm);
  font-size: 14px;
  font-family: var(--cf-font);
  color: var(--cf-slate-800);
  background: var(--cf-white);
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  -webkit-appearance: none;
  appearance: none;
}
.cf-ps-input::placeholder { color: var(--cf-slate-300); }
.cf-ps-input:focus, .cf-ps-select:focus {
  border-color: var(--cf-indigo);
  box-shadow: var(--cf-input-focus);
}
.cf-ps-input.cf-ps-lg { font-size: 16px; font-weight: 500; padding: 13px 16px; }
.cf-ps-input.cf-ps-error, .cf-ps-select.cf-ps-error {
  border-color: var(--cf-red);
  box-shadow: 0 0 0 3px rgba(239,68,68,.1);
}
.cf-ps-err { font-size: 12px; color: var(--cf-red); margin-top: 5px; font-weight: 500; }

/* Select wrapper */
.cf-ps-select-wrap {
  position: relative;
}
.cf-ps-select-wrap::after {
  content: '';
  position: absolute;
  right: 14px; top: 50%;
  transform: translateY(-50%);
  width: 0; height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 5px solid var(--cf-slate-400);
  pointer-events: none;
}

/* 2-col row */
.cf-ps-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 640px) { .cf-ps-row { grid-template-columns: 1fr; } }

/* Divider */
.cf-ps-divider {
  height: 1px;
  background: var(--cf-slate-100);
  margin: 4px 0;
}

/* Section header */
.cf-ps-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.cf-ps-section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--cf-slate-700);
}
.cf-ps-section-sub {
  font-size: 12px;
  color: var(--cf-slate-400);
  margin-top: 2px;
}

/* Toggle */
.cf-ps-toggle {
  position: relative;
  width: 42px; height: 24px;
  flex-shrink: 0;
  cursor: pointer;
}
.cf-ps-toggle input {
  opacity: 0; width: 0; height: 0; position: absolute;
}
.cf-ps-toggle-track {
  position: absolute; inset: 0;
  border-radius: 999px;
  background: var(--cf-slate-200);
  transition: background .2s;
}
.cf-ps-toggle input:checked + .cf-ps-toggle-track {
  background: var(--cf-indigo);
}
.cf-ps-toggle-thumb {
  position: absolute;
  top: 3px; left: 3px;
  width: 18px; height: 18px;
  border-radius: 50%;
  background: white;
  box-shadow: 0 1px 4px rgba(0,0,0,.2);
  transition: transform .2s cubic-bezier(.34,1.56,.64,1);
}
.cf-ps-toggle input:checked ~ .cf-ps-toggle-thumb {
  transform: translateX(18px);
}
.cf-ps-toggle:focus-within .cf-ps-toggle-track {
  box-shadow: 0 0 0 3px rgba(99,102,241,.2);
}

/* Deposit reveal */
.cf-ps-deposit-section {
  overflow: hidden;
  transition: max-height .3s ease, opacity .3s ease;
}
.cf-ps-deposit-section.hidden {
  max-height: 0;
  opacity: 0;
}
.cf-ps-deposit-section.visible {
  max-height: 120px;
  opacity: 1;
}

/* Slider */
.cf-ps-slider-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.cf-ps-slider {
  flex: 1;
  -webkit-appearance: none;
  appearance: none;
  height: 6px;
  border-radius: 999px;
  background: var(--cf-slate-200);
  outline: none;
  cursor: pointer;
}
.cf-ps-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 20px; height: 20px;
  border-radius: 50%;
  background: var(--cf-indigo);
  box-shadow: 0 0 0 3px rgba(99,102,241,.2);
  cursor: grab;
  transition: box-shadow .15s;
}
.cf-ps-slider::-webkit-slider-thumb:active { cursor: grabbing; }
.cf-ps-slider:focus::-webkit-slider-thumb { box-shadow: 0 0 0 5px rgba(99,102,241,.3); }
.cf-ps-slider-num {
  width: 70px;
  padding: 8px 12px;
  border: var(--cf-input-border);
  border-radius: var(--cf-radius-sm);
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  font-family: var(--cf-font);
  color: var(--cf-slate-800);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.cf-ps-slider-num:focus {
  border-color: var(--cf-indigo);
  box-shadow: var(--cf-input-focus);
}
.cf-ps-pct-label {
  font-size: 13px;
  color: var(--cf-slate-500);
  font-weight: 500;
}
`;

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

// Get today + 30 days as default expiry
function defaultExpiry() {
	const d = new Date();
	d.setDate( d.getDate() + 30 );
	return d.toISOString().split( 'T' )[ 0 ];
}

export default function ProposalSettings( { values = {}, onChange, errors = {} } ) {
	injectStyles( 'cf-ps-styles', CSS );

	const depositPct      = parseInt( values.deposit_pct ?? 25, 10 );
	const requireDeposit  = !! values.require_deposit;

	function handleDepositSlider( e ) {
		const v = parseInt( e.target.value, 10 );
		onChange( 'deposit_pct', v );
	}

	function handleDepositNum( e ) {
		const v = Math.max( 0, Math.min( 100, parseInt( e.target.value, 10 ) || 0 ) );
		onChange( 'deposit_pct', v );
	}

	return (
		<div className="cf-ps-wrap">
			{/* Title */ }
			<div>
				<label className="cf-ps-label" htmlFor="cf-ps-title">
					Proposal Title <span className="cf-ps-req">*</span>
				</label>
				<input
					id="cf-ps-title"
					type="text"
					className={ `cf-ps-input cf-ps-lg${ errors.title ? ' cf-ps-error' : '' }` }
					placeholder="e.g. Website Redesign for Acme Ltd"
					value={ values.title || '' }
					onChange={ ( e ) => onChange( 'title', e.target.value ) }
				/>
				{ errors.title && <div className="cf-ps-err">{ errors.title }</div> }
			</div>

			{/* Currency + Expiry row */ }
			<div className="cf-ps-row">
				<div>
					<label className="cf-ps-label" htmlFor="cf-ps-currency">Currency</label>
					<div className="cf-ps-select-wrap">
						<select
							id="cf-ps-currency"
							className="cf-ps-select"
							value={ values.currency || 'GBP' }
							onChange={ ( e ) => onChange( 'currency', e.target.value ) }
							style={ { paddingRight: 36 } }
						>
							{ CURRENCIES.map( c => (
								<option key={ c.value } value={ c.value }>{ c.label }</option>
							) ) }
						</select>
					</div>
				</div>
				<div>
					<label className="cf-ps-label" htmlFor="cf-ps-expiry">Expiry Date</label>
					<input
						id="cf-ps-expiry"
						type="date"
						className={ `cf-ps-input${ errors.expiry_date ? ' cf-ps-error' : '' }` }
						value={ values.expiry_date || defaultExpiry() }
						min={ new Date().toISOString().split( 'T' )[ 0 ] }
						onChange={ ( e ) => onChange( 'expiry_date', e.target.value ) }
					/>
					{ errors.expiry_date && <div className="cf-ps-err">{ errors.expiry_date }</div> }
				</div>
			</div>

			<div className="cf-ps-divider" />

			{/* Payment options */ }
			<div>
				<div className="cf-ps-section">
					<div>
						<div className="cf-ps-section-title">Require Deposit</div>
						<div className="cf-ps-section-sub">Client must pay a deposit before work begins</div>
					</div>
					<label className="cf-ps-toggle" aria-label="Require deposit">
						<input
							type="checkbox"
							checked={ requireDeposit }
							onChange={ ( e ) => onChange( 'require_deposit', e.target.checked ) }
						/>
						<div className="cf-ps-toggle-track" />
						<div className="cf-ps-toggle-thumb" />
					</label>
				</div>

				<div className={ `cf-ps-deposit-section ${ requireDeposit ? 'visible' : 'hidden' }` }
					style={ { marginTop: requireDeposit ? 16 : 0 } }>
					<label className="cf-ps-label">Deposit Percentage</label>
					<div className="cf-ps-slider-row">
						<input
							type="range"
							className="cf-ps-slider"
							min="5" max="100" step="5"
							value={ depositPct }
							onChange={ handleDepositSlider }
						/>
						<input
							type="number"
							className="cf-ps-slider-num"
							min="0" max="100"
							value={ depositPct }
							onChange={ handleDepositNum }
						/>
						<span className="cf-ps-pct-label">%</span>
					</div>
				</div>
			</div>
		</div>
	);
}
