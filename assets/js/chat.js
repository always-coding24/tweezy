$(document).ready(function() {
    const MESSAGE_URL = 'function/getmessage.php';
    const SEND_MESSAGE_URL = 'function/sendmessage.php';
    const TYPING_STATUS_URL = 'function/typingstatus.php';
    const GET_TYPING_STATUS_URL = 'function/gettypingstatus.php';
    const GET_STATUS = 'function/status.php';

    // Function to load content from a URL
    async function loadUrl(url) {
        try {
            const response = await $.ajax({
                url: url,
                method: 'GET',
                dataType: 'html'
            });
            return response;
        } catch (error) {
            console.log( error.statusText);
            return null;
        }
    }

    // Function to get a URL parameter by name
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    const userid = getUrlParameter('id');
    const noMessageHTML = `<div class="d-flex flex-column h-100 justify-content-center">
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

    async function getMessages(userid) {
        const newMessages = (await loadUrl(`${MESSAGE_URL}?u=${userid}`)).trim();
        const currentMessages = sessionStorage.getItem("message")?.trim() || "";
        const chatMessages = $(".chat-logs").html().trim();

        if (chatMessages === noMessageHTML || !currentMessages) {
            if (!newMessages) {
                $(".chat-logs").addClass("h-100").html(noMessageHTML);
            } else {
                $(".chat-logs").removeClass("h-100").html(newMessages);
            }
        } else if (newMessages !== currentMessages) {
            sessionStorage.setItem("message", newMessages);
            $(".chat-logs").html(newMessages);
            wrapSlangsWithTooltips(".message-text >p ")
        }
    }

    async function sendChatMessage() {
        try {
            await $.post(SEND_MESSAGE_URL, {
                message: $('#message').val(),
                recipient_id: userid
            });
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
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    }

    function stopAlert() {
        const song = document.getElementById("sound");
        if (song) {
            song.pause();
            song.currentTime = 0;
        }
    }

    if (userid) {
        // Load initial messages and set up periodic updates
        (async function initChat() {
            const initialMessages = await loadUrl(`${MESSAGE_URL}?u=${userid}`);
            $(".chat-logs").html(initialMessages);
            wrapSlangsWithTooltips(".message-text >p ")
            sessionStorage.setItem("message", initialMessages);
            $(".message-text").on("contextmenu" , function (e){
                e.preventDefault()
             this.click()
                })
            setInterval(async () => {
                if ($('main').hasClass("is-visible")) {
                    await getMessages(userid);
                    $(".send-btn").attr("disabled", !$('#message').val().trim());
                }
            }, 1000);
        })();

        // Submit chat message on form submission
        $('.chat-form').submit(async function(e) {
            e.preventDefault();
            await sendChatMessage();
        });

        // Update typing status periodically
        setInterval(async () => {
            await updateTypingStatus(userid);
        }, 1000);

        // Update typing status
        const updateTypingStatus = debounce(async function(userid) {
            const status = await loadUrl(`${GET_STATUS}?u=${userid}`);
            const responseText = await loadUrl(`${GET_TYPING_STATUS_URL}?u=${userid}`);
            $("#typingstatus").html(responseText.trim() === "true" ? 
                "is typing<span class='typing-dots'><span>.</span><span>.</span><span>.</span></span>" : status.trim());
                if (status.trim()=== "online") {
                    
                    $(".getstatus").removeClass("avatar-offline")
                    $(".getstatus").addClass("avatar-online")
                }else{
                    $(".getstatus").removeClass("avatar-online")
                    $(".getstatus").addClass("avatar-offline")

                }
        }, 500);
      
        // Detect typing and update status
        $("#message").on("keyup", async function() {
            $(".send-btn").attr("disabled", !$(this).val().trim());
            await $.post(TYPING_STATUS_URL, { recipient_id: userid });
        }).on("blur", async function() {
            await $.post(TYPING_STATUS_URL, {
                recipient_id: userid,
                nottyping: "true"
            });
        });

        // Scroll to the bottom of the chat body on page load
        const chatBody = $('.chat-body');
        if (chatBody.length > 0) {
            chatBody.scrollTop(chatBody[0].scrollHeight);
        }

        // Handle keyboard shortcut for sending messages
        document.addEventListener('keydown', (event) => {
            if (event.ctrlKey && event.key === "Enter") {
                $('.chat-form').submit();
            }
        });
    }

    // Debounce function to limit the rate of execution
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this,
                args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
});
