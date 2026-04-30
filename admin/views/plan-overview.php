<?php
/**
 * ClientFlow — Plan & Usage Admin View
 *
 * Rendered inside WordPress #wpcontent.
 * Self-contained: one <style> block + vanilla JS. No external dependencies.
 *
 * Expected variables (provided by ClientFlow::render_plan_overview()):
 *   @var string $user_plan       'free' | 'pro' | 'agency'
 *   @var array  $usage_data      Keys: ai_requests, ai_limit, proposals,
 *                                proposals_limit, storage_mb, storage_limit_mb,
 *                                team_seats, team_limit
 *   @var array  $feature_access  Keys map features to bool|string
 *
 * @package ClientFlow
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Safe defaults ───────────────────────────────────────────────────────────
$user_plan    = $user_plan    ?? 'free';
$usage_data   = $usage_data   ?? [];
$feature_access = $feature_access ?? [];

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Return percentage (0–100) and a status class.
 *
 * @param int|null $used
 * @param int|null $limit
 *
 * @return array{pct: int, status: string}
 */
function cf_progress( ?int $used, ?int $limit ): array {
	if ( null === $limit || 0 === $limit ) {
		return [ 'pct' => 0, 'status' => 'unlimited' ];
	}

	$pct = (int) min( 100, round( ( $used / $limit ) * 100 ) );

	$status = 'ok';
	if ( $pct >= 95 ) $status = 'danger';
	elseif ( $pct >= 80 ) $status = 'warn';

	return [ 'pct' => $pct, 'status' => $status ];
}

$plan_labels = [
	'free'   => 'Free',
	'pro'    => 'Pro',
	'agency' => 'Agency',
];

$plan_label = $plan_labels[ $user_plan ] ?? 'Free';
$is_agency  = 'agency' === $user_plan;
$is_pro_or_above = in_array( $user_plan, [ 'pro', 'agency' ], true );

// Usage progress.
$ai_prog       = cf_progress( $usage_data['ai_requests'] ?? 0,  $usage_data['ai_limit'] ?? null );
$prop_prog     = cf_progress( $usage_data['proposals'] ?? 0,    $usage_data['proposals_limit'] ?? null );
$storage_prog  = cf_progress( $usage_data['storage_mb'] ?? 0,   $usage_data['storage_limit_mb'] ?? null );
$team_prog     = cf_progress( $usage_data['team_seats'] ?? 1,   $usage_data['team_limit'] ?? 1 );

// Feature grid definition.
$features = [
	[
		'key'     => 'create_proposal',
		'label'   => 'Proposals',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
		'note'    => 'free' === $user_plan ? '5 max (lifetime)' : 'Unlimited',
		'gate'    => 'all',
	],
	[
		'key'     => 'use_payments',
		'label'   => 'Payments',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
		'note'    => $is_pro_or_above ? 'Stripe enabled' : 'Pro required',
		'gate'    => 'pro',
	],
	[
		'key'     => 'use_portal',
		'label'   => 'Client Portal',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		'note'    => 'agency' === $user_plan ? 'Full access' : ( 'pro' === $user_plan ? 'View-only' : 'Pro required' ),
		'gate'    => 'pro',
	],
	[
		'key'     => 'use_projects',
		'label'   => 'Projects',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
		'note'    => $is_agency ? 'Active' : 'Agency required',
		'gate'    => 'agency',
	],
	[
		'key'     => 'use_messaging',
		'label'   => 'Messaging',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
		'note'    => $is_agency ? 'Active' : 'Agency required',
		'gate'    => 'agency',
	],
	[
		'key'     => 'use_files',
		'label'   => 'File Sharing',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>',
		'note'    => $is_agency ? '1 GB storage' : 'Agency required',
		'gate'    => 'agency',
	],
	[
		'key'     => 'use_ai',
		'label'   => 'AI Assist',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
		'note'    => 'agency' === $user_plan ? '500 req/mo' : ( 'pro' === $user_plan ? '100 req/mo' : 'Pro required' ),
		'gate'    => 'pro',
	],
	[
		'key'     => 'team_access',
		'label'   => 'Team Members',
		'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
		'note'    => $is_agency ? 'Up to 5 seats' : '1 user only',
		'gate'    => 'agency',
	],
];

