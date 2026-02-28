$(document).ready(function() {
    const MESSAGE_URL = 'function/getmessage.php';
    const SEND_MESSAGE_URL = 'function/sendmessage.php';
    const DELETE_MESSAGE_URL = 'function/delete.php';
    const TYPING_STATUS_URL = 'function/typingstatus.php';
    const GET_TYPING_STATUS_URL = 'function/gettypingstatus.php';
    const GET_STATUS = 'function/status.php';
    const PAGE_SIZE = 15;
    const POLL_INTERVAL_MS = 1000;
    let loadedLimit = PAGE_SIZE;
    let hasMoreMessages = true;
    let isPaginationLoading = false;
    let isMessagesRequestInFlight = false;
    let isInitialLoadDone = false;

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

    function getMessageIdsFromMarkup(markup) {
        const template = document.createElement('template');
        template.innerHTML = (markup || '').trim();
        return Array.from(template.content.querySelectorAll('.message[id^="message-"]'))
            .map((node) => Number(String(node.id).replace('message-', '')))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    function getRenderedMessageIds() {
        return Array.from(document.querySelectorAll('.chat-logs .message[id^="message-"]'))
            .map((node) => Number(String(node.id).replace('message-', '')))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    function showLoadingAlert() {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            title: 'Loading messages...',
            text: 'Please wait',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function hideLoadingAlert() {
        if (typeof Swal === 'undefined') {
            return;
        }

        if (Swal.isVisible()) {
            Swal.close();
        }
    }

    function getChatNodeKey(node, index) {
        if (node.classList.contains('message') && node.id) {
            return `message:${node.id}`;
        }

        if (node.classList.contains('message-divider')) {
            return `divider:${node.textContent.trim().replace(/\s+/g, ' ')}`;
        }

        return `node:${node.tagName}:${index}`;
    }

    function renderEmptyState() {
        const chatLogs = document.querySelector('.chat-logs');
        if (!chatLogs) {
            return;
        }

        if (chatLogs.dataset.empty === '1') {
            return;
        }

        chatLogs.classList.add('h-100');
        chatLogs.innerHTML = noMessageHTML;
        chatLogs.dataset.empty = '1';
    }

    function syncChatLogsMarkup(markup) {
        const chatLogs = document.querySelector('.chat-logs');
        if (!chatLogs) {
            return;
        }

        const trimmedMarkup = (markup || '').trim();
        if (!trimmedMarkup) {
            renderEmptyState();
            return;
        }

        chatLogs.classList.remove('h-100');
        delete chatLogs.dataset.empty;

        const template = document.createElement('template');
        template.innerHTML = trimmedMarkup;
        const incomingNodes = Array.from(template.content.children);

        const existingByKey = new Map();
        Array.from(chatLogs.children).forEach((node, index) => {
            existingByKey.set(getChatNodeKey(node, index), node);
        });

        let referenceNode = chatLogs.firstElementChild;
        incomingNodes.forEach((incomingNode, index) => {
            const key = getChatNodeKey(incomingNode, index);
            const existingNode = existingByKey.get(key);

            if (!existingNode) {
                chatLogs.insertBefore(incomingNode.cloneNode(true), referenceNode);
                return;
            }

            existingByKey.delete(key);

            if (existingNode !== referenceNode) {
                chatLogs.insertBefore(existingNode, referenceNode);
            }

            if (existingNode.outerHTML !== incomingNode.outerHTML) {
                const clone = incomingNode.cloneNode(true);
                chatLogs.replaceChild(clone, existingNode);
                referenceNode = clone.nextElementSibling;
            } else {
                referenceNode = existingNode.nextElementSibling;
            }
        });

        existingByKey.forEach((node) => node.remove());
        wrapSlangsWithTooltips('.message-text >p ');
    }

    function removeMessageFromChat(messageId) {
        const chatLogs = document.querySelector('.chat-logs');
        const messageNode = document.getElementById(`message-${messageId}`);
        if (!chatLogs || !messageNode) {
            return;
        }

        const previousNode = messageNode.previousElementSibling;
        const nextNode = messageNode.nextElementSibling;
        messageNode.remove();

        if (
            previousNode &&
            previousNode.classList.contains('message-divider') &&
            (!nextNode || nextNode.classList.contains('message-divider'))
        ) {
            previousNode.remove();
        }

        if (!chatLogs.querySelector('.message')) {
            renderEmptyState();
        }
    }

    async function getMessages(userid, options = {}) {
        const {
            limit = loadedLimit,
            preserveScroll = false,
            showLoading = false,
            allowRequestGrowth = true,
            waitForIdle = false,
            forceRender = false
        } = options;

        if (isMessagesRequestInFlight) {
            if (!waitForIdle) {
                return;
            }

            while (isMessagesRequestInFlight) {
                await new Promise((resolve) => setTimeout(resolve, 40));
            }
        }

        isMessagesRequestInFlight = true;
        let retryLimit = null;
        let previousScrollHeight = 0;
        let previousScrollTop = 0;
        const previousMessageIds = getRenderedMessageIds();
        const chatBodyNode = document.querySelector('.chat-body');

        if (preserveScroll && chatBodyNode) {
            previousScrollHeight = chatBodyNode.scrollHeight;
            previousScrollTop = chatBodyNode.scrollTop;
        }

        if (showLoading) {
            showLoadingAlert();
        }

        try {
            const response = await loadUrl(`${MESSAGE_URL}?u=${encodeURIComponent(userid)}&limit=${limit}`);
            if (response === null) {
                return;
            }

            const newMessages = response.trim();
            const currentMessages = sessionStorage.getItem("message")?.trim() || "";
            const fetchedMessageIds = getMessageIdsFromMarkup(newMessages);
            const fetchedCount = fetchedMessageIds.length;
            const chatLogsNode = document.querySelector('.chat-logs');
            const hasRenderedState = !!(
                chatLogsNode &&
                (chatLogsNode.dataset.empty === '1' || chatLogsNode.children.length > 0)
            );

            hasMoreMessages = fetchedCount >= limit && fetchedCount > 0;
            loadedLimit = Math.max(loadedLimit, limit);

            if (forceRender || newMessages !== currentMessages || !hasRenderedState) {
                sessionStorage.setItem('message', newMessages);
                syncChatLogsMarkup(newMessages);
            }

            if (preserveScroll && chatBodyNode) {
                const newScrollHeight = chatBodyNode.scrollHeight;
                chatBodyNode.scrollTop = newScrollHeight - previousScrollHeight + previousScrollTop;
            }

            if (allowRequestGrowth && previousMessageIds.length > 0 && fetchedMessageIds.length > 0 && !preserveScroll) {
                const addedIds = fetchedMessageIds.filter((id) => !previousMessageIds.includes(id));
                const droppedIds = previousMessageIds.filter((id) => !fetchedMessageIds.includes(id));

                // Keep already fetched messages in memory when new messages push the window.
                if (addedIds.length > 0 && droppedIds.length > 0) {
                    retryLimit = limit + droppedIds.length;
                    loadedLimit = Math.max(loadedLimit, retryLimit);
                }
            }
        } finally {
            if (showLoading) {
                hideLoadingAlert();
            }
            isMessagesRequestInFlight = false;
        }

        if (retryLimit !== null) {
            await getMessages(userid, {
                limit: retryLimit,
                preserveScroll: false,
                showLoading: false,
                allowRequestGrowth: false,
                waitForIdle: true
            });
        }
    }

    async function sendChatMessage() {
        try {
            await $.post(SEND_MESSAGE_URL, {
                message: $('#message').val(),
                recipient_id: userid
            });
            $('#message').val('');
            updateSendButtonState();
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true,
                forceRender: true
            });
            scrollChatToBottom();
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

    function scrollChatToBottom() {
        const chatBody = $('.chat-body');
        if (!chatBody.length) {
            return;
        }

        const target = chatBody[0].scrollHeight;
        if (chatBody[0].scrollTop !== target) {
            chatBody.scrollTop(target);
        }
    }

    function updateSendButtonState() {
        const shouldDisable = !$('#message').val().trim();
        const sendButton = $('.send-btn');
        if (!sendButton.length) {
            return;
        }

        if (sendButton.prop('disabled') !== shouldDisable) {
            sendButton.prop('disabled', shouldDisable);
        }
    }

    function updateTypingLabel(html) {
        const node = document.getElementById('typingstatus');
        if (!node) {
            return;
        }

        if (node.innerHTML !== html) {
            node.innerHTML = html;
        }
    }

    function updateAvatarStatus(statusText) {
        const statusNodes = document.querySelectorAll('.getstatus');
        if (!statusNodes.length) {
            return;
        }

        const shouldBeOnline = statusText === 'online';
        statusNodes.forEach((node) => {
            if (shouldBeOnline) {
                if (!node.classList.contains('avatar-online')) {
                    node.classList.remove('avatar-offline');
                    node.classList.add('avatar-online');
                }
                return;
            }

            if (!node.classList.contains('avatar-offline')) {
                node.classList.remove('avatar-online');
                node.classList.add('avatar-offline');
            }
        });
    }

    async function handleChatScrollTopPagination() {
        const chatBodyNode = document.querySelector('.chat-body');
        if (!chatBodyNode || !isInitialLoadDone || !hasMoreMessages || isPaginationLoading) {
            return;
        }

        if (chatBodyNode.scrollTop > 20) {
            return;
        }

        isPaginationLoading = true;
        loadedLimit += PAGE_SIZE;

        try {
            await getMessages(userid, {
                limit: loadedLimit,
                preserveScroll: true,
                showLoading: true,
                waitForIdle: true
            });
        } finally {
            isPaginationLoading = false;
        }
    }

    window.deleteMessage = async function(messageId) {
        const numericMessageId = Number(messageId);
        if (!userid || !Number.isInteger(numericMessageId) || numericMessageId < 1) {
            return;
        }

        try {
            const response = await $.ajax({
                url: DELETE_MESSAGE_URL,
                method: 'POST',
                dataType: 'json',
                data: { message_id: numericMessageId }
            });

            if (!response || response.success !== true) {
                throw new Error(response?.message || "Delete failed");
            }

            removeMessageFromChat(numericMessageId);
            sessionStorage.removeItem("message");
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true
            });
        } catch (error) {
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    title: "Delete failed",
                    text: error.message || "Unable to delete message.",
                    icon: "error"
                });
                return;
            }
            alert(error.message || "Unable to delete message.");
        }
    };

    $(document).on('contextmenu', '.message-text', function(e) {
        e.preventDefault();
        this.click();
    });

    if (userid) {
        // Load initial messages and set up periodic updates
        (async function initChat() {
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true
            });
            updateSendButtonState();
            scrollChatToBottom();
            isInitialLoadDone = true;
            setInterval(async () => {
                if ($('main').hasClass("is-visible")) {
                    await getMessages(userid, {
                        limit: loadedLimit
                    });
                    updateSendButtonState();
                }
            }, POLL_INTERVAL_MS);
        })();

        // Submit chat message on form submission
        $('.chat-form').submit(async function(e) {
            e.preventDefault();
            await sendChatMessage();
        });

        // Update typing status periodically
        setInterval(async () => {
            await updateTypingStatus(userid);
        }, POLL_INTERVAL_MS);

        $('.chat-body').on('scroll', debounce(async () => {
            await handleChatScrollTopPagination();
        }, 150));

        // Update typing status
        const updateTypingStatus = debounce(async function(userid) {
            const status = await loadUrl(`${GET_STATUS}?u=${userid}`);
            const responseText = await loadUrl(`${GET_TYPING_STATUS_URL}?u=${userid}`);
            if (status === null || responseText === null) {
                return;
            }

            const trimmedStatus = status.trim();
            const typingLabel = responseText.trim() === "true"
                ? "is typing<span class='typing-dots'><span>.</span><span>.</span><span>.</span></span>"
                : trimmedStatus;

            updateTypingLabel(typingLabel);
            updateAvatarStatus(trimmedStatus);
        }, 500);
      
        // Detect typing and update status
        $("#message").on("keydown", function(event) {
            if (event.isComposing) {
                return;
            }

            if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                if ($(this).val().trim()) {
                    $('.chat-form').trigger('submit');
                }
            }
        }).on("input", async function() {
            updateSendButtonState();
            await $.post(TYPING_STATUS_URL, { recipient_id: userid });
        }).on("blur", async function() {
            await $.post(TYPING_STATUS_URL, {
                recipient_id: userid,
                nottyping: "true"
            });
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
