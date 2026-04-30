/**
 * ClientDetailsForm
 *
 * Step 2 of the proposal wizard. Collects client name, email,
 * company, and phone with floating labels and inline validation.
 *
 * Props:
 *   values   {object}  — { name, email, company, phone }
 *   onChange {fn}      — onChange(field, value)
 *   errors   {object}  — { name?: string, email?: string, ... }
 */
import { useState } from '@wordpress/element';

const CSS = `
.cf-cdf-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 640px) { .cf-cdf-grid { grid-template-columns: 1fr; } }

.cf-cdf-field {
  position: relative;
}
.cf-cdf-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--cf-slate-500);
  margin-bottom: 6px;
  letter-spacing: .03em;
  text-transform: uppercase;
}
.cf-cdf-req {
  color: var(--cf-indigo);
  margin-left: 2px;
}
.cf-cdf-input {
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
}
.cf-cdf-input::placeholder { color: var(--cf-slate-300); }
.cf-cdf-input:focus {
  border-color: var(--cf-indigo);
  box-shadow: var(--cf-input-focus);
}
.cf-cdf-input.cf-cdf-error {
  border-color: var(--cf-red);
  box-shadow: 0 0 0 3px rgba(239,68,68,.1);
}
.cf-cdf-err-msg {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
  font-size: 12px;
  color: var(--cf-red);
  font-weight: 500;
}
.cf-cdf-err-msg svg {
  width: 13px; height: 13px;
  stroke: currentColor;
  flex-shrink: 0;
}
.cf-cdf-hint {
  margin-top: 5px;
  font-size: 12px;
  color: var(--cf-slate-400);
}
`;

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

function Field( { label, name, type = 'text', required, placeholder, value, onChange, error, hint } ) {
	const [ focused, setFocused ] = useState( false );
	const hasError = !! error;

	return (
		<div className="cf-cdf-field">
			<label className="cf-cdf-label" htmlFor={ `cf-field-${ name }` }>
				{ label }
				{ required && <span className="cf-cdf-req" aria-hidden="true"> *</span> }
			</label>
			<input
				id={ `cf-field-${ name }` }
				type={ type }
				className={ [ 'cf-cdf-input', hasError ? 'cf-cdf-error' : '' ].join( ' ' ) }
				placeholder={ focused ? placeholder || '' : placeholder || `Enter ${ label.toLowerCase() }` }
				value={ value || '' }
				onChange={ ( e ) => onChange( name, e.target.value ) }
				onFocus={ () => setFocused( true ) }
				onBlur={ () => setFocused( false ) }
				aria-required={ required }
				aria-describedby={ hasError ? `cf-err-${ name }` : undefined }
				aria-invalid={ hasError }
			/>
			{ hasError && (
				<div className="cf-cdf-err-msg" id={ `cf-err-${ name }` } role="alert">
					<svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
						<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
					</svg>
					{ error }
				</div>
			) }
			{ hint && ! hasError && <div className="cf-cdf-hint">{ hint }</div> }
		</div>
	);
}

export default function ClientDetailsForm( { values = {}, onChange, errors = {} } ) {
	injectStyles( 'cf-cdf-styles', CSS );

	return (
		<div className="cf-cdf-grid">
			<Field
				label="Client Name"
				name="name"
				required
				value={ values.name }
				onChange={ onChange }
				error={ errors.name }
				placeholder="Jane Smith"
			/>
			<Field
				label="Email Address"
				name="email"
				type="email"
				required
				value={ values.email }
				onChange={ onChange }
				error={ errors.email }
				placeholder="jane@company.com"
			/>
			<Field
				label="Company"
				name="company"
				value={ values.company }
				onChange={ onChange }
				error={ errors.company }
				placeholder="Acme Ltd"
			/>
			<Field
				label="Phone"
				name="phone"
				type="tel"
				value={ values.phone }
				onChange={ onChange }
				error={ errors.phone }
				placeholder="+44 7700 900000"
				hint="Optional — used for proposal cover"
			/>
		</div>
	);
}