// Upgrade CTA target.
$upgrade_url = admin_url( 'admin.php?page=clientflow-upgrade' );
?>

<style>
/* ─── Google Font ─────────────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap');

/* ─── Variables ───────────────────────────────────────────────────────── */
#cf-admin-wrap {
    --navy:      #0F172A;
    --navy-mid:  #1E293B;
    --navy-dim:  #334155;
    --indigo:    #6366F1;
    --indigo-lt: #818CF8;
    --indigo-bg: #EEF2FF;
    --emerald:   #10B981;
    --emerald-bg:#ECFDF5;
    --amber:     #F59E0B;
    --amber-bg:  #FFFBEB;
    --red:       #EF4444;
    --red-bg:    #FEF2F2;
    --slate-50:  #F8FAFC;
    --slate-100: #F1F5F9;
    --slate-200: #E2E8F0;
    --slate-400: #94A3B8;
    --slate-600: #475569;
    --slate-700: #334155;
    --slate-800: #1E293B;
    --white:     #FFFFFF;
    --radius:    12px;
    --radius-sm: 8px;
    --shadow:    0 1px 3px rgba(15,23,42,.06), 0 4px 16px rgba(15,23,42,.08);
    --shadow-lg: 0 4px 6px rgba(15,23,42,.05), 0 10px 40px rgba(15,23,42,.12);
    font-family: 'Archivo', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--slate-800);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

/* ─── Reset WP admin styles inside our wrapper ────────────────────────── */
#cf-admin-wrap *, #cf-admin-wrap *::before, #cf-admin-wrap *::after {
    box-sizing: border-box;
}
#cf-admin-wrap h1, #cf-admin-wrap h2, #cf-admin-wrap h3, #cf-admin-wrap h4,
#cf-admin-wrap p, #cf-admin-wrap ul, #cf-admin-wrap li {
    margin: 0; padding: 0; font-size: inherit; font-weight: inherit;
    color: inherit; line-height: inherit;
}
#cf-admin-wrap a { text-decoration: none; }

/* ─── Layout ──────────────────────────────────────────────────────────── */
#cf-admin-wrap {
    max-width: 1100px;
    padding: 32px 28px 64px;
}

/* ─── Top Header ──────────────────────────────────────────────────────── */
.cf-header {
    margin-bottom: 28px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}
.cf-brand { display: flex; flex-direction: column; }
.cf-brand-icon { display: none; }
.cf-brand-name {
    font-family: 'Archivo', -apple-system, sans-serif;
    font-size: 26px;
    font-weight: 800;
    color: var(--navy);
    letter-spacing: -0.5px;
    margin: 0 0 4px;
}
.cf-brand-tagline {
    font-size: 13.5px;
    color: var(--slate-400);
    margin: 0;
}
.cf-header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* ─── Plan Badge ──────────────────────────────────────────────────────── */
.cf-plan-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.cf-plan-badge.free   { background: var(--slate-100); color: var(--slate-600); }
.cf-plan-badge.pro    { background: var(--indigo-bg);  color: var(--indigo); }
.cf-plan-badge.agency { background: var(--emerald-bg); color: var(--emerald); }
.cf-plan-badge::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.8;
}

/* ─── Upgrade Button ──────────────────────────────────────────────────── */
.cf-btn-upgrade {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    border-radius: var(--radius-sm);
    background: var(--indigo);
    color: #fff !important;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Archivo', sans-serif;
    cursor: pointer;
    border: none;
    transition: background 0.15s, transform 0.12s, box-shadow 0.15s;
    box-shadow: 0 2px 8px rgba(99,102,241,.4);
    white-space: nowrap;
}
.cf-btn-upgrade:hover {
    background: #4F46E5;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(99,102,241,.5);
}
.cf-btn-upgrade svg { width: 14px; height: 14px; stroke: currentColor; }

/* ─── Grid Layouts ────────────────────────────────────────────────────── */
.cf-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.cf-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 900px) {
    .cf-grid-2 { grid-template-columns: 1fr; }
    .cf-grid-4 { grid-template-columns: 1fr 1fr; }
}

