import { createRoot } from '@wordpress/element';
import SitewideChatbotWidget from './widget';
import './style.css';

function mountSitewideChatbot() {
	if ( typeof window === 'undefined' || ! window.openwpSiteChat ) {
		return;
	}

	const existing = document.getElementById( 'openwp-sitewide-chatbot-root' );
	if ( existing ) {
		return;
	}

	const container = document.createElement( 'div' );
	container.id = 'openwp-sitewide-chatbot-root';
	document.body.appendChild( container );
	createRoot( container ).render( <SitewideChatbotWidget /> );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountSitewideChatbot );
} else {
	mountSitewideChatbot();
}
