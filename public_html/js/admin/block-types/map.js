Cruinn.BlockTypes.register('map', {
    label: 'Map',
    tag: 'div',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        return {
            lat:     parseFloat((blockItem.querySelector('.block-lat-input')     || {}).value || '53.3498'),
            lng:     parseFloat((blockItem.querySelector('.block-lng-input')     || {}).value || '-6.2603'),
            caption: (blockItem.querySelector('.block-caption-input') || {}).value || '',
        };
    },
});
