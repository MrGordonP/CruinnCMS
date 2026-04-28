Cruinn.BlockTypes.register('heading', {
    label: 'Heading',
    tag: 'h2',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        return {
            level: (blockItem.querySelector('[name="heading_level"]') || {}).value || '2',
            text:  (blockItem.querySelector('[name="heading_text"]')  || {}).value || '',
        };
    },
});
