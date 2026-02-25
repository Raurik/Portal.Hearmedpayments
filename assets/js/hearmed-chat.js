/**
 * HearMed Team Chat — Pusher-Powered Real-Time Messaging
 * 
 * Handles:
 *  - Connecting to Pusher (presence channel for company, private for DMs)
 *  - Loading channel list + unread counts
 *  - Sending / receiving messages
 *  - DM creation modal
 *  - Typing indicators
 *  - Load more (pagination)
 *  - Auto-resize textarea
 */

(function () {
    'use strict';

    // ── DOM refs ─────────────────────────────────────────────────────────
    const app = document.getElementById('hm-chat-app');
    if (!app) return;

    const cfg = {
        userId:      parseInt(app.dataset.userId, 10),
        userName:    app.dataset.userName,
        pusherKey:   app.dataset.pusherKey,
        pusherCluster: app.dataset.pusherCluster,
        ajaxUrl:     app.dataset.ajaxUrl,
        nonce:       app.dataset.nonce,
    };

    const el = {
        channelsList: document.getElementById('hm-chat-channels-list'),
        dmsList:      document.getElementById('hm-chat-dms-list'),
        channelName:  document.getElementById('hm-chat-channel-name'),
        channelMeta:  document.getElementById('hm-chat-channel-meta'),
        messages:     document.getElementById('hm-chat-messages'),
        inputArea:    document.getElementById('hm-chat-input-area'),
        input:        document.getElementById('hm-chat-input'),
        sendBtn:      document.getElementById('hm-chat-send-btn'),
        typingEl:     document.getElementById('hm-chat-typing'),
        onlineCount:  document.getElementById('hm-chat-online-count'),
        newDmBtn:     document.querySelector('.hm-chat-new-dm-btn'),
        dmModal:      document.getElementById('hm-chat-dm-modal'),
        dmModalClose: document.querySelector('.hm-chat-modal-close'),
        dmSearch:     document.getElementById('hm-chat-dm-search'),
        dmResults:    document.getElementById('hm-chat-dm-results'),
    };

    // ── State ────────────────────────────────────────────────────────────
    let pusher          = null;
    let activeChannel   = null;   // { id, type, name, pusher_channel, pusherSub }
    let channelMap      = {};     // id → channel data
    let typingTimeout   = null;
    let sendingTyping   = false;
    let oldestMessageId = null;
    let hasMoreMessages = false;
    let allUsers        = [];     // For DM search

    // ── PUSHER INIT ──────────────────────────────────────────────────────

    function initPusher() {
        if (!cfg.pusherKey) {
            console.warn('HearMed Chat: Pusher key not set. Configure in Admin > Settings > Pusher.');
            return;
        }

        pusher = new Pusher(cfg.pusherKey, {
            cluster: cfg.pusherCluster,
            authEndpoint: cfg.ajaxUrl + '?action=hm_chat_pusher_auth&nonce=' + cfg.nonce,
            auth: {
                headers: { 'X-WP-Nonce': cfg.nonce },
                params:  { nonce: cfg.nonce },
            },
        });

        pusher.connection.bind('error', (err) => {
            console.error('Pusher connection error:', err);
        });
    }

    // ── AJAX helper ──────────────────────────────────────────────────────

    function ajax(action, data = {}, method = 'POST') {
        const body = new URLSearchParams({ action, nonce: cfg.nonce, ...data });
        const url  = method === 'GET'
            ? cfg.ajaxUrl + '?' + body.toString()
            : cfg.ajaxUrl;

        return fetch(url, {
            method,
            credentials: 'same-origin',
            headers: method === 'POST'
                ? { 'Content-Type': 'application/x-www-form-urlencoded' }
                : {},
            body: method === 'POST' ? body : undefined,
        }).then(r => r.json());
    }

    // ── LOAD CHANNELS ────────────────────────────────────────────────────

    function loadChannels() {
        ajax('hm_chat_get_channels', {}, 'GET').then(res => {
            if (!res.success) return;

            const channels = res.data;
            el.channelsList.innerHTML = '';
            el.dmsList.innerHTML = '';
            channelMap = {};

            channels.forEach(ch => {
                channelMap[ch.id] = ch;
                const isCompany = ch.type === 'company';
                const isDm      = ch.type === 'dm';

                const item = document.createElement('div');
                item.className = 'hm-chat-channel-item';
                item.dataset.channelId = ch.id;

                const icon = isCompany ? '#' : isDm ? '👤' : '🔒';
                const preview = ch.last_message
                    ? escHtml(ch.last_message.substring(0, 40)) + (ch.last_message.length > 40 ? '…' : '')
                    : '';
                const badge = ch.unread > 0
                    ? `<span class="hm-chat-unread-badge">${ch.unread > 99 ? '99+' : ch.unread}</span>`
                    : '';

                item.innerHTML = `
                    <span class="hm-chat-ch-icon">${icon}</span>
                    <div class="hm-chat-ch-body">
                        <div class="hm-chat-ch-name">${escHtml(ch.name || 'Unknown')}</div>
                        <div class="hm-chat-ch-preview">${preview}</div>
                    </div>
                    ${badge}
                `;

                item.addEventListener('click', () => openChannel(ch));

                if (isDm) {
                    el.dmsList.appendChild(item);
                } else {
                    el.channelsList.appendChild(item);
                }
            });

            // Auto-open company channel on first load
            if (!activeChannel) {
                const company = channels.find(c => c.type === 'company');
                if (company) openChannel(company);
            }
        });
    }

    // ── OPEN CHANNEL ─────────────────────────────────────────────────────

    function openChannel(ch) {
        // Unsubscribe old channel
        if (activeChannel?.pusherSub && pusher) {
            pusher.unsubscribe(activeChannel.pusher_channel);
        }

        activeChannel = { ...ch, pusherSub: null };

        // Update UI
        document.querySelectorAll('.hm-chat-channel-item').forEach(i => i.classList.remove('active'));
        const activeItem = document.querySelector(`.hm-chat-channel-item[data-channel-id="${ch.id}"]`);
        if (activeItem) activeItem.classList.add('active');

        el.channelName.textContent = ch.name || 'Chat';
        el.channelMeta.textContent = ch.type === 'company' ? 'Company-wide · all staff' : 'Direct message';
        el.inputArea.style.display = '';
        el.messages.innerHTML = '<div class="hm-chat-loading">Loading messages…</div>';
        el.onlineCount.textContent = '';
        oldestMessageId = null;
        hasMoreMessages = false;

        // Subscribe to Pusher
        if (pusher && ch.pusher_channel) {
            const sub = pusher.subscribe(ch.pusher_channel);
            activeChannel.pusherSub = sub;

            sub.bind('new-message', (data) => {
                if (data.sender_id !== cfg.userId) {
                    appendMessage({ ...data, is_mine: false });
                    scrollToBottom();
                }
            });

            sub.bind('message-deleted', (data) => {
                const msgEl = document.querySelector(`[data-msg-id="${data.message_id}"]`);
                if (msgEl) {
                    msgEl.querySelector('.hm-chat-msg-text').innerHTML =
                        '<em class="hm-chat-msg-deleted">Message deleted</em>';
                    const actionsEl = msgEl.querySelector('.hm-chat-msg-actions');
                    if (actionsEl) actionsEl.remove();
                }
            });

            sub.bind('client-typing', (data) => {
                if (data.user_id !== cfg.userId) {
                    el.typingEl.textContent = `${data.user_name} is typing…`;
                    clearTimeout(typingTimeout);
                    typingTimeout = setTimeout(() => el.typingEl.textContent = '', 3000);
                }
            });

            // Presence: online count
            if (ch.type === 'company') {
                sub.bind('pusher:subscription_succeeded', (members) => {
                    el.onlineCount.textContent = `${members.count} online`;
                });
                sub.bind('pusher:member_added', () => {
                    const count = parseInt(el.onlineCount.textContent) + 1;
                    el.onlineCount.textContent = `${count} online`;
                });
                sub.bind('pusher:member_removed', () => {
                    const count = Math.max(0, parseInt(el.onlineCount.textContent) - 1);
                    el.onlineCount.textContent = `${count} online`;
                });
            }
        }

        loadMessages(ch.id, null);
        markRead(ch.id);
    }

    // ── LOAD MESSAGES ────────────────────────────────────────────────────

    function loadMessages(channelId, beforeId) {
        const params = { channel_id: channelId };
        if (beforeId) params.before_id = beforeId;

        ajax('hm_chat_get_messages', params, 'GET').then(res => {
            if (!res.success) return;

            const { messages, has_more } = res.data;
            hasMoreMessages = has_more;

            if (beforeId) {
                // Prepend older messages
                const scrollHeight = el.messages.scrollHeight;
                renderMessages(messages, true);
                el.messages.scrollTop = el.messages.scrollHeight - scrollHeight;
            } else {
                renderMessages(messages, false);
                scrollToBottom();
            }

            if (has_more) renderLoadMore(messages[0]?.id);
            if (messages.length > 0) oldestMessageId = messages[0].id;
        });
    }

    function renderMessages(messages, prepend = false) {
        if (!prepend) el.messages.innerHTML = '';

        let lastSender = null;
        let lastDate   = null;

        // If prepending, insert before the load-more button
        const insertBefore = prepend ? el.messages.querySelector('.hm-chat-load-more') : null;

        messages.forEach(msg => {
            const msgDate = new Date(msg.created_at).toDateString();

            // Date divider
            if (msgDate !== lastDate) {
                const divider = document.createElement('div');
                divider.className = 'hm-chat-date-divider';
                divider.textContent = formatDate(msg.created_at);
                if (insertBefore) {
                    el.messages.insertBefore(divider, insertBefore);
                } else {
                    el.messages.appendChild(divider);
                }
                lastDate   = msgDate;
                lastSender = null;
            }

            const msgEl = buildMessageEl(msg, msg.sender_id === lastSender);
            if (insertBefore) {
                el.messages.insertBefore(msgEl, insertBefore);
            } else {
                el.messages.appendChild(msgEl);
            }
            lastSender = msg.sender_id;
        });
    }

    function buildMessageEl(msg, grouped) {
        const wrap = document.createElement('div');
        wrap.className = 'hm-chat-message';
        wrap.dataset.msgId = msg.id;

        const initial = (msg.sender_name || '?')[0].toUpperCase();
        const avatarHtml = grouped
            ? `<div class="hm-chat-avatar invisible">${initial}</div>`
            : `<div class="hm-chat-avatar">${initial}</div>`;

        const headerHtml = grouped ? '' : `
            <div class="hm-chat-msg-header">
                <span class="hm-chat-msg-sender ${msg.is_mine ? 'is-mine' : ''}">${escHtml(msg.sender_name)}</span>
                <span class="hm-chat-msg-time">${formatTime(msg.created_at)}</span>
            </div>`;

        const deleteBtn = (msg.is_mine)
            ? `<div class="hm-chat-msg-actions">
                   <button class="hm-chat-msg-delete-btn" data-msg-id="${msg.id}" title="Delete message">🗑</button>
               </div>`
            : '';

        wrap.innerHTML = `
            ${avatarHtml}
            <div class="hm-chat-msg-body">
                ${headerHtml}
                <div class="hm-chat-msg-text">${escHtml(msg.message)}</div>
            </div>
            ${deleteBtn}
        `;

        const delBtn = wrap.querySelector('.hm-chat-msg-delete-btn');
        if (delBtn) {
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteMessage(msg.id);
            });
        }

        return wrap;
    }

    function appendMessage(msg) {
        const lastMsg = el.messages.lastElementChild;
        const lastSenderId = lastMsg?.dataset?.msgId
            ? parseInt(lastMsg.dataset.msgId)
            : null;

        // Simple grouping: same sender if last element has same sender
        // We'll just not group for live messages to keep it simple
        const msgEl = buildMessageEl(msg, false);
        el.messages.appendChild(msgEl);
    }

    function renderLoadMore(firstMsgId) {
        const existing = el.messages.querySelector('.hm-chat-load-more');
        if (existing) existing.remove();

        const div = document.createElement('div');
        div.className = 'hm-chat-load-more';
        div.innerHTML = '<button class="hm-chat-load-more-btn">Load older messages</button>';
        div.querySelector('button').addEventListener('click', () => {
            loadMessages(activeChannel.id, oldestMessageId);
        });

        el.messages.insertBefore(div, el.messages.firstChild);
    }

    // ── SEND MESSAGE ─────────────────────────────────────────────────────

    function sendMessage() {
        if (!activeChannel) return;
        const text = el.input.value.trim();
        if (!text) return;

        el.input.value = '';
        el.input.style.height = 'auto';
        el.sendBtn.disabled = true;

        ajax('hm_chat_send_message', {
            channel_id: activeChannel.id,
            message:    text,
        }).then(res => {
            el.sendBtn.disabled = false;
            if (res.success) {
                appendMessage({ ...res.data, is_mine: true });
                scrollToBottom();
                // Update sidebar preview
                loadChannels();
            } else {
                alert('Failed to send message. Please try again.');
                el.input.value = text;
            }
        }).catch(() => {
            el.sendBtn.disabled = false;
            el.input.value = text;
        });
    }

    // ── DELETE MESSAGE ───────────────────────────────────────────────────

    function deleteMessage(msgId) {
        if (!confirm('Delete this message? It will be removed from the chat.')) return;

        ajax('hm_chat_delete_message', { message_id: msgId }).then(res => {
            if (res.success) {
                const msgEl = document.querySelector(`[data-msg-id="${msgId}"]`);
                if (msgEl) {
                    msgEl.querySelector('.hm-chat-msg-text').innerHTML =
                        '<em class="hm-chat-msg-deleted">Message deleted</em>';
                    const actEl = msgEl.querySelector('.hm-chat-msg-actions');
                    if (actEl) actEl.remove();
                }
            }
        });
    }

    // ── MARK READ ────────────────────────────────────────────────────────

    function markRead(channelId) {
        ajax('hm_chat_mark_read', { channel_id: channelId });
        // Clear badge in sidebar
        const item = document.querySelector(`.hm-chat-channel-item[data-channel-id="${channelId}"]`);
        if (item) {
            const badge = item.querySelector('.hm-chat-unread-badge');
            if (badge) badge.remove();
        }
    }

    // ── DM MODAL ─────────────────────────────────────────────────────────

    function openDmModal() {
        el.dmModal.style.display = 'flex';
        el.dmSearch.value = '';
        el.dmSearch.focus();
        loadAllUsers();
    }

    function closeDmModal() {
        el.dmModal.style.display = 'none';
    }

    function loadAllUsers() {
        // Fetch all WP users via a quick user search (we reuse ajax with special action)
        // For now, display placeholder — will populate after first keystroke
        el.dmResults.innerHTML = '<p style="padding:12px;color:#64748b;font-size:13px;">Start typing to search…</p>';
    }

    function searchUsers(query) {
        if (!query.trim()) {
            el.dmResults.innerHTML = '';
            return;
        }

        // Use WordPress AJAX user search
        fetch(cfg.ajaxUrl + '?action=hm_chat_search_users&nonce=' + cfg.nonce + '&q=' + encodeURIComponent(query), {
            credentials: 'same-origin',
        }).then(r => r.json()).then(res => {
            el.dmResults.innerHTML = '';
            if (!res.success || !res.data.length) {
                el.dmResults.innerHTML = '<p style="padding:12px;color:#94a3b8;font-size:13px;">No staff found.</p>';
                return;
            }
            res.data.forEach(user => {
                const item = document.createElement('div');
                item.className = 'hm-chat-dm-result-item';
                item.innerHTML = `
                    <div class="hm-chat-dm-avatar">${user.name[0].toUpperCase()}</div>
                    <div>
                        <div class="hm-chat-dm-user-name">${escHtml(user.name)}</div>
                        <div class="hm-chat-dm-user-role">${escHtml(user.role || '')}</div>
                    </div>
                `;
                item.addEventListener('click', () => startDm(user.id));
                el.dmResults.appendChild(item);
            });
        });
    }

    function startDm(otherUserId) {
        ajax('hm_chat_create_dm', { other_user_id: otherUserId }).then(res => {
            if (!res.success) {
                alert('Could not start conversation. Please try again.');
                return;
            }
            closeDmModal();
            const channelId = res.data.channel_id;

            // Reload channels, then open the DM once the channel data is in channelMap
            ajax('hm_chat_get_channels', {}, 'GET').then(chRes => {
                if (!chRes.success) return;

                const channels = chRes.data;
                el.channelsList.innerHTML = '';
                el.dmsList.innerHTML = '';
                channelMap = {};

                channels.forEach(ch => {
                    channelMap[ch.id] = ch;
                    const isDm      = ch.type === 'dm';
                    const isCompany = ch.type === 'company';
                    const icon      = isCompany ? '#' : isDm ? '👤' : '🔒';
                    const preview   = ch.last_message
                        ? escHtml(ch.last_message.substring(0, 40)) + (ch.last_message.length > 40 ? '…' : '')
                        : '';
                    const badge = ch.unread > 0
                        ? `<span class="hm-chat-unread-badge">${ch.unread > 99 ? '99+' : ch.unread}</span>`
                        : '';

                    const item = document.createElement('div');
                    item.className = 'hm-chat-channel-item';
                    item.dataset.channelId = ch.id;
                    item.innerHTML = `
                        <span class="hm-chat-ch-icon">${icon}</span>
                        <div class="hm-chat-ch-body">
                            <div class="hm-chat-ch-name">${escHtml(ch.name || 'Unknown')}</div>
                            <div class="hm-chat-ch-preview">${preview}</div>
                        </div>
                        ${badge}
                    `;
                    item.addEventListener('click', () => openChannel(ch));

                    if (isDm) {
                        el.dmsList.appendChild(item);
                    } else {
                        el.channelsList.appendChild(item);
                    }
                });

                // Now open the DM we just created/found
                const target = channelMap[channelId];
                if (target) openChannel(target);
            });
        });
    }

    // Add the user search AJAX action in PHP — register it alongside the others
    // hm_chat_search_users is handled below in mod-team-chat.php

    // ── TYPING INDICATOR ─────────────────────────────────────────────────

    function triggerTyping() {
        if (!activeChannel?.pusherSub || sendingTyping) return;

        sendingTyping = true;
        // Pusher client events require `client-` prefix and private/presence channel
        try {
            activeChannel.pusherSub.trigger('client-typing', {
                user_id:   cfg.userId,
                user_name: cfg.userName,
            });
        } catch (e) { /* client events disabled */ }

        setTimeout(() => sendingTyping = false, 2000);
    }

    // ── UTILITIES ────────────────────────────────────────────────────────

    function scrollToBottom() {
        el.messages.scrollTop = el.messages.scrollHeight;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatTime(iso) {
        const d = new Date(iso);
        return d.toLocaleTimeString('en-IE', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(iso) {
        const d   = new Date(iso);
        const now = new Date();
        const diff = Math.floor((now - d) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        return d.toLocaleDateString('en-IE', { weekday: 'long', day: 'numeric', month: 'long' });
    }

    // ── AUTO-RESIZE TEXTAREA ─────────────────────────────────────────────

    function autoResize() {
        el.input.style.height = 'auto';
        el.input.style.height = Math.min(el.input.scrollHeight, 120) + 'px';
    }

    // ── EVENT LISTENERS ──────────────────────────────────────────────────

    el.input.addEventListener('input', () => {
        autoResize();
        triggerTyping();
    });

    el.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    el.sendBtn.addEventListener('click', sendMessage);

    el.newDmBtn?.addEventListener('click', openDmModal);
    el.dmModalClose?.addEventListener('click', closeDmModal);

    el.dmModal?.addEventListener('click', (e) => {
        if (e.target === el.dmModal) closeDmModal();
    });

    let dmSearchTimer;
    el.dmSearch?.addEventListener('input', () => {
        clearTimeout(dmSearchTimer);
        dmSearchTimer = setTimeout(() => searchUsers(el.dmSearch.value), 300);
    });

    // Reload channels every 60s to refresh unread counts
    setInterval(loadChannels, 60000);

    // ── BOOT ─────────────────────────────────────────────────────────────

    // Load Pusher JS SDK dynamically
    const script = document.createElement('script');
    script.src = 'https://js.pusher.com/8.2.0/pusher.min.js';
    script.onload = () => {
        initPusher();
        loadChannels();
    };
    script.onerror = () => {
        // Fallback: load channels anyway (no real-time, just AJAX)
        loadChannels();
    };
    document.head.appendChild(script);

})();