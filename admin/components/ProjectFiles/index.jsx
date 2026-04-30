import { useState, useEffect, useRef } from '@wordpress/element';

function injectStyles( id, css ) {
	if ( document.getElementById( id ) ) return;
	const s = document.createElement( 'style' );
	s.id = id;
	s.textContent = css;
	document.head.appendChild( s );
}

const CSS = `
/* ── Section shell ─────────────────────────────────────────────── */
.cf-pf {
	margin-top: 36px;
}

.cf-pf-header {
	display: flex;
	align-items: center;
	gap: 10px;
	padding-bottom: 14px;
	border-bottom: 1.5px solid var(--cf-slate-100);
	margin-bottom: 20px;
}

.cf-pf-header-icon {
	width: 32px;
	height: 32px;
	background: var(--cf-slate-100);
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.cf-pf-header-icon svg {
	width: 15px;
	height: 15px;
	stroke: var(--cf-slate-500);
	stroke-width: 2;
}

.cf-pf-title {
	font-family: var(--cf-font-display);
	font-size: 17px;
	font-weight: 600;
	color: var(--cf-navy);
	letter-spacing: -.2px;
}

.cf-pf-count {
	margin-left: auto;
	font-size: 12px;
	font-weight: 600;
	color: var(--cf-slate-400);
	background: var(--cf-slate-100);
	padding: 2px 8px;
	border-radius: 999px;
}

/* ── Error banner ──────────────────────────────────────────────── */
.cf-pf-error {
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
	margin-bottom: 16px;
	animation: cf-pf-fade-in .2s ease both;
}
.cf-pf-error svg { width: 15px; height: 15px; stroke: currentColor; flex-shrink: 0; }
.cf-pf-error-dismiss {
	margin-left: auto;
	background: none;
	border: none;
	cursor: pointer;
	color: var(--cf-red);
	padding: 0;
	display: flex;
	align-items: center;
	opacity: .7;
	transition: opacity .15s;
}
.cf-pf-error-dismiss:hover { opacity: 1; }
.cf-pf-error-dismiss svg { width: 13px; height: 13px; }

/* ── Upload zone ───────────────────────────────────────────────── */
.cf-pf-zone {
	border: 2px dashed var(--cf-slate-200);
	border-radius: var(--cf-radius);
	padding: 32px 24px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 10px;
	cursor: pointer;
	transition: border-color .2s, background .2s;
	background: var(--cf-white);
	margin-bottom: 20px;
	position: relative;
	overflow: hidden;
	min-height: 140px;
}
.cf-pf-zone:hover {
	border-color: var(--cf-slate-300);
	background: var(--cf-slate-50);
}
.cf-pf-zone.drag-over {
	border-color: var(--cf-indigo);
	background: var(--cf-indigo-bg);
}
.cf-pf-zone.uploading {
	cursor: default;
	pointer-events: none;
}

.cf-pf-zone-icon {
	width: 44px;
	height: 44px;
	background: var(--cf-slate-100);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: background .2s, transform .2s;
}
.cf-pf-zone:hover .cf-pf-zone-icon,
.cf-pf-zone.drag-over .cf-pf-zone-icon {
	background: var(--cf-indigo-bg);
	transform: translateY(-2px);
}
.cf-pf-zone.drag-over .cf-pf-zone-icon {
	background: rgba(99,102,241,.15);
}
.cf-pf-zone-icon svg {
	width: 20px;
	height: 20px;
	stroke: var(--cf-slate-400);
	stroke-width: 1.75;
	transition: stroke .2s;
}
.cf-pf-zone:hover .cf-pf-zone-icon svg,
.cf-pf-zone.drag-over .cf-pf-zone-icon svg {
	stroke: var(--cf-indigo);
}

.cf-pf-zone-text {
	font-size: 13.5px;
	font-weight: 600;
	color: var(--cf-slate-700);
	text-align: center;
}
.cf-pf-zone.drag-over .cf-pf-zone-text { color: var(--cf-indigo); }

.cf-pf-zone-hint {
	font-size: 12px;
	color: var(--cf-slate-400);
	text-align: center;
}

/* Uploading state */
.cf-pf-uploading-overlay {
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 12px;
	background: rgba(255,255,255,.92);
	backdrop-filter: blur(2px);
}
.cf-pf-upload-spinner {
	width: 28px;
	height: 28px;
	border: 2.5px solid var(--cf-slate-200);
	border-top-color: var(--cf-indigo);
	border-radius: 50%;
	animation: cf-pf-spin .7s linear infinite;
}
.cf-pf-uploading-label {
	font-size: 13px;
	font-weight: 500;
	color: var(--cf-slate-600);
}

/* ── File list ─────────────────────────────────────────────────── */
.cf-pf-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.cf-pf-row {
	display: grid;
	grid-template-columns: 36px 1fr auto auto auto;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	background: var(--cf-white);
	border: 1px solid var(--cf-slate-200);
	border-radius: var(--cf-radius-sm);
	transition: border-color .15s, box-shadow .15s, transform .12s;
	animation: cf-pf-fade-in .3s ease both;
}
.cf-pf-row:hover {
	border-color: var(--cf-slate-300);
	box-shadow: var(--cf-shadow);
	transform: translateY(-1px);
}
.cf-pf-row:hover .cf-pf-delete { opacity: 1; }

/* File type icon cell */
.cf-pf-file-icon {
	width: 36px;
	height: 36px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.cf-pf-file-icon svg {
	width: 18px;
	height: 18px;
	stroke-width: 1.75;
}
.cf-pf-file-icon.image  { background: var(--cf-emerald-bg); }
.cf-pf-file-icon.image  svg { stroke: var(--cf-emerald); }
.cf-pf-file-icon.pdf    { background: var(--cf-red-bg); }
.cf-pf-file-icon.pdf    svg { stroke: var(--cf-red); }
.cf-pf-file-icon.archive { background: var(--cf-amber-bg); }
.cf-pf-file-icon.archive svg { stroke: var(--cf-amber); }
.cf-pf-file-icon.doc    { background: var(--cf-indigo-bg); }
.cf-pf-file-icon.doc    svg { stroke: var(--cf-indigo); }
.cf-pf-file-icon.generic { background: var(--cf-slate-100); }
.cf-pf-file-icon.generic svg { stroke: var(--cf-slate-500); }

/* Info cell */
.cf-pf-info {}
.cf-pf-name {
	font-size: 13.5px;
	font-weight: 600;
	color: var(--cf-slate-800);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 320px;
}
.cf-pf-meta {
	font-size: 11.5px;
	color: var(--cf-slate-400);
	margin-top: 2px;
}

/* Action cells */
.cf-pf-download {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 7px 13px;
	border: 1.5px solid var(--cf-indigo);
	border-radius: var(--cf-radius-sm);
	font-size: 12.5px;
	font-weight: 600;
	font-family: var(--cf-font);
	color: var(--cf-indigo);
	background: transparent;
	text-decoration: none;
	transition: background .15s, color .15s;
	white-space: nowrap;
	cursor: pointer;
}
.cf-pf-download:hover { background: var(--cf-indigo-bg); }
.cf-pf-download svg { width: 12px; height: 12px; stroke: currentColor; stroke-width: 2.5; flex-shrink: 0; }

.cf-pf-delete {
	width: 30px;
	height: 30px;
	border: none;
	background: transparent;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	color: var(--cf-slate-400);
	opacity: 0;
	transition: opacity .15s, background .12s, color .12s;
}
.cf-pf-delete:hover { background: var(--cf-red-bg); color: var(--cf-red); }
.cf-pf-delete svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; }

/* ── Empty state ───────────────────────────────────────────────── */
.cf-pf-empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	padding: 40px 20px;
	text-align: center;
}
.cf-pf-empty-icon {
	width: 64px;
	height: 64px;
	background: var(--cf-slate-100);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 4px;
}
.cf-pf-empty-icon svg { width: 28px; height: 28px; stroke: var(--cf-slate-300); stroke-width: 1.5; }
.cf-pf-empty h4 {
	font-size: 14.5px;
	font-weight: 700;
	color: var(--cf-slate-600);
	margin: 0;
}
.cf-pf-empty p {
	font-size: 13px;
	color: var(--cf-slate-400);
	margin: 0;
}

/* ── Animations ────────────────────────────────────────────────── */
@keyframes cf-pf-fade-in {
	from { opacity: 0; transform: translateY(5px); }
	to   { opacity: 1; transform: translateY(0); }
}
@keyframes cf-pf-spin { to { transform: rotate(360deg); } }

/* ── Mobile ────────────────────────────────────────────────────── */
@media (max-width: 600px) {
	.cf-pf-row {
		grid-template-columns: 36px 1fr;
		grid-template-rows: auto auto;
	}
	.cf-pf-download,
	.cf-pf-delete {
		grid-column: 2;
	}
	.cf-pf-delete { opacity: 1; }
	.cf-pf-name { max-width: 220px; }
}
`;

