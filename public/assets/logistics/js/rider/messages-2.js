function riderMessages(init) {
        return {
            messages: init.messages,
            csrf: init.csrf,
            base: init.base,
            draft: '',
            attachmentName: '',
            editing: null,
            sending: false,
            error: '',

            init() {
                this.scrollToBottom();
                setInterval(() => this.poll(), 8000);
                this.$watch('messages', () => this.$nextTick(() => this.scrollToBottom()));
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.thread;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            async sendMessage() {
                const body = this.draft.trim();
                if (!body && !this.$refs.attachment.files.length) {
                    this.error = 'Please type a message before sending.';
                    return;
                }
                if (this.editing) { await this.saveEdit(); return; }

                this.sending = true;
                this.error = '';
                try {
                    const fd = new FormData();
                    fd.append('body', body);
                    if (this.$refs.attachment.files[0]) fd.append('attachment', this.$refs.attachment.files[0]);
                    const r = await fetch(this.base, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: fd,
                    });
                    if (!r.ok) {
                        const data = await r.json().catch(() => ({}));
                        throw new Error(data.message || 'The message could not be sent.');
                    }
                    this.draft = '';
                    this.$refs.attachment.value = '';
                    this.attachmentName = '';
                    await this.poll();
                } catch (e) {
                    this.error = e.message || 'The message could not be sent.';
                } finally {
                    this.sending = false;
                }
            },

            startEdit(msg) { this.editing = msg; this.draft = msg.body; this.$refs.bodyInput.focus(); },
            cancelEdit() { this.editing = null; this.draft = ''; },

            async saveEdit() {
                if (!this.editing || !this.draft.trim()) return;
                this.sending = true;
                try {
                    const r = await fetch(this.base + '/' + this.editing.id, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ body: this.draft.trim() }),
                    });
                    if (!r.ok) throw new Error('The message could not be updated.');
                    this.editing.body = this.draft.trim();
                    this.editing.edited_at = new Date().toISOString();
                    this.cancelEdit();
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.sending = false;
                }
            },

            async deleteMessage(msg) {
                if (!confirm('Delete this message?')) return;
                try {
                    const r = await fetch(this.base + '/' + msg.id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    });
                    if (!r.ok) throw new Error('The message could not be deleted.');
                    msg.deleted = true;
                    msg.attachments = [];
                } catch (e) {
                    this.error = e.message;
                }
            },

            async poll() {
                try {
                    const r = await fetch(this.base + '/poll', { headers: { 'Accept': 'application/json' } });
                    if (r.ok) {
                        const data = await r.json();
                        this.messages = data.messages;
                    }
                } catch (e) { /* retry next tick */ }
            },

            // keep file label updated
            handleFile() {
                const f = this.$refs.attachment.files[0];
                this.attachmentName = f ? f.name : '';
            },
        };
    }
