/**
 * Business Feature Block for Gutenberg
 */

( function( blocks, element, blockEditor, components, data, apiFetch ) {
	const { registerBlockType } = blocks;
	const { createElement: el, useState, useEffect, Fragment } = element;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { 
		PanelBody, 
		TextControl, 
		SelectControl, 
		Button, 
		Spinner,
		CheckboxControl,
		Placeholder,
		Icon
	} = components;
	const { useSelect } = data;

	// Store icon as SVG
	const storeIcon = el( 'svg', { 
		width: 24, 
		height: 24, 
		viewBox: '0 0 24 24',
		fill: 'none',
		stroke: 'currentColor',
		strokeWidth: 2
	},
		el( 'path', { d: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z' } ),
		el( 'polyline', { points: '9 22 9 12 15 12 15 22' } )
	);

	/**
	 * Business Search Component
	 */
	function BusinessSearch( { onSelect, selectedIds } ) {
		const [ searchQuery, setSearchQuery ] = useState( '' );
		const [ results, setResults ] = useState( [] );
		const [ loading, setLoading ] = useState( false );
		const [ hasSearched, setHasSearched ] = useState( false );

		const doSearch = () => {
			const apiBase = bdFeatureBlock.apiUrl;
			
			if ( ! searchQuery.trim() && ! hasSearched ) {
				// Load initial businesses
				setLoading( true );
				
				// Use fetch for cross-site, apiFetch for local
				if ( bdFeatureBlock.isCrossSite ) {
					fetch( apiBase + 'feature/search?per_page=20' )
						.then( response => response.json() )
						.then( response => {
							setResults( response.businesses || [] );
							setLoading( false );
							setHasSearched( true );
						} )
						.catch( () => {
							setLoading( false );
							setHasSearched( true );
						} );
				} else {
					apiFetch( { 
						path: '/bd/v1/feature/search?per_page=20',
					} ).then( response => {
						setResults( response.businesses || [] );
						setLoading( false );
						setHasSearched( true );
					} ).catch( () => {
						setLoading( false );
						setHasSearched( true );
					} );
				}
				return;
			}

			setLoading( true );
			
			if ( bdFeatureBlock.isCrossSite ) {
				fetch( apiBase + 'feature/search?q=' + encodeURIComponent( searchQuery ) + '&per_page=20' )
					.then( response => response.json() )
					.then( response => {
						setResults( response.businesses || [] );
						setLoading( false );
					} )
					.catch( () => {
						setLoading( false );
					} );
			} else {
				apiFetch( { 
					path: '/bd/v1/feature/search?q=' + encodeURIComponent( searchQuery ) + '&per_page=20',
				} ).then( response => {
					setResults( response.businesses || [] );
					setLoading( false );
				} ).catch( () => {
					setLoading( false );
				} );
			}
		};

		// Initial load
		useEffect( () => {
			doSearch();
		}, [] );

		const isSelected = ( id ) => selectedIds.includes( id );

		return el( 'div', { className: 'bd-block-search' },
			el( 'div', { className: 'bd-block-search-bar' },
				el( TextControl, {
					placeholder: 'Search businesses...',
					value: searchQuery,
					onChange: setSearchQuery,
					onKeyPress: ( e ) => {
						if ( e.key === 'Enter' ) {
							doSearch();
						}
					}
				} ),
				el( Button, {
					variant: 'secondary',
					onClick: doSearch,
					disabled: loading
				}, 'Search' )
			),
			el( 'div', { className: 'bd-block-results' },
				loading && el( 'div', { className: 'bd-block-loading' }, el( Spinner ) ),
				! loading && results.length === 0 && hasSearched && el( 'p', { className: 'bd-block-no-results' }, 'No businesses found.' ),
				! loading && results.map( biz => 
					el( 'div', {
						key: biz.id,
						className: 'bd-block-result-item' + ( isSelected( biz.id ) ? ' is-selected' : '' ),
						onClick: () => onSelect( biz )
					},
						biz.thumbnail 
							? el( 'img', { src: biz.thumbnail, className: 'bd-block-result-thumb' } )
							: el( 'div', { className: 'bd-block-result-thumb bd-block-no-thumb' }, '🏢' ),
						el( 'div', { className: 'bd-block-result-info' },
							el( 'strong', {}, biz.title ),
							el( 'span', { className: 'bd-block-result-meta' }, 
								biz.category ? biz.category : '',
								biz.rating > 0 ? ' • ★ ' + biz.rating.toFixed(1) : ''
							),
							el( 'span', { className: 'bd-block-result-id' }, 'ID: ' + biz.id )
						),
						el( 'div', { className: 'bd-block-result-check' },
							isSelected( biz.id ) ? '✓' : ''
						)
					)
				)
			)
		);
	}

	/**
	 * Selected Businesses Component
	 */
	function SelectedBusinesses( { selected, onRemove, onReorder } ) {
		if ( selected.length === 0 ) {
			return el( 'p', { className: 'bd-block-empty' }, 'No businesses selected yet.' );
		}

		const moveUp = ( index ) => {
			if ( index === 0 ) return;
			const newOrder = [ ...selected ];
			[ newOrder[ index - 1 ], newOrder[ index ] ] = [ newOrder[ index ], newOrder[ index - 1 ] ];
			onReorder( newOrder );
		};

		const moveDown = ( index ) => {
			if ( index === selected.length - 1 ) return;
			const newOrder = [ ...selected ];
			[ newOrder[ index ], newOrder[ index + 1 ] ] = [ newOrder[ index + 1 ], newOrder[ index ] ];
			onReorder( newOrder );
		};

		return el( 'div', { className: 'bd-block-selected-list' },
			selected.map( ( biz, index ) => 
				el( 'div', { key: biz.id, className: 'bd-block-selected-item' },
					el( 'span', { className: 'bd-block-selected-title' }, biz.title ),
					el( 'div', { className: 'bd-block-selected-actions' },
						el( Button, {
							icon: 'arrow-up-alt2',
							label: 'Move up',
							onClick: () => moveUp( index ),
							disabled: index === 0,
							size: 'small'
						} ),
						el( Button, {
							icon: 'arrow-down-alt2',
							label: 'Move down',
							onClick: () => moveDown( index ),
							disabled: index === selected.length - 1,
							size: 'small'
						} ),
						el( Button, {
							icon: 'no-alt',
							label: 'Remove',
							onClick: () => onRemove( biz.id ),
							isDestructive: true,
							size: 'small'
						} )
					)
				)
			)
		);
	}

	/**
	 * Block Edit Component
	 */
	function EditBlock( props ) {
		const { attributes, setAttributes } = props;
		const { ids, layout, columns, ctaText, show } = attributes;
		const blockProps = useBlockProps();

		// Parse selected IDs and businesses
		const [ selectedBusinesses, setSelectedBusinesses ] = useState( [] );

		// Parse IDs string to array
		const selectedIds = ids ? ids.split( ',' ).map( id => parseInt( id.trim(), 10 ) ).filter( id => id > 0 ) : [];

		// Load business details for selected IDs on mount
		useEffect( () => {
			if ( selectedIds.length > 0 && selectedBusinesses.length === 0 ) {
				const apiBase = bdFeatureBlock.apiUrl;
				
				if ( bdFeatureBlock.isCrossSite ) {
					fetch( apiBase + 'feature?ids=' + selectedIds.join( ',' ) )
						.then( response => response.json() )
						.then( response => {
							if ( response.businesses ) {
								const ordered = selectedIds.map( id => 
									response.businesses.find( b => b.id === id )
								).filter( Boolean ).map( b => ( { id: b.id, title: b.title } ) );
								setSelectedBusinesses( ordered );
							}
						} )
						.catch( () => {} );
				} else {
					apiFetch( {
						path: '/bd/v1/feature?ids=' + selectedIds.join( ',' )
					} ).then( response => {
						if ( response.businesses ) {
							const ordered = selectedIds.map( id => 
								response.businesses.find( b => b.id === id )
							).filter( Boolean ).map( b => ( { id: b.id, title: b.title } ) );
							setSelectedBusinesses( ordered );
						}
					} ).catch( () => {} );
				}
			}
		}, [] );

		// Build simple preview (works cross-site without block renderer)
		const buildPreview = () => {
			if ( selectedBusinesses.length === 0 ) {
				return el( 'div', { className: 'bd-block-preview-empty' },
					el( 'p', {}, 'Select businesses from the sidebar to preview them here.' )
				);
			}

			return el( 'div', { className: 'bd-block-preview-list bd-preview-' + layout },
				el( 'div', { className: 'bd-preview-header' },
					el( 'span', { className: 'bd-preview-count' }, selectedBusinesses.length + ' business' + ( selectedBusinesses.length > 1 ? 'es' : '' ) + ' selected' ),
					el( 'span', { className: 'bd-preview-layout' }, 'Layout: ' + layout + ( layout === 'card' ? ' (' + columns + ' col)' : '' ) )
				),
				el( 'div', { className: 'bd-preview-items' },
					selectedBusinesses.map( ( biz, index ) =>
						el( 'div', { key: biz.id, className: 'bd-preview-item' },
							el( 'span', { className: 'bd-preview-num' }, ( index + 1 ) + '.' ),
							el( 'span', { className: 'bd-preview-title' }, biz.title ),
							el( 'span', { className: 'bd-preview-id' }, 'ID: ' + biz.id )
						)
					)
				),
				el( 'p', { className: 'bd-preview-note' }, '✓ Preview will render fully on the frontend' )
			);
		};

		// Handle business selection
		const handleSelect = ( biz ) => {
			const currentIds = [ ...selectedIds ];
			const currentBiz = [ ...selectedBusinesses ];
			const index = currentIds.indexOf( biz.id );

			if ( index > -1 ) {
				// Remove
				currentIds.splice( index, 1 );
				currentBiz.splice( index, 1 );
			} else {
				// Add
				currentIds.push( biz.id );
				currentBiz.push( { id: biz.id, title: biz.title } );
			}

			setSelectedBusinesses( currentBiz );
			setAttributes( { ids: currentIds.join( ',' ) } );
		};

		// Handle remove
		const handleRemove = ( id ) => {
			const newIds = selectedIds.filter( i => i !== id );
			const newBiz = selectedBusinesses.filter( b => b.id !== id );
			setSelectedBusinesses( newBiz );
			setAttributes( { ids: newIds.join( ',' ) } );
		};

		// Handle reorder
		const handleReorder = ( newOrder ) => {
			setSelectedBusinesses( newOrder );
			setAttributes( { ids: newOrder.map( b => b.id ).join( ',' ) } );
		};

		// Parse show options
		const showOptions = show.split( ',' ).map( s => s.trim() );
		const toggleShow = ( option ) => {
			let newShow = [ ...showOptions ];
			if ( newShow.includes( option ) ) {
				newShow = newShow.filter( s => s !== option );
			} else {
				newShow.push( option );
			}
			setAttributes( { show: newShow.join( ',' ) } );
		};

		return el( Fragment, {},
			// Sidebar Controls
			el( InspectorControls, {},
				// Selected Businesses Panel
				el( PanelBody, { title: 'Selected Businesses (' + selectedBusinesses.length + ')', initialOpen: true },
					el( SelectedBusinesses, {
						selected: selectedBusinesses,
						onRemove: handleRemove,
						onReorder: handleReorder
					} )
				),

				// Search Panel
				el( PanelBody, { title: 'Search Businesses', initialOpen: true },
					el( BusinessSearch, {
						onSelect: handleSelect,
						selectedIds: selectedIds
					} )
				),

				// Layout Options Panel
				el( PanelBody, { title: 'Layout Options', initialOpen: false },
					el( SelectControl, {
						label: 'Layout',
						value: layout,
						options: [
							{ label: 'Card Grid', value: 'card' },
							{ label: 'Compact List', value: 'list' },
							{ label: 'Inline', value: 'inline' },
							{ label: 'Mini Links', value: 'mini' }
						],
						onChange: ( val ) => setAttributes( { layout: val } )
					} ),
					layout === 'card' && el( SelectControl, {
						label: 'Columns',
						value: columns,
						options: [
							{ label: '1 Column', value: '1' },
							{ label: '2 Columns', value: '2' },
							{ label: '3 Columns', value: '3' },
							{ label: '4 Columns', value: '4' }
						],
						onChange: ( val ) => setAttributes( { columns: val } )
					} ),
					el( TextControl, {
						label: 'CTA Button Text',
						value: ctaText,
						onChange: ( val ) => setAttributes( { ctaText: val } )
					} )
				),

				// Display Options Panel
				el( PanelBody, { title: 'Display Options', initialOpen: false },
					el( CheckboxControl, {
						label: 'Show Image',
						checked: showOptions.includes( 'image' ),
						onChange: () => toggleShow( 'image' )
					} ),
					el( CheckboxControl, {
						label: 'Show Title',
						checked: showOptions.includes( 'title' ),
						onChange: () => toggleShow( 'title' )
					} ),
					el( CheckboxControl, {
						label: 'Show Rating',
						checked: showOptions.includes( 'rating' ),
						onChange: () => toggleShow( 'rating' )
					} ),
					el( CheckboxControl, {
						label: 'Show Excerpt',
						checked: showOptions.includes( 'excerpt' ),
						onChange: () => toggleShow( 'excerpt' )
					} ),
					el( CheckboxControl, {
						label: 'Show Category',
						checked: showOptions.includes( 'category' ),
						onChange: () => toggleShow( 'category' )
					} ),
					el( CheckboxControl, {
						label: 'Show CTA Button',
						checked: showOptions.includes( 'cta' ),
						onChange: () => toggleShow( 'cta' )
					} )
				)
			),

			// Block Content
			el( 'div', blockProps,
				! ids 
					? el( Placeholder, {
						icon: storeIcon,
						label: 'Business Feature',
						instructions: 'Search and select businesses from the sidebar panel to display them here.'
					},
						el( 'p', { style: { margin: 0, fontSize: '13px', color: '#757575' } }, 
							'Open the block settings in the right sidebar to get started.'
						)
					)
					: buildPreview()
			)
		);
	}

	/**
	 * Register the block
	 */
	registerBlockType( 'bd/feature', {
		title: 'Business Feature',
		description: 'Embed featured businesses from the directory.',
		category: 'embed',
		icon: storeIcon,
		keywords: [ 'business', 'directory', 'feature', 'embed', 'listing' ],
		supports: {
			html: false,
			align: [ 'wide', 'full' ]
		},
		edit: EditBlock,
		save: function() {
			// Dynamic block - rendered on server
			return null;
		}
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.apiFetch
);
