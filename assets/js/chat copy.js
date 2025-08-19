
$(document).ready(function(){
    // Function to get URL parameters
function loadUrl(url) {
return $.ajax({
    url: url,
    method: 'GET',
    dataType: 'html'
});
}

// Function to get a URL parameter by name
function getUrlParameter(name) {
name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
const results = regex.exec(location.search);
return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

let userid = getUrlParameter('id');

if (userid !== null && userid !== undefined) {
let noMessageHTML = `<div class="d-flex flex-column h-100 justify-content-center">
            <div class="text-center mb-6">
                <span class="icon icon-xl text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </span>
            </div>
            <p class="text-center text-muted">No messages yet, <br> start the conversation!</p>
          </div>`;

function getMessages(userid) {
    loadUrl('function/getmessage.php?u=' + userid)
        .done(function (responseText) {
            let currentMessages = sessionStorage.getItem("message").trim();
            let chatMessages =$(".chat-logs").html().trim() ;
            let newMessages = responseText.trim();
            
            if ( chatMessages == noMessageHTML ||currentMessages == "") {
                if (newMessages === "") {
                    $(".chat-logs").addClass("h-100").html(noMessageHTML);
                } else {
                    $(".chat-logs").removeClass("h-100").html(newMessages);
                }
            } else {
                if (newMessages.length>currentMessages.length ) {
                    // let newContent = newMessages.slice(currentMessages.length) ;
                    sessionStorage.setItem("message" , responseText)
                    // $(".chat-logs").append(newContent)
                    console.log("ddd");
                    loadUrl('function/getmessage.php?u=' + userid)
                    .done(function (responseText) {
                        $(".chat-logs").html(responseText);
                    });
                }
            }
        })
}

function sendChatMessage() {
    $.post('function/sendmessage.php', { message: $('#message').val(), recipient_id: userid })
        .done(function () {
            const chatBody = $('.chat-body');
            if (chatBody.length > 0) {
                chatBody.scrollTop(chatBody[0].scrollHeight);
            }
            $('#message').val('');
            getMessages(userid);
            stopAlert();
            const sound = document.getElementById("sound");
            if (sound) {
                sound.play();
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Failed to send message:', textStatus, errorThrown);
        });
}

function stopAlert() {
    const song = document.getElementById("sound");
    if (song) {
        song.pause();
        song.currentTime = 0;
    }
}

// Load messages initially

loadUrl('function/getmessage.php?u=' + userid)
    .done(function (responseText) {
        $(".chat-logs").html(responseText);
        sessionStorage.setItem("message" , responseText)
    }).then(function () {
        let rn =   setInterval(() => {
            if ($('main').hasClass("is-visible")) {
                
            
                getMessages(userid)
                if ($('#message').val().trim() === "") {
                    $(".send-btn").attr("disabled", true);
                    
                } else {
                    $(".send-btn").removeAttr("disabled");
                }
            }
            }, 1000);
    })

// Update messages every 500ms if the main element is visible
$('.chat-form').submit(function (e) {
    e.preventDefault();
    sendChatMessage();
});
setInterval(() => {
    gettypingstatus(userid);
}, 1000);
function gettypingstatus(user) {
    loadUrl('function/gettypingstatus.php?u=' + user)
    .done(function (responseText) {
        if (responseText.trim() == "true") {
            $("#typingstatus").html("is typing<span class='typing-dots'><span>.</span><span>.</span><span>.</span></span>")
        }else{
            $("#typingstatus").html(" ")

        }
    })
}
// Handle keyboard shortcut for sending messages
document.addEventListener('keydown', (event) => {
    if (event.ctrlKey && event.key === "Enter") {
        $('.chat-form').submit();
    }
});

// Update typing status
$("#message").on("keyup", function () {
    if ($('#message').val().trim() === "") {
        $(".send-btn").attr("disabled", true);
    } else {
        $(".send-btn").removeAttr("disabled");
    }
    $.post('function/typingstatus.php', { recipient_id: userid });
});

$("#message").on("blur", function () {
    $.post('function/typingstatus.php', { recipient_id: userid, nottyping: "cbdjvfvjkfbvjf" });
});

// Scroll to the bottom of the chat body on page load
const chatBody = $('.chat-body');
if (chatBody.length > 0) {
    chatBody.scrollTop(chatBody[0].scrollHeight);
}
}

})