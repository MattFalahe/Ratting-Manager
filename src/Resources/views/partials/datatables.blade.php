{{--
    DataTables for a sortable table. SeAT's own layout already loads it, with
    its Bootstrap pager; the plugin's copy is only loaded when it is missing,
    since loading it again would replace SeAT's and lose that pager.
--}}
<script>
    if (!(window.jQuery && jQuery.fn.DataTable)) {
        document.write('<script src="{{ \RattingManager\Support\Asset::url('js/vendor/jquery.dataTables.min.js') }}"><\/script>');
    }
</script>
