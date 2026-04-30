/**
 * AnalyticsApp
 *
 * Analytics dashboard for ClientFlow admins.
 * KPI cards, revenue chart, proposal performance, activity feed.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// ── Shared CSS ─────────────────────────────────────────────────────────────────

const GLOBAL_CSS = `
@import url('https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap');

:root {
  --cf-bg:       #F0F2F7;
  --cf-surface:  #FFFFFF;
  --cf-border:   #E4E8F0;
  --cf-text:     #111827;
  --cf-muted:    #6B7280;
  --cf-accent:   #4F46E5;
  --cf-accent2:  #7C3AED;
  --cf-green:    #059669;
  --cf-green-bg: #ECFDF5;
  --cf-amber:    #D97706;
  --cf-amber-bg: #FFFBEB;
  --cf-red:      #DC2626;
  --cf-red-bg:   #FEF2F2;
  --cf-radius:   14px;
  --cf-shadow:   0 1px 4px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
}

.cf-an * { box-sizing: border-box; }

.cf-an {
  font-family: 'Archivo', -apple-system, sans-serif;
  background: var(--cf-bg);
  min-height: 100vh;
  padding: 32px 28px 64px;
  color: var(--cf-text);
}

/* ── Header ── */
.cf-an-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 28px;
}
.cf-an-header h1 {
  font-family: 'Archivo', sans-serif;
  font-size: 26px;
  font-weight: 800;
  color: var(--cf-text);
  margin: 0 0 4px;
  letter-spacing: -0.5px;
}
.cf-an-header p {
  margin: 0;
  font-size: 13.5px;
  color: var(--cf-muted);
}
.cf-an-header-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

/* ── Range selector ── */
.cf-an-range {
  display: flex;
  background: var(--cf-surface);
  border: 1px solid var(--cf-border);
  border-radius: 10px;
  overflow: hidden;
}
.cf-an-range button {
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--cf-muted);
  transition: background .15s, color .15s;
}
.cf-an-range button:hover { background: var(--cf-bg); }
.cf-an-range button.active {
  background: var(--cf-accent);
  color: #fff;
}

/* ── Export button ── */
.cf-an-export {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  background: var(--cf-surface);
  border: 1px solid var(--cf-border);
  border-radius: 10px;
  cursor: pointer;
  color: var(--cf-text);
  text-decoration: none;
  transition: border-color .15s, box-shadow .15s;
}
.cf-an-export:hover { border-color: var(--cf-accent); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

/* ── KPI grid ── */
.cf-an-kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.cf-kpi {
  background: var(--cf-surface);
  border: 1px solid var(--cf-border);
  border-radius: var(--cf-radius);
  padding: 24px;
  box-shadow: var(--cf-shadow);
}
.cf-kpi-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--cf-muted);
  margin-bottom: 10px;
}
.cf-kpi-value {
  font-family: 'Archivo', sans-serif;
  font-size: 32px;
  font-weight: 800;
  letter-spacing: -1px;
  color: var(--cf-text);
  line-height: 1;
  margin-bottom: 10px;
}
.cf-kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
}
.cf-kpi-trend.up   { background: var(--cf-green-bg); color: var(--cf-green); }
.cf-kpi-trend.down { background: var(--cf-red-bg);   color: var(--cf-red); }
.cf-kpi-trend.flat { background: var(--cf-bg);        color: var(--cf-muted); }

/* ── Chart card ── */
.cf-an-card {
  background: var(--cf-surface);
  border: 1px solid var(--cf-border);
  border-radius: var(--cf-radius);
  padding: 28px;
  box-shadow: var(--cf-shadow);
  margin-bottom: 20px;
}
.cf-an-card h2 {
  font-family: 'Archivo', sans-serif;
  font-size: 16px;
  font-weight: 700;
  margin: 0 0 20px;
  color: var(--cf-text);
}

/* ── SVG chart ── */
.cf-chart-wrap { width: 100%; overflow: hidden; }
.cf-chart-wrap svg { width: 100%; display: block; }

