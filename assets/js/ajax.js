
$(document).ready(function(){
$(".loader").remove()
$(".loaded").show()

function isset(variable) {
    return typeof variable !== 'undefined' && variable !== null;
}

let loop =     setInterval(function () {
       
    fetch_user(searchValue);
    unread()

}, 500);

$('#friends').load('function/getfriend.php')
$("#friend_search").on("keyup", async function() {
    let value = $("#friend_search").val()
    $('#friends').load('function/getfriend.php?u='+value)
    if ($('#friends').html().trim()== "" ) {
        $('#friends').html("No User Found")
    }
    
})
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

var id = getUrlParameter('id'); // Get 'id' parameter from the URL

function fetch_user(user = "") {
    $(".users").load("function/get_user.php?u=" + user,)
}

let searchValue;
// Call fetch_user function on page load

    fetch_user();

    // Update user list every second 


    // Update user list when typing in the search input
    // $(".search").on("keyup", function () {
    //     searchValue = $(this).val();
       
    // });

//send data to the db


function readurl(input = this) {
    if (input.files) {
        for (let i = 0; i < input.files.length; i++) {
            var reader = new FileReader();

            reader.onload = function (e) {
                $(".preview").attr({
                    "src": e.target.result
                })
            };
            reader.readAsDataURL(input.files[i]);
        }
    }
}
function unread() {
$('#unread > span').load('function/getunreadmessage.php', function () {
    if ($('#unread > span').innerHTML  =="0" ) {
        $('#unread').css("display","unset")
     }  else  {$('#unread').css("display","none")}
})
 
}
})