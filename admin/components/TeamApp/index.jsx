/**
 * TeamApp
 *
 * Team seat management for ClientFlow Agency accounts.
 * Displays seat usage, member list, and an invite form.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';

// ── Styles ─────────────────────────────────────────────────────────────────────

const TEAM_CSS = `
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
  --cf-slate-300:  #CBD5E1;
  --cf-slate-400:  #94A3B8;
  --cf-slate-500:  #64748B;
  --cf-slate-600:  #475569;
  --cf-white:      #FFFFFF;
  --cf-radius:     12px;
  --cf-radius-sm:  8px;
  --cf-shadow:     0 1px 3px rgba(15,23,42,.06), 0 4px 16px rgba(15,23,42,.08);
  --cf-shadow-lg:  0 4px 6px rgba(15,23,42,.05), 0 10px 40px rgba(15,23,42,.12);
  --cf-font:       'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
}

.cf-tm * { box-sizing: border-box; }

.cf-tm {
  font-family: var(--cf-font);
  min-height: 100vh;
  padding: 32px 28px 64px;
  color: var(--cf-navy);
  -webkit-font-smoothing: antialiased;
}

/* Header */
.cf-tm-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 28px;
}
.cf-tm-title {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.5px;
  color: var(--cf-navy);
  margin: 0;
  line-height: 1;
}
.cf-tm-subtitle {
  font-size: 14px;
  color: var(--cf-slate-400);
  margin:6px 0 0;
}

/* Cards */
.cf-tm-card {
  background: var(--cf-white);
  border-radius: var(--cf-radius);
  box-shadow: var(--cf-shadow);
  border: 1px solid var(--cf-slate-200);
  padding: 24px;
  margin-bottom: 20px;
}
.cf-tm-card-title {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--cf-slate-400);
  margin: 0 0 16px;
}

/* Seat usage bar */
.cf-tm-usage {
  display: flex;
  align-items: center;
  gap: 16px;
}
.cf-tm-usage-label {
  font-size: 15px;
  font-weight: 600;
  color: var(--cf-navy);
  white-space: nowrap;
}
.cf-tm-bar-wrap {
  flex: 1;
  background: var(--cf-slate-100);
  border-radius: 99px;
  height: 8px;
  overflow: hidden;
}
.cf-tm-bar-fill {
  height: 100%;
  border-radius: 99px;
  background: var(--cf-indigo);
  transition: width .4s ease;
}
.cf-tm-bar-fill.full { background: var(--cf-red); }
.cf-tm-usage-count {
  font-size: 13px;
  color: var(--cf-slate-500);
  white-space: nowrap;
}

/* Member table */
.cf-tm-table {
  width: 100%;
  border-collapse: collapse;
}
.cf-tm-th {
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--cf-slate-400);
  padding: 0 12px 12px;
  border-bottom: 1px solid var(--cf-slate-200);
}
.cf-tm-th:first-child { padding-left: 0; }
.cf-tm-td {
  padding: 14px 12px;
  font-size: 14px;
  color: var(--cf-navy);
  border-bottom: 1px solid var(--cf-slate-100);
  vertical-align: middle;
}
.cf-tm-td:first-child { padding-left: 0; }
.cf-tm-tr:last-child .cf-tm-td { border-bottom: none; }

/* Avatar */
.cf-tm-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--cf-indigo-bg);
  color: var(--cf-indigo);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}
.cf-tm-member-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.cf-tm-member-name {
  font-weight: 600;
  font-size: 14px;
  color: var(--cf-navy);
}
.cf-tm-member-email {
  font-size: 12px;
  color: var(--cf-slate-500);
}

/* Role badge */
.cf-tm-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}
.cf-tm-badge-admin   { background: var(--cf-indigo-bg); color: var(--cf-indigo); }
.cf-tm-badge-editor  { background: var(--cf-emerald-bg); color: var(--cf-emerald); }
.cf-tm-badge-viewer  { background: var(--cf-slate-100); color: var(--cf-slate-600); }

/* Pending badge */
.cf-tm-pending {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--cf-amber);
  font-weight: 500;
}

/* Remove button */
.cf-tm-remove-btn {
  background: none;
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm);
  color: var(--cf-slate-500);
  font-size: 13px;
  font-weight: 500;
  padding: 6px 12px;
  cursor: pointer;
  transition: all .15s;
  font-family: var(--cf-font);
}
.cf-tm-remove-btn:hover {
  border-color: var(--cf-red);
  color: var(--cf-red);
  background: var(--cf-red-bg);
}
.cf-tm-remove-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Empty state */
.cf-tm-empty {
  text-align: center;
  padding: 48px 24px;
  color: var(--cf-slate-400);
}
.cf-tm-empty-icon {
  font-size: 40px;
  margin-bottom: 12px;
}
.cf-tm-empty p {
  margin: 0;
  font-size: 14px;
}

