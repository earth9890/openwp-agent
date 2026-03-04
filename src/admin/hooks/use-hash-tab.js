import { useState, useEffect, useCallback } from '@wordpress/element';
import { APP_ROUTE_KEYS } from '../../shared/constants';

const VALID_KEYS = new Set( APP_ROUTE_KEYS );

function normalizeHash( rawHash ) {
	return rawHash.replace( /^#/, '' ).replace( /^\/+/, '' ).trim();
}

function readHash() {
	const raw = normalizeHash( window.location.hash );
	return VALID_KEYS.has( raw ) ? raw : 'dashboard';
}

export function useHashTab() {
	const [ tab, setTabState ] = useState( readHash );

	// Keep state in sync when the user clicks Back/Forward.
	useEffect( () => {
		const onHashChange = () => setTabState( readHash() );
		window.addEventListener( 'hashchange', onHashChange );
		return () => window.removeEventListener( 'hashchange', onHashChange );
	}, [] );

	// Seed the hash on first mount if it's missing.
	useEffect( () => {
		if ( ! window.location.hash ) {
			window.location.replace( '#dashboard' );
		}
	}, [] );

	const setTab = useCallback( ( key ) => {
		const normalized = normalizeHash( key );
		if ( ! VALID_KEYS.has( normalized ) ) {
			return;
		}
		window.location.hash = normalized;
		setTabState( normalized );
	}, [] );

	return [ tab, setTab ];
}
