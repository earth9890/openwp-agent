import apiFetch from '@wordpress/api-fetch';
import { createRoot } from '@wordpress/element';
import App from './app';
import './index.css';

apiFetch.use( apiFetch.createNonceMiddleware( window.openwpAdmin.nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( window.openwpAdmin.root.replace( /\/openwp\/v1\/?$/, '/' ) ) );

const container = document.getElementById( 'openwp-admin-root' );
if ( container ) {
	createRoot( container ).render( <App /> );
}