/* Invite form */
.cf-tm-invite-form {
  display: grid;
  grid-template-columns: 1fr 1fr auto auto;
  gap: 12px;
  align-items: end;
}
@media (max-width: 768px) {
  .cf-tm-invite-form { grid-template-columns: 1fr; }
}
.cf-tm-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.cf-tm-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--cf-slate-600);
}
.cf-tm-input, .cf-tm-select {
  height: 40px;
  border: 1.5px solid var(--cf-slate-200);
  border-radius: var(--cf-radius-sm);
  padding: 0 12px;
  font-size: 14px;
  font-family: var(--cf-font);
  color: var(--cf-navy);
  background: var(--cf-white);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.cf-tm-input:focus, .cf-tm-select:focus {
  border-color: var(--cf-indigo);
  box-shadow: 0 0 0 3px rgba(99,102,241,.12);
}

/* Invite button */
.cf-tm-invite-btn {
  height: 40px;
  padding: 0 20px;
  background: var(--cf-indigo);
  color: var(--cf-white);
  border: none;
  border-radius: var(--cf-radius-sm);
  font-size: 14px;
  font-weight: 600;
  font-family: var(--cf-font);
  cursor: pointer;
  white-space: nowrap;
  transition: background .15s, opacity .15s;
}
.cf-tm-invite-btn:hover { background: var(--cf-indigo-lt); }
.cf-tm-invite-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Upgrade lock */
.cf-tm-upgrade {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--cf-indigo-bg);
  border: 1px solid rgba(99,102,241,.2);
  border-radius: var(--cf-radius);
  padding: 20px 24px;
}
.cf-tm-upgrade-text {
  flex: 1;
}
.cf-tm-upgrade-text strong {
  display: block;
  font-size: 15px;
  font-weight: 700;
  color: var(--cf-indigo);
  margin-bottom: 4px;
}
.cf-tm-upgrade-text span {
  font-size: 13px;
  color: var(--cf-slate-600);
}
.cf-tm-upgrade-btn {
  flex-shrink: 0;
  padding: 10px 20px;
  background: var(--cf-indigo);
  color: var(--cf-white);
  border: none;
  border-radius: var(--cf-radius-sm);
  font-size: 14px;
  font-weight: 600;
  font-family: var(--cf-font);
  cursor: pointer;
  transition: background .15s;
}
.cf-tm-upgrade-btn:hover { background: var(--cf-indigo-lt); }

/* Notice */
.cf-tm-notice {
  padding: 12px 16px;
  border-radius: var(--cf-radius-sm);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 16px;
}
.cf-tm-notice-error   { background: var(--cf-red-bg); color: var(--cf-red); }
.cf-tm-notice-success { background: var(--cf-emerald-bg); color: var(--cf-emerald); }

