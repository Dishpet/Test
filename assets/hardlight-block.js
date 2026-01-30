(function(blocks, element, editor, components, i18n) {
    var el = element.createElement;
    var useBlockProps = editor.useBlockProps;
    var InspectorControls = editor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;

    blocks.registerBlockType('hardlight/component', {
        title: i18n.__('HardLight Component', 'hardlight'),
        icon: 'layout',
        category: 'widgets',
        attributes: {
            id: { type: 'number', default: 0 },
            slug: { type: 'string', default: '' }
        },
        edit: function(props) {
            var attrs = props.attributes;
            var blockProps = useBlockProps();
            return [
                el(InspectorControls, { key: 'controls' },
                    el(PanelBody, { title: i18n.__('Component', 'hardlight'), initialOpen: true },
                        el(TextControl, {
                            label: i18n.__('Component ID', 'hardlight'),
                            type: 'number',
                            value: attrs.id,
                            onChange: function(value) {
                                props.setAttributes({ id: parseInt(value || 0, 10) });
                            }
                        }),
                        el(TextControl, {
                            label: i18n.__('Component Slug', 'hardlight'),
                            value: attrs.slug,
                            onChange: function(value) {
                                props.setAttributes({ slug: value });
                            }
                        })
                    )
                ),
                el('div', blockProps, i18n.__('HardLight component will render on the front end.', 'hardlight'))
            ];
        },
        save: function() {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor || window.wp.editor, window.wp.components, window.wp.i18n);
