import React, { PropTypes } from 'react';
import FormFieldset from 'components/forms/form-fieldset';
import FormLabel from 'components/forms/form-label';
import FormTextInput from 'components/forms/form-text-input';
import FormSettingExplanation from 'components/forms/form-setting-explanation';

const TextField = ( { id, schema, value, placeholder, updateValue } ) => {
	const handleChangeEvent = event => updateValue( event.target.value );

	return (
		<FormFieldset>
			<FormLabel htmlFor={ id }>{ schema.title }</FormLabel>
			<FormTextInput
				id={ id }
				name={ id }
				placeholder={ placeholder }
				value={ value }
				onChange={ handleChangeEvent } />
			<FormSettingExplanation>{ schema.description }</FormSettingExplanation>
		</FormFieldset>
	);
};

TextField.propTypes = {
	id: PropTypes.string.isRequired,
	schema: PropTypes.shape( {
		type: PropTypes.string.valueOf( 'string' ),
		title: PropTypes.string.isRequired,
		description: PropTypes.string.isRequired,
		default: PropTypes.string,
	} ).isRequired,
	value: PropTypes.string.isRequired,
	updateValue: PropTypes.func,
};

export default TextField;
