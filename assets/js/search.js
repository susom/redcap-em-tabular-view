Patient = {
    init: function () {

        $body = $("body");
        /**
         * display loader
         */
        jQuery(document).on({
            ajaxStart: function () {
                $body.addClass("loading");
            },
            ajaxStop: function () {
                $body.removeClass("loading");
            }
        });
        console.log('asdasdasdasd')
        /**
         * Event for search top parent.
         */
        jQuery("#search-top-parent").on("click", function () {

            var term = jQuery("#top-parent-field").val();
            Patient.searchTopParent(term);
            /*if (term == "") {
                alert("You must add search term");
                return false;
            } else {
                Patient.searchTopParent(term);
            }*/
        });
    },
    searchTopParent: function (term) {
        jQuery.ajax({
            url: jQuery("#search-top-parent-url").val(),
            data: {term: term, pid: jQuery("#project-id").val(), redcap_csrf_token: jQuery("#redcap_csrf_token").val()},
            type: 'POST',
            success: function (data) {
                jQuery("#record-container").html(data);

            },
            error: function (request, error) {
                alert("Request: " + JSON.stringify(request));
            },
            complete: function () {
                jQuery("#record-instances").dataTable();
            }
        });
    }
};


Patient.init();