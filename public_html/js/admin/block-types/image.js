Cruinn.BlockTypes.register('image', {
    label: 'Image',
    tag: 'figure',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        return {
            src: (blockItem.querySelector('[name="content_src"]') || blockItem.querySelector('.block-url-input') || {}).value || '',
            alt: (blockItem.querySelector('.block-alt-input') || {}).value || '',
        };
    },
});
