/* eslint-disable vars-on-top */
var ReactTestEnvSetup = require( './lib/react-test-env-setup' );

/**
 * External dependencies
 */
var expect = require( 'chai' ).expect,
	ReactDom = require( 'react-dom' ),
	React = require( 'react' ),
	TestUtils = require( 'react-addons-test-utils' );

/**
 * Internal dependencies
 */
var Settings = require( '../../client/views/settings' );

describe( 'Settings', function() {
	before( function() {
		ReactTestEnvSetup();
	} );

	afterEach( function() {
		ReactDom.unmountComponentAtNode( document.body );
	} );

	it( 'should contain a button', function() {
		var tree = TestUtils.renderIntoDocument( <Settings schema={{ "type": "string" }} /> ),
			button = TestUtils.findRenderedDOMComponentWithTag( tree, 'button' );

		expect( button ).to.be.ok;
	} );
} );