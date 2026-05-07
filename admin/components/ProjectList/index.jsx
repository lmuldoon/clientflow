/**
 * ProjectList
 *
 * Lists all projects with status filter tabs, milestone progress bars,
 * and quick-action buttons.
 *
 * Props: { onViewProject }
 */
import { useState, useEffect } from '@wordpress/element';
import { cfFetch } from '../ProjectsApp';

const STATUS_TABS = [
	{ id: '',          label: 'All'       },
	{ id: 'active',    label: 'Active'    },
	{ id: 'on-hold',   label: 'On Hold'   },
	{ id: 'completed', label: 'Completed' },
];

const STATUS_CONFIG = {
	'active':    { bg: 'var(--cf-indigo-bg)',  color: 'var(--cf-indigo)',   label: 'Active'    },
	'on-hold':   { bg: 'var(--cf-amber-bg)',   color: 'var(--cf-amber)',    label: 'On Hold'   },
	'completed': { bg: 'var(--cf-emerald-bg)', color: 'var(--cf-emerald)',  label: 'Completed' },
};

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

const CSS = `
.cf-pl-wrap { display: flex; flex-direction: column; }

.cf-pl-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 28px; gap: 16px;
}
.cf-pl-title {
  font-family: var(--cf-font);
  font-size: 28px; font-weight: 800; color: var(--cf-navy);
  letter-spacing: -.5px; margin: 0; line-height: 1;
}
.cf-pl-subtitle {
  font-size: 14px; color: var(--cf-slate-400); margin: 6px 0 0; line-height: 1.5;
}

.cf-pl-tabs {
  display: flex; gap: 2px; margin-bottom: 24px;
  border-bottom: 2px solid var(--cf-slate-100);
}
.cf-pl-tab {
  padding: 9px 18px; font-size: 13px; font-weight: 500;
  color: var(--cf-slate-500); border: none; background: none;
  cursor: pointer; border-bottom: 2px solid transparent;
  margin-bottom: -2px; transition: color .12s, border-color .12s;
}
.cf-pl-tab:hover { color: var(--cf-indigo); }
.cf-pl-tab.active { color: var(--cf-indigo); border-bottom-color: var(--cf-indigo); }

.cf-pl-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 16px;
}

.cf-pl-card {
  background: #fff;
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius);
  padding: 22px 24px 20px;
  box-shadow: var(--cf-shadow);
  display: flex; flex-direction: column; gap: 14px;
  transition: box-shadow .15s, transform .15s;
  cursor: pointer;
}
.cf-pl-card:hover {
  box-shadow: var(--cf-shadow-lg);
  transform: translateY(-1px);
}

.cf-pl-card-top {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
}
.cf-pl-card-title {
  font-family: var(--cf-font-display);
  font-size: 17px; color: var(--cf-navy); line-height: 1.3;
  flex: 1;
}
.cf-pl-badge {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 20px;
  font-size: 11px; font-weight: 600; letter-spacing: .4px;
  white-space: nowrap; flex-shrink: 0;
}

.cf-pl-card-meta {
  font-size: 13px; color: var(--cf-slate-500);
  display: flex; flex-direction: column; gap: 3px;
}
.cf-pl-card-meta span { display: flex; align-items: center; gap: 6px; }

.cf-pl-progress-wrap { display: flex; flex-direction: column; gap: 5px; }
.cf-pl-progress-label {
  font-size: 12px; color: var(--cf-slate-500);
  display: flex; justify-content: space-between;
}
.cf-pl-progress-bar {
  height: 6px; background: var(--cf-slate-100); border-radius: 99px; overflow: hidden;
}
.cf-pl-progress-fill {
  height: 100%; border-radius: 99px;
  background: var(--cf-emerald);
  transition: width .4s ease;
}
.cf-pl-progress-fill.complete { background: var(--cf-emerald); }

.cf-pl-card-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding-top: 10px; border-top: 1px solid var(--cf-slate-100);
}
.cf-pl-view-btn {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 13px; font-weight: 600; color: var(--cf-indigo);
  background: none; border: none; padding: 0; cursor: pointer;
  transition: gap .12s;
}
.cf-pl-view-btn:hover { gap: 8px; }
.cf-pl-date { font-size: 12px; color: var(--cf-slate-400); }

.cf-pl-empty {
  grid-column: 1/-1;
  background: #fff; border: 1.5px dashed var(--cf-slate-200);
  border-radius: var(--cf-radius); padding: 56px 32px;
  text-align: center;
}
.cf-pl-empty-icon { color: var(--cf-slate-300); margin: 0 auto 16px; display: block; }
.cf-pl-empty-title { font-family: var(--cf-font-display); font-size: 20px; color: var(--cf-navy); margin-bottom: 8px; }
.cf-pl-empty-sub { font-size: 14px; color: var(--cf-slate-500); max-width: 380px; margin: 0 auto; line-height: 1.6; }

.cf-pl-skeleton {
  background: linear-gradient(90deg, var(--cf-slate-100) 25%, var(--cf-slate-50) 50%, var(--cf-slate-100) 75%);
  background-size: 200% 100%;
  animation: cf-pl-shimmer 1.5s infinite;
  border-radius: 6px;
}
@keyframes cf-pl-shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

.cf-pl-upgrade-banner {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  background: var(--cf-amber-bg, #fffbeb);
  border: 1.5px solid rgba(245,158,11,.25);
  border-left: 4px solid var(--cf-amber, #f59e0b);
  border-radius: var(--cf-radius, 12px);
  padding: 14px 18px;
  margin-bottom: 24px;
}
.cf-pl-upgrade-banner__left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.cf-pl-upgrade-banner__icon {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(245,158,11,.15);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cf-pl-upgrade-banner__icon svg { stroke: var(--cf-amber, #f59e0b); }
.cf-pl-upgrade-banner__text { min-width: 0; }
.cf-pl-upgrade-banner__title { font-size: 13px; font-weight: 700; color: var(--cf-navy, #1a1a2e); }
.cf-pl-upgrade-banner__sub { font-size: 12px; color: var(--cf-slate-500, #64748b); margin-top: 2px; line-height: 1.45; }
.cf-pl-upgrade-banner__btn {
  display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
  padding: 7px 14px; border-radius: 7px;
  background: rgba(245,158,11,.12); border: 1.5px solid rgba(245,158,11,.4);
  font-size: 12px; font-weight: 600; color: #92400e;
  text-decoration: none; transition: background .15s, border-color .15s;
  cursor: pointer;
}
.cf-pl-upgrade-banner__btn svg {
fill: #92400e;
}
.cf-pl-upgrade-banner__btn:hover { background: rgba(245,158,11,.2); border-color: rgba(245,158,11,.6); color: #92400e; }
`;