/* ─── Cards ───────────────────────────────────────────────────────────── */
.cf-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--slate-200);
    padding: 24px;
}
.cf-card-title {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--slate-400);
    margin-bottom: 16px;
}

/* ─── Plan Overview Card ──────────────────────────────────────────────── */
.cf-plan-card {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
    color: #fff;
    position: relative;
    overflow: hidden;
}
.cf-plan-card::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 260px; height: 260px;
    background: radial-gradient(circle, rgba(99,102,241,.3) 0%, transparent 65%);
    pointer-events: none;
}
.cf-plan-name {
    font-family: 'Archivo', -apple-system, sans-serif;
    font-size: 32px;
    color: #fff;
    letter-spacing: -0.5px;
    margin-bottom: 4px;
    position: relative;
    z-index: 1;
}
.cf-plan-sub {
    font-size: 13px;
    color: rgba(255,255,255,.5);
    margin-bottom: 20px;
    position: relative;
    z-index: 1;
}
.cf-plan-limits {
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: relative;
    z-index: 1;
}
.cf-plan-cta {
    margin-top: 24px;
    position: relative;
    z-index: 1;
}
.cf-btn-upgrade-card {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: var(--radius-sm);
    background: var(--indigo);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Archivo', sans-serif;
    cursor: pointer;
    transition: background 0.15s, transform 0.12s, box-shadow 0.15s;
    box-shadow: 0 2px 10px rgba(99,102,241,.5);
}
.cf-btn-upgrade-card:hover {
    background: #4F46E5;
    transform: translateY(-1px);
    box-shadow: 0 4px 20px rgba(99,102,241,.6);
}

/* ─── Progress Bars ───────────────────────────────────────────────────── */
.cf-limit-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 6px;
}
.cf-limit-label {
    font-size: 12px;
    font-weight: 500;
    color: rgba(255,255,255,.7);
}
.cf-limit-count {
    font-size: 12px;
    font-weight: 600;
    color: rgba(255,255,255,.9);
    font-variant-numeric: tabular-nums;
}
.cf-bar-track {
    height: 5px;
    background: rgba(255,255,255,.12);
    border-radius: 999px;
    overflow: hidden;
}
.cf-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--indigo-lt), var(--indigo));
    width: 0; /* animated by JS */
    transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.cf-bar-fill.unlimited {
    background: linear-gradient(90deg, var(--emerald), #059669);
    width: 100% !important;
}
.cf-bar-fill.warn {
    background: linear-gradient(90deg, #FCD34D, var(--amber));
}
.cf-bar-fill.danger {
    background: linear-gradient(90deg, #FCA5A5, var(--red));
}

/* Inline (white-background) progress bars */
.cf-bar-track.light { background: var(--slate-100); }
.cf-bar-fill.light-indigo {
    background: linear-gradient(90deg, var(--indigo-lt), var(--indigo));
}
.cf-limit-label.dark  { color: var(--slate-600); }
.cf-limit-count.dark  { color: var(--slate-700); }

/* ─── Feature Grid ────────────────────────────────────────────────────── */
.cf-feature-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
@media (max-width: 900px) {
    .cf-feature-grid { grid-template-columns: repeat(2, 1fr); }
}
.cf-feature-item {
    background: var(--slate-50);
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-sm);
    padding: 16px;
    position: relative;
    transition: border-color 0.15s, box-shadow 0.15s;
    cursor: default;
}
.cf-feature-item.cf-active {
    background: var(--white);
    border-color: rgba(99,102,241,.3);
}
.cf-feature-item.cf-active:hover {
    border-color: var(--indigo);
    box-shadow: 0 0 0 3px rgba(99,102,241,.08);
}
.cf-feature-item.cf-locked {
    opacity: 0.65;
}
.cf-feature-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 10px;
}
.cf-active .cf-feature-icon {
    background: var(--indigo-bg);
}
.cf-active .cf-feature-icon svg {
    stroke: var(--indigo);
}
.cf-locked .cf-feature-icon {
    background: var(--slate-100);
}
.cf-locked .cf-feature-icon svg {
    stroke: var(--slate-400);
}
.cf-feature-icon svg {
    width: 18px; height: 18px;
}
.cf-feature-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--slate-700);
    margin-bottom: 3px;
}
.cf-feature-note {
    font-size: 11.5px;
    color: var(--slate-400);
}
.cf-active .cf-feature-name { color: var(--slate-800); }
.cf-active .cf-feature-note { color: var(--slate-500); }

