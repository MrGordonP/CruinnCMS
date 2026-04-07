Cruinn.BlockTypes.register('event-list', {
    label: 'Events',
    tag: 'div',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        return {
            count: parseInt((blockItem.querySelector('input[name="content_count"]') || {}).value || 5),
            type:  'upcoming',
        };
    },
});