function StatusBadge( { status } ) {
	const cfg = STATUS_CONFIG[ status ] || STATUS_CONFIG['active'];
	return (
		<span className="cf-pl-badge" style={ { background: cfg.bg, color: cfg.color } }>
			{ cfg.label }
		</span>
	);
}

function formatDate( d ) {
	if ( ! d ) return '—';
	try { return new Date( d ).toLocaleDateString( 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' } ); }
	catch { return d; }
}

function SkeletonCard() {
	return (
		<div className="cf-pl-card" style={ { cursor: 'default' } }>
			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' } }>
				<div className="cf-pl-skeleton" style={ { width: '55%', height: 18, borderRadius: 6 } } />
				<div className="cf-pl-skeleton" style={ { width: 68, height: 20, borderRadius: 20 } } />
			</div>
			<div className="cf-pl-skeleton" style={ { width: '40%', height: 13, borderRadius: 4 } } />
			<div>
				<div className="cf-pl-skeleton" style={ { width: '100%', height: 6, borderRadius: 99 } } />
			</div>
		</div>
	);
}

export default function ProjectList( { onViewProject } ) {
	injectStyles( 'cf-pl-styles', CSS );

	const isAgency    = window.cfData?.featureAccess?.use_projects === true;
	const currentPlan = window.cfData?.userPlan ?? 'free';
	const settingsUrl = ( window.cfData?.adminUrl || '/wp-admin/' ) + 'admin.php?page=clientflow-settings';

	const [ projects, setProjects ] = useState( [] );
	const [ loading, setLoading ]   = useState( true );
	const [ error, setError ]       = useState( null );
	const [ tab, setTab ]           = useState( '' );

	useEffect( () => {
		fetchProjects();
	}, [] );

	async function fetchProjects() {
		setLoading( true );
		setError( null );
		try {
			const data = await cfFetch( 'projects' );
			setProjects( data.projects || [] );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setLoading( false );
		}
	}

	const filtered = tab
		? projects.filter( p => p.status === tab )
		: projects;

	return (
		<div className="cf-pl-wrap">
			<div className="cf-pl-header">
				<div>
					<h1 className="cf-pl-title">Projects</h1>
					<p className="cf-pl-subtitle">
						{ loading ? '' : `${ projects.length } project${ projects.length !== 1 ? 's' : '' } total` }
					</p>
				</div>
			</div>

			{ ! isAgency && (
				<div className="cf-pl-upgrade-banner">
					<div className="cf-pl-upgrade-banner__left">
						<div className="cf-pl-upgrade-banner__icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
								<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
							</svg>
						</div>
						<div className="cf-pl-upgrade-banner__text">
							<div className="cf-pl-upgrade-banner__title">Projects — Read Only</div>
							<div className="cf-pl-upgrade-banner__sub">
								You're on the { currentPlan.charAt(0).toUpperCase() + currentPlan.slice(1) } plan. Your project data is preserved — upgrade to Agency to create and manage projects.
							</div>
						</div>
					</div>
					<a href={ settingsUrl } className="cf-pl-upgrade-banner__btn">
						Upgrade to Agency
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
							<path d="M5 12h14M12 5l7 7-7 7"/>
						</svg>
					</a>
				</div>
			) }

			{ error && (
				<div style={ {
					background: 'var(--cf-red-bg)', color: 'var(--cf-red)',
					borderRadius: 8, padding: '12px 16px', marginBottom: 24, fontSize: 14,
				} }>
					{ error }
				</div>
			) }

			<div className="cf-pl-tabs">
				{ STATUS_TABS.map( t => (
					<button
						key={ t.id }
						className={ `cf-pl-tab${ tab === t.id ? ' active' : '' }` }
						onClick={ () => setTab( t.id ) }
					>
						{ t.label }
					</button>
				) ) }
			</div>

			<div className="cf-pl-grid">
				{ loading ? (
					[ 1, 2, 3 ].map( i => <SkeletonCard key={ i } /> )
				) : filtered.length === 0 ? (
					<div className="cf-pl-empty">
						<svg className="cf-pl-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round">
							<rect x="2" y="7" width="20" height="14" rx="2"/>
							<path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/>
							<line x1="12" y1="12" x2="12" y2="16"/>
							<line x1="10" y1="14" x2="14" y2="14"/>
						</svg>
						<p className="cf-pl-empty-title">No projects yet</p>
						<p className="cf-pl-empty-sub">
							{ tab
								? `No ${ tab } projects found.`
								: <>Projects are created automatically when a client accepts a proposal. <a href="admin.php?page=clientflow-proposals" style={ { color: 'var(--cf-indigo)' } }>Send your first proposal</a> to get started.</> }
						</p>
					</div>
				) : (
					filtered.map( project => (
						<ProjectCard
							key={ project.id }
							project={ project }
							onClick={ () => onViewProject( project.id ) }
						/>
					) )
				) }
			</div>
		</div>
	);
}

function ProjectCard( { project, onClick } ) {
	const total     = project.milestone_total     || 0;
	const completed = project.milestone_completed || 0;
	const pct       = project.progress_pct        || 0;

	return (
		<div className="cf-pl-card" onClick={ onClick }>
			<div className="cf-pl-card-top">
				<span className="cf-pl-card-title">{ project.name }</span>
				<StatusBadge status={ project.status } />
			</div>

			<div className="cf-pl-card-meta">
				{ project.client_name && (
					<span>
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
							<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
							<circle cx="12" cy="7" r="4"/>
						</svg>
						{ project.client_name }
						{ project.client_company ? ` · ${ project.client_company }` : '' }
					</span>
				) }
				{ project.proposal_title && (
					<span>
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
							<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
							<polyline points="14 2 14 8 20 8"/>
						</svg>
						{ project.proposal_title }
					</span>
				) }
			</div>

			<div className="cf-pl-progress-wrap">
				<div className="cf-pl-progress-label">
					<span>Milestones</span>
					<span>{ completed } / { total }</span>
				</div>
				<div className="cf-pl-progress-bar">
					<div
						className={ `cf-pl-progress-fill${ pct === 100 ? ' complete' : '' }` }
						style={ { width: `${ pct }%` } }
					/>
				</div>
			</div>

			<div className="cf-pl-card-footer">
				<span className="cf-pl-date">Created { formatDate( project.created_at ) }</span>
				<button className="cf-pl-view-btn">
					View
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
						<polyline points="9 18 15 12 9 6"/>
					</svg>
				</button>
			</div>
		</div>
	);
}
