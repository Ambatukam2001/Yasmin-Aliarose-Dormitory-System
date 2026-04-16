document.addEventListener('DOMContentLoaded', async () => {
    if (document.querySelector('.chat-widget')) return; // Prevent double init
    // 1. Check Auth Status
    const basePath = window.location.pathname.includes('/admin/') ? '../api/' : 'api/';
    let auth = { authenticated: false };
    try {
        const authRes = await fetch(basePath + 'auth_status.php' + (window.location.pathname.includes('/admin/') ? '?from_admin=1' : ''));
        if (authRes.ok) auth = await authRes.json();
    } catch (e) {
        console.warn('Chat auth check failed, defaulting to guest mode.');
    }
    

    // 2. Inject Chat CSS
    const style = document.createElement('style');
    style.innerHTML = `
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            font-family: 'Inter', sans-serif;
        }
        .chat-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .chat-toggle:hover {
            transform: scale(1.1);
        }
        .chat-box {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform-origin: bottom right;
            transform: scale(0);
            opacity: 0;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
            pointer-events: none;
        }
        .chat-box.open {
            transform: scale(1);
            opacity: 1;
            pointer-events: auto;
        }
        .chat-header {
            background: #10b981;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Outfit', sans-serif;
        }
        .chat-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        .chat-header i {
            cursor: pointer;
        }
        .chat-body {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-msg {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 0.9rem;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .msg-me {
            background: #10b981;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .msg-them {
            background: #e2e8f0;
            color: #1e293b;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .dark-theme .chat-body { background: #0f172a; }
        .dark-theme .msg-them { background: #1e293b; color: #f8fafc; }
        .dark-theme .chat-box { background: #1e293b; border: 1px solid #334155; }
        
        .chat-footer {
            padding: 15px;
            background: white;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .dark-theme .chat-footer { background: #1e293b; border-top-color: #334155; }
        .chat-input {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 0.9rem;
            outline: none;
            background: transparent;
            color: inherit;
        }
        .dark-theme .chat-input { border-color: #334155; }
        .chat-btn {
            background: none;
            border: none;
            color: #10b981;
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-btn:hover { color: #059669; }
        
        /* Admin lists */
        .chat-list-item {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .chat-list-item:hover { background: #f1f5f9; }
        .dark-theme .chat-list-item { border-bottom-color: #334155; }
        .dark-theme .chat-list-item:hover { background: #334155; }
        .chat-list-title { font-weight: bold; margin-bottom: 4px; }
        .chat-list-sub { font-size: 0.8rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .chat-attach-preview {
            max-width: 150px;
            border-radius: 8px;
            margin-top: 5px;
        }
        .chat-audio {
            max-width: 200px;
            height: 40px;
        }
        .chat-toggle.active {
            background: #f1f5f9;
            color: #64748b;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        /* Auth Prompt */
        .chat-auth-prompt {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }
        .chat-auth-icon {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
    `;
    document.head.appendChild(style);

    // 3. Create DOM
    const widget = document.createElement('div');
    widget.className = 'chat-widget';

    if (!auth.authenticated) {
        widget.innerHTML = `
            <div class="chat-box" id="chatBox">
                <div class="chat-header">
                    <h3>Support Chat</h3>
                    <i class="fas fa-times" id="chatClose"></i>
                </div>
                <div class="chat-auth-prompt">
                    <div class="chat-auth-icon"><i class="fas fa-comments"></i></div>
                    <h3 style="margin-bottom: 0.5rem; color: #1e293b;">Chat with Admin</h3>
                    <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 1.5rem;">Please log in to start a conversation with our staff.</p>
                    <a href="login.php" class="btn btn-primary" style="text-align: center; width: 100%; padding: 0.8rem; text-decoration: none; border-radius: 0.75rem;">Login to Chat</a>
                </div>
            </div>
            <div class="chat-toggle" id="chatToggle">
                <i class="fas fa-comment-dots"></i>
            </div>
        `;
    } else {
        widget.innerHTML = `
            <div class="chat-box" id="chatBox">
                <div class="chat-header">
                    <div>
                        ${auth.role === 'admin' ? '<i class="fas fa-arrow-left" id="chatBack" style="display:none; margin-right: 10px;"></i>' : ''}
                        <h3 id="chatTitle">${auth.role === 'admin' ? 'Support Inbox' : 'Chat with Admin'}</h3>
                    </div>
                    <i class="fas fa-times" id="chatClose"></i>
                </div>
                <div class="chat-body" id="chatBody"></div>
                <div class="chat-footer" id="chatFooter" style="${auth.role === 'admin' ? 'display:none;' : ''}">
                    <button class="chat-btn" id="attachBtn" title="Attach Image"><i class="fas fa-paperclip"></i></button>
                    <input type="file" id="attachInput" accept="image/*" style="display:none;">
                    <input type="text" class="chat-input" id="chatInput" placeholder="Type a message...">
                    <button class="chat-btn" id="voiceBtn" title="Record Audio"><i class="fas fa-microphone"></i></button>
                    <button class="chat-btn" id="sendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
            <div class="chat-toggle" id="chatToggle">
                <i class="fas fa-comment-dots"></i>
            </div>
        `;
    }
    document.body.appendChild(widget);

    // Variables
    let isOpen = false;
    let currentChatId = null;
    let pollInterval = null;
    let mediaRecorder = null;
    let audioChunks = [];
    
    const ui = {
        box: document.getElementById('chatBox'),
        toggle: document.getElementById('chatToggle'),
        close: document.getElementById('chatClose'),
        body: document.getElementById('chatBody'),
        input: document.getElementById('chatInput'),
        sendBtn: document.getElementById('sendBtn'),
        attachBtn: document.getElementById('attachBtn'),
        attachInput: document.getElementById('attachInput'),
        voiceBtn: document.getElementById('voiceBtn'),
        footer: document.getElementById('chatFooter'),
        title: document.getElementById('chatTitle'),
        back: document.getElementById('chatBack')
    };

    // 4. Bind Events
    if (ui.toggle) ui.toggle.addEventListener('click', () => toggleChat());
    if (ui.close) ui.close.addEventListener('click', () => toggleChat());
    if (ui.back) ui.back.addEventListener('click', () => loadAdminInbox());

    if (ui.sendBtn) ui.sendBtn.addEventListener('click', sendMessage);
    if (ui.input) {
        ui.input.addEventListener('keypress', (e) => { 
            if (e.key === 'Enter') sendMessage(); 
        });
    }
    
    if (ui.attachBtn) ui.attachBtn.addEventListener('click', () => ui.attachInput.click());
    if (ui.attachInput) ui.attachInput.addEventListener('change', sendAttachment);
    
    if (ui.voiceBtn) ui.voiceBtn.addEventListener('click', toggleRecording);

    function toggleChat() {
        isOpen = !isOpen;
        if (isOpen) {
            ui.box.classList.add('open');
            ui.toggle.classList.add('active');
            ui.toggle.innerHTML = '<i class="fas fa-chevron-down"></i>';
            if (auth.role === 'admin' && !currentChatId) {
                loadAdminInbox();
            } else {
                loadMessages();
                pollInterval = setInterval(loadMessages, 3000);
            }
        } else {
            ui.box.classList.remove('open');
            ui.toggle.classList.remove('active');
            ui.toggle.innerHTML = '<i class="fas fa-comment-dots"></i>';
            clearInterval(pollInterval);
        }
    }

    async function loadAdminInbox() {
        currentChatId = null;
        clearInterval(pollInterval);
        ui.footer.style.display = 'none';
        ui.back.style.display = 'none';
        ui.title.textContent = 'Support Inbox';
        
        try {
            const res = await fetch(basePath + 'chat_api.php?action=get_chats');
            const chats = await res.json();
            ui.body.innerHTML = '';
            
            if (chats.length === 0) {
                ui.body.innerHTML = '<div style="text-align:center; padding: 20px; color: #94a3b8;">No active chats</div>';
                return;
            }
            
            chats.forEach(chat => {
                const el = document.createElement('div');
                el.className = 'chat-list-item';
                el.innerHTML = `
                    <div class="chat-list-title">User ${chat.username}</div>
                    <div class="chat-list-sub">${chat.last_message || 'No messages yet'}</div>
                `;
                el.addEventListener('click', () => openAdminChat(chat.id, chat.username));
                ui.body.appendChild(el);
            });
        } catch (e) {
            console.error(e);
        }
    }

    function openAdminChat(id, username) {
        currentChatId = id;
        ui.title.textContent = 'Chat: ' + username;
        ui.back.style.display = 'block';
        ui.footer.style.display = 'flex';
        loadMessages();
        pollInterval = setInterval(loadMessages, 3000);
    }

    async function loadMessages() {
        let url = basePath + 'chat_api.php?action=get_messages';
        if (auth.role === 'admin' && currentChatId) url += '&chat_id=' + currentChatId;
        
        try {
            const res = await fetch(url);
            const msgs = await res.json();
            if (msgs.error) return;
            
            let scrolledToBottom = ui.body.scrollHeight - ui.body.clientHeight <= ui.body.scrollTop + 10;
            
            ui.body.innerHTML = '';
            msgs.forEach(msg => {
                const el = document.createElement('div');
                const isMe = msg.sender_type === auth.role;
                el.className = 'chat-msg ' + (isMe ? 'msg-me' : 'msg-them');
                
                let content = '';
                if (msg.message) content += `<div>${msg.message}</div>`;
                if (msg.file_url) {
                    if (msg.is_voice) {
                        content += `<audio controls class="chat-audio" src="${(window.location.pathname.includes('/admin/') ? '../' : '') + msg.file_url}"></audio>`;
                    } else {
                        content += `<img src="${(window.location.pathname.includes('/admin/') ? '../' : '') + msg.file_url}" class="chat-attach-preview" onclick="window.open(this.src)" style="cursor:pointer">`;
                    }
                }
                
                const timeStr = msg.created_at ? new Date(msg.created_at).toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true, month: 'short', day: 'numeric' }) : '';
                content += `<div style="font-size: 0.7rem; margin-top: 5px; opacity: 0.8; text-align: right;">${timeStr}</div>`;
                
                el.innerHTML = content;
                ui.body.appendChild(el);
            });
            
            if (scrolledToBottom || msgs.length === 0) {
                ui.body.scrollTop = ui.body.scrollHeight;
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function sendMessage() {
        const text = ui.input.value.trim();
        if (!text) return;
        ui.input.value = '';
        
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('message', text);
        if (auth.role === 'admin') fd.append('chat_id', currentChatId);

        await fetch(basePath + 'chat_api.php?action=send_message', { method: 'POST', body: fd });
        loadMessages();
    }

    async function sendAttachment(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('file', file);
        if (auth.role === 'admin') fd.append('chat_id', currentChatId);

        ui.input.placeholder = "Uploading image...";
        await fetch(basePath + 'chat_api.php?action=send_message', { method: 'POST', body: fd });
        ui.input.placeholder = "Type a message...";
        loadMessages();
    }

    async function toggleRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            ui.voiceBtn.style.color = '#10b981';
            ui.input.placeholder = "Type a message...";
        } else {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.start();
                audioChunks = [];
                
                ui.voiceBtn.style.color = '#ef4444'; // Red means recording
                ui.input.placeholder = "Recording... Click mic to send.";

                mediaRecorder.addEventListener("dataavailable", event => {
                    audioChunks.push(event.data);
                });

                mediaRecorder.addEventListener("stop", async () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    
                    const fd = new FormData();
                    fd.append('action', 'send_message');
                    fd.append('file', audioBlob, 'voice.webm');
                    fd.append('is_voice', '1');
                    if (auth.role === 'admin') fd.append('chat_id', currentChatId);

                    ui.input.placeholder = "Sending voice...";
                    await fetch(basePath + 'chat_api.php?action=send_message', { method: 'POST', body: fd });
                    ui.input.placeholder = "Type a message...";
                    loadMessages();
                    
                    // Stop tracks to release mic
                    stream.getTracks().forEach(track => track.stop());
                });
            } catch (err) {
                console.error("Microphone access denied", err);
                alert("Microphone access is required for voice messages.");
            }
        }
    }
});
