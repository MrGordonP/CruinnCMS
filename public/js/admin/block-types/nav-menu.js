Cruinn.BlockTypes.register('nav-menu', {
    label: 'Nav Menu',
    tag: 'nav',
    isLayout: false,
    hasContent: true,
    getContent: function (blockItem) {
        var menuSel = blockItem.querySelector('[name="menu_id"]');
        return { menu_id: menuSel ? parseInt(menuSel.value, 10) : 0 };
    },
    populatePanel: function (g, blockItem) {
        var inlineSel = blockItem.querySelector('[name="menu_id"]');
        var panelSel  = g.querySelector('[data-content-prop="menu_id"]');
        if (inlineSel && panelSel) {
            panelSel.innerHTML = inlineSel.innerHTML;
            panelSel.value     = inlineSel.value;
        }
    },
});
