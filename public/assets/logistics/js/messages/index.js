function messagesApp(init) {
            return {
                conversations: init.conversations,
                messages: init.messages,
                active: init.active,
                totalUnread: init.totalUnread,
                search: init.search,
                filter: init.filter,
                perPage: init.perPage,
                csrf: init.csrf,
                base: init.base,
                mobileList: init.mobileList,
                recipientsUrl: init.recipientsUrl,
                startUrl: init.startUrl,
                draft: '',
                attachmentName: '',
                attachmentPreview: '',
                attachmentIsImage: false,
                dragging: false,
                openMenu: null,
                editing: null,
                sending: false,
                error: '',
                lightbox: null,
                pollTimer: null,
                composeOpen: false,
                recipientType: 'admin',
                recipientSearch: '',
                recipients: [],
                recipientsLoading: false,
                recipientError: '',
                startingRecipient: null,

                init() {
                    this.scrollToBottom();
                    this.pollTimer = setInterval(() => this.poll(), 8000);
                    this.$watch('messages', () => this.$nextTick(() => this.scrollToBottom()));
                },

                openCompose() {
                    this.composeOpen = true;
                    this.recipientSearch = '';
                    this.recipientError = '';
                    this.loadRecipients();
                    this.$nextTick(() => this.$refs.recipientSearch?.focus());
                },

                closeCompose() {
                    this.composeOpen = false;
                    this.recipientError = '';
                },

                setRecipientType(type) {
                    if (this.recipientType === type) return;
                    this.recipientType = type;
                    this.recipientSearch = '';
                    this.loadRecipients();
                },

                async loadRecipients() {
                    this.recipientsLoading = true;
                    this.recipientError = '';
                    try {
                        const url = new URL(this.recipientsUrl, window.location.origin);
                        url.searchParams.set('type', this.recipientType);
                        url.searchParams.set('search', this.recipientSearch || '');
                        const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(data.message || 'Could not load recipients.');
                        this.recipients = data.recipients || [];
                    } catch (e) {
                        this.recipients = [];
                        this.recipientError = e.message || 'Could not load recipients.';
                    } finally {
                        this.recipientsLoading = false;
                    }
                },

                async startConversation(recipient) {
                    this.startingRecipient = recipient.id;
                    this.recipientError = '';
                    try {
                        const response = await fetch(this.startUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ recipient_type: recipient.type, recipient_id: recipient.id }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(data.message || 'Could not start this conversation.');
                        this.closeCompose();
                        await this.selectConversation(data.conversation_id);
                        await this.poll();
                    } catch (e) {
                        this.recipientError = e.message || 'Could not start this conversation.';
                    } finally {
                        this.startingRecipient = null;
                    }
                },

                get sortedConversations() {
                    return [...this.conversations].sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.thread;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },

                agoLabel(iso) {
                    if (!iso) return '';
                    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
                    if (diff < 60) return 'Just now';
                    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
                    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
                    if (diff < 604800) return Math.floor(diff / 86400) + ' d ago';
                    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },

                async selectConversation(id) {
                    this.error = '';
                    try {
                        const r = await fetch(this.base + '/' + id, { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) throw new Error('Could not open this conversation.');
                        const data = await r.json();
                        this.active = data.active;
                        this.messages = data.messages;
                        this.totalUnread = data.totalUnread;
                        this.conversations = this.conversations.map(c => c.id === id ? { ...c, unread: 0 } : c);
                        this.editing = null;
                        this.draft = '';
                        this.openMenu = null;
                        this.mobileList = false;

                        // Keep the selected conversation after a page refresh.
                        const params = new URLSearchParams(window.location.search);
                        params.set('conversation', id);
                        params.delete('page');
                        history.replaceState(null, '', window.location.pathname + '?' + params.toString());
                    } catch (e) {
                        this.error = e.message || 'Failed to open the conversation.';
                    }
                },

                async sendMessage() {
                    if (!this.active) return;
                    if (this.sending) return;
                    const body = this.draft.trim();
                    if (!body && !this.$refs.attachment.files.length) {
                        this.error = 'Please type a message before sending.';
                        return;
                    }
                    if (this.editing) {
                        await this.saveEdit();
                        return;
                    }

                    this.sending = true;
                    this.error = '';
                    try {
                        const fd = new FormData();
                        fd.append('conversation_id', this.active.id);
                        fd.append('body', body);
                        if (this.$refs.attachment.files[0]) {
                            fd.append('attachment', this.$refs.attachment.files[0]);
                        }
                        const r = await fetch(this.base, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        if (!r.ok) {
                            const data = await r.json().catch(() => ({}));
                            throw new Error(data.message || 'The message could not be sent. Please try again.');
                        }
                        this.draft = '';
                        this.$refs.attachment.value = '';
                        this.clearAttachment();
                        await this.poll();
                    } catch (e) {
                        this.error = e.message || 'The message could not be sent.';
                    } finally {
                        this.sending = false;
                    }
                },

                startEdit(msg) {
                    this.editing = msg;
                    this.draft = msg.body;
                    this.$refs.bodyInput.focus();
                },

                formatSize(bytes) {
                    if (!bytes || bytes <= 0) return '';
                    const units = ['B', 'KB', 'MB'];
                    let size = bytes, unit = 0;
                    while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++; }
                    return (unit === 0 ? size : size.toFixed(1)) + ' ' + units[unit];
                },

                previewAttachment() {
                    const file = this.$refs.attachment.files[0];
                    this.clearAttachment(false);
                    if (!file) return;
                    if (file.size > 5 * 1024 * 1024) {
                        this.error = 'Attachment must not exceed 5 MB.';
                        this.$refs.attachment.value = '';
                        return;
                    }
                    this.attachmentName = file.name + (file.size ? ' · ' + this.formatSize(file.size) : '');
                    if ((file.type || '').startsWith('image/')) {
                        this.attachmentIsImage = true;
                        this.attachmentPreview = URL.createObjectURL(file);
                    }
                },

                clearAttachment(clearInput = true) {
                    if (this.attachmentPreview) {
                        URL.revokeObjectURL(this.attachmentPreview);
                        this.attachmentPreview = '';
                    }
                    this.attachmentIsImage = false;
                    this.attachmentName = '';
                    if (clearInput && this.$refs.attachment) this.$refs.attachment.value = '';
                },

                dropAttachment(event) {
                    this.dragging = false;
                    const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
                    if (!file) return;
                    try {
                        this.$refs.attachment.files = event.dataTransfer.files;
                    } catch (e) {
                        this.error = 'Could not attach that file. Use the attach button instead.';
                        return;
                    }
                    this.previewAttachment();
                },

                cancelEdit() {
                    this.editing = null;
                    this.draft = '';
                },

                async saveEdit() {
                    if (!this.editing || !this.draft.trim()) return;
                    this.sending = true;
                    this.error = '';
                    try {
                        const r = await fetch(this.base + '/' + this.editing.id, {
                            method: 'PATCH',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ body: this.draft.trim() }),
                        });
                        if (r.status === 403) throw new Error('You can only edit your own messages.');
                        if (!r.ok) throw new Error('The message could not be updated.');
                        this.editing.body = this.draft.trim();
                        this.editing.edited_at = new Date().toISOString();
                        this.cancelEdit();
                        this.poll();
                    } catch (e) {
                        this.error = e.message || 'The message could not be updated.';
                    } finally {
                        this.sending = false;
                    }
                },

                async deleteMessage(msg) {
                    if (!await window.logisticsConfirm({title: 'Delete message?', message: 'This message will be removed from the conversation.', confirmLabel: 'Delete message', danger: true})) return;
                    this.error = '';
                    try {
                        const r = await fetch(this.base + '/' + msg.id, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        });
                        if (r.status === 403) throw new Error('You can only delete your own messages.');
                        if (!r.ok) throw new Error('The message could not be deleted.');
                        msg.deleted = true;
                        msg.attachments = [];
                        this.poll();
                    } catch (e) {
                        this.error = e.message || 'The message could not be deleted.';
                    }
                },

                async poll() {
                    try {
                        const params = new URLSearchParams({
                            conversation: this.active ? this.active.id : '',
                            search: this.search || '',
                            filter: this.filter || 'all',
                            per_page: this.perPage || 10,
                        });
                        const r = await fetch(this.base + '/poll?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) return;
                        const data = await r.json();
                        this.conversations = data.conversations;
                        this.totalUnread = data.totalUnread;
                        if (data.active && this.active && data.active.id === this.active.id) {
                            this.messages = data.messages;
                        }
                    } catch (e) {
                        // Network hiccup: keep showing current data; next tick retries.
                    }
                },
            };
        }
