$(document).ready(function() {
    const MESSAGE_URL = 'function/getmessage.php';
    const SEND_MESSAGE_URL = 'function/sendmessage.php';
    const DELETE_MESSAGE_URL = 'function/delete.php';
    const MARK_READ_URL = 'function/markread.php';
    const TYPING_STATUS_URL = 'function/typingstatus.php';
    const GET_TYPING_STATUS_URL = 'function/gettypingstatus.php';
    const GET_STATUS = 'function/status.php';
    const PAGE_SIZE = 15;
    const POLL_INTERVAL_MS = 1000;
    const FALLBACK_MESSAGE_POLL_MS = 3000;
    const SOCKET_SYNC_POLL_MS = 5000;
    const WS_RECONNECT_MS = 3000;
    let loadedLimit = PAGE_SIZE;
    let hasMoreMessages = true;
    let isPaginationLoading = false;
    let isMessagesRequestInFlight = false;
    let isInitialLoadDone = false;
    let chatSocket = null;
    let socketConnected = false;
    let socketReconnectTimer = null;
    let friendIsTyping = false;
    let friendStatusLabel = 'offline';
    let typingStateSent = false;
    let typingStopTimer = null;
    let isMarkingRead = false;
    let jumpButton = null;
    let jumpCountNode = null;

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
    const currentUserId = Number(window.CHAT_USER_ID || 0);
    const chatRoomId = (
        currentUserId > 0 && Number(userid) > 0
            ? [currentUserId, Number(userid)].sort((a, b) => a - b).join(':')
            : null
    );
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

    function getSocketUrl() {
        const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
        const host = window.location.hostname;
        const port = Number(window.CHAT_WS_PORT || 8080);
        return `${protocol}://${host}:${port}`;
    }

    function scheduleSocketReconnect() {
        if (socketReconnectTimer || !chatRoomId) {
            return;
        }

        socketReconnectTimer = window.setTimeout(() => {
            socketReconnectTimer = null;
            connectChatSocket();
        }, WS_RECONNECT_MS);
    }

    function notifySocket(eventName) {
        if (!chatSocket || chatSocket.readyState !== WebSocket.OPEN || !chatRoomId) {
            return;
        }

        chatSocket.send(JSON.stringify({
            type: 'chat:update',
            room: chatRoomId,
            event: eventName,
            user_id: currentUserId
        }));
    }

    function notifyTypingSocket(isTyping) {
        if (!chatSocket || chatSocket.readyState !== WebSocket.OPEN || !chatRoomId) {
            return false;
        }

        chatSocket.send(JSON.stringify({
            type: 'typing:update',
            room: chatRoomId,
            user_id: currentUserId,
            typing: !!isTyping
        }));
        return true;
    }

    function isChatNearBottom(threshold = 80) {
        const chatBodyNode = document.querySelector('.chat-body');
        if (!chatBodyNode) {
            return true;
        }

        return chatBodyNode.scrollHeight - (chatBodyNode.scrollTop + chatBodyNode.clientHeight) <= threshold;
    }

    function getUnreadIncomingCount() {
        return document.querySelectorAll('.chat-logs .message.message-in[data-seen="0"]').length;
    }

    function ensureJumpButton() {
        if (jumpButton) {
            return;
        }

        jumpButton = document.createElement('button');
        jumpButton.type = 'button';
        jumpButton.id = 'jump-to-latest-btn';
        jumpButton.className = 'btn btn-primary shadow rounded-pill d-none';
        jumpButton.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:100px;z-index:1200;';
        jumpButton.innerHTML = '<span aria-hidden="true">↓</span> <span id="jump-unread-count" class="badge bg-light text-dark">0</span>';
        jumpButton.addEventListener('click', async () => {
            scrollChatToBottom();
            await markMessagesAsReadIfAtBottom(true);
            updateJumpButtonState();
        });
        document.body.appendChild(jumpButton);
        jumpCountNode = jumpButton.querySelector('#jump-unread-count');
    }

    function updateJumpButtonState() {
        if (!jumpButton) {
            return;
        }

        const unreadCount = getUnreadIncomingCount();
        const atBottom = isChatNearBottom(16);

        if (jumpCountNode && jumpCountNode.textContent !== String(unreadCount)) {
            jumpCountNode.textContent = String(unreadCount);
        }

        if (atBottom || unreadCount < 1) {
            jumpButton.classList.add('d-none');
            return;
        }

        jumpButton.classList.remove('d-none');
    }

    async function markMessagesAsReadIfAtBottom(force = false) {
        if (!userid || isMarkingRead) {
            return;
        }

        const atBottom = isChatNearBottom(16);
        if (!force && !atBottom) {
            return;
        }

        const unreadCount = getUnreadIncomingCount();
        if (unreadCount < 1) {
            return;
        }

        isMarkingRead = true;
        try {
            await $.ajax({
                url: MARK_READ_URL,
                method: 'POST',
                dataType: 'json',
                data: { sender_id: userid }
            });

            sessionStorage.removeItem('message');
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true,
                allowRequestGrowth: false,
                forceRender: true,
                skipAutoRead: true
            });
        } catch (error) {
            console.error('Failed to mark read:', error);
        } finally {
            isMarkingRead = false;
            updateJumpButtonState();
        }
    }

    function connectChatSocket() {
        if (!chatRoomId) {
            return;
        }

        if (chatSocket && (chatSocket.readyState === WebSocket.OPEN || chatSocket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        try {
            chatSocket = new WebSocket(getSocketUrl());
        } catch (error) {
            socketConnected = false;
            scheduleSocketReconnect();
            return;
        }

        chatSocket.addEventListener('open', () => {
            socketConnected = true;
            console.log('[WS] connected', { room: chatRoomId });
            chatSocket.send(JSON.stringify({
                type: 'subscribe',
                room: chatRoomId,
                user_id: currentUserId
            }));
            getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true
            });
        });

        chatSocket.addEventListener('message', async (event) => {
            let payload = null;
            try {
                payload = JSON.parse(event.data);
            } catch (error) {
                return;
            }

            if (!payload) {
                return;
            }

            if (payload.type === 'subscribed') {
                console.log('[WS] subscribed', payload);
                return;
            }

            if (payload.type === 'presence:snapshot' && payload.room === chatRoomId) {
                const friendOnline = !!payload.users?.[String(userid)];
                setFriendStatus(friendOnline ? 'online' : 'offline');
                return;
            }

            if (payload.type === 'presence:update' && payload.room === chatRoomId && Number(payload.user_id) === Number(userid)) {
                setFriendStatus(payload.online ? 'online' : 'offline');
                return;
            }

            if (payload.type === 'typing:update' && payload.room === chatRoomId && Number(payload.user_id) === Number(userid)) {
                setFriendTyping(!!payload.typing);
                return;
            }

            if (payload.type === 'chat:update' && payload.room === chatRoomId) {
                console.log('[WS] chat:update', payload);
                const shouldScroll = isChatNearBottom();
                await getMessages(userid, {
                    limit: loadedLimit,
                    waitForIdle: true
                });

                if (shouldScroll && payload.event === 'message_sent') {
                    scrollChatToBottom();
                }
            }
        });

        chatSocket.addEventListener('close', () => {
            socketConnected = false;
            chatSocket = null;
            typingStateSent = false;
            console.log('[WS] disconnected');
            scheduleSocketReconnect();
        });

        chatSocket.addEventListener('error', () => {
            socketConnected = false;
            console.log('[WS] error');
            if (chatSocket) {
                chatSocket.close();
            }
        });
    }

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
            forceRender = false,
            skipAutoRead = false
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
            updateJumpButtonState();
        } finally {
            if (showLoading) {
                hideLoadingAlert();
            }
            isMessagesRequestInFlight = false;
        }

        if (!skipAutoRead) {
            await markMessagesAsReadIfAtBottom();
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
            setLocalTypingState(false);
            notifySocket('message_sent');
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true,
                forceRender: true
            });
            scrollChatToBottom();
            updateJumpButtonState();
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

    function setFriendTyping(isTyping) {
        friendIsTyping = !!isTyping;
        if (friendIsTyping) {
            updateTypingLabel("is typing<span class='typing-dots'><span>.</span><span>.</span><span>.</span></span>");
            return;
        }

        updateTypingLabel(friendStatusLabel);
    }

    function setFriendStatus(statusLabel) {
        const value = (statusLabel || '').trim();
        friendStatusLabel = value || 'offline';
        updateAvatarStatus(friendStatusLabel === 'online' ? 'online' : 'offline');

        if (!friendIsTyping) {
            updateTypingLabel(friendStatusLabel);
        }
    }

    function setLocalTypingState(isTyping) {
        const shouldType = !!isTyping;
        if (typingStateSent === shouldType) {
            return;
        }

        typingStateSent = shouldType;
        if (notifyTypingSocket(shouldType)) {
            return;
        }

        if (shouldType) {
            $.post(TYPING_STATUS_URL, { recipient_id: userid });
        } else {
            $.post(TYPING_STATUS_URL, {
                recipient_id: userid,
                nottyping: "true"
            });
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
            notifySocket('message_deleted');
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true
            });
            updateJumpButtonState();
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
            ensureJumpButton();
            await getMessages(userid, {
                limit: loadedLimit,
                waitForIdle: true,
                forceRender: true
            });
            connectChatSocket();
            updateSendButtonState();
            scrollChatToBottom();
            isInitialLoadDone = true;
            setInterval(async () => {
                if ($('main').hasClass("is-visible") && !socketConnected) {
                    await getMessages(userid, {
                        limit: loadedLimit
                    });
                    updateSendButtonState();
                }
            }, FALLBACK_MESSAGE_POLL_MS);
            setInterval(async () => {
                if ($('main').hasClass("is-visible") && socketConnected) {
                    await getMessages(userid, {
                        limit: loadedLimit
                    });
                    updateSendButtonState();
                }
            }, SOCKET_SYNC_POLL_MS);
        })();

        // Submit chat message on form submission
        $('.chat-form').submit(async function(e) {
            e.preventDefault();
            await sendChatMessage();
        });

        // Update typing status periodically
        setInterval(async () => {
            if (!socketConnected) {
                await updateTypingStatus(userid);
            }
        }, POLL_INTERVAL_MS);

        $('.chat-body').on('scroll', debounce(async () => {
            await handleChatScrollTopPagination();
            updateJumpButtonState();
            await markMessagesAsReadIfAtBottom();
        }, 150));

        // Update typing status
        const updateTypingStatus = debounce(async function(userid) {
            const status = await loadUrl(`${GET_STATUS}?u=${userid}`);
            const responseText = await loadUrl(`${GET_TYPING_STATUS_URL}?u=${userid}`);
            if (status === null || responseText === null) {
                return;
            }

            const trimmedStatus = status.trim();
            setFriendStatus(trimmedStatus);
            setFriendTyping(responseText.trim() === "true");
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
        }).on("input", function() {
            updateSendButtonState();
            const hasText = !!$(this).val().trim();
            setLocalTypingState(hasText);

            if (typingStopTimer) {
                clearTimeout(typingStopTimer);
            }

            if (hasText) {
                typingStopTimer = setTimeout(() => {
                    setLocalTypingState(false);
                }, 1200);
            }
        }).on("blur", function() {
            if (typingStopTimer) {
                clearTimeout(typingStopTimer);
                typingStopTimer = null;
            }
            setLocalTypingState(false);
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