/* ── Bottom grid ── */
.cf-an-bottom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
@media (max-width: 900px) { .cf-an-bottom { grid-template-columns: 1fr; } }

/* ── Performance table ── */
.cf-perf-table { width: 100%; border-collapse: collapse; }
.cf-perf-table th {
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--cf-muted);
  padding: 0 0 12px;
  border-bottom: 1px solid var(--cf-border);
}
.cf-perf-table td {
  padding: 12px 0;
  font-size: 14px;
  border-bottom: 1px solid var(--cf-border);
  color: var(--cf-text);
}
.cf-perf-table tr:last-child td { border-bottom: none; }
.cf-win-bar-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.cf-win-bar {
  height: 6px;
  border-radius: 3px;
  background: linear-gradient(90deg, var(--cf-accent), var(--cf-accent2));
  flex-shrink: 0;
}
.cf-win-bar-bg {
  flex: 1;
  height: 6px;
  border-radius: 3px;
  background: var(--cf-bg);
  position: relative;
  overflow: hidden;
}

/* ── Activity feed ── */
.cf-feed { list-style: none; margin: 0; padding: 0; }
.cf-feed-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--cf-border);
}
.cf-feed-item:last-child { border-bottom: none; }
.cf-feed-dot {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}
.cf-feed-label {
  font-size: 13.5px;
  font-weight: 500;
  color: var(--cf-text);
  line-height: 1.4;
}
.cf-feed-time {
  font-size: 11.5px;
  color: var(--cf-muted);
  margin-top: 2px;
}

/* ── Upgrade prompt ── */
.cf-an-upgrade {
  text-align: center;
  padding: 64px 32px;
  background: var(--cf-surface);
  border-radius: var(--cf-radius);
  border: 1px solid var(--cf-border);
}
.cf-an-upgrade h2 {
  font-family: 'Archivo', sans-serif;
  font-size: 22px;
  font-weight: 800;
  margin: 16px 0 8px;
}
.cf-an-upgrade p { color: var(--cf-muted); margin: 0; font-size: 14px; }

/* ── Empty / error states ── */
.cf-an-empty { text-align: center; padding: 40px 0; color: var(--cf-muted); font-size: 14px; }