// ── File type helpers ─────────────────────────────────────────────────────────

function getFileType( mime ) {
	if ( ! mime ) return 'generic';
	if ( mime.startsWith( 'image/' ) ) return 'image';
	if ( mime === 'application/pdf' ) return 'pdf';
	if ( [ 'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-tar', 'application/gzip' ].includes( mime ) ) return 'archive';
	if ( mime.includes( 'word' ) || mime.includes( 'document' ) || mime.includes( 'text/' ) ) return 'doc';
	return 'generic';
}

function FileTypeIcon( { mime } ) {
	const type = getFileType( mime );

	if ( type === 'image' ) {
		return (
			<div className="cf-pf-file-icon image">
				<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
					<rect x="3" y="3" width="18" height="18" rx="2"/>
					<circle cx="8.5" cy="8.5" r="1.5"/>
					<polyline points="21 15 16 10 5 21"/>
				</svg>
			</div>
		);
	}

	if ( type === 'pdf' ) {
		return (
			<div className="cf-pf-file-icon pdf">
				<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
					<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
					<polyline points="14 2 14 8 20 8"/>
					<line x1="9" y1="13" x2="15" y2="13"/>
					<line x1="9" y1="17" x2="12" y2="17"/>
				</svg>
			</div>
		);
	}

	if ( type === 'archive' ) {
		return (
			<div className="cf-pf-file-icon archive">
				<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
					<polyline points="21 8 21 21 3 21 3 8"/>
					<rect x="1" y="3" width="22" height="5"/>
					<line x1="10" y1="12" x2="14" y2="12"/>
				</svg>
			</div>
		);
	}

	if ( type === 'doc' ) {
		return (
			<div className="cf-pf-file-icon doc">
				<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
					<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
					<polyline points="14 2 14 8 20 8"/>
					<line x1="16" y1="13" x2="8" y2="13"/>
					<line x1="16" y1="17" x2="8" y2="17"/>
					<polyline points="10 9 9 9 8 9"/>
				</svg>
			</div>
		);
	}

	return (
		<div className="cf-pf-file-icon generic">
			<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
				<path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
				<polyline points="13 2 13 9 20 9"/>
			</svg>
		</div>
	);
}

