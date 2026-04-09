/**
 * BD Grant Access Modal
 *
 * Shared modal for the three entry points that let a directory manager
 * grant a business owner (or marketing contact) direct access to a listing:
 *   1. "Business Access" meta box on the bd_business edit screen
 *   2. "Grant Access" row action on the businesses list table
 *   3. 🔑 Grant Access node in the frontend admin bar
 *
 * All three places dispatch to this single modal by triggering any element
 * with class `bd-grant-access-trigger`. The business ID is resolved in
 * priority order:
 *   a. element.dataset.businessId
 *   b. window.bdGrantAccess.currentBusinessId (set by GrantAccessToolbar)
 *
 * Uses wp.apiFetch so the REST nonce is handled automatically on admin AND
 * frontend contexts. No jQuery dependency.
 *
 * @package BusinessDirectory
 * @since   0.1.8
 */
( function () {
	'use strict';

	if ( typeof window.wp === 'undefined' || ! window.wp.apiFetch ) {
		// wp-api-fetch is a declared dep so this shouldn't happen, but fail
		// loudly in the console if somehow the script loads standalone.
		// eslint-disable-next-line no-console
		console.warn( '[bd-grant-access] wp.apiFetch is unavailable; modal disabled.' );
		return;
	}

	var config = window.bdGrantAccess || {};
	var i18n   = config.i18n || {};
	var apiFetch = window.wp.apiFetch;

	// IMPORTANT: do NOT register our own rootURL or nonce middleware here.
	//
	// WordPress core attaches two inline scripts to `wp-api-fetch` (see
	// wp-includes/script-loader.php::wp_default_packages_inline_scripts())
	// that pre-register BOTH middlewares with the site's REST root and a
	// wp_rest nonce. Those run on admin AND frontend pages whenever
	// wp-api-fetch is enqueued as a dep — which ours always is.
	//
	// Registering a second rootURL middleware on top would break every call:
	// `registerMiddleware` unshifts the new one so it runs first, but core's
	// middleware then runs SECOND and overwrites the URL (it doesn't clear
	// `path` after setting `url`), producing the wrong host-relative path.
	//
	// We just use absolute namespace paths ('/bd/v1/claims/grant', etc.) and
	// let core's pre-registered middleware handle URL construction and
	// nonce headers. Core's auto-retry on rest_cookie_invalid_nonce also
	// depends on `apiFetch.nonceMiddleware` being the core one — so leave
	// it alone.

	var modalEl  = null;
	var formEl   = null;
	var listEl   = null;
	var toastEl  = null;
	var state    = {
		businessId: null,
		submitting: false,
	};

	// ---------------------------------------------------------------------
	// Markup
	// ---------------------------------------------------------------------

	function buildModal() {
		var wrap = document.createElement( 'div' );
		wrap.className = 'bd-ga-modal';
		wrap.setAttribute( 'role', 'dialog' );
		wrap.setAttribute( 'aria-modal', 'true' );
		wrap.setAttribute( 'aria-labelledby', 'bd-ga-modal-title' );
		wrap.hidden = true;

		wrap.innerHTML =
			'<div class="bd-ga-modal__backdrop" data-bd-ga-close></div>' +
			'<div class="bd-ga-modal__dialog" tabindex="-1">' +
			'  <button type="button" class="bd-ga-modal__close" data-bd-ga-close aria-label="Close">×</button>' +
			'  <h2 id="bd-ga-modal-title" class="bd-ga-modal__title"></h2>' +
			'  <p class="bd-ga-modal__subtitle"></p>' +
			'  <section class="bd-ga-modal__current">' +
			'    <h3 class="bd-ga-modal__section-title"></h3>' +
			'    <ul class="bd-ga-modal__user-list" aria-live="polite"></ul>' +
			'  </section>' +
			'  <form class="bd-ga-modal__form" novalidate>' +
			'    <label class="bd-ga-field">' +
			'      <span class="bd-ga-field__label bd-ga-required"></span>' +
			'      <input type="email" name="email" autocomplete="email" required>' +
			'      <span class="bd-ga-field__help"></span>' +
			'    </label>' +
			'    <label class="bd-ga-field">' +
			'      <span class="bd-ga-field__label"></span>' +
			'      <input type="text" name="name" autocomplete="name">' +
			'      <span class="bd-ga-field__help"></span>' +
			'    </label>' +
			'    <label class="bd-ga-field">' +
			'      <span class="bd-ga-field__label"></span>' +
			'      <input type="tel" name="phone" autocomplete="tel">' +
			'    </label>' +
			'    <fieldset class="bd-ga-field bd-ga-field--role">' +
			'      <legend class="bd-ga-field__label"></legend>' +
			'      <label><input type="radio" name="relationship" value="owner" checked> <span data-bd-ga-i18n="owner"></span></label>' +
			'      <label><input type="radio" name="relationship" value="manager"> <span data-bd-ga-i18n="manager"></span></label>' +
			'      <label><input type="radio" name="relationship" value="staff"> <span data-bd-ga-i18n="staff"></span></label>' +
			'    </fieldset>' +
			'    <p class="bd-ga-modal__warning" hidden></p>' +
			'    <label class="bd-ga-field">' +
			'      <span class="bd-ga-field__label"></span>' +
			'      <textarea name="note" rows="2"></textarea>' +
			'      <span class="bd-ga-field__help"></span>' +
			'    </label>' +
			'    <label class="bd-ga-field bd-ga-field--checkbox">' +
			'      <input type="checkbox" name="send_welcome" checked>' +
			'      <span></span>' +
			'    </label>' +
			'    <div class="bd-ga-modal__actions">' +
			'      <button type="button" class="button bd-ga-btn-cancel" data-bd-ga-close></button>' +
			'      <button type="submit" class="button button-primary bd-ga-btn-submit"></button>' +
			'    </div>' +
			'  </form>' +
			'  <div class="bd-ga-modal__toast" role="status" aria-live="polite" hidden></div>' +
			'</div>';

		document.body.appendChild( wrap );
		return wrap;
	}

	function applyI18n( root ) {
		root.querySelector( '.bd-ga-modal__title' ).textContent    = i18n.title || 'Grant Business Access';
		root.querySelector( '.bd-ga-modal__subtitle' ).textContent = i18n.subtitle || '';
		root.querySelector( '.bd-ga-modal__section-title' ).textContent = i18n.currentAccess || 'People with access';

		var labels = root.querySelectorAll( '.bd-ga-field__label' );
		var helps  = root.querySelectorAll( '.bd-ga-field__help' );
		// Label order matches the DOM order of fields above.
		labels[ 0 ].textContent = i18n.email || 'Email';
		labels[ 1 ].textContent = i18n.fullName || 'Full name';
		labels[ 2 ].textContent = i18n.phone || 'Phone (optional)';
		labels[ 3 ].textContent = i18n.role || 'Role';
		labels[ 4 ].textContent = i18n.note || 'Internal note (optional)';

		helps[ 0 ].textContent  = i18n.emailHelp || '';
		helps[ 1 ].textContent  = i18n.fullNameHelp || '';
		helps[ 2 ].textContent  = i18n.noteHelp || '';

		root.querySelectorAll( '[data-bd-ga-i18n]' ).forEach( function ( el ) {
			var key = el.getAttribute( 'data-bd-ga-i18n' );
			el.textContent = i18n[ key ] || key;
		} );

		root.querySelector( '.bd-ga-field--checkbox span' ).textContent = i18n.sendWelcome || 'Email the user their login details';
		root.querySelector( '.bd-ga-btn-cancel' ).textContent = i18n.cancel || 'Cancel';
		root.querySelector( '.bd-ga-btn-submit' ).textContent = i18n.submit || 'Grant Access';
	}

	function ensureModal() {
		if ( modalEl ) {
			return modalEl;
		}
		modalEl  = buildModal();
		applyI18n( modalEl );

		formEl  = modalEl.querySelector( '.bd-ga-modal__form' );
		listEl  = modalEl.querySelector( '.bd-ga-modal__user-list' );
		toastEl = modalEl.querySelector( '.bd-ga-modal__toast' );

		// Close handlers.
		modalEl.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-bd-ga-close]' ) ) {
				e.preventDefault();
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! modalEl.hidden ) {
				close();
			}
		} );

		// Submit.
		formEl.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			submitGrant();
		} );

		// Delegated revoke buttons inside the list.
		listEl.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.bd-ga-revoke' );
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			revoke( parseInt( btn.getAttribute( 'data-claim-id' ), 10 ) );
		} );

		// Warn when an owner already exists and role is set to Owner.
		// formEl.elements.relationship is a RadioNodeList with 3 radios.
		var roleRadios = formEl.elements.namedItem( 'relationship' );
		Array.prototype.forEach.call( roleRadios, function ( radio ) {
			radio.addEventListener( 'change', updateOwnerWarning );
		} );

		return modalEl;
	}

	/**
	 * Safely read a field value from the form.
	 * Uses `form.elements` to avoid reserved-property collisions: on an
	 * HTMLFormElement, `form.name` returns the form's name attribute, NOT
	 * the input with name="name". Accessing via form.elements is reliable
	 * for every field.
	 */
	function field( name ) {
		return formEl.elements.namedItem( name );
	}

	// ---------------------------------------------------------------------
	// Open / close
	// ---------------------------------------------------------------------

	function open( businessId ) {
		ensureModal();
		state.businessId = businessId;
		formEl.reset();
		field( 'send_welcome' ).checked = true;
		clearToast();
		hideWarning();
		modalEl.hidden = false;
		document.body.classList.add( 'bd-ga-modal-open' );
		// Focus the email field after the browser paints.
		requestAnimationFrame( function () {
			field( 'email' ).focus();
		} );
		loadCurrentUsers();
	}

	function close() {
		if ( ! modalEl || modalEl.hidden ) {
			return;
		}
		modalEl.hidden = true;
		document.body.classList.remove( 'bd-ga-modal-open' );
		state.businessId = null;
	}

	// ---------------------------------------------------------------------
	// REST calls
	// ---------------------------------------------------------------------

	function loadCurrentUsers() {
		if ( ! state.businessId ) {
			return;
		}
		listEl.innerHTML = '<li class="bd-ga-loading">' + escapeHtml( i18n.loading || 'Loading…' ) + '</li>';

		apiFetch( {
			path: '/bd/v1/businesses/' + state.businessId + '/access',
			method: 'GET',
		} )
			.then( function ( res ) {
				renderUserList( res && res.users ? res.users : [] );
				updateOwnerWarning();
			} )
			.catch( function () {
				listEl.innerHTML = '<li class="bd-ga-error">' + escapeHtml( i18n.errorGeneric || 'Error' ) + '</li>';
			} );
	}

	function submitGrant() {
		if ( state.submitting ) {
			return;
		}

		var email = ( field( 'email' ).value || '' ).trim();
		if ( ! email || ! /^.+@.+\..+$/.test( email ) ) {
			showToast( i18n.errorEmail || 'Please enter a valid email', 'error' );
			field( 'email' ).focus();
			return;
		}

		// field('relationship') returns a RadioNodeList; its .value is the
		// currently-checked radio's value.
		var payload = {
			business_id:  state.businessId,
			email:        email,
			name:         ( field( 'name' ).value || '' ).trim(),
			phone:        ( field( 'phone' ).value || '' ).trim(),
			relationship: field( 'relationship' ).value,
			note:         ( field( 'note' ).value || '' ).trim(),
			send_welcome: field( 'send_welcome' ).checked,
		};

		state.submitting = true;
		var submitBtn = formEl.querySelector( '.bd-ga-btn-submit' );
		var originalLabel = submitBtn.textContent;
		submitBtn.textContent = i18n.submitting || 'Granting access…';
		submitBtn.disabled = true;

		apiFetch( {
			path: '/bd/v1/claims/grant',
			method: 'POST',
			data: payload,
		} )
			.then( function ( res ) {
				var msg = i18n.successExist || 'Access granted';
				if ( res && res.already ) {
					msg = i18n.successAlready || 'Already has access';
				} else if ( res && res.created_user ) {
					msg = i18n.successNew || 'Access granted, welcome email sent';
				}
				showToast( msg, 'success' );
				formEl.reset();
				field( 'send_welcome' ).checked = true;
				loadCurrentUsers();
				// Keep any on-page meta box in sync with the same business.
				syncMetaBoxList( state.businessId );
			} )
			.catch( function ( err ) {
				var msg = ( err && err.message ) ? err.message : ( i18n.errorGeneric || 'Error' );
				showToast( msg, 'error' );
			} )
			.finally( function () {
				state.submitting = false;
				submitBtn.textContent = originalLabel;
				submitBtn.disabled = false;
			} );
	}

	/**
	 * Revoke a user's access to a business.
	 *
	 * Can be called from two contexts:
	 *   a) Inside the open modal (the in-modal user list's Revoke button)
	 *   b) Directly from the meta box on the edit screen, without the modal
	 *      being open at all (state.businessId may be null)
	 *
	 * @param {number}      claimId     Claim row ID to revoke.
	 * @param {number|null} businessId  Optional business ID for meta box sync
	 *                                  when called outside the modal.
	 */
	function revoke( claimId, businessId ) {
		if ( ! claimId || state.submitting ) {
			return;
		}
		// We need the confirm + apiFetch regardless of modal state, but the
		// toast only shows when the modal is visible. Fall back to a simple
		// status line on the triggering element if needed — for now we just
		// call window.alert on error when the modal isn't there.
		if ( ! window.confirm( i18n.revokeConfirm || 'Revoke this user\'s access?' ) ) {
			return;
		}

		state.submitting = true;

		// Resolve the business to sync afterwards, in priority order:
		// 1. explicit businessId arg (meta box path)
		// 2. modal's current business (in-modal revoke)
		// 3. window.bdGrantAccess.currentBusinessId (frontend toolbar)
		var syncBusinessId = businessId || state.businessId || ( config.currentBusinessId ? parseInt( config.currentBusinessId, 10 ) : null );

		apiFetch( {
			path: '/bd/v1/claims/' + claimId + '/revoke',
			method: 'POST',
			data: { note: '' },
		} )
			.then( function () {
				if ( modalEl && ! modalEl.hidden ) {
					showToast( i18n.successRevoked || 'Revoked', 'success' );
					loadCurrentUsers();
				}
				if ( syncBusinessId ) {
					syncMetaBoxList( syncBusinessId );
				}
			} )
			.catch( function ( err ) {
				var msg = ( err && err.message ) ? err.message : ( i18n.errorGeneric || 'Error' );
				if ( modalEl && ! modalEl.hidden ) {
					showToast( msg, 'error' );
				} else {
					// eslint-disable-next-line no-alert
					window.alert( msg );
				}
			} )
			.finally( function () {
				state.submitting = false;
			} );
	}

	// ---------------------------------------------------------------------
	// Rendering
	// ---------------------------------------------------------------------

	function renderUserList( users ) {
		if ( ! users.length ) {
			listEl.innerHTML = '<li class="bd-ga-empty">' + escapeHtml( i18n.noAccess || 'No users.' ) + '</li>';
			return;
		}
		listEl.innerHTML = users
			.map( function ( u ) {
				var badges = '';
				if ( u.is_primary ) {
					badges += ' <span class="bd-ga-badge bd-ga-badge--primary">' + escapeHtml( i18n.primary || 'Primary' ) + '</span>';
				}
				badges += ' <span class="bd-ga-badge bd-ga-badge--' + escapeHtml( u.relationship ) + '">' + escapeHtml( capitalise( u.relationship ) ) + '</span>';
				var warn = u.user_missing
					? '<br><span class="bd-ga-warn">⚠ user missing</span>'
					: '';
				return (
					'<li class="bd-ga-user" data-claim-id="' + escapeAttr( u.claim_id ) + '">' +
					'<div class="bd-ga-user__main">' +
					'<strong>' + escapeHtml( u.display_name ) + '</strong>' + badges + '<br>' +
					'<span class="bd-ga-user__email">' + escapeHtml( u.email ) + '</span>' +
					warn +
					'</div>' +
					'<button type="button" class="button-link bd-ga-revoke" data-claim-id="' + escapeAttr( u.claim_id ) + '">' +
					escapeHtml( i18n.revoke || 'Revoke' ) +
					'</button>' +
					'</li>'
				);
			} )
			.join( '' );
	}

	function updateOwnerWarning() {
		if ( ! formEl || ! listEl ) {
			return;
		}
		var role = field( 'relationship' ).value;
		var hasOwner = !! listEl.querySelector( '.bd-ga-badge--owner' );
		var warnEl = modalEl.querySelector( '.bd-ga-modal__warning' );
		if ( role === 'owner' && hasOwner ) {
			warnEl.textContent = i18n.ownerExists || '';
			warnEl.hidden = false;
		} else {
			hideWarning();
		}
	}

	function hideWarning() {
		if ( ! modalEl ) return;
		var w = modalEl.querySelector( '.bd-ga-modal__warning' );
		if ( w ) {
			w.hidden = true;
			w.textContent = '';
		}
	}

	function showToast( msg, kind ) {
		if ( ! toastEl ) return;
		toastEl.textContent = msg;
		toastEl.className = 'bd-ga-modal__toast bd-ga-toast--' + ( kind || 'info' );
		toastEl.hidden = false;
	}

	function clearToast() {
		if ( ! toastEl ) return;
		toastEl.hidden = true;
		toastEl.textContent = '';
	}

	/**
	 * Sync the server-rendered meta box list with the latest REST data.
	 *
	 * Called after a successful grant OR revoke so the edit screen's
	 * "Business Access" meta box matches the modal state without requiring
	 * a page reload. Does nothing if the meta box isn't on the page (e.g.
	 * row action flow, frontend toolbar flow).
	 *
	 * @param {number} businessId Business post ID to resync against.
	 */
	function syncMetaBoxList( businessId ) {
		var metabox = document.querySelector( '.bd-business-access-metabox[data-business-id="' + businessId + '"]' );
		if ( ! metabox ) {
			return;
		}

		apiFetch( {
			path: '/bd/v1/businesses/' + businessId + '/access',
			method: 'GET',
		} )
			.then( function ( res ) {
				var users = ( res && res.users ) || [];
				renderMetaBoxList( metabox, users );
			} )
			.catch( function () {
				/* Non-critical — the modal list is still in sync. */
			} );
	}

	/**
	 * Rebuild the meta box's authorized-users list from a users array.
	 *
	 * Mirrors the server-rendered markup from
	 * BusinessAccessMetaBox::render_user_row() so CSS hooks stay consistent.
	 *
	 * @param {Element} metabox The .bd-business-access-metabox container.
	 * @param {Array}   users   Users from GET /businesses/{id}/access.
	 */
	function renderMetaBoxList( metabox, users ) {
		var existingList  = metabox.querySelector( '.bd-business-access-list' );
		var existingEmpty = metabox.querySelector( '.bd-business-access-empty' );
		var anchor        = metabox.querySelector( '.bd-business-access-actions' );

		if ( existingList ) {
			existingList.remove();
		}
		if ( existingEmpty ) {
			existingEmpty.remove();
		}

		if ( ! users.length ) {
			var empty = document.createElement( 'p' );
			empty.className = 'bd-business-access-empty';
			empty.textContent = i18n.noAccess || 'No users have been granted access yet.';
			if ( anchor ) {
				metabox.insertBefore( empty, anchor );
			} else {
				metabox.appendChild( empty );
			}
			return;
		}

		var ul = document.createElement( 'ul' );
		ul.className = 'bd-business-access-list';
		ul.innerHTML = users
			.map( function ( u ) {
				var badges = '';
				if ( u.is_primary ) {
					badges += ' <span class="bd-business-access-badge bd-business-access-badge--primary">' + escapeHtml( i18n.primary || 'Primary' ) + '</span>';
				}
				badges += ' <span class="bd-business-access-badge bd-business-access-badge--' + escapeHtml( u.relationship ) + '">' + escapeHtml( capitalise( u.relationship ) ) + '</span>';
				var warn = u.user_missing
					? '<br><span class="bd-business-access-item__warning">⚠ ' + escapeHtml( 'User account no longer exists' ) + '</span>'
					: '';
				return (
					'<li class="bd-business-access-item" data-claim-id="' + escapeAttr( u.claim_id ) + '" data-user-id="' + escapeAttr( u.user_id ) + '">' +
					'<div class="bd-business-access-item__main">' +
					'<strong class="bd-business-access-item__name">' + escapeHtml( u.display_name ) + '</strong>' + badges +
					'<br><span class="bd-business-access-item__email">' + escapeHtml( u.email ) + '</span>' +
					warn +
					'</div>' +
					'<div class="bd-business-access-item__actions">' +
					'<button type="button" class="button-link bd-business-access-revoke" data-claim-id="' + escapeAttr( u.claim_id ) + '">' +
					escapeHtml( i18n.revoke || 'Revoke' ) +
					'</button>' +
					'</div>' +
					'</li>'
				);
			} )
			.join( '' );

		if ( anchor ) {
			metabox.insertBefore( ul, anchor );
		} else {
			metabox.appendChild( ul );
		}
	}

	// ---------------------------------------------------------------------
	// Utilities
	// ---------------------------------------------------------------------

	function escapeHtml( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function escapeAttr( s ) {
		return escapeHtml( s );
	}

	function capitalise( s ) {
		s = String( s || '' );
		return s.charAt( 0 ).toUpperCase() + s.slice( 1 );
	}

	// ---------------------------------------------------------------------
	// Trigger delegation
	// ---------------------------------------------------------------------

	// Global trigger delegation: handles the three "Grant Access" entry
	// points (meta box button, row action, frontend admin bar) AND the
	// meta box's own Revoke buttons — which must work even when the modal
	// is not open, because they live on the server-rendered meta box.
	document.addEventListener( 'click', function ( e ) {
		// Meta box revoke button — standalone (modal might not be open).
		var revokeBtn = e.target.closest( '.bd-business-access-revoke' );
		if ( revokeBtn ) {
			e.preventDefault();
			var claimId = parseInt( revokeBtn.getAttribute( 'data-claim-id' ), 10 );
			if ( ! claimId ) {
				return;
			}
			// Resolve business ID from the surrounding meta box wrapper so
			// syncMetaBoxList can target the right container.
			var mbRoot = revokeBtn.closest( '.bd-business-access-metabox' );
			var mbBusinessId = mbRoot
				? parseInt( mbRoot.getAttribute( 'data-business-id' ), 10 )
				: null;
			revoke( claimId, mbBusinessId );
			return;
		}

		// Grant-access trigger — opens the modal.
		var trigger = e.target.closest( '.bd-grant-access-trigger' );
		if ( ! trigger ) {
			return;
		}
		e.preventDefault();

		var id = parseInt( trigger.getAttribute( 'data-business-id' ), 10 );
		if ( ! id && config.currentBusinessId ) {
			id = parseInt( config.currentBusinessId, 10 );
		}
		if ( ! id ) {
			// eslint-disable-next-line no-console
			console.warn( '[bd-grant-access] Trigger clicked without a resolvable business ID.' );
			return;
		}
		open( id );
	} );

	// Expose a small public surface for programmatic open (used by the
	// toolbar node's href hash fallback and by external scripts).
	window.bdGrantAccess = window.bdGrantAccess || {};
	window.bdGrantAccess.open  = open;
	window.bdGrantAccess.close = close;
} )();
