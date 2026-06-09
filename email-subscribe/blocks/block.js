( function( blocks, element, blockEditor, components ) {
    var el = element.createElement;
    var __ = wp.i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var ToggleControl = components.ToggleControl;
    var useBlockProps = blockEditor.useBlockProps;

    var DEFAULTS = {
        heading:               'Subscribe to our newsletter',
        subheading:            'Want to be notified when our article is published? Enter your email address and name below to be the first to know.',
        emailLabel:            'Email',
        nameLabel:             'Name',
        submitButtonLabel:     'SIGN UP FOR NEWSLETTER NOW',
        requiredFieldMessage:  'This field is required.',
        invalidEmailMessage:   'Please enter valid email address.',
        invalidRequestMessage: 'Invalid request.',
        emailExistMessage:     'This email is already exist.',
        successMessage:        'You have successfully subscribed to our Newsletter!',
        waitMessage:           'Please wait...',
        agreementText:         'I agree to <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>',
        agreementError:        'Please read and agree to our terms & conditions.',
    };

    blocks.registerBlockType( 'email-subscribe/form', {
        title: __( 'Email Subscribe Form', 'email-subscribe' ),
        description: __( 'Display the email subscription form anywhere on your site.', 'email-subscribe' ),
        icon: 'email-alt',
        category: 'widgets',
        keywords: [ 'email', 'subscribe', 'newsletter', 'form' ],

        attributes: {
            heading:               { type: 'string',  default: DEFAULTS.heading },
            subheading:            { type: 'string',  default: DEFAULTS.subheading },
            emailLabel:            { type: 'string',  default: DEFAULTS.emailLabel },
            nameLabel:             { type: 'string',  default: DEFAULTS.nameLabel },
            submitButtonLabel:     { type: 'string',  default: DEFAULTS.submitButtonLabel },
            requiredFieldMessage:  { type: 'string',  default: DEFAULTS.requiredFieldMessage },
            invalidEmailMessage:   { type: 'string',  default: DEFAULTS.invalidEmailMessage },
            invalidRequestMessage: { type: 'string',  default: DEFAULTS.invalidRequestMessage },
            emailExistMessage:     { type: 'string',  default: DEFAULTS.emailExistMessage },
            successMessage:        { type: 'string',  default: DEFAULTS.successMessage },
            waitMessage:           { type: 'string',  default: DEFAULTS.waitMessage },
            showName:              { type: 'boolean', default: true },
            showAgreement:         { type: 'boolean', default: false },
            agreementText:         { type: 'string',  default: DEFAULTS.agreementText },
            agreementError:        { type: 'string',  default: DEFAULTS.agreementError },
        },

        edit: function( props ) {
            var attrs = props.attributes;
            var set   = props.setAttributes;
            var blockProps = useBlockProps();

            function val(key) {
                return attrs[key] !== undefined && attrs[key] !== '' ? attrs[key] : DEFAULTS[key] || '';
            }

            return el( 'div', blockProps,
                el( InspectorControls, null,
                    el( PanelBody, { title: __('Form Content','email-subscribe'), initialOpen: true },
                        el( TextControl, { label: __('Heading','email-subscribe'), value: attrs.heading, placeholder: DEFAULTS.heading, onChange: function(v){ set({heading:v}); } }),
                        el( TextareaControl, { label: __('Subheading','email-subscribe'), value: attrs.subheading, placeholder: DEFAULTS.subheading, onChange: function(v){ set({subheading:v}); } }),
                        el( TextControl, { label: __('Email Field Label','email-subscribe'), value: attrs.emailLabel, placeholder: DEFAULTS.emailLabel, onChange: function(v){ set({emailLabel:v}); } }),
                        el( TextControl, { label: __('Submit Button Label','email-subscribe'), value: attrs.submitButtonLabel, placeholder: DEFAULTS.submitButtonLabel, onChange: function(v){ set({submitButtonLabel:v}); } })
                    ),
                    el( PanelBody, { title: __('Name Field','email-subscribe'), initialOpen: false },
                        el( ToggleControl, { label: __('Show Name Field','email-subscribe'), checked: attrs.showName, onChange: function(v){ set({showName:v}); } }),
                        attrs.showName ? el( TextControl, { label: __('Name Label','email-subscribe'), value: attrs.nameLabel, placeholder: DEFAULTS.nameLabel, onChange: function(v){ set({nameLabel:v}); } }) : null
                    ),
                    el( PanelBody, { title: __('GDPR Agreement','email-subscribe'), initialOpen: false },
                        el( ToggleControl, { label: __('Show Agreement Checkbox','email-subscribe'), help: __('Required for GDPR compliance','email-subscribe'), checked: attrs.showAgreement, onChange: function(v){ set({showAgreement:v}); } }),
                        attrs.showAgreement ? el( TextareaControl, { label: __('Agreement Text (HTML allowed)','email-subscribe'), value: attrs.agreementText, placeholder: DEFAULTS.agreementText, onChange: function(v){ set({agreementText:v}); } }) : null,
                        attrs.showAgreement ? el( TextControl, { label: __('Agreement Error','email-subscribe'), value: attrs.agreementError, placeholder: DEFAULTS.agreementError, onChange: function(v){ set({agreementError:v}); } }) : null
                    ),
                    el( PanelBody, { title: __('Messages','email-subscribe'), initialOpen: false },
                        el( TextControl, { label: __('Required Field Message','email-subscribe'), value: attrs.requiredFieldMessage, placeholder: DEFAULTS.requiredFieldMessage, onChange: function(v){ set({requiredFieldMessage:v}); } }),
                        el( TextControl, { label: __('Invalid Email Message','email-subscribe'), value: attrs.invalidEmailMessage, placeholder: DEFAULTS.invalidEmailMessage, onChange: function(v){ set({invalidEmailMessage:v}); } }),
                        el( TextControl, { label: __('Email Exists Message','email-subscribe'), value: attrs.emailExistMessage, placeholder: DEFAULTS.emailExistMessage, onChange: function(v){ set({emailExistMessage:v}); } }),
                        el( TextControl, { label: __('Success Message','email-subscribe'), value: attrs.successMessage, placeholder: DEFAULTS.successMessage, onChange: function(v){ set({successMessage:v}); } }),
                        el( TextControl, { label: __('Wait Message','email-subscribe'), value: attrs.waitMessage, placeholder: DEFAULTS.waitMessage, onChange: function(v){ set({waitMessage:v}); } }),
                        el( TextControl, { label: __('Invalid Request Message','email-subscribe'), value: attrs.invalidRequestMessage, placeholder: DEFAULTS.invalidRequestMessage, onChange: function(v){ set({invalidRequestMessage:v}); } })
                    )
                ),
                el( 'div', {
                    style: { background:'#f0f6fc', border:'1px solid #c3d9f5', borderRadius:'8px', padding:'20px', textAlign:'center', fontFamily:'sans-serif' }
                },
                    el('div', { style:{ fontSize:'28px', marginBottom:'6px' } }, '📧'),
                    el('p', { style:{ fontWeight:'700', fontSize:'15px', margin:'0 0 4px', color:'#1e1e1e' } }, val('heading')),
                    el('p', { style:{ fontSize:'12px', color:'#666', margin:'0 0 14px', lineHeight:'1.5' } }, val('subheading')),
                    el('div', { style:{ display:'flex', gap:'8px', justifyContent:'center', flexWrap:'wrap' } },
                        el('input', { style:{ borderRadius:'4px', border:'1px solid #ddd', padding:'8px 12px', fontSize:'13px', minWidth:'180px' }, placeholder: val('emailLabel'), readOnly:true }),
                        attrs.showName ? el('input', { style:{ borderRadius:'4px', border:'1px solid #ddd', padding:'8px 12px', fontSize:'13px', minWidth:'130px' }, placeholder: val('nameLabel'), readOnly:true }) : null,
                        el('div', { style:{ background:'#2271b1', color:'#fff', borderRadius:'4px', padding:'8px 16px', fontSize:'12px', fontWeight:'600', display:'flex', alignItems:'center' } }, val('submitButtonLabel'))
                    ),
                    attrs.showAgreement ? el('p', { style:{ fontSize:'11px', color:'#888', marginTop:'8px' } }, '☑ GDPR checkbox enabled') : null
                )
            );
        },
        save: function() { return null; }
    });

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
