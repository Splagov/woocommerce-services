import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import ActionButtons from 'components/action-buttons';
import CompactCard from 'components/card/compact';
import GlobalNotices from 'components/global-notices';
import LabelSettings from './label-settings';
import notices from 'notices';
import { sprintf } from 'sprintf-js';
import { translate as __ } from 'lib/mixins/i18n';
import * as actions from '../state/actions';
import * as NoticeActions from 'state/notices/actions';

const AccountSettingsRootView = ( props ) => {
	//const onPaymentMethodChange = ( value ) => props.actions.setFormDataValue( 'selected_payment_method_id', value );

	const onSaveSuccess = () => {
		props.actions.setFormMetaProperty( 'pristine', true );
		props.noticeActions.successNotice( __( 'Your payment method has been updated.' ), { duration: 5000 } );
	};
	const onSaveFailure = () => props.noticeActions.errorNotice( __( 'Unable to update your payment method. Please try again.' ) );
	const onSaveChanges = () => props.actions.submit( onSaveSuccess, onSaveFailure );

	const buttons = [
		{
			label: __( 'Save changes' ),
			onClick: onSaveChanges,
			isPrimary: true,
			isDisabled: props.formMeta.pristine || props.formMeta.isSaving,
		},
	];

	return (
		<div>
			<GlobalNotices id="notices" notices={ notices.list } />
			<CompactCard>
				<LabelSettings />
			</CompactCard>
			<CompactCard className="save-button-bar">
				<ActionButtons
					buttons={ buttons }
				/>
			</CompactCard>
		</div>
	);
};

AccountSettingsRootView.propTypes = {
	submit: PropTypes.func,
};

function mapStateToProps( state ) {
	return {
		formData: state.form.data,
		formMeta: state.form.meta,
	};
}

function mapDispatchToProps( dispatch ) {
	return {
		actions: bindActionCreators( actions, dispatch ),
		noticeActions: bindActionCreators( NoticeActions, dispatch ),
	};
}

export default connect(
	mapStateToProps,
	mapDispatchToProps
)( AccountSettingsRootView );