/* Spinner */
@keyframes cf-spin { to { transform: rotate(360deg); } }
.cf-tm-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: cf-spin .7s linear infinite;
  display: inline-block;
  vertical-align: middle;
}
`;

function injectStyles() {
	if ( document.getElementById( 'cf-team-css' ) ) return;
	const el = document.createElement( 'style' );
	el.id = 'cf-team-css';
	el.textContent = TEAM_CSS;
	document.head.appendChild( el );
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function cfFetch( endpoint, options = {} ) {
	const { apiUrl, nonce } = window.cfData || {};
	return fetch( apiUrl + endpoint, {
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
			...( options.headers || {} ),
		},
		...options,
	} ).then( r => r.json() );
}

function avatarInitial( name = '' ) {
	const parts = name.trim().split( ' ' );
	return parts.length > 1
		? ( parts[ 0 ][ 0 ] + parts[ parts.length - 1 ][ 0 ] ).toUpperCase()
		: ( name[ 0 ] || '?' ).toUpperCase();
}

const ERROR_MESSAGES = {
	seat_limit_reached: 'You have used all available seats. Remove a member or upgrade to add more.',
	invalid_email:      'Please enter a valid email address.',
	cannot_invite_self: 'You cannot invite yourself as a team member.',
	already_a_member:   'This person is already a member of your team.',
};

// ── Component ──────────────────────────────────────────────────────────────────

export default function TeamApp() {
	const { userPlan, teamSeats: initialSeats, teamLimit: initialLimit } = window.cfData || {};

	const [ members,    setMembers    ] = useState( [] );
	const [ seatsUsed,  setSeatsUsed  ] = useState( initialSeats || 1 );
	const [ seatsLimit, setSeatsLimit ] = useState( initialLimit || 1 );
	const [ loading,    setLoading    ] = useState( true );
	const [ removing,   setRemoving   ] = useState( null );
	const [ notice,     setNotice     ] = useState( null );

	const [ form, setForm ] = useState( { name: '', email: '', role: 'editor' } );
	const [ inviting, setInviting ] = useState( false );

	useEffect( () => { injectStyles(); }, [] );

	const loadMembers = useCallback( async () => {
		setLoading( true );
		try {
			const data = await cfFetch( 'team/members' );
			if ( data.members ) {
				setMembers( data.members );
				setSeatsUsed( data.seats_used );
				setSeatsLimit( data.seats_limit );
			}
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => { loadMembers(); }, [ loadMembers ] );

	const showNotice = ( type, message ) => {
		setNotice( { type, message } );
		setTimeout( () => setNotice( null ), 5000 );
	};

	const handleInvite = async ( e ) => {
		e.preventDefault();
		if ( ! form.email || inviting ) return;

		setInviting( true );
		setNotice( null );
		try {
			const data = await cfFetch( 'team/invite', {
				method: 'POST',
				body: JSON.stringify( form ),
			} );
			if ( data.success ) {
				setForm( { name: '', email: '', role: 'editor' } );
				showNotice( 'success', 'Invite sent! The team member will receive an email shortly.' );
				await loadMembers();
			} else {
				const msg = ERROR_MESSAGES[ data.error ] || data.error || 'Something went wrong.';
				showNotice( 'error', msg );
			}
		} catch {
			showNotice( 'error', 'Network error. Please try again.' );
		} finally {
			setInviting( false );
		}
	};

	const handleRemove = async ( memberId ) => {
		if ( removing ) return;
		setRemoving( memberId );
		try {
			const data = await cfFetch( `team/members/${ memberId }`, { method: 'DELETE' } );
			if ( data.success ) {
				showNotice( 'success', 'Team member removed.' );
				await loadMembers();
			} else {
				showNotice( 'error', 'Could not remove member. Please try again.' );
			}
		} catch {
			showNotice( 'error', 'Network error. Please try again.' );
		} finally {
			setRemoving( null );
		}
	};

	const isAgency    = userPlan === 'agency';
	const pct         = seatsLimit > 0 ? Math.min( ( seatsUsed / seatsLimit ) * 100, 100 ) : 100;
	const atLimit     = seatsUsed >= seatsLimit;
	const canInvite   = isAgency && ! atLimit && ! inviting;

	return (
		<div className="cf-tm">
			{ /* Header */ }
			<div className="cf-tm-header">
				<div>
					<h1 className="cf-tm-title">Team</h1>
					<p className="cf-tm-subtitle">Manage who has access to your ClientFlow account.</p>
				</div>
			</div>

			{ /* Seat usage */ }
			<div className="cf-tm-card">
				<p className="cf-tm-card-title">Seat Usage</p>
				<div className="cf-tm-usage">
					<span className="cf-tm-usage-label">
						{ isAgency ? 'Agency Plan' : userPlan === 'pro' ? 'Pro Plan' : 'Free Plan' }
					</span>
					<div className="cf-tm-bar-wrap">
						<div
							className={ `cf-tm-bar-fill${ atLimit ? ' full' : '' }` }
							style={ { width: `${ pct }%` } }
						/>
					</div>
					<span className="cf-tm-usage-count">{ seatsUsed } / { seatsLimit } seat{ seatsLimit !== 1 ? 's' : '' }</span>
				</div>
			</div>

			{ /* Members list */ }
			<div className="cf-tm-card">
				<p className="cf-tm-card-title">Members</p>

				{ notice && (
					<div className={ `cf-tm-notice cf-tm-notice-${ notice.type }` }>
						{ notice.message }
					</div>
				) }

				{ loading ? (
					<div className="cf-tm-empty">
						<div style={ { margin: '0 auto 12px', width: 24, height: 24, border: '2px solid #E2E8F0', borderTopColor: '#6366F1', borderRadius: '50%', animation: 'cf-spin .7s linear infinite' } } />
						<p>Loading team…</p>
					</div>
				) : members.length === 0 ? (
					<div className="cf-tm-empty">
						<div className="cf-tm-empty-icon">👥</div>
						<p>No team members yet.</p>
						{ isAgency && <p style={ { marginTop: 4 } }>Invite someone below to get started.</p> }
					</div>
				) : (
					<table className="cf-tm-table">
						<thead>
							<tr>
								<th className="cf-tm-th">Member</th>
								<th className="cf-tm-th">Role</th>
								<th className="cf-tm-th">Status</th>
								<th className="cf-tm-th" style={ { textAlign: 'right' } }></th>
							</tr>
						</thead>
						<tbody>
							{ members.map( m => (
								<tr key={ m.id } className="cf-tm-tr">
									<td className="cf-tm-td">
										<div className="cf-tm-member-info">
											<div className="cf-tm-avatar">{ avatarInitial( m.display_name ) }</div>
											<div>
												<div className="cf-tm-member-name">{ m.display_name }</div>
												<div className="cf-tm-member-email">{ m.email }</div>
											</div>
										</div>
									</td>
									<td className="cf-tm-td">
										<span className={ `cf-tm-badge cf-tm-badge-${ m.role }` }>{ m.role }</span>
									</td>
									<td className="cf-tm-td">
										{ m.accepted_at ? (
											<span style={ { color: '#10B981', fontSize: 13, fontWeight: 500 } }>Active</span>
										) : (
											<span className="cf-tm-pending">
												<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
												Invite pending
											</span>
										) }
									</td>
									<td className="cf-tm-td" style={ { textAlign: 'right' } }>
										{ isAgency && (
											<button
												className="cf-tm-remove-btn"
												onClick={ () => handleRemove( m.id ) }
												disabled={ removing === m.id }
											>
												{ removing === m.id ? 'Removing…' : 'Remove' }
											</button>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>

			{ /* Invite form / upgrade prompt */ }
			{ isAgency ? (
				<div className="cf-tm-card">
					<p className="cf-tm-card-title">Invite a Team Member</p>
					{ atLimit && (
						<div className="cf-tm-notice cf-tm-notice-error" style={ { marginBottom: 16 } }>
							You've reached your seat limit ({ seatsLimit } / { seatsLimit }). Remove a member to invite someone new.
						</div>
					) }
					<form className="cf-tm-invite-form" onSubmit={ handleInvite }>
						<div className="cf-tm-field">
							<label className="cf-tm-label">Name</label>
							<input
								className="cf-tm-input"
								type="text"
								placeholder="Jane Smith"
								value={ form.name }
								onChange={ e => setForm( f => ( { ...f, name: e.target.value } ) ) }
								disabled={ ! canInvite }
							/>
						</div>
						<div className="cf-tm-field">
							<label className="cf-tm-label">Email</label>
							<input
								className="cf-tm-input"
								type="email"
								placeholder="jane@agency.com"
								value={ form.email }
								onChange={ e => setForm( f => ( { ...f, email: e.target.value } ) ) }
								disabled={ ! canInvite }
								required
							/>
						</div>
						<div className="cf-tm-field">
							<label className="cf-tm-label">Role</label>
							<select
								className="cf-tm-select"
								value={ form.role }
								onChange={ e => setForm( f => ( { ...f, role: e.target.value } ) ) }
								disabled={ ! canInvite }
							>
								<option value="admin">Admin</option>
								<option value="editor">Editor</option>
								<option value="viewer">Viewer</option>
							</select>
						</div>
						<button
							className="cf-tm-invite-btn"
							type="submit"
							disabled={ ! canInvite || ! form.email }
						>
							{ inviting ? <span className="cf-tm-spinner" /> : 'Send Invite' }
						</button>
					</form>
					<p style={ { marginTop: 12, marginBottom: 0, fontSize: 12, color: '#94A3B8' } }>
						<strong>Admin</strong> — full access &nbsp;·&nbsp; <strong>Editor</strong> — create &amp; edit proposals and projects &nbsp;·&nbsp; <strong>Viewer</strong> — read-only
					</p>
				</div>
			) : (
				<div className="cf-tm-upgrade">
					<div className="cf-tm-upgrade-text">
						<strong>Team seats are an Agency feature</strong>
						<span>Upgrade to Agency to invite up to 5 team members with role-based access.</span>
					</div>
					<button
						className="cf-tm-upgrade-btn"
						onClick={ () => window.location.href = window.cfData?.adminUrl + 'admin.php?page=clientflow' }
					>
						Upgrade to Agency
					</button>
				</div>
			) }
		</div>
	);
}