/* Lock badge */
.cf-lock-badge {
    position: absolute;
    top: 10px; right: 10px;
    width: 22px; height: 22px;
    background: var(--slate-200);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.cf-lock-badge svg {
    width: 11px; height: 11px;
    stroke: var(--slate-400);
    stroke-width: 2;
}

/* Upgrade required tooltip */
.cf-feature-item[data-tooltip] {
    position: relative;
}
.cf-feature-item[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    background: var(--navy);
    color: rgba(255,255,255,.9);
    font-size: 11px;
    font-weight: 500;
    padding: 5px 10px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s, transform 0.15s;
    z-index: 10;
}
.cf-feature-item[data-tooltip]::before {
    content: '';
    position: absolute;
    bottom: calc(100% + 2px);
    left: 50%;
    transform: translateX(-50%) translateY(4px);
    border: 5px solid transparent;
    border-top-color: var(--navy);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s, transform 0.15s;
    z-index: 10;
}
.cf-feature-item[data-tooltip]:hover::after,
.cf-feature-item[data-tooltip]:hover::before {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* Active check badge */
.cf-check-badge {
    position: absolute;
    top: 10px; right: 10px;
    width: 22px; height: 22px;
    background: var(--emerald-bg);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.cf-check-badge svg {
    width: 11px; height: 11px;
    stroke: var(--emerald);
    stroke-width: 2.5;
}

/* ─── Stats Mini Cards ────────────────────────────────────────────────── */
.cf-stat-card {
    background: var(--white);
    border: 1px solid var(--slate-200);
    border-radius: var(--radius);
    padding: 20px 22px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
    overflow: hidden;
}
.cf-stat-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: var(--radius) var(--radius) 0 0;
}
.cf-stat-card.indigo::after { background: linear-gradient(90deg, var(--indigo), var(--indigo-lt)); }
.cf-stat-card.emerald::after { background: linear-gradient(90deg, var(--emerald), #34D399); }
.cf-stat-card.amber::after { background: linear-gradient(90deg, var(--amber), #FCD34D); }
.cf-stat-card.navy::after { background: linear-gradient(90deg, var(--navy-dim), var(--navy-mid)); }

.cf-stat-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--slate-400);
}
.cf-stat-value {
    font-family: 'Archivo', -apple-system, sans-serif;
    font-size: 28px;
    color: var(--slate-800);
    letter-spacing: -0.5px;
    line-height: 1;
}
.cf-stat-meta {
    font-size: 12px;
    color: var(--slate-400);
    margin-top: -4px;
}
.cf-stat-bar-row {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

/* ─── Quick Actions ───────────────────────────────────────────────────── */
.cf-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.cf-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    background: var(--white);
    border: 1px solid var(--slate-200);
    color: var(--slate-700) !important;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Archivo', sans-serif;
    cursor: pointer;
    transition: border-color 0.12s, box-shadow 0.12s, background 0.12s;
    text-decoration: none;
}
.cf-action-btn:hover {
    border-color: var(--indigo);
    color: var(--indigo) !important;
    box-shadow: 0 0 0 3px rgba(99,102,241,.08);
    background: var(--indigo-bg);
}
.cf-action-btn svg {
    width: 14px; height: 14px;
    stroke: currentColor;
    flex-shrink: 0;
}
.cf-action-btn.primary {
    background: var(--indigo);
    border-color: var(--indigo);
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.cf-action-btn.primary:hover {
    background: #4F46E5;
    border-color: #4F46E5;
    color: #fff !important;
    box-shadow: 0 4px 16px rgba(99,102,241,.4);
}

/* ─── Section Title ───────────────────────────────────────────────────── */
.cf-section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--slate-700);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cf-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--slate-200);
}

/* ─── Animations ──────────────────────────────────────────────────────── */
@keyframes cf-fade-up {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.cf-animate { animation: cf-fade-up 0.4s ease both; }
.cf-animate-1 { animation-delay: 0.05s; }
.cf-animate-2 { animation-delay: 0.10s; }
.cf-animate-3 { animation-delay: 0.15s; }
.cf-animate-4 { animation-delay: 0.20s; }
.cf-animate-5 { animation-delay: 0.25s; }
</style>

<div id="cf-admin-wrap">

    <?php /* ── Top Header ────────────────────────────────────────────────── */ ?>
    <div class="cf-header cf-animate">
        <div class="cf-brand">
            <div class="cf-brand-name">Plan &amp; Usage</div>
            <div class="cf-brand-tagline">Your plan, usage limits and feature access</div>
        </div>
        <div class="cf-header-right">
            <span class="cf-plan-badge <?php echo esc_attr( $user_plan ); ?>">
                <?php echo esc_html( $plan_label ); ?> Plan
            </span>
            <?php if ( ! $is_agency ) : ?>
                <a href="<?php echo esc_url( $upgrade_url ); ?>" class="cf-btn-upgrade">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="17 11 12 6 7 11"/><line x1="12" y1="6" x2="12" y2="18"/>
                    </svg>
                    Upgrade Plan
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php /* ── Row 1: Plan Card + Feature Grid ──────────────────────────── */ ?>
    <div class="cf-grid-2 cf-animate cf-animate-1">

        <?php /* Plan Overview Card */ ?>
        <div class="cf-card cf-plan-card">
            <div class="cf-card-title" style="color:rgba(255,255,255,.4);">Your Plan</div>
            <div class="cf-plan-name"><?php echo esc_html( $plan_label ); ?></div>
            <div class="cf-plan-sub">
                <?php if ( 'free' === $user_plan ) : ?>
                    Try ClientFlow — upgrade to unlock payments & AI.
                <?php elseif ( 'pro' === $user_plan ) : ?>
                    Unlimited proposals · AI assistance · Stripe payments.
                <?php else : ?>
                    Full access · Team collaboration · Projects & messaging.
                <?php endif; ?>
            </div>

            <div class="cf-plan-limits">
                <?php /* Proposals */ ?>
                <div class="cf-limit-row">
                    <div class="cf-limit-header">
                        <span class="cf-limit-label">Proposals</span>
                        <span class="cf-limit-count">
                            <?php
                            $used  = $usage_data['proposals'] ?? 0;
                            $limit = $usage_data['proposals_limit'] ?? null;
                            echo null === $limit
                                ? esc_html( $used ) . ' created'
                                : esc_html( $used ) . ' / ' . esc_html( $limit );
                            ?>
                        </span>
                    </div>
                    <div class="cf-bar-track">
                        <div class="cf-bar-fill <?php echo null === $limit ? 'unlimited' : esc_attr( $prop_prog['status'] ); ?>"
                             data-pct="<?php echo null === $limit ? 100 : esc_attr( $prop_prog['pct'] ); ?>"></div>
                    </div>
                </div>

                <?php /* AI Requests */ ?>
                <div class="cf-limit-row">
                    <div class="cf-limit-header">
                        <span class="cf-limit-label">AI Requests</span>
                        <span class="cf-limit-count">
                            <?php
                            $ai_used  = $usage_data['ai_requests'] ?? 0;
                            $ai_limit = $usage_data['ai_limit'] ?? null;
                            if ( ! $is_pro_or_above ) {
                                echo 'Not available';
                            } elseif ( null === $ai_limit ) {
                                echo esc_html( $ai_used ) . ' this month';
                            } else {
                                echo esc_html( $ai_used ) . ' / ' . esc_html( $ai_limit ) . ' this month';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="cf-bar-track">
                        <div class="cf-bar-fill <?php echo ! $is_pro_or_above ? '' : esc_attr( $ai_prog['status'] ); ?>"
                             data-pct="<?php echo ! $is_pro_or_above ? 0 : esc_attr( $ai_prog['pct'] ); ?>"></div>
                    </div>
                </div>

                <?php /* Team Seats */ ?>
                <div class="cf-limit-row">
                    <div class="cf-limit-header">
                        <span class="cf-limit-label">Team Seats</span>
                        <span class="cf-limit-count">
                            <?php echo esc_html( $usage_data['team_seats'] ?? 1 ) . ' / ' . esc_html( $usage_data['team_limit'] ?? 1 ); ?>
                        </span>
                    </div>
                    <div class="cf-bar-track">
                        <div class="cf-bar-fill <?php echo esc_attr( $team_prog['status'] ); ?>"
                             data-pct="<?php echo esc_attr( $team_prog['pct'] ); ?>"></div>
                    </div>
                </div>
            </div>

            <?php if ( ! $is_agency ) : ?>
                <div class="cf-plan-cta">
                    <a href="<?php echo esc_url( $upgrade_url ); ?>" class="cf-btn-upgrade-card">
                        <?php echo 'free' === $user_plan ? 'Upgrade to Pro' : 'Upgrade to Agency'; ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;stroke:currentColor;">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php /* Feature Access Grid */ ?>
        <div class="cf-card">
            <div class="cf-card-title">Feature Access</div>
            <div class="cf-feature-grid">
                <?php foreach ( $features as $feat ) :
                    $access  = $feature_access[ $feat['key'] ] ?? false;
                    $active  = false !== $access;
                    $tooltip = '';
                    if ( ! $active ) {
                        $tooltip = 'agency' === $feat['gate']
                            ? 'Upgrade to Agency'
                            : 'Upgrade to Pro';
                    }
                    ?>
                    <div class="cf-feature-item <?php echo $active ? 'cf-active' : 'cf-locked'; ?>"
                         <?php echo $tooltip ? 'data-tooltip="' . esc_attr( $tooltip ) . '"' : ''; ?>>

                        <div class="cf-feature-icon">
                            <?php echo $feat['icon']; // SVG is safe — defined in PHP above. ?>
                        </div>

                        <div class="cf-feature-name"><?php echo esc_html( $feat['label'] ); ?></div>
                        <div class="cf-feature-note"><?php echo esc_html( $feat['note'] ); ?></div>

                        <?php if ( $active ) : ?>
                            <div class="cf-check-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                        <?php else : ?>
                            <div class="cf-lock-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php /* ── Row 2: Usage Stats ─────────────────────────────────────────── */ ?>
    <div class="cf-section-title cf-animate cf-animate-2">Usage This Month</div>

    <div class="cf-grid-4 cf-animate cf-animate-3">

        <?php /* AI Requests */ ?>
        <div class="cf-stat-card indigo">
            <div class="cf-stat-label">AI Requests</div>
            <div class="cf-stat-value"><?php echo esc_html( $usage_data['ai_requests'] ?? 0 ); ?></div>
            <div class="cf-stat-meta">
                <?php
                $ai_limit_val = $usage_data['ai_limit'] ?? null;
                if ( ! $is_pro_or_above ) {
                    echo 'Upgrade to unlock';
                } elseif ( null === $ai_limit_val ) {
                    echo 'Unlimited';
                } else {
                    echo 'of ' . esc_html( $ai_limit_val ) . ' monthly';
                }
                ?>
            </div>
            <?php if ( $is_pro_or_above && null !== $ai_limit_val && 0 !== $ai_limit_val ) : ?>
                <div class="cf-stat-bar-row">
                    <div class="cf-bar-track light">
                        <div class="cf-bar-fill light-indigo <?php echo esc_attr( $ai_prog['status'] ); ?>"
                             data-pct="<?php echo esc_attr( $ai_prog['pct'] ); ?>"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php /* Proposals */ ?>
        <div class="cf-stat-card emerald">
            <div class="cf-stat-label">Proposals Created</div>
            <div class="cf-stat-value"><?php echo esc_html( $usage_data['proposals'] ?? 0 ); ?></div>
            <div class="cf-stat-meta">
                <?php
                $prop_limit = $usage_data['proposals_limit'] ?? null;
                echo null === $prop_limit
                    ? 'Unlimited'
                    : 'of ' . esc_html( $prop_limit ) . ' total';
                ?>
            </div>
            <?php if ( null !== $prop_limit ) : ?>
                <div class="cf-stat-bar-row">
                    <div class="cf-bar-track light">
                        <div class="cf-bar-fill light-indigo <?php echo esc_attr( $prop_prog['status'] ); ?>"
                             data-pct="<?php echo esc_attr( $prop_prog['pct'] ); ?>"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php /* Storage */ ?>
        <div class="cf-stat-card amber">
            <div class="cf-stat-label">Storage Used</div>
            <div class="cf-stat-value">
                <?php
                $mb = $usage_data['storage_mb'] ?? 0;
                echo $mb >= 1000
                    ? esc_html( number_format( $mb / 1000, 1 ) ) . '<span style="font-size:16px;color:var(--slate-400);margin-left:2px">GB</span>'
                    : esc_html( $mb ) . '<span style="font-size:16px;color:var(--slate-400);margin-left:2px">MB</span>';
                ?>
            </div>
            <div class="cf-stat-meta">
                <?php echo $is_agency ? 'of 1 GB limit' : 'Not available'; ?>
            </div>
            <?php if ( $is_agency ) : ?>
                <div class="cf-stat-bar-row">
                    <div class="cf-bar-track light">
                        <div class="cf-bar-fill light-indigo <?php echo esc_attr( $storage_prog['status'] ); ?>"
                             data-pct="<?php echo esc_attr( $storage_prog['pct'] ); ?>"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php /* Team */ ?>
        <div class="cf-stat-card navy">
            <div class="cf-stat-label">Team Seats</div>
            <div class="cf-stat-value"><?php echo esc_html( $usage_data['team_seats'] ?? 1 ); ?></div>
            <div class="cf-stat-meta">
                of <?php echo esc_html( $usage_data['team_limit'] ?? 1 ); ?> available
            </div>
            <div class="cf-stat-bar-row">
                <div class="cf-bar-track light">
                    <div class="cf-bar-fill light-indigo <?php echo esc_attr( $team_prog['status'] ); ?>"
                         data-pct="<?php echo esc_attr( $team_prog['pct'] ); ?>"></div>
                </div>
            </div>
        </div>
    </div>

    <?php /* ── Quick Actions ─────────────────────────────────────────────── */ ?>
    <div class="cf-section-title cf-animate cf-animate-4">Quick Actions</div>

    <div class="cf-animate cf-animate-5">
        <div class="cf-actions">
            <?php if ( $is_pro_or_above ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=clientflow-proposals&action=new' ) ); ?>" class="cf-action-btn primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Proposal
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=clientflow-clients' ) ); ?>" class="cf-action-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                Manage Clients
            </a>

            <?php if ( $is_pro_or_above ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=clientflow-proposals' ) ); ?>" class="cf-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    </svg>
                    All Proposals
                </a>
            <?php endif; ?>

            <?php if ( $is_agency ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=clientflow-projects' ) ); ?>" class="cf-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Projects
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=clientflow-team' ) ); ?>" class="cf-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    Team
                </a>
            <?php endif; ?>

            <?php if ( ! $is_agency ) : ?>
                <a href="<?php echo esc_url( $upgrade_url ); ?>" class="cf-action-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="17 11 12 6 7 11"/><line x1="12" y1="6" x2="12" y2="18"/>
                    </svg>
                    <?php echo 'free' === $user_plan ? 'Upgrade to Pro' : 'Upgrade to Agency'; ?>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=clientflow-settings' ) ); ?>" class="cf-action-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                </svg>
                Settings
            </a>
        </div>
    </div>

</div><!-- #cf-admin-wrap -->

<script>
(function () {
    'use strict';

    // Animate progress bars after render.
    document.addEventListener('DOMContentLoaded', function () {
        var bars = document.querySelectorAll('#cf-admin-wrap .cf-bar-fill[data-pct]');

        bars.forEach(function (bar) {
            var pct = parseInt(bar.getAttribute('data-pct'), 10) || 0;
            // Small delay so the animation is visible.
            setTimeout(function () {
                bar.style.width = pct + '%';
            }, 120);
        });
    });
}());
</script>
