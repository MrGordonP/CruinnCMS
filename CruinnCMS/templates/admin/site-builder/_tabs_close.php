    </div><!-- /.acp-panel -->
</div><!-- /.sb-wrapper -->

<script>
(function() {
    document.querySelectorAll('.acp-layout-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var layout = this.dataset.layout;
            var wrapper = document.querySelector('.acp-wrapper');
            document.querySelectorAll('.acp-layout-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            if (layout === '2') {
                wrapper.classList.add('acp-two-col');
            } else {
                wrapper.classList.remove('acp-two-col');
            }
            var csrfMeta = document.querySelector('input[name="_csrf_token"]');
            var token = csrfMeta ? csrfMeta.value : '';
            var fd = new FormData();
            fd.append('layout', layout);
            fd.append('_csrf_token', token);
            fetch('<?= url('/admin/settings/layout') ?>', { method: 'POST', body: fd });
        });
    });
})();
</script>
