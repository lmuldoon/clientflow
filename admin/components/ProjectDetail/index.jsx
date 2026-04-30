/**
 * ProjectDetail — redesigned
 *
 * Layout: hero header → tab bar (Overview | Files | Approvals) → two-column body
 * Tabs keep components mounted after first visit (no re-fetch on switch).
 * Sidebar shows project info, sticky on desktop.
 *
 * Props: { projectId, onBack }
 */
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { cfFetch } from '../ProjectsApp';
import ProjectFiles     from '../ProjectFiles';
import ProjectApprovals from '../ProjectApprovals';
import ProjectMessages  from '../ProjectMessages';

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id; s.textContent = css;
	document.head.appendChild( s );
}

const CSS = `
/* ─── Page enter ───────────────────────────────────────────────────── */
.cf-pd {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 32px 28px 64px;
  animation: cf-pd-enter .22s ease both;
}
@keyframes cf-pd-enter {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─── Back button ──────────────────────────────────────────────────── */
.cf-pd-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--cf-slate-400);
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  margin-bottom: 24px;
  letter-spacing: .01em;
  transition: color .12s;
}
.cf-pd-back:hover { color: var(--cf-indigo); }
.cf-pd-back svg { flex-shrink: 0; transition: transform .15s; }
.cf-pd-back:hover svg { transform: translateX(-2px); }

/* ─── Hero ─────────────────────────────────────────────────────────── */
.cf-pd-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  padding-bottom: 22px;
  margin-bottom: 0;
  border-bottom: 1px solid var(--cf-slate-200);
  flex-wrap: wrap;
}
.cf-pd-hero-left { flex: 1; min-width: 0; }

.cf-pd-project-name {
  font-family: var(--cf-font);
  font-size: 26px;
  font-weight: 800;
  color: var(--cf-navy);
  letter-spacing: -.5px;
  line-height: 1.15;
  margin: 0 0 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cf-pd-hero-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--cf-slate-400);
  flex-wrap: wrap;
}
.cf-pd-meta-dot {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: var(--cf-slate-300);
  flex-shrink: 0;
}

/* ─── Status pill select ───────────────────────────────────────────── */
.cf-pd-status-select {
  appearance: none;
  -webkit-appearance: none;
  padding: 7px 28px 7px 12px;
  border-radius: 999px;
  font-family: var(--cf-font);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  border: 1.5px solid transparent;
  transition: box-shadow .15s;
  background-repeat: no-repeat;
  background-position: right 9px center;
  background-size: 10px 6px;
  flex-shrink: 0;
}
.cf-pd-status-select:focus {
  box-shadow: 0 0 0 3px rgba(99,102,241,.15);
}
.cf-pd-status-active {
  background-color: var(--cf-emerald-bg);
  border-color: rgba(16,185,129,.3);
  color: var(--cf-emerald);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2310b981' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
}
.cf-pd-status-on-hold {
  background-color: var(--cf-amber-bg);
  border-color: rgba(245,158,11,.3);
  color: var(--cf-amber);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23f59e0b' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
}
.cf-pd-status-completed {
  background-color: var(--cf-indigo-bg);
  border-color: rgba(99,102,241,.3);
  color: var(--cf-indigo);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236366f1' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
}

/* ─── Tab bar ──────────────────────────────────────────────────────── */
.cf-pd-tabs {
  display: flex;
  border-bottom: 1px solid var(--cf-slate-200);
  margin-bottom: 28px;
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}
.cf-pd-tabs::-webkit-scrollbar { display: none; }

.cf-pd-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--cf-slate-400);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  cursor: pointer;
  white-space: nowrap;
  transition: color .15s, border-color .15s;
}
.cf-pd-tab:hover:not(.active) { color: var(--cf-slate-600); }
.cf-pd-tab.active {
  color: var(--cf-navy);
  font-weight: 600;
  border-bottom-color: var(--cf-indigo);
}

.cf-pd-tab-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--cf-slate-100);
  color: var(--cf-slate-500);
  transition: background .15s, color .15s;
}
.cf-pd-tab.active .cf-pd-tab-badge {
  background: var(--cf-indigo-bg);
  color: var(--cf-indigo);
}

/* ─── Body layout ──────────────────────────────────────────────────── */
.cf-pd-body {
  display: grid;
  grid-template-columns: 1fr 256px;
  gap: 28px;
  align-items: start;
}
@media (max-width: 900px) {
  .cf-pd-body { grid-template-columns: 1fr; }
  .cf-pd-sidebar { position: static !important; }
  .cf-pd-project-name { font-size: 24px; }
}

/* ─── Tab panels ───────────────────────────────────────────────────── */
.cf-pd-tab-panel { display: none; }
.cf-pd-tab-panel.cf-pd-tab-active {
  display: block;
  animation: cf-pd-panel-in .18s ease both;
}
@keyframes cf-pd-panel-in {
  from { opacity: 0; transform: translateY(5px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─── Progress strip ───────────────────────────────────────────────── */
.cf-pd-progress-strip {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 18px 20px;
  background: var(--cf-white);
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius);
  margin-bottom: 20px;
}

.cf-pd-pct-num {
  font-family: var(--cf-font-display);
  font-size: 36px;
  font-weight: 800;
  color: var(--cf-indigo);
  letter-spacing: -2px;
  line-height: 1;
  flex-shrink: 0;
}

.cf-pd-pct-num.done { color: var(--cf-emerald); }

.cf-pd-progress-right { flex: 1; }

.cf-pd-progress-label {
  font-size: 12.5px;
  color: var(--cf-slate-500);
  margin-bottom: 8px;
  font-weight: 500;
}

.cf-pd-bar-track {
  height: 5px;
  background: var(--cf-slate-100);
  border-radius: 99px;
  overflow: hidden;
}

.cf-pd-bar-fill {
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, var(--cf-indigo), var(--cf-emerald));
  transition: width .6s cubic-bezier(.4,0,.2,1);
}

/* ─── Section label ────────────────────────────────────────────────── */
.cf-pd-section-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--cf-slate-400);
  margin-bottom: 10px;
}

/* ─── Add milestone form ───────────────────────────────────────────── */
.cf-pd-add-form {
  display: grid;
  grid-template-columns: 1fr 150px auto;
  gap: 8px;
  margin-bottom: 14px;
}
@media (max-width: 600px) {
  .cf-pd-add-form { grid-template-columns: 1fr 1fr; }
  .cf-pd-add-btn  { grid-column: 1 / -1; }
}

.cf-pd-add-input {
  padding: 9px 12px;
  border-radius: var(--cf-radius-sm);
  border: var(--cf-input-border);
  font-family: var(--cf-font);
  font-size: 13px;
  background: var(--cf-slate-50);
  color: var(--cf-navy);
  transition: border-color .12s, box-shadow .12s;
}
.cf-pd-add-input:focus { outline: none; border-color: var(--cf-indigo); box-shadow: var(--cf-input-focus); }

.cf-pd-add-date {
  padding: 9px 10px;
  border-radius: var(--cf-radius-sm);
  border: var(--cf-input-border);
  font-family: var(--cf-font);
  font-size: 12px;
  background: var(--cf-slate-50);
  color: var(--cf-slate-600);
}
.cf-pd-add-date:focus { outline: none; border-color: var(--cf-indigo); box-shadow: var(--cf-input-focus); }

.cf-pd-add-btn {
  padding: 9px 16px;
  border-radius: var(--cf-radius-sm);
  background: var(--cf-indigo);
  color: #fff;
  border: none;
  font-family: var(--cf-font);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity .12s;
  white-space: nowrap;
}
.cf-pd-add-btn:hover:not(:disabled) { opacity: .88; }
.cf-pd-add-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ─── Milestone list ───────────────────────────────────────────────── */
.cf-pd-ms-list { display: flex; flex-direction: column; }

.cf-pd-ms-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 8px 10px 0;
  border-bottom: 1px solid var(--cf-slate-100);
  border-radius: 4px;
  transition: background .12s;
  cursor: grab;
}
.cf-pd-ms-row:last-child { border-bottom: none; }
.cf-pd-ms-row:hover { background: var(--cf-slate-50); }
.cf-pd-ms-row:active { cursor: grabbing; }
.cf-pd-ms-row.dragging { opacity: .35; }
.cf-pd-ms-row.drag-over {
  background: var(--cf-indigo-bg);
  border-bottom-color: var(--cf-indigo);
  outline: 1px dashed var(--cf-indigo);
  border-radius: 4px;
}

.cf-pd-ms-drag {
  width: 12px;
  flex-shrink: 0;
  cursor: grab;
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 1px;
}
.cf-pd-ms-drag span {
  display: block;
  width: 12px;
  height: 2px;
  background: var(--cf-slate-300);
  border-radius: 1px;
}

.cf-pd-ms-btn {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  flex-shrink: 0;
  border: 2px solid var(--cf-slate-300);
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .15s;
  position: relative;
  padding: 0;
}
.cf-pd-ms-btn[data-status="in-progress"] {
  border-color: var(--cf-indigo);
  background: var(--cf-indigo-bg);
}
.cf-pd-ms-btn[data-status="in-progress"]::after {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--cf-indigo);
  opacity: .7;
}
.cf-pd-ms-btn[data-status="completed"] {
  border-color: var(--cf-emerald);
  background: var(--cf-emerald);
}
.cf-pd-ms-btn[data-status="completed"]::after {
  content: '';
  display: block;
  width: 9px;
  height: 5px;
  border-left: 2px solid #fff;
  border-bottom: 2px solid #fff;
  transform: rotate(-45deg) translateY(-1px);
}
.cf-pd-ms-btn.locked {
  border-color: var(--cf-slate-300);
  background: #F8FAFC;
  cursor: not-allowed;
  opacity: .7;
  color: var(--cf-slate-400);
}
.cf-pd-ms-submit-btn {
  font-family: var(--cf-font);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: 3px 9px;
  border-radius: 5px;
  border: 1.5px solid var(--cf-indigo);
  background: transparent;
  color: var(--cf-indigo);
  cursor: pointer;
  white-space: nowrap;
  transition: background .13s, color .13s;
  flex-shrink: 0;
}
.cf-pd-ms-submit-btn:hover { background: var(--cf-indigo); color: #fff; }
.cf-pd-ms-btn.just-completed {
  animation: cf-ms-pop .35s cubic-bezier(.36,.07,.19,.97);
}
@keyframes cf-ms-pop {
  0%   { transform: scale(1); }
  40%  { transform: scale(1.5); box-shadow: 0 0 0 6px rgba(16,185,129,.2); }
  70%  { transform: scale(.9); }
  100% { transform: scale(1); }
}

.cf-pd-ms-label {
  flex: 1;
  font-size: 13.5px;
  color: var(--cf-navy);
  line-height: 1.4;
}
.cf-pd-ms-label.done {
  text-decoration: line-through;
  color: var(--cf-slate-400);
}

.cf-pd-ms-due {
  font-size: 11px;
  color: var(--cf-slate-400);
  white-space: nowrap;
  flex-shrink: 0;
}
.cf-pd-ms-due.overdue { color: var(--cf-red); font-weight: 600; }

.cf-pd-ms-del {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  flex-shrink: 0;
  border: none;
  background: transparent;
  color: var(--cf-slate-300);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color .12s, background .12s;
  padding: 0;
  opacity: 0;
  transition: opacity .12s, color .12s, background .12s;
}
.cf-pd-ms-row:hover .cf-pd-ms-del { opacity: 1; }
.cf-pd-ms-del:hover { color: var(--cf-red); background: var(--cf-red-bg); }

.cf-pd-ms-empty {
  padding: 32px 20px;
  text-align: center;
  font-size: 13px;
  color: var(--cf-slate-400);
  border: 1.5px dashed var(--cf-slate-200);
  border-radius: var(--cf-radius-sm);
  margin-top: 4px;
}

/* ─── Notes section ────────────────────────────────────────────────── */
.cf-pd-notes { margin-top: 28px; }

.cf-pd-notes-textarea {
  width: 100%;
  min-height: 120px;
  resize: vertical;
  padding: 12px 14px;
  border-radius: var(--cf-radius-sm);
  border: var(--cf-input-border);
  font-family: var(--cf-font);
  font-size: 13px;
  color: var(--cf-navy);
  background: var(--cf-slate-50);
  line-height: 1.6;
  transition: border-color .12s, box-shadow .12s;
  box-sizing: border-box;
}
.cf-pd-notes-textarea:focus { outline: none; border-color: var(--cf-indigo); box-shadow: var(--cf-input-focus); }

.cf-pd-notes-foot {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}

.cf-pd-notes-save {
  padding: 7px 16px;
  border-radius: var(--cf-radius-sm);
  background: var(--cf-indigo);
  color: #fff;
  border: none;
  font-family: var(--cf-font);
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity .12s;
}
.cf-pd-notes-save:hover:not(:disabled) { opacity: .88; }
.cf-pd-notes-save:disabled { opacity: .5; cursor: not-allowed; }
.cf-pd-notes-saved-tag {
  font-size: 12px;
  color: var(--cf-emerald);
  font-weight: 500;
}

/* ─── Sidebar ──────────────────────────────────────────────────────── */
.cf-pd-sidebar {
  position: sticky;
  top: 24px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.cf-pd-info-card {
  background: var(--cf-white);
  border: 1px solid var(--cf-slate-200);
  border-radius: var(--cf-radius);
  overflow: hidden;
}

.cf-pd-info-head {
  padding: 14px 18px;
  border-bottom: 1px solid var(--cf-slate-100);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--cf-slate-400);
}

.cf-pd-info-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.cf-pd-info-item { display: flex; flex-direction: column; gap: 3px; }

.cf-pd-info-lbl {
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: .8px;
  text-transform: uppercase;
  color: var(--cf-slate-400);
}

.cf-pd-info-val {
  font-size: 13.5px;
  color: var(--cf-navy);
  word-break: break-word;
}
.cf-pd-info-val a { color: var(--cf-indigo); text-decoration: none; }
.cf-pd-info-val a:hover { text-decoration: underline; }

.cf-pd-delete-project {
  width: 100%;
  margin-top: 12px;
  padding: 9px 14px;
  border: 1px solid #FCA5A5;
  border-radius: var(--cf-radius);
  background: transparent;
  color: #DC2626;
  font-family: 'Archivo', sans-serif;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .4px;
  cursor: pointer;
  transition: background .15s, color .15s;
}
.cf-pd-delete-project:hover {
  background: #FEF2F2;
  border-color: #F87171;
}

/* ─── Loading / Error ──────────────────────────────────────────────── */
.cf-pd-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 300px;
}
.cf-pd-spinner {
  width: 28px;
  height: 28px;
  border: 2.5px solid var(--cf-slate-200);
  border-top-color: var(--cf-indigo);
  border-radius: 50%;
  animation: cf-pd-spin .8s linear infinite;
}
@keyframes cf-pd-spin { to { transform: rotate(360deg); } }
`;

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatDate( d ) {
	if ( ! d ) return '—';
	try { return new Date( d ).toLocaleDateString( 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' } ); }
	catch { return d; }
}

function isOverdue( due_date, status ) {
	if ( status === 'completed' || ! due_date ) return false;
	return new Date( due_date ) < new Date();
}

const STATUS_CYCLE = { 'in-progress': 'completed', completed: 'pending' };

function statusClass( status ) {
	if ( status === 'active' )    return 'cf-pd-status-active';
	if ( status === 'on-hold' )   return 'cf-pd-status-on-hold';
	if ( status === 'completed' ) return 'cf-pd-status-completed';
	return 'cf-pd-status-active';
}

// ── Component ──────────────────────────────────────────────────────────────────

export default function ProjectDetail( { projectId, onBack } ) {
	injectStyles( 'cf-pd-styles', CSS );

	const [ project,      setProject      ] = useState( null );
	const [ loading,      setLoading      ] = useState( true );
	const [ activeTab,    setActiveTab    ] = useState( 'overview' );
	const [ newTitle,     setNewTitle     ] = useState( '' );
	const [ newDue,       setNewDue       ] = useState( '' );
	const [ adding,       setAdding       ] = useState( false );
	const [ notes,        setNotes        ] = useState( '' );
	const [ notesSaved,   setNotesSaved   ] = useState( false );
	const [ savingNotes,  setSavingNotes  ] = useState( false );
	const [ justCompleted,   setJustCompleted   ] = useState( null );
	const [ tabsVisited,     setTabsVisited     ] = useState( { overview: true, files: false, approvals: false, messages: false } );
	const [ messagesUnread,  setMessagesUnread  ] = useState( 0 );

	const dragItem = useRef( null );
	const dragOver = useRef( null );

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const [ projectData, msgData ] = await Promise.all( [
				cfFetch( `projects/${ projectId }` ),
				cfFetch( `messages/unread-count` ).catch( () => ( { count: 0 } ) ),
			] );
			setProject( projectData.project );
			setNotes( projectData.project.description || '' );
			// Unread count scoped to this project requires the messages list endpoint,
			// but we use the global count here as a fast badge — refined on tab open.
			setMessagesUnread( msgData.count || 0 );
		} catch {}
		finally { setLoading( false ); }
	}, [ projectId ] );

	useEffect( () => { load(); }, [ load ] );

	function switchTab( tab ) {
		setActiveTab( tab );
		if ( ! tabsVisited[ tab ] ) setTabsVisited( prev => ( { ...prev, [ tab ]: true } ) );
		// Clear unread badge when Messages tab is opened.
		if ( tab === 'messages' ) setMessagesUnread( 0 );
	}

	async function handleStatusChange( e ) {
		const status = e.target.value;
		try {
			const data = await cfFetch( `projects/${ projectId }/update`, {
				method: 'POST',
				body:   JSON.stringify( { status } ),
			} );
			setProject( data.project );
		} catch ( err ) {
			const msg = err?.message || ( await err?.json?.().catch( () => null ) )?.message;
			if ( msg ) window.alert( msg );
		}
	}

	async function handleAddMilestone( e ) {
		e.preventDefault();
		if ( ! newTitle.trim() ) return;
		setAdding( true );
		try {
			const data = await cfFetch( `projects/${ projectId }/milestones`, {
				method: 'POST',
				body:   JSON.stringify( { title: newTitle.trim(), due_date: newDue } ),
			} );
			setProject( data.project );
			setNewTitle( '' );
			setNewDue( '' );
		} catch {}
		setAdding( false );
	}

	async function handleSubmitMilestone( milestone ) {
		try {
			const data = await cfFetch( `projects/${ projectId }/milestones/${ milestone.id }/submit`, {
				method: 'POST',
			} );
			setProject( data.project );
		} catch ( err ) {
			const msg = err?.message || ( await err?.json?.().catch( () => null ) )?.message;
			if ( msg ) window.alert( msg );
		}
	}

	async function handleCycleStatus( milestone ) {
		// Only in-progress and completed milestones can be cycled by the admin.
		if ( milestone.status === 'pending' || milestone.status === 'submitted' ) return;

		const nextStatus = STATUS_CYCLE[ milestone.status ] || 'pending';
		if ( nextStatus === 'completed' ) {
			setJustCompleted( milestone.id );
			setTimeout( () => setJustCompleted( null ), 500 );
		}
		try {
			const data = await cfFetch( `projects/${ projectId }/milestones/${ milestone.id }/update`, {
				method: 'POST',
				body:   JSON.stringify( { status: nextStatus } ),
			} );
			setProject( data.project );
		} catch {}
	}

	async function handleDeleteMilestone( mid ) {
		if ( ! window.confirm( 'Delete this milestone?' ) ) return;
		try {
			const data = await cfFetch( `projects/${ projectId }/milestones/${ mid }`, { method: 'DELETE' } );
			setProject( data.project );
		} catch {}
	}

	async function handleSaveNotes() {
		setSavingNotes( true );
		try {
			const data = await cfFetch( `projects/${ projectId }/update`, {
				method: 'POST',
				body:   JSON.stringify( { description: notes } ),
			} );
			setProject( data.project );
			setNotesSaved( true );
			setTimeout( () => setNotesSaved( false ), 2500 );
		} catch {}
		setSavingNotes( false );
	}

	async function handleDeleteProject() {
		if ( ! window.confirm( 'Permanently delete this project? This will also remove all milestones, messages, approvals, and payment records. This cannot be undone.' ) ) return;
		try {
			await cfFetch( `projects/${ projectId }`, { method: 'DELETE' } );
			onBack();
		} catch ( err ) {
			const msg = err?.message || ( await err?.json?.().catch( () => null ) )?.message;
			if ( msg ) window.alert( msg );
		}
	}

	function onDragStart( idx ) { dragItem.current = idx; }
	function onDragEnter( idx ) { dragOver.current = idx; }

	async function onDragEnd() {
		const milestones = [ ...( project.milestones || [] ) ];
		const dragged    = milestones.splice( dragItem.current, 1 )[ 0 ];
		milestones.splice( dragOver.current, 0, dragged );
		dragItem.current = null;
		dragOver.current = null;
		setProject( prev => ( { ...prev, milestones } ) );
		try {
			const data = await cfFetch( `projects/${ projectId }/milestones/reorder`, {
				method: 'POST',
				body:   JSON.stringify( { ordered_ids: milestones.map( m => m.id ) } ),
			} );
			setProject( data.project );
		} catch {}
	}

	// ── Loading ──────────────────────────────────────────────────────────

	if ( loading ) {
		return (
			<div className="cf-pd">
				<button className="cf-pd-back" onClick={ onBack }>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
					Back to Projects
				</button>
				<div className="cf-pd-loading"><div className="cf-pd-spinner" /></div>
			</div>
		);
	}

	if ( ! project ) {
		return (
			<div className="cf-pd">
				<button className="cf-pd-back" onClick={ onBack }>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
					Back to Projects
				</button>
				<p style={ { color: 'var(--cf-red)', fontSize: 14 } }>Project not found.</p>
			</div>
		);
	}

	const milestones = project.milestones || [];
	const total      = project.milestone_total     || 0;
	const completed  = project.milestone_completed || 0;
	const pct        = project.progress_pct        || 0;

	// ── Render ───────────────────────────────────────────────────────────

	return (
		<div className="cf-pd">

			{ /* Back */ }
			<button className="cf-pd-back" onClick={ onBack }>
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
				Back to Projects
			</button>

			{ /* ── Hero ── */ }
			<div className="cf-pd-hero">
				<div className="cf-pd-hero-left">
					<h1 className="cf-pd-project-name">{ project.name }</h1>
					<div className="cf-pd-hero-meta">
						{ project.client_name && <span>{ project.client_name }</span> }
						{ project.client_name && project.proposal_title && <span className="cf-pd-meta-dot" /> }
						{ project.proposal_title && <span>{ project.proposal_title }</span> }
						{ project.created_at && (
							<>
								<span className="cf-pd-meta-dot" />
								<span>Created { formatDate( project.created_at ) }</span>
							</>
						) }
					</div>
				</div>
				<select
					className={ `cf-pd-status-select ${ statusClass( project.status ) }` }
					value={ project.status }
					onChange={ handleStatusChange }
				>
					<option value="active">Active</option>
					<option value="on-hold">On Hold</option>
					<option
						value="completed"
						disabled={ total > 0 && completed < total }
						title={ total > 0 && completed < total ? `${ total - completed } milestone${ total - completed !== 1 ? 's' : '' } still incomplete` : undefined }
					>
						{ total > 0 && completed < total ? `Completed (${ total - completed } pending)` : 'Completed' }
					</option>
				</select>
			</div>

			{ /* ── Tab bar ── */ }
			<div className="cf-pd-tabs" role="tablist">
				<button
					role="tab"
					aria-selected={ activeTab === 'overview' }
					className={ `cf-pd-tab${ activeTab === 'overview' ? ' active' : '' }` }
					onClick={ () => switchTab( 'overview' ) }
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
					Overview
					{ total > 0 && <span className="cf-pd-tab-badge">{ completed }/{ total }</span> }
				</button>
				<button
					role="tab"
					aria-selected={ activeTab === 'files' }
					className={ `cf-pd-tab${ activeTab === 'files' ? ' active' : '' }` }
					onClick={ () => switchTab( 'files' ) }
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
					Files
				</button>
				<button
					role="tab"
					aria-selected={ activeTab === 'approvals' }
					className={ `cf-pd-tab${ activeTab === 'approvals' ? ' active' : '' }` }
					onClick={ () => switchTab( 'approvals' ) }
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
					Approvals
				</button>
				<button
					role="tab"
					aria-selected={ activeTab === 'messages' }
					className={ `cf-pd-tab${ activeTab === 'messages' ? ' active' : '' }` }
					onClick={ () => switchTab( 'messages' ) }
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
					Messages
					{ messagesUnread > 0 && <span className="cf-pd-tab-badge">{ messagesUnread }</span> }
				</button>
			</div>

			{ /* ── Body: tab panels + sidebar ── */ }
			<div className="cf-pd-body">

				{ /* ── Main: all tab panels ── */ }
				<div>

					{ /* Overview panel */ }
					<div
						role="tabpanel"
						className={ `cf-pd-tab-panel${ activeTab === 'overview' ? ' cf-pd-tab-active' : '' }` }
					>
						{ /* Progress strip */ }
						<div className="cf-pd-progress-strip">
							<div className={ `cf-pd-pct-num${ pct === 100 ? ' done' : '' }` }>{ pct }%</div>
							<div className="cf-pd-progress-right">
								<div className="cf-pd-progress-label">
									{ completed } of { total } milestone{ total !== 1 ? 's' : '' } complete
								</div>
								<div className="cf-pd-bar-track">
									<div className="cf-pd-bar-fill" style={ { width: `${ pct }%` } } />
								</div>
							</div>
						</div>

						{ /* Add form */ }
						<p className="cf-pd-section-label">Milestones</p>
						<form className="cf-pd-add-form" onSubmit={ handleAddMilestone }>
							<input
								className="cf-pd-add-input"
								placeholder="Add a milestone…"
								value={ newTitle }
								onChange={ e => setNewTitle( e.target.value ) }
							/>
							<input
								type="date"
								className="cf-pd-add-date"
								value={ newDue }
								onChange={ e => setNewDue( e.target.value ) }
							/>
							<button type="submit" className="cf-pd-add-btn" disabled={ adding || ! newTitle.trim() }>
								{ adding ? '…' : '+ Add' }
							</button>
						</form>

						{ /* Milestone list */ }
						<div className="cf-pd-ms-list">
							{ milestones.length === 0 ? (
								<div className="cf-pd-ms-empty">No milestones yet — add one above.</div>
							) : (
								milestones.map( ( m, idx ) => (
									<div
										key={ m.id }
										className="cf-pd-ms-row"
										draggable
										onDragStart={ () => onDragStart( idx ) }
										onDragEnter={ () => onDragEnter( idx ) }
										onDragEnd={ onDragEnd }
										onDragOver={ e => e.preventDefault() }
									>
										<div className="cf-pd-ms-drag"><span/><span/><span/></div>

										{ m.status === 'pending' ? (
											<button
												className="cf-pd-ms-submit-btn"
												onClick={ () => handleSubmitMilestone( m ) }
												title="Submit this milestone for client approval"
											>
												Submit
											</button>
										) : (
											<button
												className={ `cf-pd-ms-btn${ justCompleted === m.id ? ' just-completed' : '' }${ m.status === 'submitted' ? ' locked' : '' }` }
												data-status={ m.status }
												onClick={ () => handleCycleStatus( m ) }
												title={ m.status === 'submitted' ? 'Awaiting client approval' : `Status: ${ m.status }. Click to advance.` }
											>
												{ m.status === 'submitted' && (
													<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={ { pointerEvents: 'none' } }>
														<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
														<path d="M7 11V7a5 5 0 0110 0v4"/>
													</svg>
												) }
											</button>
										) }

										<span className={ `cf-pd-ms-label${ m.status === 'completed' ? ' done' : '' }` }>
											{ m.title }
										</span>

										{ m.due_date && (
											<span className={ `cf-pd-ms-due${ isOverdue( m.due_date, m.status ) ? ' overdue' : '' }` }>
												{ isOverdue( m.due_date, m.status ) ? '⚠ ' : '' }{ formatDate( m.due_date ) }
											</span>
										) }

										<button
											className="cf-pd-ms-del"
											onClick={ () => handleDeleteMilestone( m.id ) }
											title="Delete milestone"
										>
											<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
												<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
											</svg>
										</button>
									</div>
								) )
							) }
						</div>

						{ /* Notes */ }
						<div className="cf-pd-notes">
							<p className="cf-pd-section-label">Internal Notes</p>
							<textarea
								className="cf-pd-notes-textarea"
								placeholder="Add private notes about this project…"
								value={ notes }
								onChange={ e => setNotes( e.target.value ) }
							/>
							<div className="cf-pd-notes-foot">
								<button className="cf-pd-notes-save" onClick={ handleSaveNotes } disabled={ savingNotes }>
									{ savingNotes ? 'Saving…' : 'Save Notes' }
								</button>
								{ notesSaved && <span className="cf-pd-notes-saved-tag">✓ Saved</span> }
							</div>
						</div>
					</div>

					{ /* Files panel — mount on first visit, hide/show after */ }
					<div
						role="tabpanel"
						className={ `cf-pd-tab-panel${ activeTab === 'files' ? ' cf-pd-tab-active' : '' }` }
					>
						{ tabsVisited.files && <ProjectFiles projectId={ projectId } /> }
					</div>

					{ /* Approvals panel */ }
					<div
						role="tabpanel"
						className={ `cf-pd-tab-panel${ activeTab === 'approvals' ? ' cf-pd-tab-active' : '' }` }
					>
						{ tabsVisited.approvals && <ProjectApprovals projectId={ projectId } /> }
					</div>

					{ /* Messages panel */ }
					<div
						role="tabpanel"
						className={ `cf-pd-tab-panel${ activeTab === 'messages' ? ' cf-pd-tab-active' : '' }` }
					>
						{ tabsVisited.messages && (
							<ProjectMessages
								projectId={ projectId }
								onUnreadChange={ count => setMessagesUnread( count ) }
							/>
						) }
					</div>

				</div>

				{ /* ── Sidebar: project info ── */ }
				<div className="cf-pd-sidebar">
					<div className="cf-pd-info-card">
						<div className="cf-pd-info-head">Project Info</div>
						<div className="cf-pd-info-body">
							{ project.client_name && (
								<div className="cf-pd-info-item">
									<span className="cf-pd-info-lbl">Client</span>
									<span className="cf-pd-info-val">{ project.client_name }</span>
								</div>
							) }
							{ project.proposal_title && (
								<div className="cf-pd-info-item">
									<span className="cf-pd-info-lbl">Linked Proposal</span>
									<span className="cf-pd-info-val">
										{ project.proposal_token ? (
											<a href={ `/proposals/${ project.proposal_token }` } target="_blank" rel="noreferrer">
												{ project.proposal_title }
											</a>
										) : project.proposal_title }
									</span>
								</div>
							) }
							<div className="cf-pd-info-item">
								<span className="cf-pd-info-lbl">Created</span>
								<span className="cf-pd-info-val">{ formatDate( project.created_at ) }</span>
							</div>
							{ project.completed_at && (
								<div className="cf-pd-info-item">
									<span className="cf-pd-info-lbl">Completed</span>
									<span className="cf-pd-info-val">{ formatDate( project.completed_at ) }</span>
								</div>
							) }
							<div className="cf-pd-info-item">
								<span className="cf-pd-info-lbl">Status</span>
								<span className="cf-pd-info-val" style={ { textTransform: 'capitalize' } }>{ project.status.replace( '-', ' ' ) }</span>
							</div>
						</div>
					</div>

					<button className="cf-pd-delete-project" onClick={ handleDeleteProject }>
						Delete Project
					</button>
				</div>

			</div>
		</div>
	);
}
