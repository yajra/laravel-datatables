// add csrf token, need for post requests
$.extend(true, $.fn.dataTable.defaults, {
    csrf_token : '{{ csrf_token() }}'
});

(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["%1$s"]=$("#%1$s").DataTable(%2$s);})(window,jQuery);
