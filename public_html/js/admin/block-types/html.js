Cruinn.BlockTypes.register('html', {
    label: 'HTML',
    tag: 'div',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        return { raw: (blockItem.querySelector('.block-raw-input') || {}).value || '' };
    },
});