/* ── Loading skeleton ── */
.cf-skel {
  background: linear-gradient(90deg, var(--cf-bg) 25%, #e8eaf0 50%, var(--cf-bg) 75%);
  background-size: 200% 100%;
  animation: cf-skel-shine 1.4s infinite;
  border-radius: 8px;
}
@keyframes cf-skel-shine {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
`;

// ── Utilities ──────────────────────────────────────────────────────────────────

const fmt = {
	currency: ( v ) => new Intl.NumberFormat( 'en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 } ).format( v ?? 0 ),
	pct:      ( v ) => `${ ( v ?? 0 ).toFixed( 1 ) }%`,
	days:     ( v ) => `${ ( v ?? 0 ).toFixed( 1 ) }d`,
};

function trend( current, prev ) {
	if ( ! prev ) return { dir: 'flat', delta: 0 };
	const delta = current - prev;
	return {
		dir:   delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat',
		delta: Math.abs( delta ),
		pct:   prev !== 0 ? Math.abs( ( delta / prev ) * 100 ) : 0,
	};
}

function timeAgo( ts ) {
	if ( ! ts ) return '';
	const secs = Math.floor( ( Date.now() - new Date( ts ).getTime() ) / 1000 );
	if ( secs < 60 )   return 'just now';
	if ( secs < 3600 ) return `${ Math.floor( secs / 60 ) }m ago`;
	if ( secs < 86400 ) return `${ Math.floor( secs / 3600 ) }h ago`;
	return `${ Math.floor( secs / 86400 ) }d ago`;
}

function feedDot( type ) {
	const map = {
		payment:  { bg: '#ECFDF5', icon: '£' },
		accepted: { bg: '#EEF2FF', icon: '✓' },
		sent:     { bg: '#F0F9FF', icon: '→' },
		message:  { bg: '#FFF7ED', icon: '💬' },
		project:  { bg: '#F5F3FF', icon: '📁' },
	};
	return map[ type ] ?? { bg: '#F3F4F6', icon: '•' };
}

// ── SVG Line Chart ─────────────────────────────────────────────────────────────

function LineChart( { data } ) {
	if ( ! data || data.length === 0 ) {
		return <div className="cf-an-empty">No revenue data for this period.</div>;
	}

	const W = 800, H = 200, PAD = { top: 16, right: 16, bottom: 36, left: 64 };
	const innerW = W - PAD.left - PAD.right;
	const innerH = H - PAD.top - PAD.bottom;

	const maxAmt = Math.max( ...data.map( d => d.amount ), 1 );
	const step   = innerW / Math.max( data.length - 1, 1 );

	const pts = data.map( ( d, i ) => ( {
		x: PAD.left + i * step,
		y: PAD.top  + innerH - ( d.amount / maxAmt ) * innerH,
		d,
	} ) );

	const polyline = pts.map( p => `${ p.x },${ p.y }` ).join( ' ' );
	const area     = `M ${ pts[ 0 ].x } ${ PAD.top + innerH } `
		+ pts.map( p => `L ${ p.x } ${ p.y }` ).join( ' ' )
		+ ` L ${ pts[ pts.length - 1 ].x } ${ PAD.top + innerH } Z`;

	const yTicks = 4;
	const xLabelEvery = Math.ceil( data.length / 6 );

	return (
		<div className="cf-chart-wrap">
			<svg viewBox={ `0 0 ${ W } ${ H }` } preserveAspectRatio="xMidYMid meet">
				<defs>
					<linearGradient id="cf-area-grad" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0%"   stopColor="#4F46E5" stopOpacity="0.18" />
						<stop offset="100%" stopColor="#4F46E5" stopOpacity="0" />
					</linearGradient>
				</defs>

				{ Array.from( { length: yTicks }, ( _, i ) => {
					const v = ( maxAmt / yTicks ) * ( yTicks - i );
					const y = PAD.top + ( i / yTicks ) * innerH;
					return (
						<g key={ i }>
							<line x1={ PAD.left } y1={ y } x2={ W - PAD.right } y2={ y }
								stroke="#E4E8F0" strokeWidth="1" strokeDasharray="4 3" />
							<text x={ PAD.left - 8 } y={ y + 4 } textAnchor="end"
								fill="#9CA3AF" fontSize="11" fontFamily="Archivo, sans-serif">
								{ v >= 1000 ? `£${ ( v / 1000 ).toFixed( 0 ) }k` : `£${ v.toFixed( 0 ) }` }
							</text>
						</g>
					);
				} ) }

				<path d={ area } fill="url(#cf-area-grad)" />

				<polyline points={ polyline } fill="none" stroke="#4F46E5"
					strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />

				{ pts.map( ( p, i ) => (
					<circle key={ i } cx={ p.x } cy={ p.y } r="3.5"
						fill="#4F46E5" stroke="#fff" strokeWidth="2" />
				) ) }

				{ pts.filter( ( _, i ) => i % xLabelEvery === 0 || i === pts.length - 1 ).map( ( p, i ) => (
					<text key={ i } x={ p.x } y={ PAD.top + innerH + 20 } textAnchor="middle"
						fill="#9CA3AF" fontSize="11" fontFamily="Archivo, sans-serif">
						{ p.d.date }
					</text>
				) ) }
			</svg>
		</div>
	);
}

// ── KPI Card ──────────────────────────────────────────────────────────────────

function KpiCard( { label, value, prevValue, format } ) {
	const t = trend( value, prevValue );
	const arrow = t.dir === 'up' ? '↑' : t.dir === 'down' ? '↓' : '—';
	const tLabel = t.dir !== 'flat'
		? `${ arrow } ${ t.pct.toFixed( 0 ) }% vs prev`
		: '— no change';

	return (
		<div className="cf-kpi">
			<div className="cf-kpi-label">{ label }</div>
			<div className="cf-kpi-value">{ format( value ) }</div>
			<span className={ `cf-kpi-trend ${ t.dir }` }>{ tLabel }</span>
		</div>
	);
}

function KpiSkeleton() {
	return (
		<div className="cf-kpi">
			<div className="cf-skel" style={ { height: 12, width: 80, marginBottom: 12 } } />
			<div className="cf-skel" style={ { height: 36, width: 120, marginBottom: 12 } } />
			<div className="cf-skel" style={ { height: 20, width: 100 } } />
		</div>
	);
}

// ── Main Component ────────────────────────────────────────────────────────────

const RANGES = [ 'week', 'month', 'year' ];

export default function AnalyticsApp() {
	const [ range, setRange ] = useState( 'month' );
	const [ data,  setData  ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error,   setError   ] = useState( null );
	const [ upgradeRequired, setUpgradeRequired ] = useState( false );

	const apiUrl = window.cfData?.apiUrl ?? '/wp-json/clientflow/v1/';

	const fetchData = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const result = await apiFetch( {
				url:    `${ apiUrl }analytics/overview?range=${ range }`,
				method: 'GET',
			} );
			setData( result );
			setUpgradeRequired( false );
		} catch ( err ) {
			if ( err?.code === 'upgrade_required' || err?.data?.status === 403 ) {
				setUpgradeRequired( true );
			} else {
				setError( err?.message ?? 'Failed to load analytics.' );
			}
		} finally {
			setLoading( false );
		}
	}, [ range, apiUrl ] );

	useEffect( () => { fetchData(); }, [ fetchData ] );

	// Inject CSS once.
	useEffect( () => {
		if ( document.getElementById( 'cf-analytics-css' ) ) return;
		const style = document.createElement( 'style' );
		style.id = 'cf-analytics-css';
		style.textContent = GLOBAL_CSS;
		document.head.appendChild( style );
	}, [] );

	const exportUrl = `${ apiUrl }analytics/overview?range=${ range }&export=csv&_wpnonce=${ window.cfData?.nonce ?? '' }`;

	// ── Upgrade prompt ──
	if ( upgradeRequired ) {
		return (
			<div className="cf-an">
				<div className="cf-an-upgrade">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" style={ { margin: '0 auto' } }>
						<rect x="3" y="3" width="18" height="18" rx="4" stroke="#4F46E5" strokeWidth="1.5" />
						<path d="M8 12h8M12 8v8" stroke="#4F46E5" strokeWidth="2" strokeLinecap="round" />
					</svg>
					<h2>Analytics requires a Pro plan</h2>
					<p>Upgrade to unlock revenue tracking, proposal performance, and the activity feed.</p>
				</div>
			</div>
		);
	}

	const kpis = data?.kpis ?? {};

	return (
		<div className="cf-an">
			{ /* Header */ }
			<div className="cf-an-header">
				<div>
					<h1>Analytics</h1>
					<p>Business performance at a glance</p>
				</div>
				<div className="cf-an-header-actions">
					<div className="cf-an-range">
						{ RANGES.map( r => (
							<button
								key={ r }
								className={ r === range ? 'active' : '' }
								onClick={ () => setRange( r ) }
							>
								{ r.charAt( 0 ).toUpperCase() + r.slice( 1 ) }
							</button>
						) ) }
					</div>
					<a className="cf-an-export" href={ exportUrl }>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
							<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
							<polyline points="7 10 12 15 17 10" />
							<line x1="12" y1="15" x2="12" y2="3" />
						</svg>
						Export CSV
					</a>
				</div>
			</div>

			{ /* Error */ }
			{ error && (
				<div className="cf-an-card" style={ { color: 'var(--cf-red)', marginBottom: 20 } }>
					{ error }
				</div>
			) }

			{ /* KPI Cards */ }
			<div className="cf-an-kpis">
				{ loading ? (
					<>
						<KpiSkeleton /><KpiSkeleton /><KpiSkeleton /><KpiSkeleton />
					</>
				) : (
					<>
						<KpiCard label="Revenue"         value={ kpis.revenue }           prevValue={ kpis.revenue_prev }         format={ fmt.currency } />
						<KpiCard label="Conversion Rate" value={ kpis.conversion_rate }    prevValue={ kpis.conversion_rate_prev } format={ fmt.pct } />
						<KpiCard label="Proposals Sent"  value={ kpis.proposals_sent }     prevValue={ kpis.proposals_sent_prev }  format={ v => v ?? 0 } />
						<KpiCard label="Avg Days to Close" value={ kpis.avg_days_to_close } prevValue={ kpis.avg_days_prev }       format={ fmt.days } />
					</>
				) }
			</div>

			{ /* Revenue Chart */ }
			<div className="cf-an-card">
				<h2>Revenue Over Time</h2>
				{ loading
					? <div className="cf-skel" style={ { height: 200 } } />
					: <LineChart data={ data?.chart ?? [] } />
				}
			</div>

			{ /* Bottom grid */ }
			<div className="cf-an-bottom">
				{ /* Proposal Performance */ }
				<div className="cf-an-card" style={ { margin: 0 } }>
					<h2>Proposal Performance</h2>
					{ loading ? (
						<div className="cf-skel" style={ { height: 180 } } />
					) : (
						<>
							<table className="cf-perf-table">
								<thead>
									<tr>
										<th>Template</th>
										<th>Closed</th>
										<th style={ { width: '40%' } }>Win Rate</th>
									</tr>
								</thead>
								<tbody>
									{ ( data?.performance?.by_template ?? [] ).length === 0 ? (
										<tr><td colSpan="3" style={ { color: 'var(--cf-muted)', padding: '20px 0' } }>No closed proposals in this period.</td></tr>
									) : ( data?.performance?.by_template ?? [] ).map( t => (
										<tr key={ t.template_id }>
											<td>{ t.label }</td>
											<td style={ { color: 'var(--cf-muted)' } }>{ t.closed }</td>
											<td>
												<div className="cf-win-bar-wrap">
													<div className="cf-win-bar-bg">
														<div className="cf-win-bar" style={ { width: `${ t.win_rate }%` } } />
													</div>
													<span style={ { fontSize: 12, fontWeight: 600, color: 'var(--cf-accent)', minWidth: 36 } }>
														{ t.win_rate }%
													</span>
												</div>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
							<div style={ { marginTop: 16, paddingTop: 14, borderTop: '1px solid var(--cf-border)', display: 'flex', gap: 24 } }>
								<div>
									<div style={ { fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.06em', color: 'var(--cf-muted)', marginBottom: 4 } }>Overall Win Rate</div>
									<div style={ { fontSize: 22, fontWeight: 800, fontFamily: 'Archivo, sans-serif', color: 'var(--cf-accent)' } }>
										{ fmt.pct( data?.performance?.overall_acceptance_rate ) }
									</div>
								</div>
								<div>
									<div style={ { fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.06em', color: 'var(--cf-muted)', marginBottom: 4 } }>Avg Days to Accept</div>
									<div style={ { fontSize: 22, fontWeight: 800, fontFamily: 'Archivo, sans-serif', color: 'var(--cf-text)' } }>
										{ fmt.days( data?.performance?.avg_days_to_acceptance ) }
									</div>
								</div>
							</div>
						</>
					) }
				</div>

				{ /* Activity Feed */ }
				<div className="cf-an-card" style={ { margin: 0 } }>
					<h2>Recent Activity</h2>
					{ loading ? (
						<div className="cf-skel" style={ { height: 280 } } />
					) : (
						<ul className="cf-feed">
							{ ( data?.feed ?? [] ).length === 0 ? (
								<li className="cf-an-empty">No recent activity.</li>
							) : ( data?.feed ?? [] ).map( ( item, i ) => {
								const dot = feedDot( item.type );
								return (
									<li key={ i } className="cf-feed-item">
										<div className="cf-feed-dot" style={ { background: dot.bg } }>
											{ dot.icon }
										</div>
										<div>
											<div className="cf-feed-label">{ item.label }</div>
											<div className="cf-feed-time">{ timeAgo( item.timestamp ) }</div>
										</div>
									</li>
								);
							} ) }
						</ul>
					) }
				</div>
			</div>
		</div>
	);
}
