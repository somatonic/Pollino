
/**
 * Pollino Ajax Script example
 */

$(function(){

    $('form.pollino_form').on("submit", function(e){

        e.preventDefault();
        var formdata = $(this).serialize();
        var actionurl = $(this).attr("action");
        var form = $(this);
        var container = form.closest(".pollino_poll");

        // remove error in case
        container.find(".pollino_error").remove();

        $.ajax({
            url: actionurl,
            data: formdata,
            type: "GET",
            success: function(data){
                if(data.message){
                    // add the message to the form
                    form.before($('<p class="pollino_error">' + data.message + '</p>'));
                } else {
                    form.remove();
                    var result = $(data).hide();
                    container.append(result.fadeIn());
                }
            }
        })

    });


    $('[id^="pollino_chart_"').each(function(i){
        var ctx[i] = document.getElementById($(this).id).getContext("2d");
        var pollinochart[i] = new Chart(ctx[i]).Bar();
        pollinochart[i].addData([40, 60], "August");
    });


});