function formatDate( dateStr ) {
	if ( ! dateStr ) return '';
	try {
		return new Date( dateStr ).toLocaleDateString( 'en-GB', { day: 'numeric', month: 'short', year: 'numeric' } );
	} catch {
		return dateStr;
	}
}

// ── Main component ────────────────────────────────────────────────────────────

export default function ProjectFiles( { projectId } ) {
	injectStyles( 'cf-pf-styles', CSS );

	const { apiUrl, nonce } = window.cfData || {};

	const [ files,     setFiles     ] = useState( [] );
	const [ loading,   setLoading   ] = useState( true );
	const [ uploading, setUploading ] = useState( false );
	const [ dragOver,  setDragOver  ] = useState( false );
	const [ error,     setError     ] = useState( null );

	const fileInputRef = useRef( null );
	const dragCounter  = useRef( 0 );

	// ── Fetch files on mount ──────────────────────────────────────
	useEffect( () => {
		fetch( `${ apiUrl }projects/${ projectId }/files`, {
			headers: { 'X-WP-Nonce': nonce },
		} )
			.then( r => r.json() )
			.then( data => setFiles( data.files || [] ) )
			.catch( () => setError( 'Failed to load files.' ) )
			.finally( () => setLoading( false ) );
	}, [ projectId ] );

	// ── Upload handler ────────────────────────────────────────────
	async function uploadFile( file ) {
		if ( ! file ) return;
		setUploading( true );
		setError( null );

		const form = new FormData();
		form.append( 'file', file );

		try {
			const res  = await fetch( `${ apiUrl }projects/${ projectId }/files`, {
				method:  'POST',
				headers: { 'X-WP-Nonce': nonce },
				body:    form,
			} );
			const data = await res.json();
			if ( ! res.ok ) throw new Error( data.message || `Upload failed (${ res.status })` );
			setFiles( data.files || [] );
		} catch ( err ) {
			setError( err.message || 'Upload failed. Please try again.' );
		} finally {
			setUploading( false );
		}
	}

	// ── Delete handler ────────────────────────────────────────────
	async function deleteFile( fileId ) {
		try {
			const res = await fetch( `${ apiUrl }projects/${ projectId }/files/${ fileId }`, {
				method:  'DELETE',
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
			} );
			if ( ! res.ok ) throw new Error( 'Delete failed.' );
			setFiles( prev => prev.filter( f => f.id !== fileId ) );
		} catch ( err ) {
			setError( err.message );
		}
	}

	// ── Drag events ───────────────────────────────────────────────
	function onDragEnter( e ) {
		e.preventDefault();
		dragCounter.current++;
		setDragOver( true );
	}
	function onDragLeave( e ) {
		e.preventDefault();
		dragCounter.current--;
		if ( dragCounter.current === 0 ) setDragOver( false );
	}
	function onDragOver( e ) { e.preventDefault(); }
	function onDrop( e ) {
		e.preventDefault();
		dragCounter.current = 0;
		setDragOver( false );
		const file = e.dataTransfer.files[ 0 ];
		if ( file ) uploadFile( file );
	}

	function onInputChange( e ) {
		const file = e.target.files[ 0 ];
		if ( file ) {
			uploadFile( file );
			e.target.value = '';
		}
	}

	const zoneClass = [
		'cf-pf-zone',
		dragOver  ? 'drag-over'  : '',
		uploading ? 'uploading' : '',
	].filter( Boolean ).join( ' ' );

	return (
		<div className="cf-pf">
			<div className="cf-pf-header">
				<div className="cf-pf-header-icon">
					<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
						<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>
					</svg>
				</div>
				<span className="cf-pf-title">Files</span>
				{ files.length > 0 && (
					<span className="cf-pf-count">{ files.length } { files.length === 1 ? 'file' : 'files' }</span>
				) }
			</div>

			{ error && (
				<div className="cf-pf-error">
					<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
						<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
					</svg>
					{ error }
					<button type="button" className="cf-pf-error-dismiss" onClick={ () => setError( null ) } aria-label="Dismiss">
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5">
							<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
						</svg>
					</button>
				</div>
			) }

			{ /* Upload zone */ }
			<div
				className={ zoneClass }
				onDragEnter={ onDragEnter }
				onDragLeave={ onDragLeave }
				onDragOver={ onDragOver }
				onDrop={ onDrop }
				onClick={ () => ! uploading && fileInputRef.current?.click() }
				role="button"
				tabIndex={ 0 }
				aria-label="Upload file"
				onKeyDown={ e => e.key === 'Enter' && fileInputRef.current?.click() }
			>
				{ uploading ? (
					<div className="cf-pf-uploading-overlay">
						<div className="cf-pf-upload-spinner" />
						<span className="cf-pf-uploading-label">Uploading…</span>
					</div>
				) : (
					<>
						<div className="cf-pf-zone-icon">
							<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
								<polyline points="16 16 12 12 8 16"/>
								<line x1="12" y1="12" x2="12" y2="21"/>
								<path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
							</svg>
						</div>
						<span className="cf-pf-zone-text">
							{ dragOver ? 'Release to upload' : 'Drop files here or click to upload' }
						</span>
						<span className="cf-pf-zone-hint">Any file type · Max 1 GB total storage</span>
					</>
				) }
				<input
					ref={ fileInputRef }
					type="file"
					style={ { display: 'none' } }
					onChange={ onInputChange }
				/>
			</div>

			{ /* File list */ }
			{ loading ? null : files.length === 0 ? (
				<div className="cf-pf-empty">
					<div className="cf-pf-empty-icon">
						<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
							<polyline points="16 16 12 12 8 16"/>
							<line x1="12" y1="12" x2="12" y2="21"/>
							<path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
						</svg>
					</div>
					<h4>No files yet</h4>
					<p>Upload your first deliverable above</p>
				</div>
			) : (
				<div className="cf-pf-list">
					{ files.map( ( file, idx ) => (
						<div
							key={ file.id }
							className="cf-pf-row"
							style={ { animationDelay: `${ idx * 0.04 }s` } }
						>
							<FileTypeIcon mime={ file.file_mime } />

							<div className="cf-pf-info">
								<div className="cf-pf-name" title={ file.file_name }>{ file.file_name }</div>
								<div className="cf-pf-meta">{ file.file_size_human } · { formatDate( file.created_at ) }</div>
							</div>

							<a
								className="cf-pf-download"
								href={ `${ apiUrl }projects/${ projectId }/files/${ file.id }/download?_wpnonce=${ nonce }` }
								download={ file.file_name }
							>
								<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
									<polyline points="8 17 12 21 16 17"/>
									<line x1="12" y1="12" x2="12" y2="21"/>
									<path d="M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 103 16.11"/>
								</svg>
								Download
							</a>

							<button
								type="button"
								className="cf-pf-delete"
								title="Delete file"
								onClick={ () => deleteFile( file.id ) }
								aria-label={ `Delete ${ file.file_name }` }
							>
								<svg viewBox="0 0 24 24" fill="none" strokeLinecap="round" strokeLinejoin="round">
									<polyline points="3 6 5 6 21 6"/>
									<path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>
									<path d="M10 11v6M14 11v6"/>
									<path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/>
								</svg>
							</button>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
